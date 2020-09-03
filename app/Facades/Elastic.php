<?php
namespace App\Facades;
 
use Illuminate\Support\Facades\Facade as Facade; 
 
class Elastic extends Facade { 
  protected static function getFacadeAccessor()
  {
    return 'Elasticsearch\Client';
  }
}