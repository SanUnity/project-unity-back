<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;

class HospitalPublic {

    public static function hospitals($stateID, $municipalityID, Request $request){
        $hospitalsData = Elastic::search([
            'index'     => 'hospitals_publics',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 10000,'query' => ['bool' => 
                [
                    'must' => [ 
                        ['term' => ['stateID' => $stateID]],
                        ['term' => ['municipalityID' => $municipalityID]],
                        ['terms' => ['level' => [1,2,3]]],
                    ],
                    'must_not' => [ 
                        ['term' => ['status' => 'BAJA']],
                    ] 
                ]
             ]]
        ]);
        
        $finalHospitals = [];
        if($hospitalsData && $hospitalsData['hits']['total']['value']){
            foreach($hospitalsData['hits']['hits'] as $hospital){
                $aux = [
                    'hospitalID'    => $hospital['_source']['hospitalID'],
                    'hospital'      => $hospital['_source']['name'],
                    'address'       => $hospital['_source']['address'],
                    'level'         => $hospital['_source']['level'],
                    'openTime'      => $hospital['_source']['openTime'],
                    'location'      => $hospital['_source']['location'],
                    'suburb'        => $hospital['_source']['suburb'],
                    'covid'         => $hospital['_source']['covid'],
                ];
                if(!empty($hospital['_source']['typeAddress'])){
                    $aux['address'] = $hospital['_source']['typeAddress'] . ' ' . $aux['address'];
                }
                if(!empty($hospital['_source']['address_num'])){
                    $aux['address'] = $aux['address'] . ',' . $hospital['_source']['address_num'];
                }

                $finalHospitals[] = $aux;
            }
        }

        return $finalHospitals;
    }
}