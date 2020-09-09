<?php

namespace App;


use Sns;
use Aws\Exception\AwsException;

class SMS {

    public static function send($phone, $message){
        
        $phone = '+34' . $phone;

        $success = true;
        try {

            $data = [
                'Message'       => $message,
                'PhoneNumber'   => $phone,
            ];

            if(config('app.env') === 'production'){
                $result = Sns::publish($data);
                return $result['MessageId'];
            }
        } catch (AwsException $e) {
            \Log::error('send sms', ['exception' => $e]);

            $success = false;
        } 
        
        return $success;
    }
}