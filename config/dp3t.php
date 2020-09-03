<?php
 
 return [
   'region'             => env('DP3T_REGION', 'sp'),
   'batchlength'        => env('DP3T_BATCHLENGTH', 7200000),
   'cacheControl'       => env('DP3T_CACHE_CONTROL', 300000),
   'requestTime'        => env('DP3T_REQUEST_TIME', 1500),
   'keyVersion'         => env('DP3T_KEY_VERSION', 'v1'),
   'keyIdentifier'      => env('DP3T_KEY_IDENTIFIER', '214'),
   'signatureAlgorithm' => env('DP3T_SIGNATURE_ALGORITHM', '1.2.840.10045.4.3.2'),
   'bundelId'           => env('DP3T_BUNDLEID'),
   'androidPackage'     => env('DP3T_ANDROID_PACKAGE'),
   'privateKey'         => env('DP3T_PRIVATE_KEY'),
   'publicKey'          => env('DP3T_PUBLIC_KEY'),
 ];