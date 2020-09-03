<?php

namespace App\Http\Middleware;

use Closure;
use Elastic;
use Log;

class LogAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }


    public function terminate($request, $response){
        global $ADMINID;

        try{
            $route  = $request->route();
            $data   = $request->except('password');
            $data   = array_merge($data, $route->parameters);
            $ip     = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $request->ip();
            $logData = [    
                'adminID'       => !empty($ADMINID) ? $ADMINID : 'anonymous',
                'uri'           => $route->uri,
                'method'        => $request->method(),
                'status'        => $response->getStatusCode(),
                'timestamp'     => time(),
                'time'          => (LARAVEL_START - microtime(true)),
                'ip'            => $ip,
                'data'          => $data,
            ];

            Elastic::index(['index' => 'logs', 'body' => $logData, 'refresh' => "false"]);
        }catch(\Exception $e){
            Log::error('error save log admin', ['exception' => $e]);
        }
    }
}
