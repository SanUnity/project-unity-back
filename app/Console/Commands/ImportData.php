<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class ImportData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:postalCode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import postal codes from file to elasticsearch';

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
        set_time_limit(0);

        $file       = base_path('resources/extra/postal_codes.txt');
        $gestor     = @fopen($file, "r");

        $states = [
            'Aguascalientes' => 2,
            'Baja California' => 3,
            'Baja California Sur' => 4,
            'Campeche' => 5,
            'Ciudad de México' => 1,
            'Chiapas' => 8,
            'Chihuahua' => 9,
            'Coahuila de Zaragoza' => 6,
            'Colima' => 7,
            'Durango' => 10,
            'México' => 15,
            'Guanajuato' => 11,
            'Guerrero' => 12,
            'Hidalgo' => 13,
            'Jalisco' => 14,
            'Michoacán de Ocampo' => 16,
            'Morelos' => 17,
            'Nayarit' => 18,
            'Nuevo León' => 19,
            'Oaxaca' => 20,
            'Puebla' => 21,
            'Querétaro' => 22,
            'Quintana Roo' => 23,
            'San Luis Potosí' => 24,
            'Sinaloa' => 25,
            'Sonora' => 26,
            'Tabasco' => 27,
            'Tamaulipas' => 28,
            'Tlaxcala' => 29,
            'Veracruz de Ignacio de la Llave' => 30,
            'Yucatán' => 31,
            'Zacatecas' => 32,
        ];

        foreach($states as $state => $stateID){
            $aux = Elastic::index(['index' => 'states', 'id' => $stateID, 'body' => [
                'id' => $stateID,
                'name' => $state,
            ], 'refresh' => "false"]);
        }

        if ($gestor) {
            $suburbs        = [];
            $municipalities = [];
            $postalCodes    = [];
            $idSuburb       = 1;
            $idMunicipality = 1;

            while (($búfer = fgets($gestor, 4096)) !== false) {
                $aux    = explode("|", trim($búfer));

                $postalCode     = $aux[0];
                $suburb         = mb_convert_encoding($aux[1],'UTF-8', 'ISO-8859-3');
                $municipality   = mb_convert_encoding($aux[3],'UTF-8', 'ISO-8859-3');
                $state          = mb_convert_encoding($aux[4],'UTF-8', 'ISO-8859-3');

                if(!isset($states[$state])){
                    $this->error('State not exist => ' . $state);
                    return false;
                }
                $stateID = $states[$state];

                if(!isset($municipalities[$stateID])){
                    $municipalities[$stateID] = [];
                }

                if(!isset($municipalities[$stateID][$municipality])){
                    $municipalities[$stateID][$municipality] = $idMunicipality;
                    $aux = Elastic::index([
                        'index' => 'municipalities', 
                        'id' => $idMunicipality,
                        'body' => [
                            'id'        => $idMunicipality,
                            'name'      => $municipality,
                            'stateID'   => $stateID,
                        ], 'refresh' => "false"
                    ]);
                    $idMunicipality++;
                }

                $municipalityID = $municipalities[$stateID][$municipality];

                if(!isset($suburbs[$municipalityID])){
                    $suburbs[$municipalityID] = [];
                }

                if(!isset($suburbs[$municipalityID][$suburb])){
                    $suburbs[$municipalityID][$suburb] = $idSuburb;
                    $aux = Elastic::index([
                        'index' => 'suburbs', 
                        'id' => $idSuburb,
                        'body' => [
                            'id'                => $idSuburb,
                            'name'              => $suburb,
                            'municipalityID'    => $municipalityID,
                        ], 
                        'refresh' => "false"
                    ]);
                    $idSuburb++;
                }
                $suburbID = $suburbs[$municipalityID][$suburb];

                if(!isset($postalCodes["{$postalCode}_{$stateID}_{$municipalityID}_{$suburbID}"])){
                    $postalCodes["{$postalCode}_{$stateID}_{$municipalityID}_{$suburbID}"] = true;
                    Elastic::index(['index' => 'postal_codes', 'body' => [
                        'country'                   => 'MX',
                        'postalCode'                => $postalCode,
                        'stateID'                   => $stateID,
                        'municipalityID'            => $municipalityID,
                        'suburbID'                  => $suburbID,
                    ], 'refresh' => "false"]);
                }


            }
            if (!feof($gestor)) {
                $this->error("fallo inesperado de fgets()");
            }
            fclose($gestor);
        }

        $this->info('Postal Codes created');
        return true;
    }
}
