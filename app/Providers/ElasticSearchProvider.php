<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ElasticSearchProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Elasticsearch\Client', function($app) {
            $hosts      = explode(',', $app['config']['elasticsearch.hosts']);
            $elastic    = \Elasticsearch\ClientBuilder::create()->setHosts($hosts)->setRetries($app['config']['elasticsearch.retries']);

            if($app['config']['app.debug']){
                $logger = new Logger('Elastic');
                $logger->pushHandler(new StreamHandler(base_path('storage/logs/elasticsearch.log'), Logger::INFO));
                $elastic->setLogger($logger);
            }

            return $elastic->build();
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
