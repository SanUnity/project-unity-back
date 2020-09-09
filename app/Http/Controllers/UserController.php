<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Elastic;
use Str;
use App\JWT;
use App\Parse;
use App\SMS;
use App\Http\Helpers\User;
use App\Http\Helpers\State;
use App\Http\Helpers\HospitalPublic;
use App\Http\Helpers\Info;
use App\Http\Helpers\DP3T;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;

class UserController extends Controller{

    public function getProfiles($userID){
        $profilesData   = [];
        $profiles       = Elastic::search([
            'index'     => 'profiles',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 50,
                'query' => ['bool' => ['must' => [ ['term' => ['userID' => $userID]] ] ] ],
                'sort' => [[ 'timestamp' => ['order' => 'asc','missing' =>  '_last']]]
            ]
        ]);

        if($profiles && $profiles['hits']['total']['value']){
            $profileIDs = [];
            foreach($profiles['hits']['hits'] as $profile){
                $resultTest = '-';
                if(isset($profile['_source']['level'])){
                    switch($profile['_source']['level']){
                        case 0 : $resultTest = 'low';                   break;
                        case 1 : $resultTest = 'medium-low';            break;
                        case 2 : $resultTest = 'medium-vulnerable';     break;
                        case 3 : $resultTest = 'medium';                break;
                        case 4 : $resultTest = 'medium-high';           break;
                        case 5 : $resultTest = 'high';                  break;
                    }
                }

                $home = '';
                if(isset($profile['_source']['street'])){
                    $home = $this->decryptData($profile['_source']['street']);
                }
                if(isset($profile['_source']['numberExternal'])){
                    if(!empty($home))   $home .= ', ';
                    $home .= $profile['_source']['numberExternal'];
                }
                if(isset($profile['_source']['suburb'])){
                    if(!empty($home))   $home .= ', ';
                    $home .= 'Col.' . $profile['_source']['suburb'];
                }
                if(isset($profile['_source']['municipality'])){
                    if(!empty($home))   $home .= ', ';
                    $home .= 'Alc.' . $profile['_source']['municipality'];
                }

                $name = $profile['_source']['name'];
                if(!empty($profile['_source']['lastname1'])){
                    $name .= ' ' . $this->decryptData($profile['_source']['lastname1']);
                }

                $auxProfile = [
                    'id'            => $profile['_id'],
                    'name'          => $name,
                    'lastTest'      => $profile['_source']['lastTest'],
                    'level'         => $resultTest,
                    'postalCode'    => isset($profile['_source']['postalCode']) ? $profile['_source']['postalCode'] : null,
                    'passStatus'    => isset($profile['_source']['passStatus']) ? $profile['_source']['passStatus'] : 'good',
                    'exitRequests'  => [],
                    'pcr'           => [],
                ];

                $profilesData[] = $auxProfile;
                $profileIDs[]   = $profile['_id'];
            }

            // User::getExitRequest($profileIDs, $profilesData);
            DP3T::getPCRs($userID, $profilesData);
        }

