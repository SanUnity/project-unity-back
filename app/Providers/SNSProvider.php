<?php

namespace App\Providers;

use Aws\Sns\SnsClient; 

use Illuminate\Support\ServiceProvider;

class SNSProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Aws\Sns\SnsClient', function($app) {
            $SnSclient = new SnsClient([
                'region' => $app['config']['sns.region'],
                'credentials' => [
                    'key'    => $app['config']['sns.key'],
                    'secret' => $app['config']['sns.secret'],
                ],
                'version' => '2010-03-31'
            ]);

            return $SnSclient;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}