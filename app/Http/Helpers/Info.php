<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use Elastic;

class Info {

    public static function getStatesInfo(Request $request){
        $status = $request->input('status');

        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($status)){
            $statusValue = -1;
            switch($status){
                case 'low'          : $statusValue = 0; break;
                case 'medium'       : $statusValue = 1; break;
                case 'high'         : $statusValue = 2; break;
                case 'very-high'    : $statusValue = 3; break;
            }
            $query['bool']['must'][] = ['term' => ['status' => $statusValue]];
        }

        $body = [
            'from' => 0,
            'size' => 100,
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $info = Elastic::search([
            'index'     => 'states_info',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => $body 
        ]);

        $response   = [];
        $statesIDs  = [];
        if($info && $info['hits']['total']['value']){
            foreach($info['hits']['hits'] as $inf){
                $status = '-';
                if($inf['_source']['status'] !== null){
                    switch($inf['_source']['status']){
                        case 0: $status = 'low';        break;
                        case 1: $status = 'medium';     break;
                        case 2: $status = 'high';       break;
                        case 3: $status = 'very-high';  break;
                    }
                }
                $aux = [
                    'id'        => $inf['_id'],
                    'name'      => $inf['_source']['name'],
                    'status'    => $status,
                    'cases'     => $inf['_source']['cases'],
                    'municipalities'     => [
                        'low'           => 0,
                        'medium'        => 0,
                        'high'          => 0,
                        'very-high'     => 0,
                    ],
                ];

                $statesIDs[] = $inf['_id'];

                $response[$inf['_id']] = $aux;
            }

            $body = [
                'size' => 0, 
                'aggs' => [
                    'states' => [
                        'terms' => ['field' => 'stateIDCVE', 'size' => 50],
                        'aggs' => [
                            'status' => [
                                'terms' => ['field' => 'status'],
                            ]
                        ]
                    ]
                ],
                'query' => ['bool' => ['must' => [ ['terms' => ['stateID' => $statesIDs]] ] ] ]
            ];
    
            $body['query'] = $query;
    
            $municipalities = Elastic::search([
                'index'     => 'municipalities_info',
                'client'    => ['ignore' => 404],
                'body'      => $body
            ]);

            if($municipalities && $municipalities['aggregations']){
                foreach($municipalities['aggregations']['states']['buckets'] as $bucket){
                    if(!empty($bucket['status']['buckets'])){
                        foreach($bucket['status']['buckets'] as $statusAgg){
                            switch($statusAgg['key']){
                                case 0: $response[$bucket['key']]['municipalities']['low']          = $statusAgg['doc_count'];          break;
                                case 1: $response[$bucket['key']]['municipalities']['medium']       = $statusAgg['doc_count'];          break;
                                case 2: $response[$bucket['key']]['municipalities']['high']         = $statusAgg['doc_count'];          break;
                                case 3: $response[$bucket['key']]['municipalities']['very-high']    = $statusAgg['doc_count'];          break;
                            }
                        }
                    }
                }
            }
        }

        return array_values($response);
    }
    
    public static function setStatesInfo($stateID, Request $request){
        $status = $request->input('status');
        
        $statusValue = null;
        switch($status){
            case 'low'          : $statusValue = 0; break;
            case 'medium'       : $statusValue = 1; break;
            case 'high'         : $statusValue = 2; break;
            case 'very-high'    : $statusValue = 3; break;
        }
        
        Elastic::update(['index' => 'states_info', 'id' => $stateID, 'body' => ['doc' => ['status' => $statusValue]],'refresh' => "wait_for"]);

        return [];
    }
    
    public static function getMunicipalitiesInfo(Request $request){
        $status     = $request->input('status');
        $stateID    = $request->input('stateID');
        
        $query = ['bool' => ['must' => [  ] ] ];
        if(!empty($status)){
            $statusValue = -1;
            switch($status){
                case 'low'          : $statusValue = 0; break;
                case 'medium'       : $statusValue = 1; break;
                case 'high'         : $statusValue = 2; break;
                case 'very-high'    : $statusValue = 3; break;
            }
            $query['bool']['must'][] = ['term' => ['status' => $statusValue]];
        }
        if(!empty($stateID)){
            $query['bool']['must'][] = ['term' => ['stateIDCVE' => $stateID]];
        }

        $body = [
            'from' => 0,
            'size' => 5000,
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }
        
        $info = Elastic::search([
            'index'     => 'municipalities_info',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => $body 
        ]);
        
        $response = [];
        
        if($info && $info['hits']['total']['value']){
            $states = Elastic::search([
                'index'     => 'states_info',
                'client'    => ['ignore' => 404],
                '_source'   => true,
                'body'      => ['from' => 0,'size' => 100] 
            ]);
            $statesNames = [];
            foreach($states['hits']['hits'] as $state){
                $statesNames[$state['_id']] = $state['_source']['name'];
            }
            
            foreach($info['hits']['hits'] as $inf){
                $status = '-';
                if($inf['_source']['status'] !== null){
                    switch($inf['_source']['status']){
                        case 0: $status = 'low';        break;
                        case 1: $status = 'medium';     break;
                        case 2: $status = 'high';       break;
                        case 3: $status = 'very-high';  break;
                    }
                }
                $aux = [
                    'id'        => $inf['_id'],
                    'name'      => $inf['_source']['name'],
                    'stateID'   => $inf['_source']['stateIDCVE'],
                    'state'     => isset($statesNames[$inf['_source']['stateIDCVE']]) ? $statesNames[$inf['_source']['stateIDCVE']] : '',
                    'status'    => $status,
                    'cases'     => $inf['_source']['cases'],
                ];
                
                $response[] = $aux;
            }
        }
            
        return $response;
    }

