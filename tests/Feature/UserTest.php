<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Elastic;
use Illuminate\Encryption\Encrypter;

class UserTest extends TestCase
{
    private static $jwt         = null;
    private static $userID      = null;
    private static $phoneNumber = '1234567897';

    public function testSignup(){
        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response->assertStatus(200);
        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response->assertStatus(200);

        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '00000']);
        $response->assertUnauthorized();

        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '123456']);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
            
        self::$jwt      = $response->original['jwt'];
        self::$userID   = $response->original['id'];
        sleep(1); //sync elasticsearch
    }

    public function testSession(){
        
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/session');
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
        $response = $this->withHeaders(['Authorization' => 'Bearer fakeJWT'])->json('GET', '/api/users/session');
        $response->assertUnauthorized();
    }

    public function testRegisterAnonymous(){
        $response = $this->json('POST', '/api/users/signup/anonymous');
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
        Elastic::delete(['index' => 'users', 'id' => $response->original['id']]);
    }

    public function testLogout(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/logout');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/session');
        $response->assertUnauthorized();

        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '123456']);
        self::$jwt = $response->original['jwt'];
    }

    public function testSetState(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/state', [
            'state' => '1'
        ]);
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/session');
        $response->assertStatus(200)->assertJson(['state' => '1']);
    }

    public function testSaveError(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/error', [
            'log' => [
                "sysURL"        => "http://localhost:3000",
                "currentPath"   => "http://localhost:3000/main/test",
                "navigator"     => "Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
                "error"         => "fake error",
                "errorInfo"     => "fake error",
            ]
        ]);
        $response->assertStatus(200);
    }


    public function testBlueTrace(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/bluetrace/tempIDs');
        $response->assertStatus(200)->assertJsonStructure([
            'status',
            'tempIDs',
            'refreshTime'
        ]);

        $tempIDs    = $response->original['tempIDs'];
        $response   = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/bluetrace', [
            'manufacturer'  => 'Apple',
            'model'         => 'iPhoneX',
            'records'       => [
                [
                    "modelP"    => "iPhone X",
                    "org"       => "MX_GCM",
                    "modelC"    => "iPhone 7",
                    "rssi"      => -26,
                    "txPower"   => null,
                    "msg"       => $tempIDs[0]['tempID'],
                    "v"         => 2,
                    "timestamp" => time() - 1000
                ]
            ],
        ]);
        $response->assertStatus(200);
    }

    public function testDeviceToken(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/devicetoken', [
            'devicetype'    => 'android',
            'devicetoken'   => 'fakeDeviceToken'
        ]);
        $response->assertStatus(200);
        
        $response = $this->json('POST', '/api/devicetoken', [
            'devicetype'    => 'android',
            'devicetoken'   => 'fakeDeviceToken'
        ]);
        $response->assertStatus(200);

    }

    public function testPostalCode(){
        $response = $this->json('GET', '/api/postalCodes/03400');
        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function testStates(){
        $response = $this->json('GET', '/api/states');
        $response->assertStatus(200);

        $states = $response->original;
        
        $response = $this->json('GET', '/api/states/'.$states[0]['id'].'/municipalities');
        $response->assertStatus(200);
        
        $municipalities = $response->original;

        $response = $this->json('GET', '/api/states/'.$states[0]['id'].'/municipalities/'.$municipalities[0]['id'].'/hospitals');
        $response->assertStatus(200);
    }

    public function testStatesInfo(){
        $response = $this->json('GET', '/api/states/info');
        $response->assertStatus(200);

        $response = $this->json('GET', '/api/municipalities/info');
        $response->assertStatus(200);
    }

    public function testSetInfected(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/exposed', [
            'gaenKeys' => [
                [
                    'keyData' => 'fakeKey',
                    'rollingStartNumber' => ((time() - 1000) / 60 / 10),
                    'rollingPeriod' => 144,
                ]
            ]
        ]);
        $response->assertStatus(200);
    }

    public function testGetExposedConfig(){
        $response = $this->json('GET', '/api/exposed/config');
        $response->assertStatus(200)->assertJsonStructure([
            "minimumRiskScore",
            "attenuationLevelValues",
            "daysSinceLastExposureLevelValues",
            "durationLevelValues",
            "transmissionRiskLevelValues",
            "lowerThreshold",
            "higherThreshold",
            "factorLow",
            "factorHigh",
            "triggerThreshold",
            'alert'
        ]);
    }

    public function testGetExposedData(){
        $response = $this->json('GET', '/api/exposed/' . ((int) (time() - 86400) * 1000 ));
        $response->assertSuccessful();
    }

    public function testSetExposed(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/exposed/contact');
        $response->assertStatus(200)->assertJsonStructure([
            "title",
            "body",
            "actions",
        ]);
    }

    public function testValidateCenter(){
        $cipher     = new Encrypter(md5($this->app['config']['app.QR_PASSWORD']), 'AES-256-CBC');
        $centerID   = $cipher->encryptString('DFSSA003256');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/centers/'.$centerID.'/validate');
        $response->assertStatus(200)->assertJson(['valid' => true]);
    }

    public function testSetFavSemphore(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/semaphore/fav',[
            'fav' => [1]
        ]);
        $response->assertStatus(200);
    }

    public function testclear(){
        sleep(1); //sync elasticsearch
        $phoneHash  = hash_pbkdf2('sha256', self::$phoneNumber, $this->app['config']['app.ENCRYPTION_SALT'], 30000, 0);

        Elastic::delete(['index' => 'otps', 'id' => $phoneHash, 'client'    => ['ignore' => 404]]);
        Elastic::deleteByQuery([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);
        Elastic::deleteByQuery([
            'index'     => 'logs_error',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['userID' => self::$userID]] ] ] ]]
        ]);
        Elastic::deleteByQuery([
            'index'     => 'bluetraces',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['userID1' => self::$userID]] ] ] ]]
        ]);
        Elastic::deleteByQuery([
            'index'     => 'dp3t',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['userID' => self::$userID]] ] ] ]]
        ]);
        $this->assertTrue(true);

        sleep(1); //sync elasticsearch
    }
}
