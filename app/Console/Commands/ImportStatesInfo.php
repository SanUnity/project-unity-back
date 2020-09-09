<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class ImportStatesInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:statesInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import states from inegi.org.mx';

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
        $file     = base_path('resources/extra/AGEEML_20205181346358.csv');

        if(!file_exists($file)){
            $this->error("file not exists");
            return;
        }
        $gestor = false;
        try{
            $gestor = fopen($file, "r");
        }catch(\Exception $e){
            $this->error("Error open file", ['error' => $e]);
            return;
        }
        if(!$gestor){
            $this->error("Error open file");
            return;
        }

        $i                  = 0;
        $states             = [];
        $statesName         = [];
        $municipalitiesName = [];
        while (($dataAux = fgetcsv($gestor, 10000, ",")) !== FALSE) {
            $i++;
            if($i === 1 ) continue;

            // Cve_Ent,Nom_Ent,Nom_Abr,Cve_Mun,Nom_Mun,Cve_Cab,Nom_Cab,Pob_Total,Total De Viviendas Habitadas,Pob_Masculina,Pob_Femenina
            $dataAll = [
                'stateID'           => $dataAux[0],
                'state'             => $dataAux[1],
                'municipalityID'    => $dataAux[3],
                'municipalityIDCVE' => $dataAux[0] . $dataAux[3],
                'municipality'      => $dataAux[4],
                'population'        =>[
                    'total'     => (int) $dataAux[7],
                    'male'      => (int) $dataAux[9],
                    'female'    => (int) $dataAux[10],
                ], 
                'house'             => (int) $dataAux[8],
            ];

            if(!isset($states[$dataAll['stateID']])){
                $states[$dataAll['stateID']] = true;
                
                switch($dataAll['stateID']){
                    case '01'  : $postalStateID = 2;    break;
                    case '02'  : $postalStateID = 3;    break;
                    case '03'  : $postalStateID = 4;    break;
                    case '04'  : $postalStateID = 5;    break;
                    case '05'  : $postalStateID = 6;    break;
                    case '06'  : $postalStateID = 7;    break;
                    case '07'  : $postalStateID = 8;    break;
                    case '08'  : $postalStateID = 9;    break;
                    case '09'  : $postalStateID = 1;    break;
                    case '10'  : $postalStateID = 10;   break;
                    case '11'  : $postalStateID = 11;   break;
                    case '12'  : $postalStateID = 12;   break;
                    case '13'  : $postalStateID = 13;   break;
                    case '14'  : $postalStateID = 14;   break;
                    case '15'  : $postalStateID = 15;   break;
                    case '16'  : $postalStateID = 16;   break;
                    case '17'  : $postalStateID = 17;   break;
                    case '18'  : $postalStateID = 18;   break;
                    case '19'  : $postalStateID = 19;   break;
                    case '20'  : $postalStateID = 20;   break;
                    case '21'  : $postalStateID = 21;   break;
                    case '22'  : $postalStateID = 22;   break;
                    case '23'  : $postalStateID = 23;   break;
                    case '24'  : $postalStateID = 24;   break;
                    case '25'  : $postalStateID = 25;   break;
                    case '26'  : $postalStateID = 26;   break;
                    case '27'  : $postalStateID = 27;   break;
                    case '28'  : $postalStateID = 28;   break;
                    case '29'  : $postalStateID = 29;   break;
                    case '30'  : $postalStateID = 30;   break;
                    case '31'  : $postalStateID = 31;   break;
                    case '32'  : $postalStateID = 32;   break;
                }

                Elastic::index([
                    'index' => 'states_info', 
                    'id' => $dataAll['stateID'],
                    'body' => [
                        'id'        => $dataAll['stateID'],
                        'name'      => $dataAll['state'],
                        'stateID'   => $postalStateID,
                        'status'    => 3,
                        'cases'     => [
                            'confirm'       => 0,
                            'negative'      => 0,
                            'suspicious'    => 0,
                            'death'         => 0,
                        ]
                    ], 'refresh' => "false"
                ]);

                $statesName[$dataAll['state']] = $dataAll['stateID'];

                Elastic::update(['index' => 'states', 'id' => $postalStateID, 'body' => ['doc' => ['cveID' => $dataAll['stateID']]],'refresh' => "false"]);
            }

            if(!isset($municipalitiesName[$dataAll['stateID']])){
                $municipalitiesName[$dataAll['stateID']] = [];
            }
            $municipalitiesName[$dataAll['stateID']][$dataAll['municipality']] = $dataAll['municipalityIDCVE'];

            Elastic::index([
                'index' => 'municipalities_info', 
                'id'    => $dataAll['municipalityIDCVE'],
                'body' => [
                    'cveID'             => $dataAll['municipalityIDCVE'],
                    'id'                => $dataAll['municipalityID'],
                    'name'              => $dataAll['municipality'],
                    'population'        => $dataAll['population'],
                    'house'             => $dataAll['house'],
                    'stateIDCVE'        => $dataAll['stateID'],
                    'status'            => 3,
                    'cases'             => [
                        'confirm'       => 0,
                        'negative'      => 0,
                        'suspicious'    => 0,
                        'death'         => 0,
                    ]
                ], 'refresh' => "false"
            ]);
        }

        $file     = base_path('resources/extra/municipiosDeLaEsperanza.csv');

        if(!file_exists($file)){
            $this->error("file municipios not exists");
            return;
        }
        $gestor = false;
        try{
            $gestor = fopen($file, "r");
        }catch(\Exception $e){
            $this->error("Error open municipios file", ['error' => $e]);
            return;
        }
        if(!$gestor){
            $this->error("Error open municipios file");
            return;
        }

        while (($dataAux = fgetcsv($gestor, 10000, ",")) !== FALSE) {
            $dataAll = [
                'state'             => $dataAux[0],
                'municipality'      => $dataAux[1],
            ];

            switch($dataAll['state']){
                case 'Michoacán'    : $dataAll['state'] = 'Michoacán de Ocampo';                break;
                case 'Veracruz'     : $dataAll['state'] = 'Veracruz de Ignacio de la Llave';    break;
            }

            if(!isset($statesName[$dataAll['state']])){
                $this->error("states => '{$dataAll['state']}'");
                continue;
            }
            $stateID = $statesName[$dataAll['state']];

            if(!isset($municipalitiesName[$stateID][$dataAll['municipality']])){
                $this->error("municipality => '{$dataAll['municipality']}'");
                continue;
            }

            $municipalityID = $municipalitiesName[$stateID][$dataAll['municipality']];

            Elastic::update(['index' => 'municipalities_info', 'id' => $municipalityID, 'body' => ['doc' => ['status' => 0]],'refresh' => "false"]);
        }

        return true;
    }
}
