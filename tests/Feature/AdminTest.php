<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Elastic;
use Illuminate\Encryption\Encrypter;

class AdminTest extends TestCase {

    private static $jwt             = null;
    private static $jwtUser         = null;
    private static $adminID         = null;
    private static $stateID         = null;
    private static $municipalityID  = null;
    private static $suburbID        = null;
    private static $userID          = null;
    private static $profileID       = null;
    private static $phoneNumber     = '1234567897';

    public function setUp(): void{
        parent::setUp();
        if(!defined('LARAVEL_START')){
            define('LARAVEL_START', microtime(true));
        }
    }

    public function testCreate(){
        $this->artisan('admin:create TestAdmin testadmin@test.com 11111 1')->assertExitCode(1);
    }

    public function testLogin(){
        $response = $this->json('POST', '/api/admins/signin', [
            'email'     => 'testadmin@test.com',
            'password'  => '00000',
        ]);
        $response->assertUnauthorized();

        $response = $this->json('POST', '/api/admins/signin', [
            'email'     => 'testadmin@test.com',
            'password'  => '11111',
        ]);
        $response->assertStatus(200);

        self::$jwt      = $response->original['jwt'];
        self::$adminID  = $response->original['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/logout');
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/session');
        $response->assertUnauthorized();

        $response = $this->json('POST', '/api/admins/signin', [
            'email'     => 'testadmin@test.com',
            'password'  => '11111',
        ]);
        $response->assertStatus(200);

        self::$jwt = $response->original['jwt'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/session');
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
    }

    public function testStates(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states');
        $response->assertStatus(200);

        $states = $response->original;
        self::$stateID = $states[0]['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states');
        $response->assertStatus(200);

        $states = $response->original;
        self::$stateID = $states[0]['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/'.self::$stateID.'/municipalities');
        $response->assertStatus(200);

        $municipalities = $response->original;
        self::$municipalityID = $municipalities[0]['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])
            ->json('GET', '/api/admins/states/'.self::$stateID.'/municipalities/'.self::$municipalityID.'/suburbs');
        $response->assertStatus(200);

        $suburbs = $response->original;
        self::$suburbID = $suburbs[0]['id'];
    }

    public function testCreateData(){
        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response->assertStatus(200);
        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '123456']);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
            
        self::$jwtUser  = $response['jwt'];
        self::$userID   = $response['id'];
        sleep(1); //sync elasticsearch

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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])->json('POST', '/api/users/profiles', $data);
        $response->assertStatus(200)->assertJsonStructure([
            'id'
        ]);

        self::$profileID = $response->original['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])->json('GET', '/api/users/profiles/' . self::$profileID);
        $response->assertStatus(200)->assertJson($data);

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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'low'
        ]);

        $data['symptomWeek'] = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium-low'
        ]);

        $data['symptomWeek']    = false;
        $data['symptoms']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
        ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium'
            ]);
            
        $data['diabetes']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'medium-high'
        ]);

        $data['breathing']       = true;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/tests', $data);
        $response->assertStatus(200)->assertJsonStructure($responseData)->assertJson([
            'level' => 'high'
        ]);

        sleep(1); //sync elasticsearch
    }

    public function testStats(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/stats/age');
        $response->assertStatus(200)->assertJsonCount(11);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/stats/gender');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/stats/timeline');
        $response->assertStatus(200)->assertJsonStructure([
            'items',
            'level-0',
            'level-1',
            'level-2',
            'level-3',
            'level-4',
            'level-5',
            'total',
        ]);
    }

    public function testTests(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/tests');
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/users/' . self::$profileID);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'userID',
            'lastTest',
            'totalTests',
            'phone',
            'tests',
            'messages',
        ]);
    }

    public function testRegisters(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/registers');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/registers');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/municipalities/3/registers');
        $response->assertStatus(200);
    }

    public function testStatesTests(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/tests');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/tests');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/municipalities/3/tests');
        $response->assertStatus(200);
    }

    public function testStatesTrends(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/trends/positive');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/trends/positive');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/1/municipalities/3/trends/positive');
        $response->assertStatus(200);
    }

    public function testHospitals(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/hospitals/tests',[
            'name'              => 'name hospital',
            'stateID'           => self::$stateID,
            'municipalityID'    => self::$municipalityID,
            'suburbID'          => self::$suburbID,
            'testingService'    => true,
            'timestamp'         => time(),
            'totalCapacity'     => 5000,
            'occupiedCapacity'  => 3000,
            'totalTest'         => 250000,
            'positiveTest'      => 1000,
            'negativeTest'      => 2000,
            'search'            => 1,
        ]);
        $response->assertStatus(200);
        $testID     = $response['id'];
        $hospitalID = $response['hospitalID'];


        sleep(1); //sync elasticsearch

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/hospitals/tests');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/hospitals/stats');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/hospitalsTests');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/'.self::$stateID.'/hospitalsTests');
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/'.self::$stateID.'/municipalities/'.self::$municipalityID.'/hospitalsTests');
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', "/api/admins/hospitals/$hospitalID/tests/$testID",[
            'stateID'           => self::$stateID,
            'municipalityID'    => self::$municipalityID,
            'suburbID'          => self::$suburbID,
            'testingService'    => true,
            'timestamp'         => time(),
            'totalCapacity'     => 6000,
            'occupiedCapacity'  => 4000,
            'totalTest'         => 350000,
            'positiveTest'      => 2000,
            'negativeTest'      => 3000,
            'search'            => 0,
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('DELETE', "/api/admins/hospitals/$hospitalID/tests/$testID");
        $response->assertStatus(200);
        sleep(1); //sync elasticsearch
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('DELETE', "/api/admins/hospitals/$hospitalID");
        $response->assertStatus(200);
    }

    public function testMessages(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->app['config']['app.TOKEN_BACK']])
        ->json('POST', '/api/backend/devicetoken', [
            'userID'    => self::$userID,
            'deviceARN' => 'fakeARN',
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/message',[
            'message' => 'content message',
        ]);
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/states/'.self::$stateID.'/message',[
            'message' => 'content message',
        ]);
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/states/'.self::$stateID.'/municipalities/'.self::$municipalityID.'/message',[
            'message' => 'content message',
        ]);
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/states/'.self::$stateID.'/municipalities/'.self::$municipalityID.'/suburbs/'.self::$suburbID.'/message',[
            'message' => 'content message',
        ]);
        $response->assertStatus(200);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/users/'.self::$profileID.'/message',[
            'message' => 'content message',
        ]);
        $response->assertStatus(200);
    }

    public function testAdmins(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins');
        $response->assertStatus(200)->assertJsonStructure([
            'items',
            'total',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/invite',[
            'email' => 'gestor@tests.com',
            'name' => 'name admin',
            'role' => 2,
        ]);
        $response->assertStatus(200);

        $emailHash  = hash_pbkdf2('sha256', 'gestor@tests.com', $this->app['config']['app.ENCRYPTION_SALT'], 1, 0);
        $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/password',[
            'hash'      => $adminData['_source']['hash'],
            'password'  => '123abCD._tr',
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/resetPassword',[
            'email' => 'gestor@tests.com',
        ]);
        $response->assertStatus(200);

        sleep(1); //sync elasticsearch

        $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/password',[
            'hash'      => $adminData['_source']['hash'],
            'password'  => '123abCD._tr',
        ]);
        $response->assertStatus(400);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', '/api/admins/' . $emailHash,[
            'email' => 'gestor@tests.com',
            'name' => 'name admin 2',
            'role' => 1,
        ]);
        $response->assertStatus(200);

        for($i = 0; $i < 4;$i++){
            $response = $this->json('POST', '/api/admins/signin', [
                'email'     => 'gestor@tests.com',
                'password'  => '00000',
            ]);
            $response->assertUnauthorized();
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('DELETE', '/api/admins/' . $emailHash);
        $response->assertStatus(200);

    }

    public function testSemaphore(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/info');
        $response->assertStatus(200);

        $states     = $response->original;
        $stateID    = $states[0]['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', '/api/admins/states/'.$stateID.'/info',[
            'status' => 'medium',
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/municipalities/info');
        $response->assertStatus(200);

        $municipalities = $response->original;
        $municipalityID = $municipalities[0]['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', '/api/admins/municipalities/'.$municipalityID.'/info',[
            'status' => 'medium',
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/states/info/file');
        $response->assertStatus(200)->assertHeader('content-disposition', 'attachment; filename=statesInfo.csv');

        $file = UploadedFile::fake()->createWithContent('statesInfo.csv', '01,Aguascalientes,low');
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/states/info/file', [
            'file' => $file
        ]);
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/municipalities/info/file');
        $response->assertStatus(200)->assertHeader('content-disposition', 'attachment; filename=municipalitiesInfo.csv');

        $file = UploadedFile::fake()->createWithContent('statesInfo.csv', '20,Oaxaca,20463,"Santiago Huauclilla",low');
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('POST', '/api/admins/municipalities/info/file', [
            'file' => $file
        ]);
    }

    public function testNewStats(){

        foreach(['test', 'trendPositive', 'trendNegative', 'trendNeutral'] as $type){
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/ageGender/$type");
            $response->assertStatus(200);
            
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/symptoms/$type");
            $response->assertStatus(200);

            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/socialSecurity/$type");
            $response->assertStatus(200);
            
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/states/$type");
            $response->assertStatus(200);

            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/states/".self::$stateID."/municipalities/$type");
            $response->assertStatus(200);

            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/states/".self::$stateID."/municipalities/".self::$municipalityID."/suburbs/$type");
            $response->assertStatus(200);

            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/socialSecurity/$type");
            $response->assertStatus(200);
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/geo");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/indicators");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/cases/hour");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/age");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/stats/or/diabetes/".self::$stateID);
        $response->assertStatus(200);
    }

    public function testExposureNotification(){
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/admins/en/stats");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', "/api/admins/en/config");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('PUT', "/api/admins/en/config",[
            "attenuationLevelValues"            => [0, 1, 3, 5, 6, 7, 8, 8],
            "daysSinceLastExposureLevelValues"  => [0, 0, 2, 4, 5, 6, 7, 8],
            "durationLevelValues"               => [0, 0, 1, 2, 4, 6, 7, 8],
            "lowerThreshold"                    => 50,
            "higherThreshold"                   => 55,
            "factorLow"                         => 1.0,
            "factorHigh"                        => 0.5,
            "triggerThreshold"	                => 25,
            "titlePush"	                        => 'titlePush',
            "contentPush"	                    => 'contentPush',
            "title"	                            => 'titulo',
            "content"	                        => 'content',
            'actions' => [
                "faq"	                    => true,
                "hospital1"	                => true,
                "hospital23"	            => true,
                "freePCR"	                => true,
                "goodPractices"	            => true,
                "freeMask"	                => true,
                "basicPackage"	            => true,
                "freeBed"	                => true,
                "call911"	                => true,
                "localSystems"	            => true,
                "test"	                    => true,
            ],
            'stateID'                           => self::$stateID,
            'municipalityID'                    => self::$municipalityID,
            'suburbID'                          => self::$suburbID,
        ]);
        $response->assertStatus(200);

    }

    public function testDownloadTests(){

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwt])->json('GET', '/api/admins/tests/file');
        $response->assertStatus(200)->assertHeader('content-disposition', 'attachment; filename=testsData.csv');
    }

    public function testPcrResult(){
        $cipher     = new Encrypter(md5($this->app['config']['app.QR_PASSWORD']), 'AES-256-CBC');
        $centerID   = $cipher->encryptString('DFSSA003256');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr',[
                "name"      => "name",
                "lastname"  => "lastname",
                "phone"     => "44444444444",
                "email"     => "emailPCrAdmin@test.com",
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

        $pcrID = $response['id'];

        sleep(1); //sync elasticsearch

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])
            ->json('POST', '/api/users/profiles/' . self::$profileID . '/pcr/'.$pcrID.'/validate',[
                "otpEmail"  => 123456,
                "otpPhone"  => 123456,
            ]);
            
        $response->assertStatus(200)->assertJson([
            'email'   => true,
            'phone'   => true,
            ]);


        sleep(1); //sync elasticsearch

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->app['config']['app.PCR_RESULT_TOKEN']])->json('POST', "/api/admins/pcr/result",[
            "data" => [
                [
                    'clue'          => 'DFSSA003256',
                    'name'          => 'name',
                    'lastname1'     => 'lastname',
                    'lastname2'     => '',
                    'phone'         => '44444444444',
                    'date'          => date('Y-m-d'),
                    'resultTest'    => 1,
                ]
            ],
        ]);
        $response->assertStatus(200);
    }

    public function testclear(){
        sleep(1); //sync elasticsearch

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::$jwtUser])->json('DELETE', '/api/users/profiles/' . self::$profileID);
        $response->assertStatus(200);

        sleep(1); //sync elasticsearch
        $phoneHash  = hash_pbkdf2('sha256', self::$phoneNumber, $this->app['config']['app.ENCRYPTION_SALT'], 30000, 0);

        Elastic::delete(['index' => 'otps', 'id' => $phoneHash, 'client'    => ['ignore' => 404]]);
        Elastic::deleteByQuery([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);

        Elastic::delete(['index' => 'admins', 'id' => self::$adminID, 'client'    => ['ignore' => 404]]);
        $this->assertTrue(true);

        sleep(1); //sync elasticsearch
    }
}
