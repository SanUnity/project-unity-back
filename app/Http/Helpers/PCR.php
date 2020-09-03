<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Elastic;
use App\SMS;

class PCR {

    public static function setPcrResult(Request $request){
        $data = $request->input('data');

        foreach($data as $pcr){
            $pcrResultID    = null;
            $pcrInfoID      = null;

            $pcrResult = Elastic::index(['index' => 'pcr_results', 'body' => [
                'centerId'      => $pcr['clue'],
                'name'          => $pcr['name'],
                'lastname1'     => $pcr['lastname1'],
                'lastname2'     => $pcr['lastname2'],
                'phone'         => $pcr['phone'],
                'dateTest'      => $pcr['date'],
                'resultTest'    => $pcr['resultTest'], //1 -> Positivo, 2 -> Negativo, 3 -> No procesada 
                'pcrInfoId'     => $pcrInfoID,
            ], 'refresh' => "false"]);

            if($pcrResult && $pcrResult['_id']){
                $pcrResultID = $pcrResult['_id'];
            }

            $pcrInfo = Elastic::search([
                'index'             => 'pcr_info',
                'client'            => ['ignore' => 404],
                'track_total_hits'  => true,
                'body'              => [
                    'from'  => 0,
                    'size'  => 10000, 
                    'query' => ['bool' => ['must' => [ 
                        ['term' => ['phone'         => $pcr['phone']]],
                        ['term' => ['verifiedPhone' => true]],
                        ['term' => ['dateTest'      => $pcr['date']]],
                        ['term' => ['centerId'      => $pcr['clue']]],
                    ] ] ]
                ]
            ]);

            if($pcrInfo && $pcrInfo['hits']['total']['value']){
                //valores de SSA, 1 -> Positivo, 2 -> Negativo, 3 -> No procesada 
                //valores internos, 0 => negative, 1 => positive, 2 => no procesada, 3 esperando resultado, 
                $resultTest = 0; //negative in pcr_info
                if($pcr['resultTest'] === 1){
                    $resultTest = 1; //positive in pcr_info
                }else if($pcr['resultTest'] === 2){
                    $resultTest = 0; //no negative in pcr_info
                }else if($pcr['resultTest'] === 3){
                    $resultTest = 2; //no procesada in pcr_info
                }
                if($pcrInfo['hits']['total']['value'] === 1){
                    $pcrData    = $pcrInfo['hits']['hits'][0];
                    $pcrInfoID  = $pcrData['_id'];

                    Elastic::update(['index' => 'pcr_info', 'id' => $pcrData['_id'], 'body' => ['doc' => [
                        'verified'      => true,
                        'readed'        => false,
                        'resultTest'    => $resultTest,
                        'pcrResultId'   => $pcrResultID,
                    ]],'refresh' => "false"]);
                    
                }else{
                    $name       = trim(Str::lower(Str::of($pcr['name'])->ascii()));
                    $lastname   = trim(Str::lower(Str::of($pcr['lastname1'])->ascii() . ' ' . Str::of($pcr['lastname2'])->ascii()));

                    $levenshtein    = [];
                    $matched        = false;
                    foreach($pcrInfo['hits']['hits'] as $pcrData){
                        $pcrName        = trim(Str::lower(Str::of($pcrData['_source']['name'])->ascii()));
                        $pcrLastname    = trim(Str::lower(Str::of($pcrData['_source']['lastname'])->ascii()));

                        $aux = [
                            'value'     => levenshtein($name, $pcrName) + levenshtein($lastname, $pcrLastname),
                            'id'        => $pcrData['_id']
                        ];
                        
                        if($aux['value'] === 0){ //coincide nombre y apellido
                            Elastic::update(['index' => 'pcr_info', 'id' => $pcrData['_id'], 'body' => ['doc' => [
                                'verified'      => true,
                                'readed'        => false,
                                'resultTest'    => $resultTest,
                                'pcrResultId'   => $pcrResultID,
                            ]],'refresh' => "false"]);
                            if(empty($pcrInfoID)){
                                $pcrInfoID  = $pcrData['_id'];
                            }else{
                                if(!is_array($pcrInfoID)){
                                    $pcrInfoID = [$pcrInfoID];
                                }
                                $pcrInfoID[] = $pcrData['_id'];
                            }
                            $matched    = true;
                        }else{ //nombre o apellido son diferentes
                            $levenshtein[] = $aux;
                        }
                    }

                    if(!$matched){ //no encontro un test que coincidiera en nombre y apellido, asi que buscamos el mas parecido
                        usort($levenshtein, function($a, $b){
                            if ($a['value'] == $b['value']) {
                                return 0;
                            }
                            return ($a['value'] < $b['value']) ? -1 : 1;
                        });

                        $pcrInfoID = $levenshtein[0]['id'];
                        Elastic::update(['index' => 'pcr_info', 'id' => $levenshtein[0]['id'], 'body' => ['doc' => [
                            'verified'      => true,
                            'readed'        => false,
                            'resultTest'    => $resultTest,
                            'pcrResultId'   => $pcrResultID,
                        ]],'refresh' => "false"]);
                    }
                }

                SMS::send($pcr['phone'], config('app.sms_result_test'));

                if(!empty($pcrResultID) && !empty($pcrInfoID)){
                    Elastic::update(['index' => 'pcr_results', 'id' => $pcrResultID, 'body' => ['doc' => ['pcrInfoId' => $pcrInfoID]],'refresh' => "false"]);
                }
            }
        }
    }
}