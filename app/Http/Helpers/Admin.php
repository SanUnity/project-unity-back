<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;
use Mail;
use Str;
use App\JWT;
use App\Mail\AdminCreate;
use App\Mail\AdminResetPassword;
use App\Mail\AdminBlockUser;
use Illuminate\Support\Facades\Crypt;

class Admin {
    const ADMIN        = 1;
    const CONSULTOR    = 2;


    public static function session($adminData){
        $adminData['_source']['jwt']    = JWT::createToken($adminData['_id']);
        $adminData['_source']['id']     = $adminData['_id'];
        
        $session = [
            'id'    => $adminData['_id'],
            'name'  => $adminData['_source']['name'],
            'role'  => 1,
            'jwt'   => JWT::createToken($adminData['_id'], $adminData['_source']['role']),
        ];

        if(isset($adminData['_source']['role'])){
            $session['role'] = $adminData['_source']['role'];
        }
        switch($session['role']){
            case 1: $session['roleDescription'] = 'admin';          break;
            case 2: $session['roleDescription'] = 'consultant';     break;
        }
        
        return $session;
    }

    private static function sendMailBlockUser($adminData){

        $hashResetPassword = Str::random(40);

        $adminData['_source']['hash']       = $hashResetPassword;
        $adminData['_source']['timesHash']  = time();
        unset($adminData['_source']['password']);
        unset($adminData['_source']['retries']);
        unset($adminData['_source']['timeRetries']);

        Elastic::index(['index' => 'admins', 'id' => $adminData['_id'], 'body' => $adminData['_source'], 'refresh' => "false"]);

        Mail::to($adminData['_source']['email'])->send(new AdminBlockUser([
            'name' => $adminData['_source']['name'],
            'hash' => $hashResetPassword,
        ]));

    }

    public static function signin(Request $request){
        $email      = $request->input('email');
        $password   = $request->input('password');

        $emailHash  = hash_pbkdf2('sha256', $email, config('app.ENCRYPTION_SALT'), 1, 0);
        $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);

        if(!$adminData || !$adminData['found'] || !isset($adminData['_source']['password'])){
            return response('Unauthorized user', 401);
        }
        
        $password   = hash_pbkdf2('sha256', $password, config('app.ENCRYPTION_SALT'), 50000, 0);

        if($password != $adminData['_source']['password']){

            if(!isset($adminData['_source']['retries'])){
                $adminData['_source']['retries'] = 0;
            }
            $adminData['_source']['retries']++;
            if($adminData['_source']['retries'] >= 3){
                if(isset($adminData['_source']['password'])){
                    self::sendMailBlockUser($adminData);
                    return response('Unauthorized user', 401);
                }
            }
            Elastic::update(['index' => 'admins', 'id' => $adminData['_id'], 'body' => ['doc' => [
                'retries'       => $adminData['_source']['retries'],
                'timeRetries'  => time(),
            ]],'refresh' => "false"]);

            return response('Unauthorized user', 401);
        }

        unset($adminData['_source']['retries']);
        unset($adminData['_source']['timeRetries']);

        Elastic::index(['index' => 'admins', 'id' => $adminData['_id'], 'body' => $adminData['_source'], 'refresh' => "false"]);

        $roleDescription = 'admin';
        switch($adminData['_source']['role']){
            case 1: $roleDescription = 'admin';          break;
            case 2: $roleDescription = 'consultant';     break;
        }

