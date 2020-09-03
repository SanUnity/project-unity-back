<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class Hospitals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hospitals:create {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create hospitals set from file';

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
        $file     = $this->argument('file');
        set_time_limit(0);
        $gestor = @fopen($file, "r");
        if ($gestor) {
            while (($búfer = fgets($gestor, 4096)) !== false) {
                $aux    = explode(";", trim($búfer));
                $data   = [
                    'name'          => $aux[0],
                    'timestamp'     => time(),
                    'testingService'=> true,
                    'stateID'       => $aux[1],
                    'municipalityID'=> $aux[2],
                    'suburbID'      => $aux[3],
                ];

                $aux = Elastic::index(['index' => 'hospitals', 'body' => $data, 'refresh' => "false"]);

                $totalCapacity      = rand(50,100);
                $occupiedCapacity   = rand(50,100);

                for($i = 0;$i<4;$i++){
                    $totalTest      = rand(200,600);
                    $positiveTest   = ceil($totalTest * 0.15);
                    $negativeTest   = $totalTest - $positiveTest;
                    Elastic::index(['index' => 'hospitals_tests', 'body' => [
                        'hospitalID'        => $aux['_id'],
                        'timestamp'         => time() - (60*60*24*$i),
                        'testingService'    => true,
                        'totalCapacity'     => $totalCapacity,
                        'occupiedCapacity'  => $occupiedCapacity,
                        'stateID'           => $data['stateID'],
                        'municipalityID'    => $data['municipalityID'],
                        'suburbID'          => $data['suburbID'],
                        'totalTest'         => $totalTest,
                        'positiveTest'      => $positiveTest,
                        'negativeTest'      => $negativeTest,
                        'search'            => 5,
                    ], 'refresh' => "false"]);
                }
            }
            if (!feof($gestor)) {
                $this->error("fallo inesperado de fgets()");
            }
            fclose($gestor);
        }

        $this->info('Hospitals created');
    }
}
