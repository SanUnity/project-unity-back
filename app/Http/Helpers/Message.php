<?php

namespace App\Http\Helpers;

use Illuminate\Http\Request;
use Elastic;
use App\Push;

class Message {

    static $title = 'Tienes un mensaje de ';

    private static function iteratorScroll($scroll, $message){
        $scrollsIDs = [];
        while (true) {
            $scrollsIDs[] = $scroll['_scroll_id'];

            if (isset($scroll['hits']['hits']) && isset($scroll['hits']['hits'][0])){
                $profiles = [];
                foreach($scroll['hits']['hits'] as $hit){
                    $profiles[] = [
                        'profileID' => $hit['_id'],
                    ];
                }
                self::saveMessage($profiles, $message);

                $scroll = Elastic::scroll([
                    "scroll" => "10m",
                    'body'  => ["scroll_id" => $scroll['_scroll_id']]
                ]);
            } else {
                break;
            }
        }

        Elastic::clearScroll(['body' => ['scroll_id' => $scrollsIDs]]);
    }

    public static function sendMessageState($stateID, $adminID, Request $request){
        $message    = $request->input('message');

        Push::sendToGroup('state-'.$stateID, config('app.PUSH_TITLE'), $message);

        $scroll     = Elastic::search([
            'index'     => 'profiles',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            "scroll"    => "10m",
            "size"      => 5000,
            'body'      => ['from' => 0,'size' => 0,'query' => ['bool' => ['must' => [ ['term' => ['stateID' => $stateID]] ] ] ]]
        ]);

        return self::iteratorScroll($scroll, [
            'message'   => $message,
            'type'      => 'state',
            'aux'       => $stateID,
            'adminID'   => $adminID,
        ]);
    }

    public static function sendMessageMunicipality($stateID, $municipalityID, $adminID, Request $request){
        $message    = $request->input('message');

        Push::sendToGroup('municipality-'.$municipalityID, config('app.PUSH_TITLE'), $message);

        $scroll     = Elastic::search([
            'index'     => 'profiles',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            "scroll"    => "10m",
            "size"      => 5000,
            'body'      => ['from' => 0,'size' => 0,'query' => ['bool' => ['must' => [ 
                ['term' => ['stateID'           => $stateID]],
                ['term' => ['municipalityID'    => $municipalityID]] 
            ] ] ]]
        ]);

        return self::iteratorScroll($scroll, [
            'message'   => $message,
            'type'      => 'municipalityID',
            'aux'       => $municipalityID,
            'adminID'   => $adminID,
        ]);
    }

    public static function sendMessageSuburb($stateID, $municipalityID, $suburbID, $adminID, Request $request){
        $message    = $request->input('message');

        Push::sendToGroup('surburb-'.$suburbID, config('app.PUSH_TITLE'), $message);

        $scroll     = Elastic::search([
            'index'     => 'profiles',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            "scroll"    => "10m",
            "size"      => 5000,
            'body'      => ['from' => 0,'size' => 0,'query' => ['bool' => ['must' => [ 
                ['term' => ['stateID'           => $stateID]],
                ['term' => ['municipalityID'    => $municipalityID]],
                ['term' => ['suburbID'          => $suburbID]],
            ] ] ]]
        ]);

        return self::iteratorScroll($scroll, [
            'message'   => $message,
            'type'      => 'suburb',
            'aux'       => $suburbID,
            'adminID'   => $adminID,
        ]);
    }

    public static function sendMessageUser($profileID, $adminID, Request $request){
        $message = $request->input('message');
        $profileData = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);
        if(!$profileData || !$profileData['found']){
            return response('Invalid profileID', 404);
        }

        $userData = Elastic::get(['index' => 'users', 'id' => $profileData['_source']['userID'], 'client' => ['ignore' => 404]]);
        if($userData && $userData['found']){
            Push::sendToUser($userData['_source']['devicesARN'], config('app.PUSH_TITLE'), $message);
    
            self::saveMessage([[
                'profileID' => $profileID,
            ]], [
                'message'   => $message,
                'type'      => 'personal',
                'aux'       => null,
                'adminID'   => $adminID,
            ]);
        }
    }

    public static function sendMessageAllUser($adminID, Request $request){
        $message    = $request->input('message');

        Push::sendToGroup('general', config('app.PUSH_TITLE'), $message);

        $scroll     = Elastic::search([
            'index'     => 'profiles',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            "scroll"    => "10m",
            "size"      => 5000,
            'body'      => ['from' => 0,'size' => 0]
        ]);

        return self::iteratorScroll($scroll, [
            'message'   => $message,
            'type'      => 'all',
            'aux'       => null,
            'adminID'   => $adminID,
        ]);
    }

    /**
     * $phones Array [['profileID' => 'profileID']]
     * $message Array ['message' => 'text to send', 'type' => 'personal|state|munucipality|suburb', 'aux' => 'id of type']
     */
    private static function saveMessage($phones, $message){

        $params     = ['body' => []];
        $timestamp  = time();

        foreach($phones as $phone){

            $params['body'][] = [
                'index' => [
                    '_index' => 'messages',
                ]
            ];
            $params['body'][] = [
                'profileID' => $phone['profileID'],
                'timestamp' => $timestamp,
                'message'   => $message['message'],
                'type'      => $message['type'],
                'aux'       => $message['aux'],
                'adminID'   => $message['adminID'],
            ];
        }

        try{
            if(!empty($params['body'])){
                Elastic::bulk($params);
            }
        }catch (Exception $e){

        }
    }

}