<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;

class State {

    public static function states(Request $request){
        $states = Elastic::search([
            'index'     => 'states',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 100, 'sort' => [[ 'name.raw' => ['order' => 'asc','missing' =>  '_first']]]] 
        ]);

        $finalStates = [];
        if($states && $states['hits']['total']['value']){
            foreach($states['hits']['hits'] as $state){
                $finalStates[] = $state['_source'];
            }
        }

        return $finalStates;
    }

    public static function municipalities($stateID, Request $request){
        $municipalities = Elastic::search([
            'index'     => 'municipalities',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                ['term' => ['stateID' => $stateID]]
            ] ] ], 'sort' => [[ 'name.raw' => ['order' => 'asc','missing' =>  '_first']] ] ]
        ]);

        $finalMunicipalities = [];
        if($municipalities && $municipalities['hits']['total']['value']){
            foreach($municipalities['hits']['hits'] as $municipality){
                $finalMunicipalities[] = $municipality['_source'];
            }
        }

        return $finalMunicipalities;
    }

    public static function suburbs($stateID, $municipalityID, Request $request){
        $suburbs = Elastic::search([
            'index'     => 'suburbs',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 2000,'query' => ['bool' => ['must' => [ 
                ['term' => ['municipalityID' => $municipalityID]]
            ] ] ], 'sort' => [[ 'name.raw' => ['order' => 'asc','missing' =>  '_first']] ] ]
        ]);

        $finalSuburbs= [];
        if($suburbs && $suburbs['hits']['total']['value']){
            foreach($suburbs['hits']['hits'] as $suburb){
                $finalSuburbs[] = $suburb['_source'];
            }
        }

        return $finalSuburbs;
    }
}