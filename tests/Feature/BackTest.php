<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Elastic;

class BackTest extends TestCase
{
    private static $phoneNumber = '1234567897';

    
    public function testSetArn()
    {
        $response = $this->json('POST', '/api/users/signup', ['phone' => self::$phoneNumber]);
        $response->assertStatus(200);
        $response = $this->json('POST', '/api/users/validate', ['phone' => self::$phoneNumber, 'otp' => '123456']);
        $response->assertStatus(200)->assertJsonStructure([
            'id',
            'jwt'
        ]);
            
        $userID = $response->original['id'];
        $jwt    = $response->original['jwt'];

        sleep(1); //sync elasticsearch

        $response = $this->json('POST', '/api/backend/devicetoken', [
            'userID'    => $userID,
            'deviceARN' => 'fakeARN',
        ]);
        $response->assertUnauthorized();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->app['config']['app.TOKEN_BACK']])
        ->json('POST', '/api/backend/devicetoken', [
            'userID'    => $userID,
            'deviceARN' => 'fakeARN',
        ]);
        $response->assertStatus(200);


        sleep(1); //sync elasticsearch
        $phoneHash  = hash_pbkdf2('sha256', self::$phoneNumber, $this->app['config']['app.ENCRYPTION_SALT'], 30000, 0);
        Elastic::delete(['index' => 'otps', 'id' => $phoneHash, 'client'    => ['ignore' => 404]]);
        Elastic::deleteByQuery([
            'index'     => 'users',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['phoneHash' => $phoneHash]] ] ] ]]
        ]);

        sleep(1); //sync elasticsearch
    }
}