        return $profilesData;
    }

    public function session(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Unauthorized user', 401);
        }
        $userData = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);

        if(!$userData || !$userData['found']){
            return response('Unauthorized user', 401);
        }

        $resultValidate = [
            'id' => $userID,
            'profiles' => [ ]
        ];

        $resultValidate['profiles']         = $this->getProfiles($userID);
        $resultValidate['jwt']              = JWT::createToken($userID);
        $resultValidate['covidPositive']    = isset($userData['_source']['covidPositive'])  ? $userData['_source']['covidPositive'] : false;
        $resultValidate['state']            = isset($userData['_source']['state'])          ? $userData['_source']['state']         : null;
        $resultValidate['mainProfile']      = isset($userData['_source']['mainProfile'])    ? $userData['_source']['mainProfile']   : null;
        $resultValidate['semaphoreFav']     = isset($userData['_source']['semaphoreFav'])   ? $userData['_source']['semaphoreFav']  : [];
        $resultValidate['exposed']          = isset($userData['_source']['exposed'])        ? $userData['_source']['exposed']       : false;

        if(empty($userData['_source']['anonymous'])){
            $resultValidate['phone']        = isset($userData['_source']['phone'])          ? $this->decryptData($userData['_source']['phone'])  : '';
        }else{
            $resultValidate['phone']        = '';
        }

        return $resultValidate;
    }
    
    public function signup(Request $request){
        $phone = $request->input('phone');
        if(empty($phone)){
            return response('Invalid phone', 400);
        }

        $phoneHash  = hash_pbkdf2('sha256', $phone, config('app.ENCRYPTION_SALT'), 30000, 0);
        $otpData    = Elastic::get(['index' => 'otps', 'id' => $phoneHash, 'client' => ['ignore' => 404]]);
        $data       = null;

        if($otpData && $otpData['found']){
            if($otpData['_source']['retries'] >= 4){
                if($otpData['_source']['timestamp'] > (time() - 60 * 30)){
                    return response([
                        'message'   => 'Max retries exceeded',
                        'next'      => $otpData['_source']['timestamp'] + 60 *30,
                    ], 402); 
                }
                $otpData['_source']['retries']      = 2;
                $otpData['_source']['timestamp']    = time();
            }
            $data = $otpData['_source'];
            
            if(!is_array($data['otp'])){
                $data['otp'] = [
                    [
                        'timestamp' => $data['lastTimestamp'],
                        'otp'       => $data['otp']
                    ]
                ];
            }
            $data['lastTimestamp'] = time();
        }

        if($data === null){
            $data = [
                'phone'         => $phoneHash,
                'phoneEncrypt'  => Crypt::encryptString($phone),
                'timestamp'     => time(),
                'lastTimestamp' => time(),
                'retries'       => 0,
                'otp'           => [],
            ];
        }

        $data['retries']++;

        $otp = random_int(100000,999999); 

        if(config('app.env') !== 'production'){
            $otp = 123456;
        }

        $data['otp'][] = [
            'timestamp' => $data['lastTimestamp'],
            'otp'       => $otp
        ];

        Elastic::index(['index' => 'otps', 'id' => $phoneHash, 'body' => $data, 'refresh' => "false"]);

        SMS::send($phone, $otp . ' es tu código de verificación para ' . config('app.name'));
        
        return [];
    }

    private function checkOTPs($data, $otp, $timestamp){
        if(!is_array($data['otp'])){
            if($data['otp'] == $otp && $data['lastTimestamp'] >= $timestamp){
                return true;
            }
        }else{
            foreach($data['otp'] as $aux) {
                if($aux['otp'] == $otp && $aux['timestamp'] >= $timestamp){
                    return true;
                }
            }
        }
        return false;
    }
    
    public function validateOTP(Request $request){
        $phone  = $request->input('phone');
        $otp    = $request->input('otp');
        if(empty($phone) || empty($otp)){
            return response('invalid data', 400);
        }

        //hash phone
        $phoneHash  = hash_pbkdf2('sha256', $phone, config('app.ENCRYPTION_SALT'), 30000, 0);
        $otpData    = Elastic::get(['index' => 'otps', 'id' => $phoneHash, 'client' => ['ignore' => 404]]);

        if(!$otpData || !$otpData['found']){ //Invalid OTP
            return response('Invalid OTP', 401);
        }

        if($otpData['_source']['retries'] >= 4){ //Max retries exceeded
            if($otpData['_source']['timestamp'] > (time() - 60 * 30)){
                return response([
                    'message'   => 'Max retries exceeded',
                    'next'      => $otpData['_source']['timestamp'] + 60 * 30,
                ], 402); 
            }
            $otpData['_source']['retries']      = 2;
            $otpData['_source']['timestamp']    = time();
        }

        if(!$this->checkOTPs($otpData['_source'], $otp, time() - 60 * 15)){ //Invalid OTP
            $data = $otpData['_source'];
            $data['lastTimestamp'] = time();
            $data['retries']++;
            Elastic::index(['index' => 'otps', 'id' => $phoneHash, 'body' => $data, 'refresh' => "false"]);
            return response('Invalid OTP', 401);
        }

        //remove OTP
        Elastic::delete(['index' => 'otps', 'id' => $phoneHash]);

        return $this->createUser($phone, $phoneHash, false, false, $request);
    }

    public function signupAnonymous(Request $request){
        
        $phone      = time() . Str::random(25);
        $phoneHash  = hash_pbkdf2('sha256', $phone, config('app.ENCRYPTION_SALT'), 30000, 0);

        return $this->createUser($phone, $phoneHash, false, true, $request);
    }

    public function createUser($phone, $phoneHash, $timestamp = false, $anonymous = false, $request = false){
        //check if user exists
        $user = Elastic::search([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 1,'query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);

        $resultValidate = [];
        
        if(!$user || !$user['hits']['total']['value']){
            if(!$timestamp){
                $timestamp = time();
            }
            $data = [
                'phoneHash'         => $phoneHash,
                'phone'             => Crypt::encryptString($phone),
                'timestamp'         => $timestamp,
                'anonymous'         => $anonymous,
                'mainProfile'       => null,
                'updateChannels'    => false,
                'contactTrace'      => false,
                'geo'               => false,
                'totalProfiles'     => 0,
                'totalDevices'      => 0,
                'riskContacts'      => 0,
                'devices'           => [],
                'devicesARN'        => [],
                'state'             => $request ? $request->input('state') : null,
                'covidPositive'     => false,
                'semaphoreFav'      => [],
                // 'exposed'           => User::checkContactExposed($phoneHash),
                'exposed'           => false,
            ];

            $resultUser = Elastic::index(['index' => 'users', 'body' => $data,'refresh' => "false"]);
            if($resultUser && !empty($resultUser['_id'])){
                $resultValidate = [
                    'id' => $resultUser['_id'],
                    'profiles' => [
                        
                    ],
                    'state'             => $data['state'],
                    'covidPositive'     => $data['covidPositive'],
                    'mainProfile'       => $data['mainProfile'],
                    'semaphoreFav'      => $data['semaphoreFav'],
                    'exposed'           => $data['exposed'],
                ];
                if(!$anonymous){
                    $resultValidate['phone'] = $phone;
                }else{
                    $resultValidate['phone'] = '';
                }
            }else{
                return response('unexpected error', 400);
            }
        }else{

            $userData = $user['hits']['hits'][0];
            
            $resultValidate = [
                'id'            => $userData['_id'],
                'profiles'      => [ ],
                'state'         => isset($userData['_source']['state'])             ? $userData['_source']['state']             : null,
                'covidPositive' => isset($userData['_source']['covidPositive'])     ? $userData['_source']['covidPositive']     : false,
                'mainProfile'   => isset($userData['_source']['mainProfile'])       ? $userData['_source']['mainProfile']       : null,
                'semaphoreFav'  => isset($userData['_source']['semaphoreFav'])      ? $userData['_source']['semaphoreFav']      : [],
                'exposed'       => isset($userData['_source']['exposed'])           ? $userData['_source']['exposed']           : false,
            ];

            if(empty($userData['_source']['anonymous'])){
                $resultValidate['phone'] = isset($userData['_source']['phone']) ? $this->decryptData($userData['_source']['phone'])  : '';
            }else{
                $resultValidate['phone'] = '';
            }

            if($request && $request->input('state')){
                Elastic::update(['index' => 'users', 'id' => $resultValidate['id'], 'body' => ['doc' => ['state' => $request->input('state')]],'refresh' => "false"]);
                $resultValidate['state'] = $request->input('state');
            }

            $resultValidate['profiles'] = $this->getProfiles($resultValidate['id']);
        }

        $resultValidate['jwt'] = JWT::createToken($resultValidate['id']);

        return $resultValidate;
    }

    public function createProfile(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'name'          => 'required',
            'age'           => 'required|integer',
            'gender'        => 'required|in:male,female,nonBinary',
            'postalCode'    => 'required|numeric',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return User::createEditProfile($request, $userID);
    }

    public function editProfile($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'name'          => 'required',
            'age'           => 'required|integer',
            'gender'        => 'required|in:male,female,nonBinary',
            'postalCode'    => 'required|numeric',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }
        
        return User::createEditProfile($request, $userID, $profileID);
    }

    public function removeProfile($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return User::removeProfile($profileID);
    }

    public function getProfile($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $data = [
            'name'          => isset($profile['_source']['name'])           ? $profile['_source']['name'] : '',
            'lastname1'     => isset($profile['_source']['lastname1'])      ? $this->decryptData($profile['_source']['lastname1']) : '',
            'lastname2'     => isset($profile['_source']['lastname2'])      ? $this->decryptData($profile['_source']['lastname2']) : '',
            'age'           => isset($profile['_source']['age'])            ? $profile['_source']['age'] : '',
            'gender'        => isset($profile['_source']['gender'])         ? $profile['_source']['gender'] : '',
            'postalCode'    => isset($profile['_source']['postalCode'])     ? $profile['_source']['postalCode'] : '',
            'imss'          => isset($profile['_source']['imss'])           ? $profile['_source']['imss'] : false,
            'street'        => isset($profile['_source']['street'])         ? $this->decryptData($profile['_source']['street']) : '',
            'numberExternal'=> isset($profile['_source']['numberExternal']) ? $profile['_source']['numberExternal'] : '',
            'numberInternal'=> isset($profile['_source']['numberInternal']) ? $profile['_source']['numberInternal'] : '',
            'stateID'       => isset($profile['_source']['stateID'])        ? $profile['_source']['stateID'] : '',
            'municipalityID'=> isset($profile['_source']['municipalityID']) ? $profile['_source']['municipalityID'] : '',
            'suburbID'      => isset($profile['_source']['suburbID'])       ? $profile['_source']['suburbID'] : '',
            'state'         => isset($profile['_source']['state'])          ? $profile['_source']['state'] : '',
            'municipality'  => isset($profile['_source']['municipality'])   ? $profile['_source']['municipality'] : '',
            'suburb'        => isset($profile['_source']['suburb'])         ? $profile['_source']['suburb'] : '',
            'lastTest'      => isset($profile['_source']['suburbID'])       ? $profile['_source']['lastTest'] : 0,
        ];

        return $data;
    }

    private function decryptData($value){
        try{
            $value = Crypt::decryptString($value);
        }catch(\Exception $e){
            //bad encryption
        }
        return $value;
    }

    public function testsNew($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);

        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $now        = time();
        $timeBefore = $now - config('app.TIME_BETWEEN_TEST');
        if($profile['_source']['lastTest'] != 0 && $profile['_source']['lastTest'] > $timeBefore){
            return response('You can only do a test every '.(config('app.TIME_BETWEEN_TEST')/60/60).'h', 402);
        }

        $data = User::createDataTest($profileID, $request, $now, $profile);

        $resultTest = '-';
        if(isset($data['level'])){
            switch($data['level']){
                case 0 : $resultTest = 'low';                   break;
                case 1 : $resultTest = 'medium-low';            break;
                case 2 : $resultTest = 'medium-vulnerable';     break;
                case 3 : $resultTest = 'medium';                break;
                case 4 : $resultTest = 'medium-high';           break;
                case 5 : $resultTest = 'high';                  break;
            }
        }

        return [
            'folio'     => $data['id'],
            'id'        => $profileID,
            'name'      => $data['name'],
            'lastTest'  => $data['timestamp'],
            'level'     => $resultTest,
        ];
    }

    public function postalCode($postalCode, Request $request){
        //get location from postal code
        $postalCodeData = Elastic::search([
            'index'     => 'postal_codes',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 100,'query' => ['bool' => ['must' => [ 
                ['term' => ['postalCode' => $postalCode]],
                ['term' => ['country' => 'MX']],
            ] ] ]]
        ]);

        $finalPostalCodes   = [];
        $states             = [];
        $municipalities     = [];
        $suburbsIDs         = [];
        if($postalCodeData && $postalCodeData['hits']['total']['value']){
            foreach($postalCodeData['hits']['hits'] as $postal){
                $finalPostalCodes[] = [
                    'stateID'           => $postal['_source']['stateID'],
                    'municipalityID'    => $postal['_source']['municipalityID'],
                    'suburbID'          => $postal['_source']['suburbID'],
                ];
                if(!isset($states[$postal['_source']['stateID']])){
                    $states[$postal['_source']['stateID']] = $postal['_source']['stateID'];
                }
                if(!isset($municipalities[$postal['_source']['municipalityID']])){
                    $municipalities[$postal['_source']['municipalityID']] = $postal['_source']['municipalityID'];
                }
                if(!isset($suburbsIDs[$postal['_source']['suburbID']])){
                    $suburbsIDs[$postal['_source']['suburbID']] = $postal['_source']['suburbID'];
                }
            }
        }

        $finalStates = [];
        if(!empty($states)){
            $states = Elastic::search([
                'index'     => 'states',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                    ['terms' => ['id' => array_values($states)]]
                ] ] ]]
            ]);
    
            if($states && $states['hits']['total']['value']){
                foreach($states['hits']['hits'] as $state){
                    $finalStates[$state['_source']['id']] = $state['_source'];
                }
            }
        }
        $finalMunicipalities = [];
        if(!empty($municipalities)){
            $municipalities = Elastic::search([
                'index'     => 'municipalities',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                    ['terms' => ['id' => array_values($municipalities)]]
                ] ] ]]
            ]);
    
            if($municipalities && $municipalities['hits']['total']['value']){
                foreach($municipalities['hits']['hits'] as $municipality){
                    $finalMunicipalities[$municipality['_source']['id']] = $municipality['_source']['name'];
                }
            }
        }
        
        $finalSuburbs = [];
        if(!empty($suburbsIDs)){
            $suburbs = Elastic::search([
                'index'     => 'suburbs',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 1000, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($suburbsIDs)]] ] ] ]] 
            ]);

            if($suburbs && $suburbs['hits']['total']['value']){
                foreach($suburbs['hits']['hits'] as $suburb){
                    $finalSuburbs[$suburb['_source']['id']] = $suburb['_source']['name'];
                }
            }
        }

        foreach($finalPostalCodes as &$register){
            if(isset($finalStates[$register['stateID']])){
                $register['state']      = $finalStates[$register['stateID']]['name'];
                if(isset($finalStates[$register['stateID']]['cveID'])){
                    $register['stateCVE']   = $finalStates[$register['stateID']]['cveID'];
                }
            }
            if(isset($finalMunicipalities[$register['municipalityID']])){
                $register['municipality'] = $finalMunicipalities[$register['municipalityID']];
            }
            if(isset($finalSuburbs[$register['suburbID']])){
                $register['suburb'] = $finalSuburbs[$register['suburbID']];
            }
        }

        return $finalPostalCodes;
    }

    public function getTests($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return User::getTests($profileID);
    }

    public function createExitRequest($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return User::createExitRequest($profileID, $request);
    }

    public function deleteExitRequest($profileID, $exitRequestID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return User::deleteExitRequest($profileID, $exitRequestID);
    }
    
    public function bluetraceTempIDs(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        return User::bluetraceTempIDs($userID);
    }

    public function bluetraceData(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        return User::bluetraceData($userID, $request);
    }

    public function devicetoken(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'devicetoken'   => 'required',
            'devicetype'    => 'required|in:android,ios',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return User::devicetoken($userID, $request);
    }

    public function anonymousDevicetoken(Request $request){
        $validation = Validator::make($request->all(),[ 
            'devicetoken'   => 'required',
            'devicetype'    => 'required|in:android,ios',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return User::anonymousDevicetoken($request);
    }

    public function states(Request $request){
        return State::states($request);
    }

    public function municipalities($stateID, Request $request){
        return State::municipalities($stateID, $request);
    }

    public function hospitals($stateID, $municipalityID, Request $request){
        return HospitalPublic::hospitals($stateID, $municipalityID, $request);
    }

    public function saveError(Request $request){
        $userID = JWT::validateToken($request->bearerToken());

        return User::saveError($userID, $request);
    }

    public function getStatesInfo(Request $request){
        return Info::getStatesInfo($request);
    }

    public function getMunicipalitiesInfo(Request $request){
        return Info::getMunicipalitiesInfo($request);
    }

    public function saveExposedDP3T(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }
        
        $validation = Validator::make($request->all(),[ 
            'key'       => 'required',
            'keyDate'   => 'required|integer',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return DP3T::saveExposedDP3T($userID, $request);
    }

    public function saveExposedEndDP3T(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }
        
        return DP3T::saveExposedEndDP3T($userID);
    }
    
    public function getExposedByBatchReleaseTimeDP3T($batchReleaseTime, Request $request){
        
        return DP3T::getExposedByBatchReleaseTimeDP3T($batchReleaseTime);
    }
    
    public function saveExposed(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }
        
        return DP3T::saveExposed($userID, $request);
    }

    public function saveExposedContact(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }
        
        return DP3T::saveExposedContact($userID, $request);
    }

    public function getExposedByBatchReleaseTime($batchReleaseTime, Request $request){
        return DP3T::getExposedByBatchReleaseTime($batchReleaseTime, $request);
    }

    public function getExposedConfig(Request $request){
        return DP3T::getExposedConfig($request);
    }

    public function logout(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('invalid session', 401);
        }

        return User::logout($userID, $request);
    }

    public function createDataPCR($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return DP3T::createDataPCR($userID, $profileID, $request);
    }

    public function verifyOTPPCR($profileID, $pcrID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $pcr = Elastic::get(['index' => 'pcr_info', 'id' => $pcrID, 'client' => ['ignore' => 404]]);
        if(!$pcr || !$pcr['found'] || $pcr['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return DP3T::verifyOTPPCR($pcr, $request);
    }

    public function editDataPCR($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $pcrID = $request->input('id');
        $pcr = Elastic::get(['index' => 'pcr_info', 'id' => $pcrID, 'client' => ['ignore' => 404]]);
        if(!$pcr || !$pcr['found'] || $pcr['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return DP3T::editDataPCR($userID, $pcr, $request);
    }

    public function setStateUser(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'state' => 'required',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return User::setStateUser($userID, $request);
    }

    public function setDefaultProfile($profileID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return User::setDefaultProfile($userID, $profileID);
    }


    public function setSemaphoreFav(Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'fav' => 'required|array',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return User::setSemaphoreFav($userID, $request);
    }

    public function getValidateCenter($centerID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        return User::getValidateCenter($centerID);
    }

    public function readedPCR($profileID, $pcrID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $pcr = Elastic::get(['index' => 'pcr_info', 'id' => $pcrID, 'client' => ['ignore' => 404]]);
        if(!$pcr || !$pcr['found'] || $pcr['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        return DP3T::readedPCR($pcr, $request);
    }

    public function notifyPCR($profileID, $pcrID, Request $request){
        $userID = JWT::validateToken($request->bearerToken());
        if($userID === false){
            return response('Invalid session', 401);
        }

        $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profile || !$profile['found'] || $profile['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $pcr = Elastic::get(['index' => 'pcr_info', 'id' => $pcrID, 'client' => ['ignore' => 404]]);
        if(!$pcr || !$pcr['found'] || $pcr['_source']['userID'] != $userID){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'phones'   => 'required|array',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return DP3T::notifyPCR($userID, $profile, $pcr, $request);
    }

}