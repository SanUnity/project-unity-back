<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CommandTest extends TestCase
{
    public function testElasticsearch(){
        $this->artisan('elasticsearch:create --clear=fake')->assertExitCode(1);
    }

    public function testSms(){
        $this->artisan('sms:test 12345678')->assertExitCode(1);
    }

    public function testPush(){
        $this->artisan('push:test fakeARN')->assertExitCode(1);
    }

    public function testDecryptText(){
        $this->artisan('decrypt:text random')->assertExitCode(0);
    }

    public function testDummyInfo(){
        $this->artisan('dummy:create 2')->assertExitCode(1);
    }
}
