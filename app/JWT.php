<?php

namespace App;

use Elastic;
use Illuminate\Support\Str;

class JWT {
    
    public static function createToken($userID, $admin = false){
        if($admin){
            $expire = time () + config('app.JWT_TIME_ADMIN');
        }else{
            $expire = time () + config('app.JWT_TIME');
        }

        $payload = [
            "iss"   => config('app.JWT_DOMAIN'),
            "aud"   => config('app.JWT_DOMAIN'),
            "iat"   => time(),
            "exp"   => $expire,
            "sub"   => $userID,
            'jti'   => Str::random(25)
        ];

        if($admin){
            $payload['admin'] = $admin;
        }
        
        return \Firebase\JWT\JWT::encode($payload, config('app.JWT_PRIVATE_KEY'), 'RS256');
    }

    public static function validateToken($jwt, $admin = false){
        if(empty($jwt)){
            return false;
        }

        try{
            $decoded = \Firebase\JWT\JWT::decode($jwt, config('app.JWT_PUBLIC_KEY'), array('RS256'));
            if(isset($decoded->sub)){
                if($admin){
                    if($decoded->admin && $decoded->admin <= $admin){
                        $adminData = Elastic::get(['index' => 'admins', 'id' => $decoded->sub, 'client' => ['ignore' => 404]]);

                        if(!$adminData || !$adminData['found']){
                            return false;
                        }

                        if(!empty($adminData['_source']['logout'])){
                            if(isset($adminData['_source']['logout'][$decoded->jti])){
                                return false;
                            }
                        }

                        return $decoded->sub;
                    }
                }else{
                    $userData = Elastic::get(['index' => 'users', 'id' => $decoded->sub, 'client' => ['ignore' => 404]]);

                    if(!$userData || !$userData['found']){
                        return false;
                    }

                    if(!empty($userData['_source']['logout'])){
                        if(isset($userData['_source']['logout'][$decoded->jti])){
                            return false;
                        }
                    }

                    return $decoded->sub;
                }
            }
            return false;
        }catch(\Exception $e){
            return false;
        }
    }


    public static function createSignatureDP3T($batchReleaseTime, $body){
        $time = time ();

        $payload = [
            "iss"               => config('app.JWT_DOMAIN'),
            "aud"               => config('app.JWT_DOMAIN'),
            "iat"               => $time,
            "exp"               => $time + 1000,
            "hash-alg"          => 'sha256',
            "batchReleaseTime"  => $batchReleaseTime,
            "contentHash"       => base64_encode(hash ('sha256', $body, true))
        ];

        $payload['content-hash'] = $payload['contentHash'];
        
        return \Firebase\JWT\JWT::encode($payload, config('dp3t.privateKey'), 'ES256');
    }
}