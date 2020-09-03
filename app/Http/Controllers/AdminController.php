<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Elastic;
use App\JWT;
use App\Http\Helpers\Hospital;
use App\Http\Helpers\Register;
use App\Http\Helpers\Test;
use App\Http\Helpers\Trend;
use App\Http\Helpers\Stat;
use App\Http\Helpers\State;
use App\Http\Helpers\Admin;
use App\Http\Helpers\Message;
use App\Http\Helpers\Info;
use App\Http\Helpers\DP3T;
use App\Http\Helpers\PCR;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller{

    private function checkUserAdmin($request, $level = Admin::CONSULTOR){
        global $ADMINID;

        $adminID = JWT::validateToken($request->bearerToken(), $level);
        if($adminID === false){
            return false;
        }

        $adminData = Elastic::get(['index' => 'admins', 'id' => $adminID, 'client' => ['ignore' => 404]]);

        if(!$adminData || !$adminData['found']){
            return false;
        }

        $ADMINID = $adminID;

        return $adminData;
    }

    public function session(Request $request){
        $adminData = $this->checkUserAdmin($request);
        if($adminData === false){
            return response('Unauthorized user', 401);
        }

        return Admin::session($adminData);
    }
    
    public function signin(Request $request){
        return Admin::signin($request);
    }
    
    public function states(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return State::states($request);
    }
    
    public function municipalities($stateID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return State::municipalities($stateID, $request);
    }
    
    public function suburbs($stateID, $municipalityID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return State::suburbs($stateID, $municipalityID, $request);
    }
    
    public function age(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Stat::age($request);
    }
    
    public function gender(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::gender($request);
    }
    
    public function timeline(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::timeline($request);
    }

    public function tests(Request $request){  //return profiles
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Test::tests($request);
    }

    public function userDetail($profileID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Test::userDetail($profileID, $request);
    }

    public function hospitals(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::hospitals($request);
    }

    public function hospitalsStats(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::hospitalsStats($request);
    }


    public function statesHospitalsTests(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::statesHospitalsTests($request);
    }

    public function municipalitiesHospitalsTests($stateID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::municipalitiesHospitalsTests($stateID, $request);
    }

    public function suburbsHospitalsTests($stateID, $municipalityID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::suburbsHospitalsTests($stateID, $municipalityID, $request);
    }

    public function createHospitalsTests(Request $request){
        if($this->checkUserAdmin($request, Admin::ADMIN) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::createHospitalsTests($request);
    }

    public function deleteHospital($hospitalID, Request $request){
        if($this->checkUserAdmin($request, Admin::ADMIN) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::deleteHospital($hospitalID);
    }

    public function editHospitalsTests($hospitalID, $testID, Request $request){
        if($this->checkUserAdmin($request, Admin::ADMIN) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::editHospitalsTests($hospitalID, $testID, $request);
    }

    public function deleteHospitalsTests($hospitalID, $testID, Request $request){
        if($this->checkUserAdmin($request, Admin::ADMIN) === false){
            return response('Unauthorized user', 401);
        }

        return Hospital::deleteHospitalsTests($hospitalID, $testID);
    }

    public function statesRegisters(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Register::statesRegisters($request);
    }
    
    public function municipalitiesRegisters($stateID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Register::municipalitiesRegisters($stateID, $request);
    }
    
    public function suburbsRegisters($stateID, $municipalityID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Register::suburbsRegisters($stateID, $municipalityID, $request);
    }

    public function statesTests(Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }

        return Test::statesTests($request);
    }
    
    public function municipalitiesTests($stateID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Test::municipalitiesTests($stateID, $request);
    }
    
    public function suburbsTests($stateID, $municipalityID, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Test::suburbsTests($stateID, $municipalityID, $request);
    }
    
    public function statesTrends($trendType, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Trend::statesTrends($trendType, $request);
    }
    
    public function municipalitiesTrends($stateID, $trendType, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Trend::municipalitiesTrends($stateID, $trendType, $request);
    }
    
    public function suburbsTrends($stateID, $municipalityID, $trendType, Request $request){
        if($this->checkUserAdmin($request) === false){
            return response('Unauthorized user', 401);
        }
        
        return Trend::suburbsTrends($stateID, $municipalityID, $trendType, $request);
    }
    
    public function sendMessageState($stateID, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Message::sendMessageState($stateID, $adminData['_id'], $request);
    }
    
    public function sendMessageMunicipality($stateID, $municipalityID, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Message::sendMessageMunicipality($stateID, $municipalityID, $adminData['_id'], $request);
    }
    
    public function sendMessageSuburb($stateID, $municipalityID, $suburbID, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Message::sendMessageSuburb($stateID, $municipalityID, $suburbID, $adminData['_id'], $request);
    }
    
    public function sendMessageUser($profileID, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Message::sendMessageUser($profileID, $adminData['_id'], $request);
    }
    
    public function sendMessageAllUser(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Message::sendMessageAllUser($adminData['_id'], $request);
    }
    
    public function getAdmins(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Admin::getAdmins($request);
    }

    public function createAdmin(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'email'     => 'required|email:filter',
            'name'      => 'required',
            'role'      => 'required|in:1,2',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }
        
        return Admin::createAdmin($request);
    }

    public function editAdmin($adminID, Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make($request->all(),[ 
            'email'     => 'required|email:filter',
            'name'      => 'required',
            'role'      => 'required|in:1,2',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }
        
        return Admin::editAdmin($adminID, $request);
    }

    public function deleteAdmin($adminID, Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Admin::deleteAdmin($adminID);
    }

    public function setPassword(Request $request){
        $validation = Validator::make($request->all(),[ 
            'hash'          => 'required',
            'password'      => [
                'required',
                'string',
                'min:10',                                       // must be at least 10 characters in length
                'regex:/[a-z].*[a-z]/',                         // must contain at least 2 lowercase letter
                'regex:/[A-Z].*[A-Z]/',                         // must contain at least 2 uppercase letter
                'regex:/[0-9].*[0-9]/',                         // must contain at least 2 digit
                'regex:/[@$!%*#?&\.\-_].*[@$!%*#?&\.\-_]/',     // must contain at least 2 special character
            ],
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return Admin::setPassword($request);
    }

    public function resetPassword(Request $request){
        $validation = Validator::make($request->all(),[ 
            'email' => 'required',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return Admin::resetPassword($request);
    }

    public function statsAgeGenderType($type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsAgeGenderType($type, $request);
    }

    public function statsSymptoms($type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsSymptoms($type, $request);
    }

    public function statsSocialSecurity($type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsSocialSecurity($type, $request);
    }

    public function statsGeo(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsGeo($request);
    }

    public function contactTrace(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::contactTrace($request);
    }

    public function statsStates($type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsStates($type, $request);
    }

    public function statsMunicipalities($stateID, $type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsMunicipalities($stateID, $type, $request);
    }

    public function statsSuburbs($stateID, $municipalityID, $type, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsSuburbs($stateID, $municipalityID, $type, $request);
    }

    public function statsIndicators(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsIndicators($request);
    }

    public function statsCases($interval, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsCases($interval, $request);
    }
    
    public function statsAge(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Stat::statsAge($request);
    }
    
    public function statsOddsRatio($type, $stateID, Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make(['type' => $type],[ 
            'type'      => 'required|in:diabetes,obesity,hypertension,defenses',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }
        
        return Stat::statsOddsRatio($type, $stateID, $request);
    }

    public function getStatesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }

        return Info::getStatesInfo($request);
    }
    
    public function setStatesInfo($stateID, Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::setStatesInfo($stateID, $request);
    }
    
    public function getMunicipalitiesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::getMunicipalitiesInfo($request);
    }
    
    public function setMunicipalitiesInfo($municipalityID, Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::setMunicipalitiesInfo($municipalityID, $request);
    }

    public function getOtpsData(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Admin::getOtpsData($request);
    }

    public function downloadStatesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::downloadStatesInfo($request);
    }

    public function updloadStatesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::updloadStatesInfo($request);
    }

    public function downloadMunicipalitiesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::downloadMunicipalitiesInfo($request);
    }
    
    public function updloadMunicipalitiesInfo(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Info::updloadMunicipalitiesInfo($request);
    }

    public function downloadTests(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }
        
        return Test::downloadTests($request);
    }

    public function logout(Request $request){
        if(($adminData = $this->checkUserAdmin($request)) === false){
            return response('Unauthorized user', 401);
        }

        return Admin::logout($adminData, $request);
    }

    public function enGetStats(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }

        return DP3T::enGetStats($request);
    }

    public function enGetConfig(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }

        return DP3T::enGetConfig($request);
    }

    public function enSetConfig(Request $request){
        if(($adminData = $this->checkUserAdmin($request, Admin::ADMIN)) === false){
            return response('Unauthorized user', 401);
        }

        $validation = Validator::make($request->all(),[ 
            "attenuationLevelValues"            => 'required|array',
            "daysSinceLastExposureLevelValues"  => 'required|array',
            "durationLevelValues"               => 'required|array',
            "lowerThreshold"                    => 'required|integer',
            "higherThreshold"                   => 'required|integer',
            "factorLow"                         => 'required|numeric',
            "factorHigh"                        => 'required|numeric',
            "triggerThreshold"	                => 'required|integer',
            "titlePush"	                        => 'required',
            "contentPush"	                    => 'required',
            "title"	                            => 'required',
            "content"	                        => 'required',
            "actions.faq"	                    => 'required|boolean',
            "actions.hospital1"	                => 'required|boolean',
            "actions.hospital23"	            => 'required|boolean',
            "actions.freePCR"	                => 'required|boolean',
            "actions.goodPractices"	            => 'required|boolean',
            "actions.freeMask"	                => 'required|boolean',
            "actions.basicPackage"	            => 'required|boolean',
            "actions.freeBed"	                => 'required|boolean',
            "actions.call911"	                => 'required|boolean',
            "actions.localSystems"	            => 'required|boolean',
            "actions.test"	                    => 'required|boolean',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return DP3T::enSetConfig($request);
    }

    public function setPcrResult(Request $request){
        if($request->bearerToken() !== config('app.PCR_RESULT_TOKEN')){
            return response('Unauthorized user', 401);
        }

        global $ADMINID;
        $ADMINID = 'pcrResult';

        $validation = Validator::make($request->all(),[ 
            "data"              => 'required|array',
            'data.*.clue'       => 'required|string',
            'data.*.name'       => 'required|string',
            'data.*.lastname1'  => 'required|string',
            'data.*.lastname2'  => 'nullable',
            'data.*.phone'      => 'required',
            'data.*.date'       => 'required|date_format:Y-m-d',
            'data.*.resultTest' => 'required|in:1,2,3',
        ]);

        if($validation->fails()){
            return response($validation->errors(), 400);
        }

        return PCR::setPcrResult($request);
    }
}