        return [
            'id'                => $adminData['_id'],
            'name'              => $adminData['_source']['name'],
            'role'              => $adminData['_source']['role'],
            'roleDescription'   => $roleDescription,
            'jwt'               => JWT::createToken($adminData['_id'], $adminData['_source']['role']),
        ];
    }

    public static function getAdmins(Request $request){
        $page           = $request->input('page');
        $size           = $request->input('size');
        $queryText      = $request->input('query');

        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($queryText)){
            $query['bool']['must'][] = ['query_string' => [
                'query'             => $queryText . '*', 
                'fields'            => ['name'],
                'default_operator'  => 'and',
                'analyze_wildcard'  => true,
                'lenient'           => true
            ]];
        }

        if(empty($size)){
            $size = 10;
        }
        if(empty($page)){
            $page = 0;
        }
        if($size > 100){
            $size = 100;
        }

        $body = [
            'from' => $page * $size,
            'size' => $size, 
            'sort' => [[ 'name.raw' => ['order' => 'asc','missing' =>  '_last']]]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $admins = Elastic::search([
            'index'             => 'admins',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        $finalAdmins    = [];
        if($admins && $admins['hits']['total']['value']){
            foreach($admins['hits']['hits'] as $admin){
                $roleDescription = 'admin';
                switch($admin['_source']['role']){
                    case 1: $roleDescription = 'admin';          break;
                    case 2: $roleDescription = 'consultant';     break;
                }
                $finalAdmins[] = [
                    'id'                => $admin['_id'],
                    'name'              => $admin['_source']['name'],
                    'email'             => $admin['_source']['email'],
                    'role'              => $admin['_source']['role'],
                    'roleDescription'   => $roleDescription,
                ];
            }
        }

        return [
            'items'             => $finalAdmins,
            'total'             => $admins['hits']['total']['value'],
        ];
    }

    public static function createAdmin(Request $request){
        $name   = $request->input('name');
        $email  = $request->input('email');
        $role   = $request->input('role');

        if(empty($name) || empty($email) || empty($role)){
            return response('Invalid data', 400);
        }

        $emailHash  = hash_pbkdf2('sha256', $email, config('app.ENCRYPTION_SALT'), 1, 0);
        $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);
        if($adminData && $adminData['found']){
            return response([
                'message' => 'Usuario existente'
            ], 400);
        }

        $hashResetPassword = Str::random(40);

        Elastic::index(['index' => 'admins', 'id' => $emailHash, 'body' => [
            'name'      => $name,
            'email'     => $email,
            'role'      => (int) $role,
            'hash'      => $hashResetPassword,
            'timesHash' => time(),
            'timestamp' => time(),
        ], 'refresh' => "wait_for"]);

        Mail::to($email)->send(new AdminCreate([
            'name' => $name,
            'hash' => $hashResetPassword,
        ]));
    }

    public static function resetPassword(Request $request){
        $email  = $request->input('email');

        if(!empty($email)){
            $emailHash  = hash_pbkdf2('sha256', $email, config('app.ENCRYPTION_SALT'), 1, 0);
            $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);
            if($adminData && $adminData['found']){
                
                $hashResetPassword = Str::random(40);

                Elastic::update(['index' => 'admins', 'id' => $emailHash, 'body' => ['doc' => [
                    'hash'      => $hashResetPassword,
                    'timesHash' => time(),
                ]],'refresh' => "false"]);
        
                Mail::to($email)->send(new AdminResetPassword([
                    'name' => $adminData['_source']['name'],
                    'hash' => $hashResetPassword,
                ]));
            }
        }   
    }

    public static function setPassword(Request $request){
        $hash       = $request->input('hash');
        $password   = $request->input('password');

        $adminData = Elastic::search([
            'index'     => 'admins',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 1,
                'query' => ['bool' => ['must' => [ 
                    ['term' => ['hash' => $hash]],
            ] ] ]]
        ]);


        if(!$adminData || !$adminData['hits']['total']['value']){
            return response('Invalid data', 401);
        }
        $adminData = $adminData['hits']['hits'][0];
        if($adminData['_source']['timesHash'] < (time() - 60 * 60 * 24 * 7)){
            return response('Invalid data', 401);
        }

        if($password === $adminData['_source']['email']){
            return response([
                'password' => ['El contraseña no puede ser igual al usuario.']
            ], 400);
        }
        
        $password   = hash_pbkdf2('sha256', $password, config('app.ENCRYPTION_SALT'), 50000, 0);

        if(!empty($adminData['_source']['passwordsOld'])){
            foreach($adminData['_source']['passwordsOld'] as $passwordOld){
                if($password === $passwordOld){
                    return response([
                        'passwordOld' => ['La contraseña tiene que ser diferente a una de tus 6 anteriores contraseñas.']
                    ], 400);
                }
            }
        }else{
            $adminData['_source']['passwordsOld'] = [];
        }
        $adminData['_source']['passwordsOld'][] = $password;
        if(count($adminData['_source']['passwordsOld']) > 6){
            array_shift($adminData['_source']['passwordsOld']);
        }

        Elastic::update(['index' => 'admins', 'id' => $adminData['_id'], 'body' => ['doc' => [
            'password'      => $password,
            'hash'          => '',
            'passwordsOld'  => $adminData['_source']['passwordsOld']
        ]],'refresh' => "false"]);
        
    }


    public static function editAdmin($adminID, Request $request){
        $name   = $request->input('name');
        $email  = $request->input('email');
        $role   = $request->input('role');

        if(empty($name) || empty($email) || empty($role)){
            return response('Invalid data', 400);
        }

        Elastic::update(['index' => 'admins', 'id' => $adminID, 'body' => ['doc' => [
            'name'      => $name,
            'email'     => $email,
            'role'      => (int) $role
        ]],'refresh' => "wait_for"]);
    }

    public static function deleteAdmin($adminID){
        Elastic::delete(['index' => 'admins', 'id' => $adminID,'refresh' => "wait_for"]);
    }

    public static function getOtpsData(Request $request){
        $body = [
            'from' => 0,
            'size' => 10000,
        ];

        $info = Elastic::search([
            'index'     => 'otps',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0, 'size' => 10000] 
        ]);

        $response   = [];
        $time       = time() - (60 * 2);
        if($info && $info['hits']['total']['value']){
            foreach($info['hits']['hits'] as $inf){
                $aux = [
                    'id'        => $inf['_id'],
                ];

                if(isset($inf['_source']['phoneEncrypt']) && $inf['_source']['lastTimestamp'] < $time){
                    $response[] = [
                        'phone'             => Crypt::decryptString($inf['_source']['phoneEncrypt']),
                        'timestamp'         => $inf['_source']['timestamp'],
                        'lastTimestamp'     => $inf['_source']['lastTimestamp'],
                        'retries'           => $inf['_source']['retries'],
                    ];
                }
            }
        }

        return $response;
    }

    public static function logout($adminData, Request $request){
        $logout = [];
        if(!empty($adminData['_source']['logout'])){
            $logout = $adminData['_source']['logout'];
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

            Elastic::update(['index' => 'admins', 'id' => $adminData['_id'], 'body' => ['doc' => ['logout' => $logout]],'refresh' => "wait_for"]);

            return true;

        }catch(\Exception $e){
            return false;
        }
    }
}