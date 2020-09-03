<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Elastic;

class BackendController extends Controller{
    
    public function devicetoken(Request $request){
        if($request->bearerToken() !== config('app.TOKEN_BACK')){
            return response('Invalid session', 401);
        }

        $userID     = $request->input('userID');
        $arn        = $request->input('deviceARN');
        
        $userData   = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);

        if($userData && $userData['found']){
            if(!isset($userData['_source']['devicesARN'])){
                $userData['_source']['devicesARN'] = [];
            }

            $userData['_source']['devicesARN'][]  = $arn;
            $userData['_source']['devicesARN']    = array_values(array_unique($userData['_source']['devicesARN']));

            Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => [
                'devicesARN' => $userData['_source']['devicesARN']
            ]],'refresh' => "false"]);
        }
    }
}
