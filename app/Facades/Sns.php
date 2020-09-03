<?php
namespace App\Facades;
 
use Illuminate\Support\Facades\Facade as Facade; 
 
class Sns extends Facade { 
  protected static function getFacadeAccessor()
  {
    return 'Aws\Sns\SnsClient';
  }
}