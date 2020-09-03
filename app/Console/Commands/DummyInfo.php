<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use App\Http\Helpers\User;
use Elastic;

class DummyInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dummy:create {users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create dummy info';

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

        $totalUsers     = $this->argument('users');
        $userController = new UserController();

        $datas = [
            ['name' => 'Pepito', 'imss' => true, 'postalCode' => '01857', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 1, 'municipality' => 'Álvaro Obregón', 'suburbID' => 214, 'suburb' => 'Lomas de Chamontoya',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
            ['name' => 'Juanito', 'postalCode' => '03010', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 3, 'municipality' => 'Benito Juárez', 'suburbID' => 307, 'suburb' => 'Atenor Salas',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
            ['name' => 'Jorguito', 'postalCode' => '04660', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 4, 'municipality' => 'Coyoacán', 'suburbID' => 411, 'suburb' => 'Joyas del Pedregal',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
            ['name' => 'Jaimito', 'postalCode' => '11000', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 11, 'municipality' => 'Miguel Hidalgo', 'suburbID' => 947, 'suburb' => 'Lomas de Chapultepec IV Sección',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
            ['name' => 'Tio gilito', 'postalCode' => '09696', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 9, 'municipality' => 'Iztapalapa', 'suburbID' => 830, 'suburb' => 'Miravalles',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
            ['name' => 'Jhon Doe', 'postalCode' => '13060', 'stateID' => 1, 'state' => 'Ciudad de México', 'municipalityID' => 13, 'municipality' => 'Tláhuac', 'suburbID' => 936, 'suburb' => 'La Guadalupe',
                'symptoms' => true, 'symptomWeek' => false, 'diabetes' => true, 'obesity' => false, 'hypertension' => true, 'defenses' => false, 'breathing' => true, 'pregnant' => false],
        ];

        for($j = 0;$j<$totalUsers;$j++){
            $iterations     = rand(1,30);
            $rand           = rand(0,5);
            $data           = $datas[$rand];
            $data['age']    = rand(15,99);
            $data['gender'] = rand(1,2) == 1 ? 'male' : 'female';
            $phone          = '+34' . rand(111111111,999999999);

            if($data['gender'] === 'female'){
                $data['pregnant'] = rand(1,2) == 1;
            }

            $phoneHash  = hash_pbkdf2('sha256', $phone, config('app.ENCRYPTION_SALT'), 30000, 0);
            $dataUser   = $userController->createUser($phone, $phoneHash);
            if(!empty($dataUser['profiles'])){
                $profileID  = $dataUser['profiles'][0]['id'];
            }else{
                $request = new Request([], [], [], $cookies = [], $files = [], $server = [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $dataUser['jwt'],
                    'CONTENT_TYPE' => 'application/json',
                    'REQUEST_METHOD' => 'POST',
                ], json_encode($data));
                $aux        = User::createEditProfile($request, $dataUser['id']);
                $profileID  = $aux['id'];
            }
            

            $now = (time() + (60*60*24*7)) - (60*60*24*$iterations);
            for($i = 0;$i < $iterations;$i++){
                $requestAux = new Request([], [], [], $cookies = [], $files = [], $server = [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $dataUser['jwt'],
                    'CONTENT_TYPE' => 'application/json',
                    'REQUEST_METHOD' => 'POST',
                ], json_encode($data));


                $profile = Elastic::get(['index' => 'profiles', 'id' => $profileID, 'client' => ['ignore' => 404]]);

                $data = User::createDataTest($profileID, $request, $now, $profile);

                $now+= (60*60*24);
            }
        }
    }
}
