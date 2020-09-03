<?php

namespace App;


use Illuminate\Support\Facades\Crypt;

class SMS {

    public static function send($phone, $message){
        
        $phone = '+34' . $phone;

        $success = true;
        try {
            $result = Sns::publish([
                'Message' => $message,
                'PhoneNumber' => $phone,
            ]);
            return $result['MessageId'];
        } catch (AwsException $e) {
            \Log::error('send sms', ['exception' => $e]);

            $success = false;
        } 
        
        return $success;
    }
}