<?php
 
 return [
   'region'     => env('SNS_AWS_DEFAULT_REGION'),
   'key'        => env('AWS_ACCESS_KEY_ID'),
   'secret'     => env('AWS_SECRET_ACCESS_KEY'),
   'ios_target' => env('SNS_IOS_TARGET', 'APNS_SANDBOX'),
   'topic_arn'  => env('SNS_TOPIC_ARN'),
 ];