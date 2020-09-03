<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;

class Trend {

    public static function statesTrends($trendType, Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['trend' => $trendType]] ] ] ];
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
                'trends'    => $bucket['doc_count'],
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

    public static function municipalitiesTrends($stateID, $trendType, Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]],['term' => ['trend' => $trendType]] ] ] ];
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
                'trends'            => $bucket['doc_count'],
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

    public static function suburbsTrends($stateID, $municipalityID, $trendType, Request $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]],['term' => ['municipalityID' => $municipalityID]],['term' => ['trend' => $trendType]] ] ] ];
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
                'trends'      => $bucket['doc_count'],
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
            'body'      => ['from' => 0,'size' => 2000, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($suburbsIDs)]] ] ] ]] 
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

}