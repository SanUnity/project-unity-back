<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class DecryptText extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decrypt:text {text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $text = $this->argument('text');
        try{
            $value = Crypt::decryptString($text);
            $this->info($value);
            return;
        }catch(\Exception $e){
            //bad encryption
        }
        $this->error('invalid text');
    }
}
