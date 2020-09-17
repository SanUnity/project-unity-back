<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Test {

    public static function statesTests(Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($startDate) || !empty($endDate)){
            $range_aux = [];
            if(!empty($startDate)){
                $range_aux['gte'] = $startDate;
            }
            if(!empty($endDate)){
                $range_aux['lte'] = $endDate;
            }
            $query['bool']['must'][] = ['range' => ['timestamp' => $range_aux]];
        }

        $body = [
            'size' => 0, 
            'aggs' => [
                'registers' => [
                    'terms' => ['field' => 'stateID', 'size' => 100]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $registers = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalRegisters = [];
        $statesIDs      = [];
        foreach($registers['aggregations']['registers']['buckets'] as $bucket){
            $finalRegisters[] = [
                'stateID'   => $bucket['key'],
                'tests'     => $bucket['doc_count'],
                'state'     => ''
            ];
            if(!isset($statesIDs[$bucket['key']])){
                $statesIDs[$bucket['key']] = $bucket['key'];
            }
        }

        $states = Elastic::search([
            'index'     => 'states',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 100, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($statesIDs)]] ] ] ]] 
        ]);

        $finalStates = [];
        if($states && $states['hits']['total']['value']){
            foreach($states['hits']['hits'] as $state){
                $finalStates[$state['_source']['id']] = $state['_source']['name'];
            }
            foreach($finalRegisters as &$register){
                if(isset($finalStates[$register['stateID']])){
                    $register['state'] = $finalStates[$register['stateID']];
                }
            }
        }

        return $finalRegisters;
    }

    public static function municipalitiesTests($stateID, Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]] ] ] ];
        if(!empty($startDate) || !empty($endDate)){
            $range_aux = [];
            if(!empty($startDate)){
                $range_aux['gte'] = $startDate;
            }
            if(!empty($endDate)){
                $range_aux['lte'] = $endDate;
            }
            $query['bool']['must'][] = ['range' => ['timestamp' => $range_aux]];
        }

        $body = [
            'size' => 0, 
            'aggs' => [
                'registers' => [
                    'terms' => ['field' => 'municipalityID', 'size' => 1000]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $registers = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalRegisters     = [];
        $municipalitiesIDs  = [];
        foreach($registers['aggregations']['registers']['buckets'] as $bucket){
            $finalRegisters[] = [
                'municipalityID'    => $bucket['key'],
                'tests'             => $bucket['doc_count'],
                'municipality'      => ''
            ];
            if(!isset($municipalitiesIDs[$bucket['key']])){
                $municipalitiesIDs[$bucket['key']] = $bucket['key'];
            }
        }

        $municipalities = Elastic::search([
            'index'     => 'municipalities',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 1000, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($municipalitiesIDs)]] ] ] ]] 
        ]);

        $finalMunicipalities = [];
        if($municipalities && $municipalities['hits']['total']['value']){
            foreach($municipalities['hits']['hits'] as $municipality){
                $finalMunicipalities[$municipality['_source']['id']] = $municipality['_source']['name'];
            }
            foreach($finalRegisters as &$register){
                if(isset($finalMunicipalities[$register['municipalityID']])){
                    $register['municipality'] = $finalMunicipalities[$register['municipalityID']];
                }
            }
        }

        return $finalRegisters;
    }

    public static function suburbsTests($stateID, $municipalityID, Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]],['term' => ['municipalityID' => $municipalityID]] ] ] ];
        if(!empty($startDate) || !empty($endDate)){
            $range_aux = [];
            if(!empty($startDate)){
                $range_aux['gte'] = $startDate;
            }
            if(!empty($endDate)){
                $range_aux['lte'] = $endDate;
            }
            $query['bool']['must'][] = ['range' => ['timestamp' => $range_aux]];
        }

        $body = [
            'size' => 0, 
            'aggs' => [
                'registers' => [
                    'terms' => ['field' => 'suburbID', 'size' => 2000]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $registers = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalRegisters     = [];
        $suburbsIDs  = [];
        foreach($registers['aggregations']['registers']['buckets'] as $bucket){
            $finalRegisters[] = [
                'suburbID'    => $bucket['key'],
                'tests'       => $bucket['doc_count'],
                'suburb'      => ''
            ];
            if(!isset($suburbsIDs[$bucket['key']])){
                $suburbsIDs[$bucket['key']] = $bucket['key'];
            }
        }

        $suburbs = Elastic::search([
            'index'     => 'suburbs',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 1000, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($suburbsIDs)]] ] ] ]] 
        ]);

        $finalSuburbs = [];
        if($suburbs && $suburbs['hits']['total']['value']){
            foreach($suburbs['hits']['hits'] as $suburb){
                $finalSuburbs[$suburb['_source']['id']] = $suburb['_source']['name'];
            }
            foreach($finalRegisters as &$register){
                if(isset($finalSuburbs[$register['suburbID']])){
                    $register['suburb'] = $finalSuburbs[$register['suburbID']];
                }
            }
        }

        return $finalRegisters;
    }

    public static function tests(Request $request){
        $page           = $request->input('page');
        $size           = $request->input('size');
        $start          = $request->input('start'); //datatable js
        $length         = $request->input('length'); //datatable js

        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($stateID)){
            $query['bool']['must'][] = ['term' => ['stateID' => $stateID]];
        }
        if(!empty($municipalityID)){
            $query['bool']['must'][] = ['term' => ['municipalityID' => $municipalityID]];
        }
        if(!empty($suburbID)){
            $query['bool']['must'][] = ['term' => ['suburbID' => $suburbID]];
        }
        if(!empty($postalCode)){
            $query['bool']['must'][] = ['term' => ['postalCode' => $postalCode]];
        }
        if(!empty($startDate) || !empty($endDate)){
            $range_aux = [];
            if(!empty($startDate)){
                $range_aux['gte'] = $startDate;
            }
            if(!empty($endDate)){
                $range_aux['lte'] = $endDate;
            }
            $query['bool']['must'][] = ['range' => ['timestamp' => $range_aux]];
        }

        if(empty($size)){
            if(!empty($length)){
                $size = $length;
            }else{
                $size = 10;
            }
        }
        if(empty($page)){
            if(!empty($start)){
                $page = $start/$size;
            }else{
                $page = 0;
            }
        }
        if($size > 100){
            $size = 100;
        }

        $body = [
            'from' => $page * $size,
            'size' => $size, 
            'sort' => [[ 'totalTests' => ['order' => 'desc','missing' =>  '_last']]]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $tests = Elastic::search([
            'index'             => 'profiles',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        $finalTests    = [];
        if($tests && $tests['hits']['total']['value']){
            foreach($tests['hits']['hits'] as $test){
                $resultTest = '-';
                if(isset($test['_source']['level'])){
                    switch($test['_source']['level']){
                        case 0: $resultTest = 'Sin sintomas';                       break;
                        case 5: $resultTest = 'Sintomas graves';                    break;
                        case 4: $resultTest = 'Sintomas leves grupo vulnerable';    break;
                        case 1: $resultTest = 'Sintomas previos';                   break;
                        case 3: $resultTest = 'Sintomas leves';                     break;
                        case 2: $resultTest = 'Sintomas previos grupo vulnerable';  break;
                    }
                }

                $auxTest = [
                    'id'                => $test['_id'],
                    'date'              => $test['_source']['timestamp'],
                    'phone'             => self::getPhone($test['_source']['userID']),
                    'name'              => $test['_source']['name'],
                    'stateID'           => isset($test['_source']['stateID'])           ? $test['_source']['stateID'] : -1,
                    'municipalityID'    => isset($test['_source']['municipalityID'])    ? $test['_source']['municipalityID'] : -1,
                    'suburbID'          => isset($test['_source']['suburbID'])          ? $test['_source']['suburbID'] : -1,
                    'totalTest'         => $test['_source']['totalTests'],
                    'contactTrace'      => $test['_source']['contactTrace'],
                    'geo'               => $test['_source']['geo'],
                    'riskContacts'      => $test['_source']['riskContacts'],
                    'totalExitRequests' => $test['_source']['totalExitRequests'],
                    'totalDevices'      => $test['_source']['totalDevices'],
                    'totalOficialTest'  => 0,
                    'level'             => isset($test['_source']['level'])             ? $test['_source']['level'] : null,
                    'resultTest'        => $resultTest,
                ];

                $finalTests[] = $auxTest;
            }
        }
        $states         = [];
        $municipalities = [];
        $suburbs        = [];
        foreach($finalTests as $test){
            if(!isset($states[$test['stateID']])){
                $states[$test['stateID']] = $test['stateID'];
            }
            if(!isset($municipalities[$test['municipalityID']])){
                $municipalities[$test['municipalityID']] = $test['municipalityID'];
            }
            if(!isset($suburbs[$test['suburbID']])){
                $suburbs[$test['suburbID']] = $test['suburbID'];
            }
        }

        $finalStates = [];
        if(!empty($states)){
            $states = Elastic::search([
                'index'     => 'states',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                    ['terms' => ['id' => array_values($states)]]
                ] ] ]]
            ]);
    
            if($states && $states['hits']['total']['value']){
                foreach($states['hits']['hits'] as $state){
                    $finalStates[$state['_source']['id']] = $state['_source']['name'];
                }
            }
        }
        $finalMunicipalities = [];
        if(!empty($municipalities)){
            $municipalities = Elastic::search([
                'index'     => 'municipalities',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                    ['terms' => ['id' => array_values($municipalities)]]
                ] ] ]]
            ]);
    
            if($municipalities && $municipalities['hits']['total']['value']){
                foreach($municipalities['hits']['hits'] as $municipality){
                    $finalMunicipalities[$municipality['_source']['id']] = $municipality['_source']['name'];
                }
            }
        }
        $finalSuburbs = [];
        if(!empty($suburbs)){
            $suburbs = Elastic::search([
                'index'     => 'suburbs',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                    ['terms' => ['id' => array_values($suburbs)]]
                ] ] ]]
            ]);
    
            if($suburbs && $suburbs['hits']['total']['value']){
                foreach($suburbs['hits']['hits'] as $suburb){
                    $finalSuburbs[$suburb['_source']['id']] = $suburb['_source']['name'];
                }
            }
        }

        foreach($finalTests as &$test){
            $test['state']          = '';
            $test['municipality']   = '';
            $test['suburb']         = '';
            if(isset($finalStates[$test['stateID']])){
                $test['state'] = $finalStates[$test['stateID']];
            }
            if(isset($finalMunicipalities[$test['municipalityID']])){
                $test['municipality'] = $finalMunicipalities[$test['municipalityID']];
            }
            if(isset($finalSuburbs[$test['suburbID']])){
                $test['suburb'] = $finalSuburbs[$test['suburbID']];
            }

            unset($test['stateID']);
            unset($test['municipalityID']);
            unset($test['suburbID']);
        }

        return [
            'items'             => $finalTests,
            'total'             => $tests['hits']['total']['value'],
            'recordsTotal'      => $tests['hits']['total']['value'],
            'recordsFiltered'   => $tests['hits']['total']['value'],
        ];
    }

    public static function userDetail($profileID, Request $request){
        $profileData = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profileData || !$profileData['found']){
            return response('Invalid profileID', 404);
        }

        $response = [
            'id'            => $profileID,
            'userID'        => $profileData['_source']['userID'],
            'lastTest'      => $profileData['_source']['lastTest'],
            'totalTests'    => $profileData['_source']['totalTests'],
            'phone'         => self::getPhone($profileData['_source']['userID']),
            'tests'         => [],
            'messages'      => []
        ];

        $tests = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                ['term' => ['profileID' => $profileID]]
            ] ] ]]
        ]);

        if($tests && $tests['hits']['total']['value']){
            foreach($tests['hits']['hits'] as $test){
                $resultTest = '-';
                if(isset($test['_source']['level'])){
                    switch($test['_source']['level']){
                        case 0 : $resultTest = 'Bajo';          break;
                        case 1 : $resultTest = 'Medio';         break;
                        case 2 : $resultTest = 'Alto';          break;
                    }
                }
                $response['tests'][] = [
                    'id'                => $test['_id'],
                    'resultTest'        => $resultTest,
                    'timestamp'         => $test['_source']['timestamp'],
                    'age'               => $test['_source']['age'],
                    'gender'            => $test['_source']['gender'],
                    'postalCode'        => $test['_source']['postalCode'],
                    'breathing'         => $test['_source']['breathing'],
                    'defenses'          => $test['_source']['defenses'],
                    'diabetes'          => $test['_source']['diabetes'],
                    'hypertension'      => $test['_source']['hypertension'],
                    'obesity'           => $test['_source']['obesity'],
                    'pregnant'          => $test['_source']['pregnant'],
                    'symptomWeek'       => $test['_source']['symptomWeek'],
                    'symptoms'          => $test['_source']['symptoms'],
                    'stateID'           => isset($test['_source']['stateID']) ? $test['_source']['stateID'] :-1,
                    'municipalityID'    => isset($test['_source']['municipalityID']) ? $test['_source']['municipalityID'] :-1,
                    'suburbID'          => isset($test['_source']['suburbID']) ? $test['_source']['suburbID'] :-1,
                ];
            }
        }
        $messages = Elastic::search([
            'index'     => 'messages',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                ['term' => ['profileID' => $profileID]]
            ] ] ]]
        ]);

        if($messages && $messages['hits']['total']['value']){
            foreach($messages['hits']['hits'] as $message){
                $response['messages'][] = [
                    'id'                => $message['_id'],
                    'timestamp'         => $message['_source']['timestamp'],
                    'message'           => $message['_source']['message'],
                ];
            }
        }

        return $response;
    }


    public static function getPhone($userID, $mask = true){
        $realPhone = '';
        $userData = Elastic::get(['index' => 'users', 'id' => $userID, 'client' => ['ignore' => 404]]);
        if($userData && $userData['found']){
            try{
                $realPhone = Crypt::decryptString($userData['_source']['phone']);
            }catch(\Exception $e){
                //bad encryption
            }
            if($mask){
                $realPhone = '******' . substr($realPhone, 6);
            }
        }

        return $realPhone;
    }


    public static function downloadTests(Request $request){

        if(!Storage::exists('info')){
            Storage::makeDirectory('info');
        }

        $tempName   = bin2hex(random_bytes(10)) . time() . '.csv';
        $gestor     = false;

        try{
            $gestor = fopen(storage_path('app') . '/info/' . $tempName, "w");
        }catch(\Exception $e){
            return response('Error open file', 500);
        }

        ini_set('memory_limit', '10G');   

        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($stateID)){
            $query['bool']['must'][] = ['term' => ['stateID' => $stateID]];
        }
        if(!empty($municipalityID)){
            $query['bool']['must'][] = ['term' => ['municipalityID' => $municipalityID]];
        }
        if(!empty($suburbID)){
            $query['bool']['must'][] = ['term' => ['suburbID' => $suburbID]];
        }
        if(!empty($postalCode)){
            $query['bool']['must'][] = ['term' => ['postalCode' => $postalCode]];
        }
        if(!empty($startDate) || !empty($endDate)){
            $range_aux = [];
            if(!empty($startDate)){
                $range_aux['gte'] = $startDate;
            }
            if(!empty($endDate)){
                $range_aux['lte'] = $endDate;
            }
            $query['bool']['must'][] = ['range' => ['timestamp' => $range_aux]];
        }

        $body = [
            'from' => 0,
            'size' => 300000, 
            'sort' => [[ 'timestamp' => ['order' => 'desc','missing' =>  '_last']]]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $tests = Elastic::search([
            'index'             => 'tests',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        fputcsv ($gestor, [
            'idTest',
            'profileID',
            'timestamp',
            'stateID',
            'municipalityID',
            'suburbID',
            'postalCode',
            'age',
            'gender',
            'anonymous',
            'resultTest',
            'level',
            'firstSymptom',
            'symptoms',
            'symptomWeek',
            'diabetes',
            'obesity',
            'hypertension',
            'defenses',
            'breathing',
            'pregnant',
        ]);

        if($tests && $tests['hits']['total']['value']){
            foreach($tests['hits']['hits'] as $test){
                $resultTest = '-';
                if(isset($test['_source']['level'])){
                    switch($test['_source']['level']){
                        case 0: $resultTest = 'Sin sintomas';                       break;
                        case 5: $resultTest = 'Sintomas graves';                    break;
                        case 4: $resultTest = 'Sintomas leves grupo vulnerable';    break;
                        case 1: $resultTest = 'Sintomas previos';                   break;
                        case 3: $resultTest = 'Sintomas leves';                     break;
                        case 2: $resultTest = 'Sintomas previos grupo vulnerable';  break;
                    }
                }

                $anonymous = 1;
                $profileData = Elastic::get(['index' => 'profiles', 'id' => $test['_source']['profileID'], 'client' => ['ignore' => 404]]);
                if($profileData || $profileData['found']){
                    $anonymous = isset($profileData['_source']['anonymous']) && $profileData['_source']['anonymous'] ? 1 : 0;
                }

                fputcsv ($gestor, [
                    $test['_id'],
                    $test['_source']['profileID'],
                    $test['_source']['timestamp'],
                    $test['_source']['stateID'],
                    $test['_source']['municipalityID'],
                    $test['_source']['suburbID'],
                    $test['_source']['postalCode'],
                    $test['_source']['age'],
                    $test['_source']['gender'],
                    $anonymous,
                    $resultTest,
                    $test['_source']['level'] ? 1 : 0,
                    $test['_source']['firstSymptom'] ? 1 : 0,
                    $test['_source']['symptoms'] ? 1 : 0,
                    $test['_source']['symptomWeek'] ? 1 : 0,
                    $test['_source']['diabetes'] ? 1 : 0,
                    $test['_source']['obesity'] ? 1 : 0,
                    $test['_source']['hypertension'] ? 1 : 0,
                    $test['_source']['defenses'] ? 1 : 0,
                    $test['_source']['breathing'] ? 1 : 0,
                    $test['_source']['pregnant'] ? 1 : 0,
                ]);
            }
        }

        fclose($gestor);

        return response()->download(storage_path('app') . '/info/' . $tempName, 'testsData.csv')->deleteFileAfterSend();
    }
}