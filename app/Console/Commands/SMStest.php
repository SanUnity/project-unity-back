<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SMS;

class SMStest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {phone}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS';

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
    public function handle()
    {
        $phone= $this->argument('phone');

        SMS::send($phone, 'Mensaje de prueba con texto desde back');
        $this->info('Send custom');

        return true;
    }
}
