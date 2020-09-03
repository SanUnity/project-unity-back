<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;
use Log;
use Mail;
use App\JWT;
use App\SMS;
use App\Http\Helpers\Test;
use App\Http\Helpers\User;
use App\Mail\VerifyMail;
use Proto\TemporaryExposureKeyExport;
use Proto\TemporaryExposureKey;
use Proto\SignatureInfo;
use Proto\TEKSignatureList;
use Proto\TEKSignature;
use Proto\ProtoExposee;
use Proto\ProtoExposedList;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class DP3T {

    public static function saveExposedDP3T($userID, Request $request){

        $user = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);
        if($user && $user['found'] && isset($user['_source']['covidPositive']) && $user['_source']['covidPositive'] === true){
            $data = [
                'userID'    => $userID,
                'key'       => $request->input('key'),
                'date'      => $request->input('keyDate'),
                'timestamp' => (int) round(microtime(true) * 1000)
            ];

            Elastic::index(['index' => 'dp3t', 'body' => $data,'refresh' => "false"]);
        }
    }

    public static function saveExposedEndDP3T($userID){

        $user = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);
        if($user && $user['found'] && isset($user['_source']['covidPositive']) && $user['_source']['covidPositive'] === true){
            Elastic::update(['index' => 'users', 'id' => $user['_id'], 'body' => ['doc' => ['covidPositive' => false]],'refresh' => "false"]);
        }
    }

    public static function getExposedByBatchReleaseTimeDP3T($batchReleaseTime){

        $batchLimit = config('dp3t.batchlength');
        $start      = config('dp3t.batchlength') - $batchLimit;

        $dp3ts = Elastic::search([
            'index'             => 'dp3t',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => [
                'from'  => 0,
                'size'  => 10000, 
                'query' => ['bool' => ['must' => [ ['range' => ['timestamp' => ['gte' => $start, 'lte' => $batchReleaseTime]]] ] ] ],
                'sort'  => [[ 'date' => ['order' => 'asc','missing' =>  '_last']]]
            ]
        ]);

        $exposedList = new ProtoExposedList();
        $exposedList->setBatchReleaseTime((int) $batchReleaseTime);
        
        $exposeeAux = [];
        if($dp3ts && $dp3ts['hits']['total']['value']){
            foreach($dp3ts['hits']['hits'] as $dp3t){
                $exposee = new ProtoExposee();
                $exposee->setKey(base64_decode($dp3t['_source']['key']));
                $exposee->setKeyDate((int) $dp3t['_source']['date']);

                $exposeeAux[] = $exposee;
            }
        }
        
        $exposedList->setExposed($exposeeAux);

        $response = $exposedList->serializeToString();

        return response($response)
            ->header('Content-Type', 'application/x-protobuf')
            ->header('Signature', JWT::createSignatureDP3T((int) $batchReleaseTime, $response));
    }


    public static function saveExposed($userID, Request $request){
        $gaenKeys = $request->input('gaenKeys');

        if(!empty($request->input('fake'))){
            return;
        }

        Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => ['submitExposureNotification' => true]],'refresh' => "false"]);

        foreach($gaenKeys as $key){
            if(!empty($key['fake'])){
                continue;
            }

            if(empty($key['rollingPeriod']) || $key['rollingPeriod'] < 0){
                continue;
            }

            $key['transmissionRiskLevel'] = 8;

            Elastic::index(['index' => 'dp3t', 'body' => [
                'keyData'               => $key['keyData'],
                'rollingStartNumber'    => $key['rollingStartNumber'],
                'rollingPeriod'         => isset($key['rollingPeriod']) ? $key['rollingPeriod'] : 144,
                'transmissionRiskLevel' => $key['transmissionRiskLevel'],
                'timestamp'             => (int) round(microtime(true) * 1000, 0),
                'userID'                => $userID,
            ], 'refresh' => "false"]);
        }
    }

    public static function saveExposedContact($userID, Request $request){

        Elastic::update(['index' => 'users', 'id' => $userID, 'body' => ['doc' => [
            'exposedContact'    => true, //lo marcamos como que ha estado expuesto alguna vez
            'exposed'           => false //ponemos a false para no mostrarle mas el mensaje de que ha estado expuesto
        ]],'refresh' => "false"]);

        return [
            'title' => 'Alguien próximo a tí ha tenido una muestra de laboratorio que resultó positiva a SARS-COV-2, eso quiere decir que esa persona tiene la enfermedad COVID-19.',
            'body' => 'Te mandamos este mensaje pidiéndote que sigas estas recomendaciones:</br></br><b>1)</b> Manten la Sana Distancia y refuerza tus hábitos de higiene, especialmente el lavado de manos frecuente con agua y jabón. Si tienes <b>síntomas</b> de <b>infección respiratoria</b> como: <b>fiebre, tos, dolor de cabeza o malestar general, aíslate tanto como sea posible</b> y avisa a las personas con las que tuviste contacto desde dos días antes de haber iniciado tus síntomas y pídeles que <b>eviten el contacto directo</b> con otras personas.</br></br><b>2)</b> Si perteneces a alguno de los siguientes grupos: </br><b>a)</b> Ser persona adulta mayor con 60 años cumplidos. </br><b>b)</b> Estar en estado de embarazo. </br><b>c)</b> Vivir con hipertensión, diabetes mellitus o enfermedades pulmonares como el EPOC.</br><b>d)</b> Haber recibido un trasplante. </br><b>e)</b> Vivir con VIH o alguna otra enfermedad que debilite tu sistema inmune o recibes un tratamiento inmunosupresor por vivir con alguna otra enfermedad como cáncer.</br></br>Vigila tu salud y si tienes <b>algún síntoma de enfermedad respiratoria llama de inmediato al 911</b> indicando tu condición. </br></br><b>4)</b> Descarga la App COVID-19MX para mantenerte al día con información confiable y visita la página coronavirus.gob.mx',
            'actions' => [
                ['text' => 'Ver más consejos', 'action' => 'faq']
            ]
        ];
    }

    private static function gaenTenMinutesBetween($temporal1Inclusive, $temporal2Exclusive){
        $diff = 0;
        if($temporal1Inclusive < $temporal2Exclusive){
            $diff = $temporal2Exclusive - $temporal1Inclusive;
        }else{
            $diff = $temporal1Inclusive - $temporal2Exclusive;
        }

        if($diff > 0){
            $diff = $diff / 1000 / 60 / 10;
        }

        return (int) round($diff, 0);
    }

    public static function getExposedByBatchReleaseTime($batchReleaseTime, Request $request){
        $batchLength    = config('dp3t.batchlength');
        $publishedafter = $request->input('publishedafter');

        $now = (int) round(microtime(true) * 1000, 0);

        $date = \DateTime::createFromFormat('U', (int) round($batchReleaseTime / 1000, 0));
        $date->add(new \DateInterval('P1D')); //add 1 day

        $publishedUntil                 = $now - ($now % $batchLength);
        $rollingPeriodStartNumberStart  = self::gaenTenMinutesBetween(0, $batchReleaseTime);
        $rollingPeriodStartNumberEnd    = self::gaenTenMinutesBetween(0, $date->getTimestamp() * 1000);

        $query = ['bool' => ['must' => [ 
            ['range' => ['timestamp' => ['lt' => $publishedUntil]]],
            ['range' => ['rollingStartNumber' => ['gte' => $rollingPeriodStartNumberStart, 'lt' => $rollingPeriodStartNumberEnd]]]
        ] ] ];

        if($publishedafter){
            $query['bool']['must'][] = ['range' => ['timestamp' => ['gte' => $publishedafter]]];
        }

        $dp3ts = Elastic::search([
            'index'             => 'dp3t',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => [
                'from'  => 0,
                'size'  => 10000, 
                'query' => $query,
                'sort'  => [[ 'rollingStartNumber' => ['order' => 'asc','missing' =>  '_last']]]
            ]
        ]);

        if(!$dp3ts ||  empty($dp3ts['hits']['total']['value'])){
            return response('', 204)->header('X-PUBLISHED-UNTIL', '' . $publishedUntil);
        }


        $file = new TemporaryExposureKeyExport();
        $exposeeAux = [];
        if($dp3ts && $dp3ts['hits']['total']['value']){
            foreach($dp3ts['hits']['hits'] as $dp3t){
                $exposee = new TemporaryExposureKey();
                $exposee->setKeyData(base64_decode($dp3t['_source']['keyData']));
                $exposee->setRollingPeriod($dp3t['_source']['rollingPeriod']);
                $exposee->setRollingStartIntervalNumber($dp3t['_source']['rollingStartNumber']);
                $exposee->setTransmissionRiskLevel($dp3t['_source']['transmissionRiskLevel']);

                $exposeeAux[] = $exposee;
            }
        }

        $keyDate = $dp3ts['hits']['hits'][0]['_source']['rollingStartNumber'] * 10 * 60; //Ten minutes to seconds

        $file->setKeys($exposeeAux);
        $file->setRegion(config('dp3t.region'));
        $file->setBatchNum(1);
        $file->setBatchSize(1);
        $file->setStartTimestamp($keyDate);
        $file->setEndTimestamp($keyDate + (int) round($batchLength / 1000, 0));

        $signature = new SignatureInfo();
        $signature->setAppBundleId(config('dp3t.bundelId'));
        // $signature->setAndroidPackage(config('dp3t.androidPackage'));
        $signature->setVerificationKeyVersion(config('dp3t.keyVersion'));
        $signature->setVerificationKeyId(config('dp3t.keyIdentifier'));
        $signature->setSignatureAlgorithm(config('dp3t.signatureAlgorithm'));

        $file->setSignatureInfos([$signature]);

        

        if(!Storage::exists('dp3t')){
            Storage::makeDirectory('dp3t');
        }

        $tempName   = bin2hex(random_bytes(10)) . time() . '.zip';

        $zip = new \ZipArchive();
        $res = $zip->open(storage_path('app') . '/dp3t/' . $tempName, \ZipArchive::CREATE);
        if ($res) {
            $EXPORT_MAGIC = [0x45, 0x4B, 0x20, 0x45, 0x78, 0x70, 0x6F, 0x72, 0x74, 0x20, 0x76, 0x31, 0x20, 0x20, 0x20, 0x20 ];
            
            $exportBin = implode('', array_map("chr",$EXPORT_MAGIC)) . $file->serializeToString();

            $zip->addFromString('export.bin', $exportBin);


            $pkeyid = openssl_pkey_get_private(config('dp3t.privateKey'));
            openssl_sign($exportBin, $sign, $pkeyid, OPENSSL_ALGO_SHA256);

            $TEKSignatureList   = new TEKSignatureList();
            $TEKSignature       = new TEKSignature();
            $TEKSignature->setSignatureInfo($signature);
            $TEKSignature->setSignature($sign);
            $TEKSignature->setBatchSize(1);
            $TEKSignature->setBatchNum(1);

            $TEKSignatureList->setSignatures([$TEKSignature]);

            $zip->addFromString('export.sig', $TEKSignatureList->serializeToString());

            $zip->close();
        }

        return response()
            ->download(storage_path('app') . '/dp3t/' . $tempName, 'export.zip', [
                'X-PUBLISHED-UNTIL' => '' . $publishedUntil
            ])->deleteFileAfterSend();
    }

    public static function getExposedConfig(){
        return [
            "minimumRiskScore"                  => 1,                           // default 1
            "attenuationLevelValues"            => [0, 1, 3, 5, 6, 7, 8, 8],    // default [1, 2, 3, 4, 5, 6, 7, 8]
            "daysSinceLastExposureLevelValues"  => [0, 0, 2, 4, 5, 6, 7, 8],    // default [1, 2, 3, 4, 5, 6, 7, 8]
            "durationLevelValues"               => [0, 0, 1, 2, 4, 6, 7, 8],    // default [1, 2, 3, 4, 5, 6, 7, 8]
            "transmissionRiskLevelValues"       => [0, 1, 2, 3, 5, 6, 7, 8],    // default [1, 2, 3, 4, 5, 6, 7, 8]
            "lowerThreshold"                    => 50,                          // default 50
            "higherThreshold"                   => 55,                          // default 55
            "factorLow"                         => 1.0,                         // default 1.0
            "factorHigh"                        => 0.5,                         // default 0.5
            "triggerThreshold"	                => 25,                          // detault 25
            'alert'                             => [
                'title'     => 'Los casos sospechosos en tu entidad se mantienen elevados',
                'body'      => 'Cuídate, sigue las recomendaciones y vigila tus síntomas.'
            ],
        ];
    }

    public static function createDataPCR($userID, $profileID, Request $request){

        $email = $request->input('email');
        $phone = $request->input('phone');

        $verifiedEmail      = false;
        $verifiedPhone      = false;
        $haveVerifyEmail    = false;
        $haveVerifyPhone    = false;
        
        $dp3tInfo = [
            'userID'        => $userID,
            'profileID'     => $profileID,
            'timestamp'     => time(),
            'email'         => $email,
            'phone'         => $phone,
            'name'          => $request->input('name'),
            'lastname'      => $request->input('lastname'),
            'gender'        => $request->input('gender'),
            'birthday'      => $request->input('birthday'),
            'dateTest'      => $request->input('dateTest'),
            'resultTest'    => $request->input('resultTest'), //0 => negative, 1 => positive, 2 => wait result
            'centerId'      => $request->input('centerId'),
            'verified'      => false,  //test result verified by lab
            'readed'        => false,  //reded result by user
            'sendedSSA'     => false,  //sended to secretaria de salud
        ];


        $validateCenter = User::getValidateCenter($dp3tInfo['centerId'], true);
        if(!$validateCenter || !$validateCenter['valid']){
            return response('Invalid centerId', 400);
        }
        $dp3tInfo['centerId'] = $validateCenter['centerID'];

        $pcr = Elastic::search([
            'index'     => 'pcr_info',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 1000,
                'query' => ['bool' => ['must' => [ ['term' => ['userID' => $userID]] ] ] ],
            ]
        ]);

        $realPhone = Test::getPhone($userID, false);
        if($phone == $realPhone){
            $verifiedPhone = true;
        }

        if($pcr && $pcr['hits']['total']['value']){
            foreach($pcr['hits']['hits'] as $data){
                if(!$verifiedEmail && $data['_source']['verifiedEmail'] && $data['_source']['email'] == $email){
                    $verifiedEmail = true;
                }
                if(!$verifiedPhone && $data['_source']['verifiedPhone'] && $data['_source']['phone'] == $phone){
                    $verifiedPhone = true;
                }
            }
        }
        if(!$verifiedEmail && empty($email)){ //email is optional, so we dont have to verified email if is empty
            $verifiedEmail = true;
        }
        if(!$verifiedEmail){
            $haveVerifyEmail = true;
        }
        if(!$verifiedPhone){
            $haveVerifyPhone = true;
        }

        if($haveVerifyEmail){
            $dp3tInfo['otpEmail'] = random_int(100000,999999);
            try{
                Mail::to($email)->send(new VerifyMail([
                    'otp' => $dp3tInfo['otpEmail'],
                ]));
            }catch(\Exception $e){
                Log::error('send verify email', ['exception' => $e]);
            }
        }

        if($haveVerifyPhone){
            $dp3tInfo['otpPhone'] = random_int(100000,999999);
            SMS::send($phone, $dp3tInfo['otpPhone'] . ' es tu código de verificación para ' . config('app.name'));
        }

        $dp3tInfo['verifiedEmail'] = $verifiedEmail;
        $dp3tInfo['verifiedPhone'] = $verifiedPhone;

        $resultPCR = Elastic::index(['index' => 'pcr_info', 'body' => $dp3tInfo, 'refresh' => "false"]);

        return [
            'id'                => $resultPCR['_id'],
            'haveVerifyEmail'   => $haveVerifyEmail,
            'haveVerifyPhone'   => $haveVerifyPhone,
        ];
    }

    public static function verifyOTPPCR($pcr, Request $request){
        $result = [];

        $otpEmail = $request->input('otpEmail');
        $otpPhone = $request->input('otpPhone');

        if(!empty($otpEmail)){
            if(isset($pcr['_source']['otpEmail']) && $otpEmail == $pcr['_source']['otpEmail']){
                $pcr['_source']['verifiedEmail'] = true;
                unset($pcr['_source']['otpEmail']);
                $result['email'] = true;
            }else{
                $result['email'] = false;
            }
        }
        if(!empty($otpPhone)){
            if(isset($pcr['_source']['otpPhone']) && $otpPhone == $pcr['_source']['otpPhone']){
                $pcr['_source']['verifiedPhone'] = true;
                unset($pcr['_source']['otpPhone']);
                $result['phone'] = true;
            }else{
                $result['phone'] = false;
            }
        }

        Elastic::index(['index' => 'pcr_info', 'id' => $pcr['_id'], 'body' => $pcr['_source'],'refresh' => "wait_for"]);

        return $result;
    }
    
    public static function getPCRs($userID, &$profilesData){
        $pcr = Elastic::search([
            'index'     => 'pcr_info',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 1000,
                'query' => ['bool' => ['must' => [ ['term' => ['userID' => $userID]] ] ] ],
                'sort' => [
                    [ 'dateTest' => ['order' => 'desc','missing' =>  '_last']],
                    [ 'timestamp' => ['order' => 'desc','missing' =>  '_last']],
                ]
            ]
        ]);
        
        $finalPCR = [];
        if($pcr && $pcr['hits']['total']['value']){
            foreach($pcr['hits']['hits'] as $data){
                if(!isset($finalPCR[$data['_source']['profileID']])){
                    $finalPCR[$data['_source']['profileID']] = [];
                }
                $finalPCR[$data['_source']['profileID']][] = [
                    'id'            => $data['_id'],
                    'profileID'     => $data['_source']['profileID'],
                    'timestamp'     => $data['_source']['timestamp'],
                    'email'         => $data['_source']['email'],
                    'phone'         => $data['_source']['phone'],
                    'name'          => $data['_source']['name'],
                    'lastname'      => $data['_source']['lastname'],
                    'gender'        => $data['_source']['gender'],
                    'birthday'      => $data['_source']['birthday'],
                    'dateTest'      => $data['_source']['dateTest'],
                    'resultTest'    => $data['_source']['resultTest'],
                    'verifiedEmail' => $data['_source']['verifiedEmail'],
                    'verifiedPhone' => $data['_source']['verifiedPhone'],
                    'verified'      => $data['_source']['verified'],
                    'centerId'      => $data['_source']['centerId'],
                    'readed'        => $data['_source']['readed'],
                ];
            }
        }
        foreach($profilesData as &$profile){
            if(isset($finalPCR[$profile['id']])){
                $profile['pcr'] = $finalPCR[$profile['id']];
            }
        }
    }

    public static function editDataPCR($userID, $pcrData, Request $request){
        $email = $request->input('email');
        $phone = $request->input('phone');

        $verifiedEmail      = false;
        $verifiedPhone      = false;
        $haveVerifyEmail    = false;
        $haveVerifyPhone    = false;
        
        $pcrData['_source']['email']        = $request->input('email');
        $pcrData['_source']['phone']        = $request->input('phone');
        $pcrData['_source']['name']         = $request->input('name');
        $pcrData['_source']['lastname']     = $request->input('lastname');
        $pcrData['_source']['gender']       = $request->input('gender');
        $pcrData['_source']['birthday']     = $request->input('birthday');
        $pcrData['_source']['dateTest']     = $request->input('dateTest');
        $pcrData['_source']['resultTest']   = $request->input('resultTest');

        $pcr = Elastic::search([
            'index'     => 'pcr_info',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => [
                'from' => 0,
                'size' => 1000,
                'query' => ['bool' => ['must' => [ ['term' => ['userID' => $userID]] ] ] ],
            ]
        ]);

        $realPhone = Test::getPhone($userID, false);
        if($phone == $realPhone){
            $verifiedPhone = true;
        }

        if($pcr && $pcr['hits']['total']['value']){
            foreach($pcr['hits']['hits'] as $data){
                if(!$verifiedEmail && $data['_source']['verifiedEmail'] && $data['_source']['email'] == $email){
                    $verifiedEmail = true;
                }
                if(!$verifiedPhone && $data['_source']['verifiedPhone'] && $data['_source']['phone'] == $phone){
                    $verifiedPhone = true;
                }
            }
        }

        if(!$verifiedEmail && empty($email)){ //email is optional, so we dont have to verified email if is empty
            $verifiedEmail = true;
        }

        if(!$verifiedEmail){
            $haveVerifyEmail = true;
        }
        if(!$verifiedPhone){
            $haveVerifyPhone = true;
        }

        if($haveVerifyEmail){
            $pcrData['_source']['otpEmail'] = random_int(100000,999999);
            try{
                Mail::to($email)->send(new VerifyMail([
                    'otp' => $pcrData['_source']['otpEmail'],
                ]));
            }catch(\Exception $e){
                Log::error('send verify email', ['exception' => $e]);
            }
        }

        if($haveVerifyPhone){
            $pcrData['_source']['otpPhone'] = random_int(100000,999999);
            SMS::send($phone, $pcrData['_source']['otpPhone'] . ' es tu código de verificación para ' . config('app.name'));
        }

        $pcrData['_source']['verifiedEmail'] = $verifiedEmail;
        $pcrData['_source']['verifiedPhone'] = $verifiedPhone;

        Elastic::index(['index' => 'pcr_info', 'id' => $pcrData['_id'], 'body' => $pcrData['_source'],'refresh' => "wait_for"]);

        self::sendInfoToSSa([
            'id'                => $pcrData['_id'],
            'phone'             => $pcrData['_source']['phone'],
            'centerId'          => $pcrData['_source']['centerId'],
            'dateTest'          => $pcrData['_source']['dateTest'],
        ]);

        return [
            'id'                => $pcrData['_id'],
            'haveVerifyEmail'   => $haveVerifyEmail,
            'haveVerifyPhone'   => $haveVerifyPhone,
        ];
    }


    public static function enGetConfig(Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');

        $id = 'default';
        if(!empty($suburbID)){
            $id = 'suburb-' . $suburbID;
        }else if(!empty($municipalityID)){
            $id = 'municipality-' . $municipalityID;
        }else if(!empty($stateID)){
            $id = 'state-' . $stateID;
        }

        $config = Elastic::get(['index' => 'dp3t_config', 'id' => $id, 'client' => ['ignore' => 404]]);
        if($config && $config['found']){
            return [
                'config'                            => true,
                "attenuationLevelValues"            => $config['_source']['attenuationLevelValues'],
                "daysSinceLastExposureLevelValues"  => $config['_source']['daysSinceLastExposureLevelValues'],
                "durationLevelValues"               => $config['_source']['durationLevelValues'],
                "lowerThreshold"                    => $config['_source']['lowerThreshold'],
                "higherThreshold"                   => $config['_source']['higherThreshold'],
                "factorLow"                         => $config['_source']['factorLow'],
                "factorHigh"                        => $config['_source']['factorHigh'],
                "triggerThreshold"	                => $config['_source']['triggerThreshold'],
                "titlePush"	                        => $config['_source']['titlePush'],
                "contentPush"	                    => $config['_source']['contentPush'],
                "title"	                            => $config['_source']['title'],
                "content"	                        => $config['_source']['content'],
                "actions"	                        => [
                    'faq'           => $config['_source']['actions']['faq'],
                    'hospital1'     => $config['_source']['actions']['hospital1'],
                    'hospital23'    => $config['_source']['actions']['hospital23'],
                    'freePCR'       => $config['_source']['actions']['freePCR'],
                    'goodPractices' => $config['_source']['actions']['goodPractices'],
                    'freeMask'      => $config['_source']['actions']['freeMask'],
                    'basicPackage'  => $config['_source']['actions']['basicPackage'],
                    'freeBed'       => $config['_source']['actions']['freeBed'],
                    'call911'       => $config['_source']['actions']['call911'],
                    'localSystems'  => $config['_source']['actions']['localSystems'],
                    'test'          => $config['_source']['actions']['test'],
                ],
            ];
        }

        return [
            'config' => false
        ];
    }

    public static function enSetConfig(Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');

        $id = 'default';
        if(!empty($suburbID)){
            $id = 'suburb-' . $suburbID;
        }else if(!empty($municipalityID)){
            $id = 'municipality-' . $municipalityID;
        }else if(!empty($stateID)){
            $id = 'state-' . $stateID;
        }

        $config = [
            "attenuationLevelValues"            => $request->input('attenuationLevelValues'),
            "daysSinceLastExposureLevelValues"  => $request->input('daysSinceLastExposureLevelValues'),
            "durationLevelValues"               => $request->input('durationLevelValues'),
            "lowerThreshold"                    => $request->input('lowerThreshold'),
            "higherThreshold"                   => $request->input('higherThreshold'),
            "factorLow"                         => $request->input('factorLow'),
            "factorHigh"                        => $request->input('factorHigh'),
            "triggerThreshold"	                => $request->input('triggerThreshold'),
            "titlePush"	                        => $request->input('titlePush'),
            "contentPush"	                    => $request->input('contentPush'),
            "title"	                            => $request->input('title'),
            "content"	                        => $request->input('content'),
            "actions"	                        => [
                'faq'           => $request->input('actions.faq'),
                'hospital1'     => $request->input('actions.hospital1'),
                'hospital23'    => $request->input('actions.hospital23'),
                'freePCR'       => $request->input('actions.freePCR'),
                'goodPractices' => $request->input('actions.goodPractices'),
                'freeMask'      => $request->input('actions.freeMask'),
                'basicPackage'  => $request->input('actions.basicPackage'),
                'freeBed'       => $request->input('actions.freeBed'),
                'call911'       => $request->input('actions.call911'),
                'localSystems'  => $request->input('actions.localSystems'),
                'test'          => $request->input('actions.test'),
            ],
        ];

        Elastic::index(['index' => 'dp3t_config', 'id' => $id, 'body' => $config, 'refresh' => "false"]);
    }


    public static function enGetStats(Request $request){
        return [
            "confirmed"             => 0,
            "symptoms"              => 0,
            "contacts"              => 0,
            "contactsSymptoms"      => 0,
            "recommendations"       => 0,
            "recommendationsStats"  => [
                'faq'           => 0,
                'hospital1'     => 0,
                'hospital23'    => 0,
                'freePCR'       => 0,
                'goodPractices' => 0,
                'freeMask'      => 0,
                'basicPackage'  => 0,
                'freeBed'       => 0,
                'call911'       => 0,
                'localSystems'  => 0,
                'test'          => 0,
            ]
        ];
    }

    public static function readedPCR($pcr, Request $request){
        Elastic::update(['index' => 'pcr_info', 'id' => $pcr['_id'], 'body' => ['doc' => ['readed' => true]],'refresh' => "false"]);
    }

    public static function notifyPCR($userID, $profile, $pcr, Request $request){
        
        if($pcr['_source']['readed'] == false && $pcr['_source']['resultTest'] === 1){
            $phones     = $request->input('phones');
            $params     = ['body' => []];
            $timestamp  = time();

            foreach($phones as $phone){
                $phone['phone'] = str_replace(' ', '', $phone['phone']);
                if(strpos($phone['phone'], '+521') === 0){
                    $phone['phone'] =  substr($phone['phone'], -10);
                }

                $params['body'][] = [
                    'index' => [
                        '_index' => 'contact_tracing_manual',
                    ]
                ];

                $phoneHash  = hash_pbkdf2('sha256', $phone['phone'], config('app.ENCRYPTION_SALT'), 30000, 0);

                $params['body'][] = [
                    'userID'            => $userID,
                    'profileID'         => $profile['_id'],
                    'pcrID'             => $pcr['_id'],
                    'timestamp'         => $timestamp,
                    'accepted'          => false,
                    'phone'             => Crypt::encryptString($phone['phone']),
                    'phoneHash'         => $phoneHash,
                    'name'              => Crypt::encryptString($phone['name']),
                    'stateID'           => isset($profile['_source']['stateID'])        ?  $profile['_source']['stateID']           : null,
                    'municipalityID'    => isset($profile['_source']['municipalityID']) ?  $profile['_source']['municipalityID']    : null,
                    'suburbID'          => isset($profile['_source']['suburbID'])       ?  $profile['_source']['suburbID']          : null,
                ];

                // User::checkUserExposed($phoneHash);
                // if(!empty(config('app.sms_contact_exposed'))){
                //     SMS::send($phone['phone'], config('app.sms_contact_exposed'));
                // }
            }

            if(!empty($params['body'])){
                Elastic::bulk($params);
            }

            // Elastic::update(['index' => 'pcr_info', 'id' => $pcr['_id'], 'body' => ['doc' => ['readed' => true]],'refresh' => "false"]);
        }
    }

}