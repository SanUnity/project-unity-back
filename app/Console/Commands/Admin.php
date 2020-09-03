<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class Admin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {name} {email} {password} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create admin';

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
        $name       = $this->argument('name');
        $email      = $this->argument('email');
        $password   = $this->argument('password');
        $role       = $this->argument('role');

        $emailHash  = hash_pbkdf2('sha256', $email, config('app.ENCRYPTION_SALT'), 1, 0);

        $adminData  = Elastic::get(['index' => 'admins', 'id' => $emailHash, 'client' => ['ignore' => 404]]);
        if($adminData && $adminData['found']){
            $this->error('Admin exist');
            return;
        }

        $password   = hash_pbkdf2('sha256', $password, config('app.ENCRYPTION_SALT'), 50000, 0);

        Elastic::index(['index' => 'admins', 'id' => $emailHash, 'body' => [
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'role'      => (int) $role
        ], 'refresh' => "false"]);

        $this->info('Admin created');
    }
}
