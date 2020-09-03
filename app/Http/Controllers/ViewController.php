<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ViewController extends Controller{

    public function setPassword($hash, Request $request){
        
        return view('setPassword', ['hash' => $hash]);
    }

    public function exitRequests($hash, Request $request){
        
        return view('exitRequests', ['hash' => $hash]);
    }
}
