<?php

namespace App;

use Illuminate\Support\Facades\Queue;
use Log;

class Parse {
    
    public static function updateChannels($deviceId, $stateID, $municipalityID, $suburbID){
        $response = Queue::pushRaw(json_encode([
            'type'          => 'topics',
            'deviceId'      => $deviceId,
            'topics'        => [
                "state-{$stateID}",
                "municipality-{$municipalityID}",
                "suburb-{$suburbID}",
            ]
        ]));

        Log::debug("update channels ", [
            'deviceIds'         => $deviceId, 
            'stateID'           => $stateID, 
            'municipalityID'    => $municipalityID, 
            'suburbID'          => $suburbID,
            'response'          => $response
        ]);
    }
}