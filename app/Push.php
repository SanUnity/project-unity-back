<?php

namespace App;

use Sns;
use Aws\Exception\AwsException;

class Push {
    
    public static function sendToUser($userArns, $title, $message, $url = false){
        $success = true;

        $title      = strip_tags($title);
        $message    = strip_tags($message);

        foreach($userArns as $userArn){
            try {
                $ios = config('sns.ios_target');
                $data = [
                    'TargetArn' => $userArn,
                    'MessageStructure' => 'json',
                    'Message' => [
                        'default'       => $message,
                        $ios            => [
                            'aps' => [
                                'sound'     => 'default',
                                'badge'     => 'Increment',
                                'alert'     => [
                                    'title'     => $title,
                                    'body'      => $message,
                                ],
                            ]
                        ],
                        'GCM'  =>  [
                            'notification' => [
                                'title' => $title,
                                'body'  => $message,
                            ]
                        ],
                    ],
                ];

                if($url){
                    $data['Message'][$ios]['aps']['type']     = 'url';
                    $data['Message'][$ios]['aps']['element']  = $url;

                    $data['Message']['GCM']['notification']['type']     = 'url';
                    $data['Message']['GCM']['notification']['element']  = $url;
                }

                $data['Message'][config('sns.ios_target')]  = json_encode($data['Message'][config('sns.ios_target')]);
                $data['Message']['GCM']                     = json_encode($data['Message']['GCM']);
                $data['Message']                            = json_encode($data['Message']);

                $result = Sns::publish($data);

            } catch (AwsException $e) {
                \Log::error('send push', ['exception' => $e]);

                $success = false;
            } 
        }
        
        return $success;
    }


    public static function sendToGroup($group, $title, $message, $url = false){
        $success = true;

        $title      = strip_tags($title);
        $message    = strip_tags($message);

        $ios        = config('sns.ios_target');
        $topicARN   = config('sns.topic_arn') . $group;

        try {
            $data = [
                'TopicArn' => $topicARN,
                'MessageStructure' => 'json',
                'Message' => [
                    'default'       => $message,
                    $ios            => [
                        'aps' => [
                            'sound'     => 'default',
                            'badge'     => 'Increment',
                            'alert'     => [
                                'title'     => $title,
                                'body'      => $message,
                            ],
                        ]
                    ],
                    'GCM'  =>  [
                        'notification' => [
                            'title' => $title,
                            'body'  => $message,
                        ]
                    ],
                ],
            ];

            if($url){
                $data['Message'][$ios]['aps']['type']     = 'url';
                $data['Message'][$ios]['aps']['element']  = $url;

                $data['Message']['GCM']['notification']['data']  = [
                    'type'      => 'url',
                    'element'   => $url,
                ];
            }

            $data['Message'][$ios]  = json_encode($data['Message'][$ios]);
            $data['Message']['GCM'] = json_encode($data['Message']['GCM']);
            $data['Message']        = json_encode($data['Message']);

            $result = Sns::publish($data);

        } catch (AwsException $e) {
            \Log::error('send push', ['exception' => $e]);

            $success = false;
        } 
        
        return $success;
    }
}