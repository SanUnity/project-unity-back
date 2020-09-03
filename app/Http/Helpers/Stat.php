<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;

class Stat {

    public static function age(Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');
        $gender         = $request->input('gender');

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
        if(!empty($gender)){
            $query['bool']['must'][] = ['term' => ['gender' => $gender]];
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
                'age' => [
                    'range' => [
                        'field' => 'age', 
                        "ranges" => [
                            [ "to" => 10 ],
                            [ "from" => 10, "to" => 20 ],
                            [ "from" => 20, "to" => 30 ],
                            [ "from" => 30, "to" => 40 ],
                            [ "from" => 40, "to" => 50 ],
                            [ "from" => 50, "to" => 60 ],
                            [ "from" => 60, "to" => 70 ],
                            [ "from" => 70, "to" => 80 ],
                            [ "from" => 80, "to" => 90 ],
                            [ "from" => 90 ]
                        ]
                    ],
                    'aggs' => [
                        'profiles' => [
                            'cardinality' => ['field' => 'profileID', 'precision_threshold' => 10000]
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $age = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $totalElements = 0;
        foreach($age['aggregations']['age']['buckets'] as $bucket){
            $totalElements += $bucket['profiles']['value'];
        }

        $finalAge       = [];
        foreach($age['aggregations']['age']['buckets'] as $bucket){
            $finalAge[] = [
                'range' => $bucket['key'],
                'cases' => $bucket['profiles']['value'],
                'percentage' => $totalElements == 0 ? $totalElements : round((($bucket['profiles']['value'] * 100) / $totalElements), 1),
            ];
        }
        $finalAge[] = [
            'range' => 'total',
            'cases' => $totalElements,
            'percentage' => 100,
        ];

        return $finalAge;
    }

    public static function gender(Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $minAge         = $request->input('minAge');
        $maxAge         = $request->input('maxAge');
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
        if(!empty($minAge) || !empty($maxAge)){
            $range_aux = [];
            if(!empty($minAge)){
                $range_aux['gte'] = $minAge;
            }
            if(!empty($maxAge)){
                $range_aux['lte'] = $maxAge;
            }
            $query['bool']['must'][] = ['range' => ['age' => $range_aux]];
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
                'gender' => [
                    'terms' => ['field' => 'gender'],
                    'aggs' => [
                        'profiles' => [
                            'cardinality' => ['field' => 'profileID', 'precision_threshold' => 10000]
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $gender = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $totalElements = 0;
        foreach($gender['aggregations']['gender']['buckets'] as $bucket){
            $totalElements += $bucket['profiles']['value'];
        }

        $finalGender    = [];
        foreach($gender['aggregations']['gender']['buckets'] as $bucket){
            $finalGender[] = [
                'gender' => $bucket['key'],
                'cases' => $bucket['profiles']['value'],
                'percentage' => $totalElements == 0 ? $totalElements : round((($bucket['profiles']['value'] * 100) / $totalElements), 1),
            ];
        }

        return $finalGender;
    }

    public static function timeline(Request $request){
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
            'size' => 0, 
            'aggs' => [
                'timeline' => [
                    'date_histogram' => [
                        'field'     => 'timestamp',
                        'interval'  => 'day',
                        "time_zone" => "Mexico/General"
                    ],
                    'aggs' => [
                        'level' => [
                            'terms' => ['field' => 'level']
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $timeline = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finaltimeline  = [];
        $totalElements  = 0;
        $totalLevel0    = 0;
        $totalLevel1    = 0;
        $totalLevel2    = 0;
        $totalLevel3    = 0;
        $totalLevel4    = 0;
        $totalLevel5    = 0;
        foreach($timeline['aggregations']['timeline']['buckets'] as $bucket){
            $auxData = [
                'date' => (int) $bucket['key_as_string'],
                'level' => [
                    'level-0'       => 0,
                    'level-1'       => 0,
                    'level-2'       => 0,
                    'level-3'       => 0,
                    'level-4'       => 0,
                    'level-5'       => 0,
                    'total'         => $bucket['doc_count'],
                ]
            ];

            if(isset($bucket['level']) && !empty($bucket['level']['buckets'])){
                foreach($bucket['level']['buckets'] as $buck){
                    switch($buck['key']){
                        case 0: $auxData['level']['level-0']    = $buck['doc_count'];  break;
                        case 5: $auxData['level']['level-1']    = $buck['doc_count'];  break;
                        case 4: $auxData['level']['level-2']    = $buck['doc_count'];  break;
                        case 1: $auxData['level']['level-3']    = $buck['doc_count'];  break;
                        case 3: $auxData['level']['level-4']    = $buck['doc_count'];  break;
                        case 2: $auxData['level']['level-5']    = $buck['doc_count'];  break;
                    }
                }
            }

            $totalElements+=    $bucket['doc_count'];
            $totalLevel0+=      $auxData['level']['level-0'];
            $totalLevel1+=      $auxData['level']['level-1'];
            $totalLevel2+=      $auxData['level']['level-2'];
            $totalLevel3+=      $auxData['level']['level-3'];
            $totalLevel4+=      $auxData['level']['level-4'];
            $totalLevel5+=      $auxData['level']['level-5'];

            $finaltimeline[] = $auxData;
        }

        return [
            'items'     => $finaltimeline,
            'total'     => $totalElements,
            'level-0'   => $totalLevel0,
            'level-1'   => $totalLevel1,
            'level-2'   => $totalLevel2,
            'level-3'   => $totalLevel3,
            'level-4'   => $totalLevel4,
            'level-5'   => $totalLevel5,
        ];
    }

    public static function statsAgeGenderType($type, Request $request){
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
            'size' => 0, 
            'aggs' => [
                'age' => [
                    'range' => [
                        'field' => 'age', 
                        "ranges" => [
                            [ "to" => 10 ],
                            [ "from" => 10, "to" => 20 ],
                            [ "from" => 20, "to" => 30 ],
                            [ "from" => 30, "to" => 40 ],
                            [ "from" => 40, "to" => 50 ],
                            [ "from" => 50, "to" => 60 ],
                            [ "from" => 60, "to" => 70 ],
                            [ "from" => 70, "to" => 80 ],
                            [ "from" => 80, "to" => 90 ],
                            [ "from" => 90 ]
                        ]
                    ],
                    'aggs' => [
                        'gender' => [
                            'terms' => ['field' => 'gender']
                        ]
                    ]
                ]
            ]
        ];

        $index = 'profiles';
        switch($type){
            case 'contactTracing'   : $query['bool']['must'][] = ['term' => ['contactTracing' => true]];                    break;
            case 'geo'              : $query['bool']['must'][] = ['term' => ['geo' => true]];                               break;
            case 'exitRequest'      : $query['bool']['must'][] = ['range' => ['exitRequest' => ['gt' => 0]]];               break;
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $age = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalAge       = [];
        foreach($age['aggregations']['age']['buckets'] as $bucket){
            $auxData = [
                'range'     => $bucket['key'],
                'male'      => 0,
                'female'    => 0,
                'nonBinary' => 0,
            ];
            foreach($bucket['gender']['buckets'] as $gender){
                switch($gender['key']){
                    case 'male'     : $auxData['male']      = $gender['doc_count'];  break;
                    case 'female'   : $auxData['female']    = $gender['doc_count'];  break;
                    case 'nonBinary': $auxData['nonBinary'] = $gender['doc_count'];  break;
                }
            }

            $finalAge[] = $auxData;
        }

        return $finalAge;
    }

    public static function statsSymptoms($type, Request $request){
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
            'size' => 0, 
            'aggs' => [
                'symptom'           => ['terms' => ['field' => 'symptom']],
                'symptomWeek'       => ['terms' => ['field' => 'symptomWeek']],
                'pregnant'          => ['terms' => ['field' => 'pregnant']],
                'diabetes'          => ['terms' => ['field' => 'diabetes']],
                'hypertension'      => ['terms' => ['field' => 'hypertension']],
                'obesity'           => ['terms' => ['field' => 'obesity']],
                'defenses'          => ['terms' => ['field' => 'defenses']],
                'breathing'         => ['terms' => ['field' => 'breathing']],
            ]
        ];

        $index = 'profiles';
        switch($type){
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $symptoms = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalSymptoms = [
            'symptom'           => self::getTrueValue($symptoms['aggregations']['symptom']['buckets']),
            'symptomWeek'       => self::getTrueValue($symptoms['aggregations']['symptomWeek']['buckets']),
            'pregnant'          => self::getTrueValue($symptoms['aggregations']['pregnant']['buckets']),
            'diabetes'          => self::getTrueValue($symptoms['aggregations']['diabetes']['buckets']),
            'hypertension'      => self::getTrueValue($symptoms['aggregations']['hypertension']['buckets']),
            'obesity'           => self::getTrueValue($symptoms['aggregations']['obesity']['buckets']),
            'defenses'          => self::getTrueValue($symptoms['aggregations']['defenses']['buckets']),
            'breathing'         => self::getTrueValue($symptoms['aggregations']['breathing']['buckets']),
        ];

        return $finalSymptoms;
    }

    private static function getTrueValue($buckets){
        $value = 0;
        foreach($buckets as $bucket){
            if($bucket['key_as_string'] === 'true'){
                $value = $bucket['doc_count'];
                break;
            }
        }
        return $value;
    }

    public static function statsSocialSecurity($type, Request $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['exists' => ['field' => 'imss']] ] ] ]; //must be exist field becouse not all profile set this field
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
            'size' => 0, 
            'aggs' => [
                'imss' => ['terms' => ['field' => 'imss']],
            ]
        ];

        $index = 'profiles';
        switch($type){
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $socialSecurity = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        return self::getYesNoValue($socialSecurity['aggregations']['imss']['buckets']);
    }

    private static function getYesNoValue($buckets){
        $response = [
            'yes'   => 0,
            'no'    => 0
        ];
        foreach($buckets as $bucket){
            switch($bucket['key_as_string']){
                case 'true'     : $response['yes']  = $bucket['doc_count'];     break;
                case 'false'    : $response['no']   = $bucket['doc_count'];     break;
            }
        }
        return $response;
    }

    private static function getYesNoRequest($index, $query, $fieldAggs, $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $postalCode     = $request->input('postalCode');
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

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
            'size' => 0, 
            'aggs' => [
                'yesno' => ['terms' => ['field' => $fieldAggs]],
            ]
        ];
        

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $socialSecurity = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        return self::getYesNoValue($socialSecurity['aggregations']['yesno']['buckets']);
    }

    public static function statsGeo(Request $request){

        $query = ['bool' => ['must' => [  ] ] ]; 

        return self::getYesNoRequest('users', $query, 'geo', $request);
    }

    public static function contactTrace(Request $request){

        $query = ['bool' => ['must' => [  ] ] ]; 

        return self::getYesNoRequest('users', $query, 'contactTrace', $request);
    }

    public static function statsStates($type, Request $request){
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
                'states' => [
                    'terms' => ['field' => 'stateID', 'size' => 1000],
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $index = 'profiles';
        switch($type){
            case 'contactTracing'   : $query['bool']['must'][] = ['term' => ['contactTracing' => true]];                    break;
            case 'geo'              : $query['bool']['must'][] = ['term' => ['geo' => true]];                               break;
            case 'exitRequest'      : $query['bool']['must'][] = ['range' => ['exitRequest' => ['gt' => 0]]];               break;
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        $data = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalData = [];
        $statesIDs      = [];
        foreach($data['aggregations']['states']['buckets'] as $bucket){
            $finalData[] = [
                'id'        => $bucket['key'],
                'value'     => $bucket['doc_count'],
                'name'      => ''
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

            foreach($finalData as &$data){
                if(isset($finalStates[$data['id']])){
                    $data['name'] = $finalStates[$data['id']];
                }
            }
        }

        return $finalData;
    }

    public static function statsMunicipalities($stateID, $type, Request $request){
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
                'municipalities' => [
                    'terms' => ['field' => 'municipalityID', 'size' => 1000],
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $index = 'profiles';
        switch($type){
            case 'contactTracing'   : $query['bool']['must'][] = ['term' => ['contactTracing' => true]];                    break;
            case 'geo'              : $query['bool']['must'][] = ['term' => ['geo' => true]];                               break;
            case 'exitRequest'      : $query['bool']['must'][] = ['range' => ['exitRequest' => ['gt' => 0]]];               break;
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        $data = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalData          = [];
        $municipalitiesIDs  = [];
        foreach($data['aggregations']['municipalities']['buckets'] as $bucket){
            $finalData[] = [
                'id'        => $bucket['key'],
                'value'     => $bucket['doc_count'],
                'name'      => ''
            ];
            if(!isset($municipalitiesIDs[$bucket['key']])){
                $municipalitiesIDs[$bucket['key']] = $bucket['key'];
            }
        }

        $municipalities = Elastic::search([
            'index'     => 'municipalities',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 100, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($municipalitiesIDs)]] ] ] ]] 
        ]);

        $finalMunicipalities = [];
        if($municipalities && $municipalities['hits']['total']['value']){
            foreach($municipalities['hits']['hits'] as $municipality){
                $finalMunicipalities[$municipality['_source']['id']] = $municipality['_source']['name'];
            }

            foreach($finalData as &$data){
                if(isset($finalMunicipalities[$data['id']])){
                    $data['name'] = $finalMunicipalities[$data['id']];
                }
            }
        }

        return $finalData;
    }

    public static function statsSuburbs($stateID, $municipalityID, $type, Request $request){
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
                'suburbs' => [
                    'terms' => ['field' => 'suburbID', 'size' => 1000],
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $index = 'profiles';
        switch($type){
            case 'contactTracing'   : $query['bool']['must'][] = ['term' => ['contactTracing' => true]];                    break;
            case 'geo'              : $query['bool']['must'][] = ['term' => ['geo' => true]];                               break;
            case 'exitRequest'      : $query['bool']['must'][] = ['range' => ['exitRequest' => ['gt' => 0]]];               break;
            case 'test'             : $index = 'tests';                                                                     break;
            case 'trendPositive'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'positive']];     break;
            case 'trendNegative'    : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'negative']];     break;
            case 'trendNeutral'     : $index = 'tests'; $query['bool']['must'][] = ['term' => ['trend' => 'neutral']];      break;
        }

        $data = Elastic::search([
            'index'     => $index,
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $finalData          = [];
        $suburbsIDs  = [];
        foreach($data['aggregations']['suburbs']['buckets'] as $bucket){
            $finalData[] = [
                'id'        => $bucket['key'],
                'value'     => $bucket['doc_count'],
                'name'      => ''
            ];
            if(!isset($suburbsIDs[$bucket['key']])){
                $suburbsIDs[$bucket['key']] = $bucket['key'];
            }
        }

        $suburbs = Elastic::search([
            'index'     => 'suburbs',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 100, 'query' => ['bool' => ['must' => [ ['terms' => ['id' => array_values($suburbsIDs)]] ] ] ]] 
        ]);

        $finalSuburbs = [];
        if($suburbs && $suburbs['hits']['total']['value']){
            foreach($suburbs['hits']['hits'] as $suburb){
                $finalSuburbs[$suburb['_source']['id']] = $suburb['_source']['name'];
            }

            foreach($finalData as &$data){
                if(isset($finalSuburbs[$data['id']])){
                    $data['name'] = $finalSuburbs[$data['id']];
                }
            }
        }

        return $finalData;
    }

    public static function statsIndicators(Request $request){
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
            'size' => 0, 
            'aggs' => [
                'avgProfiles'   => ['avg' => ['field' => 'totalProfiles']],
                'totalProfiles' => ['sum' => ['field' => 'totalProfiles']],
                'usersMultiplesProfiles' => ['range' => ['field' => 'totalProfiles',  "ranges" => [ [ "from" => 2 ] ] ]],
                'contactTrace' => ['terms' => ['field' => 'contactTrace']],
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $users = Elastic::search([
            'index'             => 'users',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        $usersMultiplesProfiles = 0;
        foreach($users['aggregations']['usersMultiplesProfiles']['buckets'] as $bucket){
            if($bucket['from'] == 2){
                $usersMultiplesProfiles = $bucket['doc_count'];
            }
        }

        $body = [
            'size' => 0, 
            'aggs' => [
                'totalTests'        => ['avg' => ['field' => 'totalTests']],
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $profiles = Elastic::search([
            'index'             => 'profiles',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        $body = [
            'size' => 0, 
            'aggs' => [
                'level'        => ['terms' => ['field' => 'level']],
            ]
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

        $highTests = 0;
        foreach($tests['aggregations']['level']['buckets'] as $bucket){
            if($bucket['key'] == 2){
                $highTests = $bucket['doc_count'];
            }
        }

        $data = [
            "totalUsers"                => $users['hits']['total']['value'],
            "totalProfiles"             => $profiles['hits']['total']['value'],
            "avgProfiles"               => $users['aggregations']['avgProfiles']['value'],
            "usersMultiplesProfiles"    => $usersMultiplesProfiles,
            "contactTrace"              => self::getTrueValue($users['aggregations']['contactTrace']['buckets']),
            "totalTests"                => $tests['hits']['total']['value'],
            "highTests"                 => $highTests,
            "geo"                       => self::getTrueValue($users['aggregations']['contactTrace']['buckets']),
            "profilesExitRequests"      => 0,
            "totalExitRequests"         => 0
        ];

        return $data;
    }

    private static function createFilters($query, $request){
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $gender         = $request->input('gender');
        $diabetes       = $request->input('diabetes');
        $obesity        = $request->input('obesity');
        $hypertension   = $request->input('hypertension');
        $defenses       = $request->input('defenses');
        $pregnant       = $request->input('pregnant');

        if(!empty($stateID)){
            $query['bool']['must'][] = ['term' => ['stateID' => $stateID]];
        }
        if(!empty($municipalityID)){
            $query['bool']['must'][] = ['term' => ['municipalityID' => $municipalityID]];
        }
        if(!empty($suburbID)){
            $query['bool']['must'][] = ['term' => ['suburbID' => $suburbID]];
        }
        if(!empty($gender)){
            $query['bool']['must'][] = ['term' => ['gender' => $gender]];
        }
        if(!empty($diabetes)){
            $query['bool']['must'][] = ['term' => ['diabetes' => $diabetes == 1]];
        }
        if(!empty($obesity)){
            $query['bool']['must'][] = ['term' => ['obesity' => $obesity == 1]];
        }
        if(!empty($hypertension)){
            $query['bool']['must'][] = ['term' => ['hypertension' => $hypertension == 1]];
        }
        if(!empty($defenses)){
            $query['bool']['must'][] = ['term' => ['defenses' => $defenses == 1]];
        }
        if(!empty($pregnant)){
            $query['bool']['must'][] = ['term' => ['pregnant' => $pregnant == 1]];
        }

        return $query;
    }

    private static function createFiltersPrincipalCases($query, $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');

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

        return $query;
    }

    private static function createFiltersSecondaryCases($query, $request){
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');
        $stateID        = $request->input('stateID');
        $municipalityID = $request->input('municipalityID');
        $suburbID       = $request->input('suburbID');
        $gender         = $request->input('gender');
        $diabetes       = $request->input('diabetes');
        $obesity        = $request->input('obesity');
        $hypertension   = $request->input('hypertension');
        $defenses       = $request->input('defenses');
        $pregnant       = $request->input('pregnant');

        if(!empty($stateID)){
            $query['bool']['must'][] = ['term' => ['stateID' => $stateID]];
        }
        if(!empty($municipalityID)){
            $query['bool']['must'][] = ['term' => ['municipalityID' => $municipalityID]];
        }
        if(!empty($suburbID)){
            $query['bool']['must'][] = ['term' => ['suburbID' => $suburbID]];
        }
        if(!empty($gender)){
            $query['bool']['must'][] = ['term' => ['gender' => $gender]];
        }
        if(!empty($diabetes)){
            $query['bool']['must'][] = ['term' => ['diabetes' => $diabetes == 1]];
        }
        if(!empty($obesity)){
            $query['bool']['must'][] = ['term' => ['obesity' => $obesity == 1]];
        }
        if(!empty($hypertension)){
            $query['bool']['must'][] = ['term' => ['hypertension' => $hypertension == 1]];
        }
        if(!empty($defenses)){
            $query['bool']['must'][] = ['term' => ['defenses' => $defenses == 1]];
        }
        if(!empty($pregnant)){
            $query['bool']['must'][] = ['term' => ['pregnant' => $pregnant == 1]];
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

        return $query;
    }


    public static function statsCases($interval, Request $request){
        

        $query = ['bool' => ['must' => [ ['term' => ['firstSymptom' => true]],['range' => ['level' => ['gt' => 0]]] ] ] ];

        $querySecondary = self::createFiltersSecondaryCases($query, $request);
        $queryPrimary   = self::createFiltersPrincipalCases($query, $request);
        
        $realInterval = 'day';
        switch($interval){
            case 'hour' : $realInterval = 'hour';   break;
            case 'day'  : $realInterval = 'day';    break;
        }

        $body = [
            'size' => 0, 
            'aggs' => [
                'timeline' => [
                    'date_histogram' => [
                        'field'     => 'timestamp',
                        'interval'  => $realInterval,
                        "time_zone" => "Mexico/General"
                    ],
                ]
            ]
        ];

        $bodyPrimary =  $body;

        if(!empty($querySecondary['bool']['must'])){
            $body['query'] = $querySecondary;
        }
        if(!empty($queryPrimary['bool']['must'])){
            $bodyPrimary['query'] = $queryPrimary;
        }

        $tests = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);
        $testsPrimary = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $bodyPrimary
        ]);

        $totalData = [];
        if($testsPrimary && !empty($testsPrimary['aggregations']['timeline']['buckets'])){
            foreach($testsPrimary['aggregations']['timeline']['buckets'] as $bucket){
                $totalData[(int) $bucket['key_as_string']] = $bucket['doc_count'];
            }
        }


        $finalData = [];
        if($tests && !empty($tests['aggregations']['timeline']['buckets'])){
            foreach($tests['aggregations']['timeline']['buckets'] as $bucket){
                $data = [
                    'date' => (int) $bucket['key_as_string'],
                    'totalSuspect' => [
                        'value'         => $bucket['doc_count'],
                        'percentage'    => 100
                    ]
                ];

                if(isset($totalData[$data['date']])){
                    $total = $totalData[$data['date']];
                    if($total != $data['totalSuspect']['value']){
                        $data['totalSuspect']['percentage'] = round(($data['totalSuspect']['value'] * 100) / $total,1);
                    }
                }

                $finalData[] = $data;
            }
        }

        return $finalData;
    }

    public static function statsAge(Request $request){
        
        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');

        $query = ['bool' => ['must' => [ ['term' => ['firstSymptom' => true]],['range' => ['level' => ['gt' => 0]]] ] ] ];

        $querySecondary = self::createFiltersSecondaryCases($query, $request);
        $queryPrimary   = self::createFiltersPrincipalCases($query, $request);
        
        $body = [
            'size' => 0, 
            'aggs' => [
                'age' => [
                    'terms' => ['field' => 'age', 'size' => 150, 'order' => ['_term' => 'asc']],
                ]
            ]
        ];

        $bodyPrimary =  $body;

        if(!empty($querySecondary['bool']['must'])){
            $body['query'] = $querySecondary;
        }
        if(!empty($queryPrimary['bool']['must'])){
            $bodyPrimary['query'] = $queryPrimary;
        }

        $tests = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $body
        ]);

        $testsPrimary = Elastic::search([
            'index'     => 'tests',
            'client'    => ['ignore' => 404],
            'body'      => $bodyPrimary
        ]);

        $totalData = [];
        if($testsPrimary && !empty($testsPrimary['aggregations']['age']['buckets'])){
            foreach($testsPrimary['aggregations']['age']['buckets'] as $bucket){
                $totalData[(int) $bucket['key']] = $bucket['doc_count'];
            }
        }

        

        $finalData = [];
        if($tests && !empty($tests['aggregations']['age']['buckets'])){
            $total = $tests['hits']['total']['value'];

            foreach($tests['aggregations']['age']['buckets'] as $bucket){
                $data = [
                    'age' => $bucket['key'],
                    'totalSuspect' => [
                        'value'         => $bucket['doc_count'],
                        'percentage'    => 100
                    ]
                ];

                if(isset($totalData[$data['age']])){
                    $total = $totalData[$data['age']];
                    if($total != $data['totalSuspect']['value']){
                        $data['totalSuspect']['percentage'] = round(($data['totalSuspect']['value'] * 100) / $total,1);
                    }
                }

                $finalData[] = $data;
            }
        }

        return $finalData;
    }


    public static function statsOddsRatio($type, $stateID, Request $request){

        $query  = ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]] ] ] ];
        $query  = self::createFilters($query, $request);

        $startDate      = $request->input('startDate');
        $endDate        = $request->input('endDate');
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


        $body   = [
            'size' => 0, 
            'aggs' => [
                $type => [
                    'terms' => ['field' => $type],
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $profiles = Elastic::search([
            'index'             => 'profiles',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        $result = [
            'cases' => [
                'exposed'   => 0,
                'noExposed' => 0,
            ],
            'controls' => [
                'exposed'   => 0,
                'noExposed' => 0,
            ],
            'or' => 0,
            'ic' => [
                'low'   => 0,
                'high'  => 0,
            ],
        ];

        if($profiles && !empty($profiles['aggregations'][$type])){
            foreach($profiles['aggregations'][$type]['buckets'] as $bucket){
                if($bucket['key_as_string'] === 'true'){
                    $result['cases']['exposed'] = $bucket['doc_count'];
                }else if($bucket['key_as_string'] === 'false'){
                    $result['cases']['noExposed'] = $bucket['doc_count'];
                }
            }
        }

        $query  = ['bool' => ['must' => [ ] , 'must_not' => [ ['term' => ['stateID' => $stateID]] ] ]];
        $query  = self::createFilters($query, $request);
        $body   = [
            'size' => 0, 
            'aggs' => [
                $type => [
                    'terms' => ['field' => $type],
                ]
            ]
        ];

        if(!empty($query['bool']['must'])){
            $body['query'] = $query;
        }

        $profiles = Elastic::search([
            'index'             => 'profiles',
            'client'            => ['ignore' => 404],
            'track_total_hits'  => true,
            'body'              => $body
        ]);

        if($profiles && !empty($profiles['aggregations'][$type])){
            foreach($profiles['aggregations'][$type]['buckets'] as $bucket){
                if($bucket['key_as_string'] === 'true'){
                    $result['controls']['exposed'] = $bucket['doc_count'];
                }else if($bucket['key_as_string'] === 'false'){
                    $result['controls']['noExposed'] = $bucket['doc_count'];
                }
            }
        }

        
        $a = $result['cases']['exposed'];
        $b = $result['controls']['exposed'];
        $c = $result['cases']['noExposed'];
        $d = $result['controls']['noExposed'];

        if($a === 0 || $b === 0 || $c === 0 || $d === 0){
            return $result;
        }

        //OR = (a * d) / (b * c)  //odds ratio, razon de momios
        $or = ($a * $d) / ($b * $c);

        $result['or'] = round($or, 3);

        //Intervalo de confianza 95%
        // $ic = 1.96 * sqrt(($or * ( 1 - $or))/ ($result['cases']['exposed'] + $result['cases']['noExposed'] + $result['controls']['exposed'] + $result['controls']['noExposed']));

        $pcn = self::probCriticalNormal(95 / 100.0);
        $d1 = (1/$a) + (1/$b) + (1/$c) + (1/$d);
        $d2 = sqrt($d1);
        $d3 = ($a*$d)/($b*$c);
        $d4 = log($d3);
        $d5 = $d4-$pcn*$d2;
        $d6 = exp($d5);
        $result['ic']['low'] = round($d6*100)/100;


        $d1 = 1/$a+1/$b+1/$c+1/$d;
        $d2 = sqrt($d1);
        $d3 = ($a*$d)/($b*$c);
        $d4 = log($d3);
        $d5 = $d4+$pcn*$d2;
        $d6 = exp($d5);
        $result['ic']['high'] = round($d6*100)/100;
        

        return $result;
    }

    private static function probCriticalNormal($P){
        //      input p is confidence level convert it to
        //      cumulative probability before computing critical

        $PN = [0,    // ARRAY[1..5] OF REAL
                -0.322232431088  ,
                -1.0             ,
                -0.342242088547  ,
                -0.0204231210245 ,
                -0.453642210148E-4 ];

        $QN = [0,   //  ARRAY[1..5] OF REAL
                0.0993484626060 ,
                0.588581570495  ,
                0.531103462366  ,
                0.103537752850  ,
                0.38560700634E-2 ];

        $Pr = 0.5 - $P/2; // one side significance


        if ( $Pr <=1.0E-8){
            $HOLD = 6;
        }else {
                if ($Pr == 0.5){
                    $HOLD = 0;
                }else{
                    $Y = sqrt ( log( 1.0 / ($Pr * $Pr) ) );
                    $Real1 = $PN[5];  
                    $Real2 = $QN[5];

                    for ( $I=4; $I >= 1; $I--){
                        $Real1 = $Real1 * $Y + $PN[$I];
                        $Real2 = $Real2 * $Y + $QN[$I];
                    }

                    $HOLD = $Y + $Real1/$Real2;
                } // end of else pr = 0.5
            } // end of else Pr <= 1.0E-8

        return $HOLD;
    }  
}