    public static function setMunicipalitiesInfo($municipalityID, Request $request){
        $status = $request->input('status');
        
        $statusValue = null;
        switch($status){
            case 'low'          : $statusValue = 0; break;
            case 'medium'       : $statusValue = 1; break;
            case 'high'         : $statusValue = 2; break;
            case 'very-high'    : $statusValue = 3; break;
        }
        
        Elastic::update(['index' => 'municipalities_info', 'id' => $municipalityID, 'body' => ['doc' => ['status' => $statusValue]],'refresh' => "wait_for"]);

        return [];
    }

    public static function downloadStatesInfo(Request $request){
        if(!Storage::exists('info')){
            Storage::makeDirectory('info');
        }

        $statesInfo = self::getStatesInfo($request);

        $tempName   = bin2hex(random_bytes(10)) . time() . '.csv';
        $gestor     = false;

        try{
            $gestor = fopen(storage_path('app') . '/info/' . $tempName, "w");
        }catch(\Exception $e){
            return response('Error open file', 500);
        }

        foreach($statesInfo as $info){
            fputcsv ($gestor, [
                $info['id'],
                $info['name'],
                $info['status'],
            ]);
        }

        fclose($gestor);

        return response()->download(storage_path('app') . '/info/' . $tempName, 'statesInfo.csv')->deleteFileAfterSend();
    }

    public static function updloadStatesInfo(Request $request){
        if(!Storage::exists('info')){
            Storage::makeDirectory('info');
        }

        if($request->hasFile('file')){
            $path       = $request->file('file')->store('info');
            $realPath   = storage_path('app') . '/' . $path;
            $gestor     = false;

            try{
                $gestor = fopen($realPath, "r");
            }catch(\Exception $e){
                return response('Error open file', 500);
            }

            $bom    = pack('CCC', 0xEF, 0xBB, 0xBF);
            $first  = true;
            while (($dataAux = fgetcsv($gestor, 10000, ",")) !== FALSE) {
                if ($first && substr($dataAux[0], 0, 3) === $bom) {
                    $dataAux[0] = substr($dataAux[0], 3);
                }
                $first = false;
                if(!empty($dataAux) && count($dataAux) == 3){
                    $dataAll = [
                        'id'        => $dataAux[0],
                        'status'    => $dataAux[2],
                    ];

                    $status = false;
                    switch($dataAll['status']){
                        case 'low'          : $status = 0; break;
                        case 'medium'       : $status = 1; break;
                        case 'high'         : $status = 2; break;
                        case 'very-high'    : $status = 3; break;
                    }

                    if($status !== false){
                        Elastic::update(['index' => 'states_info', 'id' => $dataAll['id'], 'body' => ['doc' => ['status' => $status]],'refresh' => "false", 'client' => ['ignore' => 404]]);
                    }
                }
            }
        }
    }

    public static function downloadMunicipalitiesInfo(Request $request){

        if(!Storage::exists('info')){
            Storage::makeDirectory('info');
        }

        $statesInfo = self::getMunicipalitiesInfo($request);

        $tempName   = bin2hex(random_bytes(10)) . time() . '.csv';
        $gestor     = false;

        try{
            $gestor = fopen(storage_path('app') . '/info/' . $tempName, "w");
        }catch(\Exception $e){
            return response('Error open file', 500);
        }

        foreach($statesInfo as $info){
            fputcsv ($gestor, [
                $info['stateID'],
                $info['state'],
                $info['id'],
                $info['name'],
                $info['status'],
            ]);
        }

        fclose($gestor);

        return response()->download(storage_path('app') . '/info/' . $tempName, 'municipalitiesInfo.csv')->deleteFileAfterSend();
    }


    public static function updloadMunicipalitiesInfo(Request $request){
        if(!Storage::exists('info')){
            Storage::makeDirectory('info');
        }

        if($request->hasFile('file')){
            $path       = $request->file('file')->store('info');
            $realPath   = storage_path('app') . '/' . $path;
            $gestor     = false;

            try{
                $gestor = fopen($realPath, "r");
            }catch(\Exception $e){
                return response('Error open file', 500);
            }

            $bom    = pack('CCC', 0xEF, 0xBB, 0xBF);
            $first  = true;
            while (($dataAux = fgetcsv($gestor, 10000, ",")) !== FALSE) {
                if ($first && substr($dataAux[0], 0, 3) === $bom) {
                    $dataAux[0] = substr($dataAux[0], 3);
                }
                $first = false;
                if(!empty($dataAux) && count($dataAux) == 5){
                    $dataAll = [
                        'id'        => $dataAux[2],
                        'status'    => $dataAux[4],
                    ];

                    $status = false;
                    switch($dataAll['status']){
                        case 'low'          : $status = 0; break;
                        case 'medium'       : $status = 1; break;
                        case 'high'         : $status = 2; break;
                        case 'very-high'    : $status = 3; break;
                    }

                    if($status !== false){
                        Elastic::update(['index' => 'municipalities_info', 'id' => $dataAll['id'], 'body' => ['doc' => ['status' => $status]],'refresh' => "false", 'client' => ['ignore' => 404]]);
                    }
                }
            }
        }
    }
}