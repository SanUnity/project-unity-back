<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;
use Illuminate\Encryption\Encrypter;

class ExportQRList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:qrlist {stateID} {file}';

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
    public function handle(){
        $stateID        = $this->argument('stateID');
        $file           = $this->argument('file');

        $gestor         = @fopen($file, "w");


        $hospitalsData = Elastic::search([
            'index'     => 'hospitals_publics',
            'client'    => ['ignore' => 404],
            '_source'   => true,
            'body'      => ['from' => 0,'size' => 10000,'query' => ['bool' => 
                [
                    'must' => [ 
                        ['term' => ['stateID' => $stateID]],
                        ['terms' => ['level' => [1,2,3]]],
                    ],
                    'must_not' => [ 
                        ['term' => ['status' => 'BAJA']],
                    ] 
                ]
             ]]
        ]);
        
        $cipher = new Encrypter(md5(config('app.QR_PASSWORD')), 'AES-256-CBC');

        if($hospitalsData && $hospitalsData['hits']['total']['value']){
            foreach($hospitalsData['hits']['hits'] as $hospital){
                $aux = [
                    'clues'         => $hospital['_source']['clues'],
                    'hospital'      => $hospital['_source']['name'],
                    'address'       => $hospital['_source']['address'],
                    'level'         => $hospital['_source']['level'],
                    'suburb'        => $hospital['_source']['suburb'],
                    'covid'         => $hospital['_source']['covid'],
                ];
                if(!empty($hospital['_source']['typeAddress'])){
                    $aux['address'] = $hospital['_source']['typeAddress'] . ' ' . $aux['address'];
                }
                if(!empty($hospital['_source']['address_num'])){
                    $aux['address'] = $aux['address'] . ',' . $hospital['_source']['address_num'];
                }

                $aux['cluesCodeQR'] = $cipher->encryptString($aux['clues']);

                fputcsv($gestor, [
                    $aux['clues'],
                    $aux['cluesCodeQR'],
                    $aux['hospital'],
                    $aux['address'],
                    $aux['level'],
                    $aux['suburb'],
                    $aux['covid'],
                ],'|');

            }
        }

        fclose($gestor);
    }
}
