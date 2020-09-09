<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Push extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:test {arn}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tesh send push';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){
        $arn = $this->argument('arn');

        $response = \App\Push::sendToUser([$arn], 'Test push' . config('app.name'), 'Mensaje de prueba');
        if(!$response){
            $this->error('Fail send push');
            return false;
        }else{
            $this->info('Success send push');
            return true;
        }
    }
}