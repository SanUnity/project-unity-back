<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Elastic;
use Illuminate\Encryption\Encrypter;

class ProfileTest extends TestCase
{

    private static $jwt = null;
    private static $profileID = null;
    private static $phoneNumber = '1234567897';

    public function testCreateUser(){
        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response->assertStatus(200);
        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '123456']);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
            
        self::$jwt = $response->original['jwt'];
        sleep(1); //sync elasticsearch
    }

    public function testCreateProfile(){
        $data = [
            "name"              => "Name",
            "lastname1"         => "lastname1",
            "lastname2"         => "lastname2",
            "age"               => 33,
            "gender"            => "male",
            "postalCode"        => '03400',
            "imss"              => true,
            "street"            => "street name",
            "numberExternal"    => 10,
            "numberInternal"    => 2,
            "stateID"           => 1,
            "state"             => "Ciudad de México",
            "municipalityID"    => 3,
            "municipality"      => "Benito Juárez",
            "suburbID"          => 324,
            "suburb"            => "Álamos"
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/profiles', $data);
        $response->assertStatus(200)->assertJsonStructure([
            'id'
        ]);

        self::$profileID = $response->original['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/profiles/' . self::$profileID);
        $response->assertStatus(200)->assertJson($data);
    }


    public function testEditProfile(){
        $data = [
            "name"              => "Name edit",
            "lastname1"         => "lastname1 edit",
            "lastname2"         => "lastname2 edit",
            "age"               => 32,
            "gender"            => "female",
            "postalCode"        => '03400',
            "imss"              => false,
            "street"            => "street name edit",
            "numberExternal"    => 11,
            "numberInternal"    => 3,
            "stateID"           => 1,
            "state"             => "Ciudad de México",
            "municipalityID"    => 3,
            "municipality"      => "Benito Juárez",
            "suburbID"          => 324,
            "suburb"            => "Álamos"
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', '/api/users/profiles/' . self::$profileID, $data);
        $response->assertStatus(200)->assertJsonStructure([
            'id'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/profiles/' . self::$profileID);
        $response->assertStatus(200)->assertJson($data);
    }

    public function testSetMainProfile(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/profiles/' . self::$profileID . '/main');
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/session');
        $response->assertStatus(200)->assertJson([
            'mainProfile' => self::$profileID
        ]);
    }

    public function testCreateTest(){
        $data = [
            'breathing'     => false,
            'defenses'      => false,
            'diabetes'      => false,
            'hypertension'  => false,
            'obesity'       => false,
            'pregnant'      => false,
            'symptomWeek'   => false,
            'symptoms'      => false
        ];
        $responseData =  [
            'id',
            'lastTest',
            'level',
            'name'
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'low'
        ]);

        $data['symptomWeek'] = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium-low'
        ]);

        $data['symptomWeek']    = false;
        $data['symptoms']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
        ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium'
            ]);
            
        $data['diabetes']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium-high'
        ]);

        $data['breathing']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'high'
        ]);

    }

    public function testGetTests(){
        sleep(1); //sync elastic

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/profiles/' . self::$profileID . '/tests');
        $response->assertStatus(200)->assertJsonCount(5);
    }

    public function testRequestExit(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/users/profiles/' . self::$profileID . '/exitRequests',[
            'destiny'   => 'supermarket',
            'motive'    => 'buy food',
        ]);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'url',
            'profileID',
            'timestamp',
            'expiry',
            'deleted',
            'destiny',
            'motive',
        ])->assertJson([
            'destiny'   => 'supermarket',
            'motive'    => 'buy food',
        ]);

        $exitRequestID = $response->original['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('DELETE', '/api/users/profiles/' . self::$profileID . '/exitRequests/' . $exitRequestID);

        $response->assertStatus(200);
    }

    public function testPcr(){
        $cipher     = new Encrypter(md5($this->app['config']['app.QR_PASSWORD']), 'AES-256-CBC');
        $centerID   = $cipher->encryptString('DFSSA003256');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr',[
                "name"      => "name",
                "lastname"  => "lastname",
                "phone"     => "111111111111",
                "email"     => "email@test.com",
                "gender"    => "male",
                "birthday"  => "1987-08-17",
                "dateTest"  => date('Y-m-d'),
                "centerId"  => $centerID
            ]);
            
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'haveVerifyEmail',
            'haveVerifyPhone',
        ])->assertJson([
            'haveVerifyEmail'   => true,
            'haveVerifyPhone'   => true,
        ]);

        $pcrID = $response->original['id'];

        sleep(1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/session');
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('PUT', '/api/users/profiles/' . self::$profileID . '/pcr',[
                "id"        => $pcrID,
                "name"      => "name 2",
                "lastname"  => "lastname 2",
                "phone"     => "22222222222222",
                "email"     => "email2@test.com",
                "gender"    => "male",
                "birthday"  => "1987-08-17",
                "dateTest"  => date('Y-m-d'),
                "centerId"  => $centerID
            ]);

        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'haveVerifyEmail',
            'haveVerifyPhone',
        ])->assertJson([
            'haveVerifyEmail'   => true,
            'haveVerifyPhone'   => true,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr/'.$pcrID.'/validate',[
                "otpEmail"  => 111111,
                "otpPhone"  => 111111,
            ]);
            
        $response->assertStatus(200)->assertJson([
            'email'   => false,
            'phone'   => false,
            ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr/'.$pcrID.'/validate',[
                "otpEmail"  => 123456,
                "otpPhone"  => 123456,
            ]);
            
        $response->assertStatus(200)->assertJson([
            'email'   => true,
            'phone'   => true,
            ]);

        Elastic::update(['index' => 'pcr_info', 'id' => $pcrID, 'body' => ['doc' => ['resultTest' => 1]],'refresh' => "wait_for"]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr/'.$pcrID.'/notify',[
                "phones"  => [
                    ['phone' => '123456789', 'name'  => 'Name']
                ],
            ]);

        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr/'.$pcrID.'/readed');

        $response->assertStatus(200);
    }

    public function testDeleteProfile(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('DELETE', '/api/users/profiles/' . self::$profileID);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/users/profiles/' . self::$profileID);
        $response->assertUnauthorized();

        sleep(1); //sync elasticsearch
        $phoneHash  = hash_pbkdf2('sha256', self::$phoneNumber, $this->app['config']['app.ENCRYPTION_SALT'], 30000, 0);

        Elastic::delete(['index' => 'otps', 'id' => $phoneHash, 'client'    => ['ignore' => 404]]);
        Elastic::deleteByQuery([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);
        Elastic::deleteByQuery([
            'index'     => 'pcr_info',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['profileID' => self::$profileID]] ] ] ]]
        ]);
        sleep(1); //sync elasticsearch
    }
}
