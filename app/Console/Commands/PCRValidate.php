<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class PCRValidate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pcr:validate {pcrID} {resultTest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate pcr by pcrID';

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
        $pcrID      = $this->argument('pcrID');
        $resultTest = (int) $this->argument('resultTest');

        Elastic::update(['index' => 'pcr_info', 'id' => $pcrID, 'body' => ['doc' => [
            'verified'      => true,
            'resultTest'    => $resultTest,
        ]],'refresh' => "false"]);
    }
}
