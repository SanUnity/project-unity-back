<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;
use Log;
use Str;
use App\JWT;
use App\Parse;
use App\Http\Helpers\Test;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use App\Http\Controllers\UserController;
use Illuminate\Encryption\Encrypter;

class User {

    private static function decryptData($value){
        try{
            $value = Crypt::decryptString($value);
        }catch(\Exception $e){
            //bad encryption
        }
        return $value;
    }

    public static function createEditProfile(Request $request, $userID = false, $profileID = false, $timestamp = false){

        $data = [
            'name'          => $request->input('name'),
            'lastname1'     => $request->input('lastname1'),
            'lastname2'     => $request->input('lastname2'),
            'age'           => $request->input('age'),
            'gender'        => $request->input('gender'),
            'postalCode'    => $request->input('postalCode'),
            'imss'          => $request->input('imss'),
            'street'        => $request->input('street'),
            'numberExternal'=> $request->input('numberExternal'),
            'numberInternal'=> $request->input('numberInternal'),
            'stateID'       => $request->input('stateID'),
            'municipalityID'=> $request->input('municipalityID'),
            'suburbID'      => $request->input('suburbID'),
            'state'         => $request->input('state'),
            'municipality'  => $request->input('municipality'),
            'suburb'        => $request->input('suburb'),
        ];

        if($data['lastname1'] === null)         unset($data['lastname1']);      else $data['lastname1'] = Crypt::encryptString($data['lastname1']);
        if($data['lastname2'] === null)         unset($data['lastname2']);      else $data['lastname2'] = Crypt::encryptString($data['lastname2']);
        if($data['imss'] === null)              unset($data['imss']);
        if($data['street'] === null)            unset($data['street']);         else $data['street']    = Crypt::encryptString($data['street']);
        if($data['numberExternal'] === null)    unset($data['numberExternal']);
        if($data['numberInternal'] === null)    unset($data['numberInternal']);
        if($data['stateID'] === null)           unset($data['stateID']);
        if($data['municipalityID'] === null)    unset($data['municipalityID']);
        if($data['suburbID'] === null)          unset($data['suburbID']);
        if($data['state'] === null)             unset($data['state']);
        if($data['municipality'] === null)      unset($data['municipality']);
        if($data['suburb'] === null)            unset($data['suburb']);

        if(!$profileID){
            $updateUser     = false;
            $userUpdateData = [];
            $user           = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);
            if($user && $user['found']){
                if(!$timestamp){
                    $timestamp = time();
                }
                $data['userID']             = $userID;
                $data['timestamp']          = $timestamp;
                $data['lastTest']           = 0;
                $data['firstSymptom']       = false;
                $data['totalTests']         = 0;
                $data['totalExitRequests']  = 0;
                $data['riskContacts']       = $user['_source']['riskContacts'];;
                $data['totalDevices']       = $user['_source']['totalDevices'];
                $data['contactTrace']       = $user['_source']['contactTrace'];
                $data['geo']                = $user['_source']['geo'];
                $data['totalProfiles']      = $user['_source']['totalProfiles'] + 1;
                $data['anonymous']          = isset($user['_source']['anonymous']) ? $user['_source']['anonymous'] : false;
                if(empty($user['_source']['mainProfile'])){
                    $data['mainProfile'] = true;
                }else{
                    $data['mainProfile'] = false;
                }


                if(empty($user['_source']['updateChannels']) && !empty($user['_source']['devicesARN'])){
                    if(isset($data['stateID']) && isset($data['municipalityID']) && isset($data['suburbID'])){
                        Parse::updateChannels($user['_source']['devicesARN'], $data['stateID'], $data['municipalityID'], $data['suburbID']);
                        $userUpdateData['updateChannels'] = true;
                    }
                }

                $resultProfile = Elastic::index(['index' => 'profiles', 'body' => $data, 'refresh' => "false"]);
                if($resultProfile && !empty($resultProfile['_id'])){
                    $profileID = $resultProfile['_id'];

                    if(empty($user['_source']['mainProfile'])){
                        $userUpdateData['mainProfile']      = $profileID;
                        $userUpdateData['age']              = $data['age'];
                        $userUpdateData['gender']           = $data['gender'];
                        $userUpdateData['stateID']          = $data['stateID'];
                        $userUpdateData['municipalityID']   = $data['municipalityID'];
                        $userUpdateData['suburbID']         = $data['suburbID'];
                        $userUpdateData['postalCode']       = $data['postalCode'];
                    }
                    
                    $userUpdateData['totalProfiles']    = $data['totalProfiles'];

                    Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => $userUpdateData],'refresh' => "false"]);
                }else{
                    return response('unexpected error', 400);
                }
            }
        }else{
            $resultProfile = Elastic::update(['index' => 'profiles', 'id' => $profileID, 'body' => ['doc' => $data],'refresh' => "false"]);
        }

        return ['id' => $profileID];
    }

    public static function removeProfile($profileID){
        
        Elastic::delete(['index' => 'profiles', 'id' => $profileID]);

        // delete all test from this profile
        Elastic::deleteByQuery([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['profileID' => $profileID]] ] ] ]]
        ]);
    }

    public static function createDataTest($profileID, $request, $timestamp, $profile){
        $data = [
            'profileID'         => $profileID,
            'timestamp'         => $timestamp,
            'firstSymptom'      => false,
            'symptoms'          => $request->input('symptoms'),
            'symptomWeek'       => $request->input('symptomWeek'),
            'diabetes'          => $request->input('diabetes'),
            'obesity'           => $request->input('obesity'),
            'hypertension'      => $request->input('hypertension'),
            'defenses'          => $request->input('defenses'),
            'breathing'         => $request->input('breathing'),
            'pregnant'          => $request->input('pregnant'),
            'stateID'           => !empty($profile['_source']['stateID'])           ? $profile['_source']['stateID']        : 0,
            'municipalityID'    => !empty($profile['_source']['municipalityID'])    ? $profile['_source']['municipalityID'] : 0,
            'suburbID'          => !empty($profile['_source']['suburbID'])          ? $profile['_source']['suburbID']       : 0,
            'postalCode'        => !empty($profile['_source']['postalCode'])        ? $profile['_source']['postalCode']     : '',
            'age'               => !empty($profile['_source']['age'])               ? $profile['_source']['age']            : 0,
            'gender'            => !empty($profile['_source']['gender'])            ? $profile['_source']['gender']         : '',
            'anonymous'         => isset($profile['_source']['anonymous'])          ? $profile['_source']['anonymous']      : false,
        ];

        $data['level'] = 0;
        if(!$data['symptoms'] && !$data['symptomWeek']){
            $data['level'] = 0; //low
        }else if(!$data['symptoms'] && $data['symptomWeek']) {
            $data['level'] = 1; //3 en su sistem, Sintomas previos  //medium-low
            if($data['age'] >= 60){
                $data['level'] = 2; //5 en su sistema, Sintomas previos grupo vulnerable //medium-vulnerable
            }
        }else if($data['symptoms']){
            if(!$data['breathing']){
                $data['level'] = 3; //4 en su sistema, Sintomas leves //medium
                if($data['age'] >= 60 || $data['diabetes'] || $data['obesity'] || $data['hypertension'] || $data['defenses'] || $data['pregnant']){
                    $data['level'] = 4; //2 en su sistema, Sintomas leves grupo vulnerable //medium-high
                }
            }else{
                $data['level'] = 5; //1 en su sistema, Sintomas graves // high
            }
        }

        $lastLevel = (!empty($profile['_source']['level']) ? $profile['_source']['level'] : -1);

        if($lastLevel == -1){
            if($data['level'] > 0){
                $data['trend']  = 'negative';
            }else{
                $data['trend']  = 'positive';
            }
        }else if($lastLevel < $data['level']){
            $data['trend']  = 'negative';
        }else if($lastLevel > $data['level']){
            $data['trend']  = 'positive';
        }else{
            $data['trend']  = 'neutral';
        }
        if(empty($profile['_source']['firstSymptom']) && $data['level'] > 0){
            $data['firstSymptom']  = true;
        }

        $resultTest = Elastic::index(['index' => 'tests', 'body' => $data,'refresh' => "false"]);

        try{
            $totalTests = (!empty($profile['_source']['totalTests']) ? $profile['_source']['totalTests'] : 0) + 1;

            $auxData = [
                'lastTest'          => $data['timestamp'],
                'symptoms'          => $data['symptoms'],
                'symptomWeek'       => $data['symptomWeek'],
                'diabetes'          => $data['diabetes'],
                'obesity'           => $data['obesity'],
                'hypertension'      => $data['hypertension'],
                'defenses'          => $data['defenses'],
                'breathing'         => $data['breathing'],
                'pregnant'          => $data['pregnant'],
                'level'             => $data['level'],
                'trend'             => $data['trend'],
                'totalTests'        => $totalTests,
            ];

            if($data['firstSymptom']){
                $auxData['firstSymptom'] = true;
            }

            Elastic::update(['index' => 'profiles', 'id' => $profileID, 'body' => ['doc' => $auxData],'refresh' => "false"]);
        }catch(\Exception $e){
            // error version conflict
        }

        $data['name']   = $profile['_source']['name'];
        $data['id']     = $resultTest['_id'];

        return $data;
    }

    public static function createExitRequest($profileID, Request $request){
        
        $hash = false;
        
        while(true){
            $hash = Str::random(40);
            $exitRequest = Elastic::get(['index' => 'exit_requests', 'id' => $hash, 'client' => ['ignore' => 404]]);
            if(!$exitRequest || !$exitRequest['found']){
                break;
            }
        }

        $timestamp = time();
        $data = [
            'profileID' => $profileID,
            'timestamp' => $timestamp,
            'expiry'    => $timestamp + (60 * 60 * 24),
            'deleted'   => false,
            'destiny'   => $request->input('destiny'),
            'motive'    => $request->input('motive'),
        ];
        
        Elastic::index(['index' => 'exit_requests', 'id' => $hash, 'body' => $data, 'refresh' => "wait_for"]);
        
        $data['id']     = $hash;
        $data['url']    = config('app.url') . '/exitRequests/' . $hash;
        
        return $data;
    }

    public static function deleteExitRequest($profileID, $exitRequestID){

        $exitRequest = Elastic::get(['index' => 'exit_requests', 'id' => $exitRequestID, 'client' => ['ignore' => 404]]);
        if(!$exitRequest || !$exitRequest['found'] || $exitRequest['_source']['deleted']){
            return response('Invalid Data', 400);
        }
        if($exitRequest['_source']['profileID'] !== $profileID){
            return response('Unauthorized user', 401);
        }

        Elastic::update(['index' => 'exit_requests', 'id' => $exitRequestID, 'body' => ['doc' => ['deleted' => true]],'refresh' => "wait_for"]);
    }

    public static function getTests($profileID){
        $tests = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 100,
                'sort' => [[ 'timestamp' => ['order' => 'desc','missing' =>  '_last']]],
                'query' => ['bool' => ['must' => [ 
                    ['term' => ['profileID' => $profileID]],
            ] ] ]]
        ]);

        $finalTests = [];
        if($tests && $tests['hits']['total']['value']){
            foreach($tests['hits']['hits'] as $test){
                $resultTest = '-';
                if(isset($test['_source']['level'])){
                    switch($test['_source']['level']){
                        case 0 : $resultTest = 'low';                   break;
                        case 1 : $resultTest = 'medium-low';            break;
                        case 2 : $resultTest = 'medium-vulnerable';     break;
                        case 3 : $resultTest = 'medium';                break;
                        case 4 : $resultTest = 'medium-high';           break;
                        case 5 : $resultTest = 'high';                  break;
                    }
                }

                $finalTests[] = [
                    'folio'     => $test['_id'],
                    'level'     => $resultTest,
                    'timestamp' => $test['_source']['timestamp'],
                ];
            }
        }

        return $finalTests;
    }

    public static function getExitRequest($profileIDs, &$profilesData){
        return; //disable in federal

        $exitRequests = Elastic::search([
            'index'     => 'exit_requests',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 100,
                'sort' => [[ 'expiry' => ['order' => 'asc','missing' =>  '_last']]],
                'query' => ['bool' => ['must' => [ 
                    ['terms' => ['profileID' => $profileIDs]],
                    ['term' => ['deleted' => false]],
                    ['range' => ['expiry' => ['gte' => time()]]]
            ] ] ]]
        ]);

        $finalExitRequests = [];
        if($exitRequests && $exitRequests['hits']['total']['value']){
            foreach($exitRequests['hits']['hits'] as $exit){
                if(!isset($finalExitRequests[$exit['_source']['profileID']])){
                    $finalExitRequests[$exit['_source']['profileID']] = [];
                }
                $finalExitRequests[$exit['_source']['profileID']][] = [
                    'id'        => $exit['_id'],
                    'profileID' => $exit['_source']['profileID'],
                    'timestamp' => $exit['_source']['timestamp'],
                    'expiry'    => $exit['_source']['expiry'],
                    'destiny'   => $exit['_source']['destiny'],
                    'motive'    => $exit['_source']['motive'],
                    'url'       => config('app.url') . '/exitRequests/' . $exit['_id']
                ];
            }
        }
        foreach($profilesData as &$profile){
            if(isset($finalExitRequests[$profile['id']])){
                $profile['exitRequests'] = $finalExitRequests[$profile['id']];
            }
        }
    }

    public static function bluetraceTempIDs($userID){
        $tempIDs    = [];
        $timePeriod = config('app.BLUETRACE_PERIOD') * 60;
        $key        = substr(hash('sha256', config('app.BLUETRACE_PASSWORD'), true), 0, 32);
        $cipher     = 'aes-256-gcm';
        $iv_len     = openssl_cipher_iv_length($cipher);
        $tag_length = 16;
        $timeStart  = time();
        $timeEnd    = $timeStart + $timePeriod;

        for($i = 0; $i < config('app.BLUETRACE_BATCHSIZE'); $i++){
            $iv     = openssl_random_pseudo_bytes($iv_len);
            $tag    = ""; // will be filled by openssl_encrypt
            
            $textToEncrypt  = $timeStart . $timeEnd . $userID;
            $ciphertext     = openssl_encrypt($textToEncrypt, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, "", $tag_length);
            $encrypted      = base64_encode($iv.$tag.$ciphertext);

            $tempIDs[] = [
                'tempID'    => $encrypted,
                'startTime' => $timeStart,
                'expiryTime'=> $timeEnd,
            ];

            $timeStart  += $timePeriod;
            $timeEnd    += $timePeriod;
        }

        return [
            'status'        => 'SUCCESS',
            'tempIDs'       => $tempIDs,
            'refreshTime'   => time() + 3600 * config('app.BLUETRACE_REFRESH_INTERVAL')
        ];
    }

    public static function bluetraceData($userID, Request $request){
        $manufacturer   = $request->input('manufacturer');
        $model          = $request->input('model');
        $records        = $request->input('records');

        if(!empty($records)){
            $params     = ['body' => []];

            $key        = substr(hash('sha256', config('app.BLUETRACE_PASSWORD'), true), 0, 32);
            $cipher     = 'aes-256-gcm';
            $iv_len     = openssl_cipher_iv_length($cipher);
            $tag_length = 16;
            $timestamp  = time();
            $i          = 0;

            foreach($records as $record){
                try{
                    if($record['org'] == 'MX_GCM' && !empty($record['msg']) && $record['msg'] != 'not_found'){
                        $params['body'][] = [
                            'index' => [
                                '_index' => 'bluetraces',
                            ]
                        ];
                        
                        $encrypted  = base64_decode($record['msg']);
                        $iv         = substr($encrypted, 0, $iv_len);
                        $tag        = substr($encrypted, $iv_len, $tag_length);
                        $ciphertext = substr($encrypted, $iv_len + $tag_length);
                        $decrypted  = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

                        if ($decrypted !== false) {                    
                            $timeStart  = substr($decrypted, 0, 10);
                            $timeEnd    = substr($decrypted, 10, 10);
                            $userID2    = substr($decrypted, 20);

                            $params['body'][] = [
                                'userID1'   => $userID,
                                'userID2'   => $userID2,
                                'timestamp' => $record['timestamp'],
                                'timeStart' => (int) $timeStart,
                                'timeEnd'   => (int) $timeEnd,
                                'timeUpload'=> (int) $timestamp,
                                'modelP'    => $record['modelP'],
                                'modelC'    => $record['modelC'],
                                'rssi'      => $record['rssi'],
                                'txPower'   => $record['txPower'],
                                'v'         => $record['v'],
                            ];

                            $i++;
                            if($i >= 500){
                                try{
                                    if(!empty($params['body'])){
                                        Elastic::bulk($params);
                                    }
                                }catch (\Exception $e){
                        
                                }
                                $params = ['body' => []];
                                $i      = 0;
                            }
                        }
                    }
                }catch (\Exception $e2){

                }
            }

            try{
                if(!empty($params['body'])){
                    Elastic::bulk($params);
                }
            }catch (\Exception $e){
    
            }
        }
    }

    public static function devicetoken($userID, Request $request){
        $deviceToken    = $request->input('devicetoken');
        $deviceType     = $request->input('devicetype');

        $userData = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);
        if($userData && $userData['found']){

            if(!isset($userData['_source']['devices'])){
                $userData['_source']['devices'] = [];
            }

            $userData['_source']['devices'][]  = $deviceToken;
            $userData['_source']['devices']    = array_values(array_unique($userData['_source']['devices']));

            Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => [
                'devices'       => $userData['_source']['devices'],
                'totalDevices'  => count($userData['_source']['devices']),
            ]],'refresh' => "false"]);

            //TODO update all profiles


            $response = Queue::pushRaw(json_encode([
                'type'          => 'registerDevice',
                'userID'        => $userID,
                'deviceToken'   => $deviceToken,
                'deviceType'    => $deviceType,
            ]));

            Log::debug("send devicetoken  ", [
                'type'          => 'registerDevice',
                'userID'        => $userID,
                'deviceToken'   => $deviceToken,
                'deviceType'    => $deviceType,
                'response'      => $response,
            ]);
        }
    }

    public static function anonymousDevicetoken(Request $request){
        $deviceToken    = $request->input('devicetoken');
        $deviceType     = $request->input('devicetype');

        $response = Queue::pushRaw(json_encode([
            'type'          => 'registerAnonymousDevice',
            'deviceToken'   => $deviceToken,
            'deviceType'    => $deviceType,
        ]));

        Log::debug("send devicetoken  ", [
            'type'          => 'registerAnonymousDevice',
            'deviceToken'   => $deviceToken,
            'deviceType'    => $deviceType,
            'response'      => $response,
        ]);
    }

    public static function saveError($userID, Request $request){
        $log                = $request->input('log');
        $log['userID']      = $userID ? $userID : null;
        $log['timestamp']   = time();

        Elastic::index(['index' => 'logs_error', 'body' => $log,'refresh' => "false"]);
    }

    public static function logout($userID, Request $request){
        $userData = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);

        if(!$userData || !$userData['found']){
            return false;
        }

        $logout = [];
        if(!empty($userData['_source']['logout'])){
            $logout = $userData['_source']['logout'];
        }

        try{
            $decoded = \Firebase\JWT\JWT::decode($request->bearerToken(), config('app.JWT_PUBLIC_KEY'), array('RS256'));

            $timestamp = time();
            foreach($logout as $jti => $time){
                if($timestamp < $time){
                    unset($logout[$jti]);
                }
            }

            $logout[$decoded->jti] = $decoded->exp;

            Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => ['logout' => $logout]],'refresh' => "wait_for"]);

            return true;

        }catch(\Exception $e){
            return false;
        }
    }

    public static function setStateUser($userID, Request $request){
        $state = $request->input('state');

        Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => ['state' => $state]],'refresh' => "false"]);
    }

    public static function setDefaultProfile($userID, $profileID){
        

        Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => ['mainProfile' => $profileID]],'refresh' => "false"]);
    }


    public static function setSemaphoreFav($userID, $request){
        $fav = $request->input('fav');

        Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => ['semaphoreFav' => $fav]],'refresh' => "false"]);
    }

    public static function getValidateCenter($centerID, $returnCenter = false){

        try{
            $cipher     = new Encrypter(md5(config('app.QR_PASSWORD')), 'AES-256-CBC');
            $centerID   = $cipher->decryptString($centerID);
        }catch(\Exception $e){
            //bad encryption
        }
        
        $valid = false;
        switch($centerID){
            case 'DFSSA003256' :
            case 'DFSSA003244' :
                $valid = true;
        }

        $return = [
            'valid'     => $valid
        ];

        if($returnCenter){
            $return['centerID'] = $centerID;
        }

        return $return;
    }

    public static function checkUserExposed($phoneHash){
        
        $user = Elastic::search([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 1,'query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);

        if($user && $user['hits']['total']['value']){
            $userData = $user['hits']['hits'][0];

            Elastic::update(['index' => 'users', 'id' => $userData['_id'], 'body' => ['doc' => [
                'exposed' => true
            ] ],'refresh' => "false"]);
        }
    }

    public static function checkContactExposed($phoneHash){
        
        $contacts = Elastic::search([
            'index'     => 'contact_tracing_manual',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 1,'query' => ['bool' => ['must' => [ 
                ['term' => ['phoneHash' => $phoneHash]],
                ['range' => ['timestamp' => ['gte' => strtotime('-14 days')]]],
            ] ] ]]
        ]);

        if($contacts && $contacts['hits']['total']['value']){
            return true;
        }
        return false;
    }
}