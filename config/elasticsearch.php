<?php
 
 return [
   'hosts' => env('ELASTICSEARCH_HOSTS'),
   'retries' => env('ELASTICSEARCH_RETRIES', 6),
 ];