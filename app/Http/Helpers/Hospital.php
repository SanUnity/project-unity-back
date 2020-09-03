<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;

class Hospital {

    public static function hospitals(Request $request){
        $page           = $request->input('page');
        $size           = $request->input('size');
        $start          = $request->input('start'); //datatable js
        $length         = $request->input('length'); //datatable js

        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $testingService = $request->input('testingService');
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
        if($testingService === '1' || $testingService === '0'){
            $testingService = $testingService === '1';
            $query['bool']['must'][] = ['term' => ['testingService' => $testingService]];
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
            'sort' => [[ 'timestamp' => ['order' => 'desc','missing' =>  '_last']]]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $hospitals = Elastic::search([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalHospitals = [];
        $states         = [];
        $municipalities = [];
        $suburbs        = [];
        foreach($hospitals['hits']['hits'] as $hospital){

            $hospitalName = '-';
            $hospitalData = Elastic::get(['index' => 'hospitals', 'id' => $hospital['_source']['hospitalID'], 'client' => ['ignore' => 404]]);
            if($hospitalData && $hospitalData['found']){
                $hospitalName = $hospitalData['_source']['name'];
            }

            $finalHospitals[] = [
                'id'                => $hospital['_id'],
                'hospitalID'        => $hospital['_source']['hospitalID'],
                'name'              => $hospitalName,
                'testingService'    => $hospital['_source']['testingService'],
                'totalCapacity'     => $hospital['_source']['totalCapacity'],
                'occupiedCapacity'  => $hospital['_source']['occupiedCapacity'],
                'totalTest'         => $hospital['_source']['totalTest'],
                'positiveTest'      => $hospital['_source']['positiveTest'],
                'negativeTest'      => $hospital['_source']['negativeTest'],
                'search'            => $hospital['_source']['search'],
                'timestamp'         => $hospital['_source']['timestamp'],
                'stateID'           => $hospital['_source']['stateID'],
                'municipalityID'    => $hospital['_source']['municipalityID'],
                'suburbID'          => $hospital['_source']['suburbID'],
            ];

            if(!isset($states[$hospital['_source']['stateID']])){
                $states[$hospital['_source']['stateID']] = $hospital['_source']['stateID'];
            }
            if(!isset($municipalities[$hospital['_source']['municipalityID']])){
                $municipalities[$hospital['_source']['municipalityID']] = $hospital['_source']['municipalityID'];
            }
            if(!isset($suburbs[$hospital['_source']['suburbID']])){
                $suburbs[$hospital['_source']['suburbID']] = $hospital['_source']['suburbID'];
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

        foreach($finalHospitals as &$test){
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
        }


        return [
            'items' => $finalHospitals,  
            'total' => $hospitals['hits']['total']['value']
        ];
    }

    public static function hospitalsStats(Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
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
                'hospitals' => [
                    'terms' => ['field' => 'hospitalID', 'size' => 1000],
                    'aggs' => [
                        'totalCapacity' => [
                            'avg' => ['field' => 'totalCapacity']
                        ],
                        'occupiedCapacity' => [
                            'avg' => ['field' => 'occupiedCapacity']
                        ],
                        'totalTest' => [
                            'sum' => ['field' => 'totalTest']
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $hospitals = Elastic::search([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalHospitals = [
            'tests' => 0,
            'capacity' => [
                'low'       => 0,
                'medium'    => 0,
                'high'      => 0,
                'total'     => 0,
            ]
        ];

        foreach($hospitals['aggregations']['hospitals']['buckets'] as $hospital){
            $finalHospitals['tests'] += $hospital['totalTest']['value'];
            $finalHospitals['capacity']['total'] += $hospital['totalCapacity']['value'];
            $percentOccupied = ($hospital['occupiedCapacity']['value'] * 100) / $hospital['totalCapacity']['value'];
            if($percentOccupied < 30){
                $finalHospitals['capacity']['low']++;
            }else if($percentOccupied < 70){
                $finalHospitals['capacity']['medium']++;
            }else{
                $finalHospitals['capacity']['high']++;
            }
        }

        return $finalHospitals;
    }


    public static function statesHospitalsTests(Request $request){
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
                'hospitals' => [
                    'terms' => ['field' => 'stateID', 'size' => 1000],
                    'aggs' => [
                        'totalTest' => [
                            'sum' => ['field' => 'totalTest']
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $hospitals = Elastic::search([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalHospitals = [];
        $statesIDs      = [];
        foreach($hospitals['aggregations']['hospitals']['buckets'] as $hospital){
            $finalHospitals[] = [
                'stateID'   => $hospital['key'],
                'tests'     => $hospital['totalTest']['value'],
                'state'     => ''
            ];
            if(!isset($statesIDs[$hospital['key']])){
                $statesIDs[$hospital['key']] = $hospital['key'];
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
            foreach($finalHospitals as &$register){
                if(isset($finalStates[$register['stateID']])){
                    $register['state'] = $finalStates[$register['stateID']];
                }
            }
        }

        return $finalHospitals;
    }

    public static function municipalitiesHospitalsTests($stateID, Request $request){
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
                'hospitals' => [
                    'terms' => ['field' => 'municipalityID', 'size' => 1000],
                    'aggs' => [
                        'totalTest' => [
                            'sum' => ['field' => 'totalTest']
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $hospitals = Elastic::search([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalHospitals     = [];
        $municipalitiesIDs  = [];
        foreach($hospitals['aggregations']['hospitals']['buckets'] as $hospital){
            $finalHospitals[] = [
                'municipalityID'    => $hospital['key'],
                'tests'             => $hospital['totalTest']['value'],
                'municipality'      => ''
            ];
            if(!isset($municipalitiesIDs[$hospital['key']])){
                $municipalitiesIDs[$hospital['key']] = $hospital['key'];
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
            foreach($finalHospitals as &$register){
                if(isset($finalMunicipalities[$register['municipalityID']])){
                    $register['municipality'] = $finalMunicipalities[$register['municipalityID']];
                }
            }
        }

        return $finalHospitals;
    }

    public static function suburbsHospitalsTests($stateID, $municipalityID, Request $request){
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
                'hospitals' => [
                    'terms' => ['field' => 'suburbID', 'size' => 2000],
                    'aggs' => [
                        'totalTest' => [
                            'sum' => ['field' => 'totalTest']
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $hospitals = Elastic::search([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalHospitals = [];
        $suburbsIDs     = [];
        foreach($hospitals['aggregations']['hospitals']['buckets'] as $hospital){
            $finalHospitals[] = [
                'suburbID'  => $hospital['key'],
                'tests'     => $hospital['totalTest']['value'],
                'suburb'    => ''
            ];
            if(!isset($suburbsIDs[$hospital['key']])){
                $suburbsIDs[$hospital['key']] = $hospital['key'];
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
            foreach($finalHospitals as &$register){
                if(isset($finalSuburbs[$register['suburbID']])){
                    $register['suburb'] = $finalSuburbs[$register['suburbID']];
                }
            }
        }

        return $finalHospitals;
    }

    public static function createHospital(Request $request){
        $input  = $request->input();
        $data   = [
            'timestamp' => time()
        ];

        if(!empty($request->input('name'))){
            $data['name'] = $input['name'];
        }else{
            return false;
        }
        if(!empty($request->input('stateID'))){
            $data['stateID'] = $input['stateID'];
        }else{
            return false;
        }
        if(!empty($request->input('municipalityID'))){
            $data['municipalityID'] = $input['municipalityID'];
        }else{
            return false;
        }
        if(!empty($request->input('suburbID'))){
            $data['suburbID'] = $input['suburbID'];
        }else{
            return false;
        }
        if(isset($input['testingService']) && is_bool($input['testingService'])){
            $data['testingService'] = $input['testingService'];
        }else{
            return false;
        }

        $result = Elastic::index(['index' => 'hospitals', 'body' => $data,'refresh' => "false"]);
        if($result && $result['_id']){
            return $result['_id'];
        }
    }

    public static function createHospitalsTests(Request $request){
        $hospitalID = $request->input('hospitalID');
        if(empty($hospitalID)){
            $hospitalID = self::createHospital($request);
            if(!$hospitalID){
                return response('error creating hospital', 400);
            }
        }else{
            $hospitalData = Elastic::get(['index' => 'hospitals', 'id' => $hospitalID, 'client' => ['ignore' => 404]]);
            if(!$hospitalData || !$hospitalData['found']){
                return response('Invalid hospitalID', 404);
            }
        }

        $input  = $request->input();
        $data   = [
            'hospitalID' => $hospitalID,
        ];
        
        if(!empty($request->input('stateID'))){
            $data['stateID'] = $input['stateID'];
        }
        if(!empty($request->input('municipalityID'))){
            $data['municipalityID'] = $input['municipalityID'];
        }
        if(!empty($request->input('suburbID'))){
            $data['suburbID'] = $input['suburbID'];
        }
        if(isset($input['timestamp'])){
            $data['timestamp'] = $input['timestamp'];
        }
        if(isset($input['totalCapacity']) && is_numeric($input['totalCapacity'])){
            $data['totalCapacity'] = $input['totalCapacity'];
        }
        if(isset($input['occupiedCapacity']) && is_numeric($input['occupiedCapacity'])){
            $data['occupiedCapacity'] = $input['occupiedCapacity'];
        }
        if(isset($input['totalTest']) && is_numeric($input['totalTest'])){
            $data['totalTest'] = $input['totalTest'];
        }
        if(isset($input['positiveTest']) && is_numeric($input['positiveTest'])){
            $data['positiveTest'] = $input['positiveTest'];
        }
        if(isset($input['negativeTest']) && is_numeric($input['negativeTest'])){
            $data['negativeTest'] = $input['negativeTest'];
        }
        if(isset($input['search']) && is_numeric($input['search'])){
            $data['search'] = $input['search'];
        }
        if(isset($input['testingService']) && is_bool($input['testingService'])){
            $data['testingService'] = $input['testingService'];
        }

        Elastic::index(['index' => 'hospitals_tests', 'body' => $data,'refresh' => "false"]);
    }

    public static function deleteHospital($hospitalID){
        Elastic::delete(['index' => 'hospitals', 'id' => $hospitalID]);

        Elastic::deleteByQuery([
            'index'     => 'hospitals_tests',
            'client'    => ['ignore' => 404],
            'body'      => ['query' => ['bool' => ['must' => [ ['term' => ['hospitalID' => $hospitalID]] ] ] ]]
        ]);
    }

    public static function editHospitalsTests($hospitalID, $testID, Request $request){
        $hospitalTestData = Elastic::get(['index' => 'hospitals_tests', 'id' => $testID, 'client' => ['ignore' => 404]]);
        if(!$hospitalTestData || !$hospitalTestData['found']){
            return response('Invalid testID', 404);
        }
        if($hospitalTestData['_source']['hospitalID'] != $hospitalID){
            return response('Invalid testID', 404);
        }
        
        $input  = $request->input();
        $data   = [];
        
        if(!empty($request->input('stateID'))){
            $data['stateID'] = $input['stateID'];
        }
        if(!empty($request->input('municipalityID'))){
            $data['municipalityID'] = $input['municipalityID'];
        }
        if(!empty($request->input('suburbID'))){
            $data['suburbID'] = $input['suburbID'];
        }
        if(isset($input['timestamp'])){
            $data['timestamp'] = $input['timestamp'];
        }
        if(isset($input['totalCapacity']) && is_numeric($input['totalCapacity'])){
            $data['totalCapacity'] = $input['totalCapacity'];
        }
        if(isset($input['occupiedCapacity']) && is_numeric($input['occupiedCapacity'])){
            $data['occupiedCapacity'] = $input['occupiedCapacity'];
        }
        if(isset($input['totalTest']) && is_numeric($input['totalTest'])){
            $data['totalTest'] = $input['totalTest'];
        }
        if(isset($input['positiveTest']) && is_numeric($input['positiveTest'])){
            $data['positiveTest'] = $input['positiveTest'];
        }
        if(isset($input['negativeTest']) && is_numeric($input['negativeTest'])){
            $data['negativeTest'] = $input['negativeTest'];
        }
        if(isset($input['search']) && is_numeric($input['search'])){
            $data['search'] = $input['search'];
        }
        if(isset($input['testingService']) && is_bool($input['testingService'])){
            $data['testingService'] = $input['testingService'];
        }

        Elastic::update(['index' => 'hospitals_tests', 'id' => $testID, 'body' => ['doc' => $data],'refresh' => "false"]);
    }

    public static function deleteHospitalsTests($hospitalID, $testID){
        $hospitalTestData = Elastic::get(['index' => 'hospitals_tests', 'id' => $testID, 'client' => ['ignore' => 404]]);
        if(!$hospitalTestData || !$hospitalTestData['found']){
            return response('Invalid testID', 404);
        }
        if($hospitalTestData['_source']['hospitalID'] != $hospitalID){
            return response('Invalid testID', 404);
        }

        Elastic::delete(['index' => 'hospitals_tests', 'id' => $testID]);
    }
}