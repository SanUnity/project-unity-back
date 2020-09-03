<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic;

class ElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:create {--clear= : all or name of index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create elasticsearch index';

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
        $clear = $this->option('clear');

        if(!empty($clear) && config('app.env') === 'production'){
            if(!$this->confirm('Are you sure you want to delete the data?')) {
                $clear = null;
            }
        }
        
        if(!empty($clear)){
            if(($clear == 'all' || $clear == 'users') && Elastic::indices()->exists(['index' => 'users'])){
                Elastic::indices()->delete(['index' => 'users']);
                $this->line('users removed');
            }
            if(($clear == 'all' || $clear == 'tests') && Elastic::indices()->exists(['index' => 'tests'])){
                Elastic::indices()->delete(['index' => 'tests']);
                $this->line('tests removed');
            }
            if(($clear == 'all' || $clear == 'suburbs') && Elastic::indices()->exists(['index' => 'suburbs'])){
                Elastic::indices()->delete(['index' => 'suburbs']);
                $this->line('suburbs removed');
            }
            if(($clear == 'all' || $clear == 'states') && Elastic::indices()->exists(['index' => 'states'])){
                Elastic::indices()->delete(['index' => 'states']);
                $this->line('states removed');
            }
            if(($clear == 'all' || $clear == 'profiles') && Elastic::indices()->exists(['index' => 'profiles'])){
                Elastic::indices()->delete(['index' => 'profiles']);
                $this->line('profiles removed');
            }
            if(($clear == 'all' || $clear == 'postal_codes') && Elastic::indices()->exists(['index' => 'postal_codes'])){
                Elastic::indices()->delete(['index' => 'postal_codes']);
                $this->line('postal_codes removed');
            }
            if(($clear == 'all' || $clear == 'otps') && Elastic::indices()->exists(['index' => 'otps'])){
                Elastic::indices()->delete(['index' => 'otps']);
                $this->line('otps removed');
            }
            if(($clear == 'all' || $clear == 'municipalities') && Elastic::indices()->exists(['index' => 'municipalities'])){
                Elastic::indices()->delete(['index' => 'municipalities']);
                $this->line('municipalities removed');
            }
            if(($clear == 'all' || $clear == 'hospitals') && Elastic::indices()->exists(['index' => 'hospitals'])){
                Elastic::indices()->delete(['index' => 'hospitals']);
                $this->line('hospitals removed');
            }
            if(($clear == 'all' || $clear == 'hospitals_tests') && Elastic::indices()->exists(['index' => 'hospitals_tests'])){
                Elastic::indices()->delete(['index' => 'hospitals_tests']);
                $this->line('hospitals_tests removed');
            }
            if(($clear == 'all' || $clear == 'admins') && Elastic::indices()->exists(['index' => 'admins'])){
                Elastic::indices()->delete(['index' => 'admins']);
                $this->line('admins removed');
            }
            if(($clear == 'all' || $clear == 'messages') && Elastic::indices()->exists(['index' => 'messages'])){
                Elastic::indices()->delete(['index' => 'messages']);
                $this->line('messages removed');
            }
            if(($clear == 'all' || $clear == 'exit_requests') && Elastic::indices()->exists(['index' => 'exit_requests'])){
                Elastic::indices()->delete(['index' => 'exit_requests']);
                $this->line('exit_requests removed');
            }
            if(($clear == 'all' || $clear == 'bluetraces') && Elastic::indices()->exists(['index' => 'bluetraces'])){
                Elastic::indices()->delete(['index' => 'bluetraces']);
                $this->line('bluetraces removed');
            }
            if(($clear == 'all' || $clear == 'hospitals_publics') && Elastic::indices()->exists(['index' => 'hospitals_publics'])){
                Elastic::indices()->delete(['index' => 'hospitals_publics']);
                $this->line('hospitals_publics removed');
            }
            if(($clear == 'all' || $clear == 'logs') && Elastic::indices()->exists(['index' => 'logs'])){
                Elastic::indices()->delete(['index' => 'logs']);
                $this->line('logs removed');
            }
            if(($clear == 'all' || $clear == 'states_info') && Elastic::indices()->exists(['index' => 'states_info'])){
                Elastic::indices()->delete(['index' => 'states_info']);
                $this->line('states_info removed');
            }
            if(($clear == 'all' || $clear == 'municipalities_info') && Elastic::indices()->exists(['index' => 'municipalities_info'])){
                Elastic::indices()->delete(['index' => 'municipalities_info']);
                $this->line('municipalities_info removed');
            }
            if(($clear == 'all' || $clear == 'logs_error') && Elastic::indices()->exists(['index' => 'logs_error'])){
                Elastic::indices()->delete(['index' => 'logs_error']);
                $this->line('logs_error removed');
            }
            if(($clear == 'all' || $clear == 'cases') && Elastic::indices()->exists(['index' => 'cases'])){
                Elastic::indices()->delete(['index' => 'cases']);
                $this->line('cases removed');
            }
            if(($clear == 'all' || $clear == 'dp3t') && Elastic::indices()->exists(['index' => 'dp3t'])){
                Elastic::indices()->delete(['index' => 'dp3t']);
                $this->line('dp3t removed');
            }
            if(($clear == 'all' || $clear == 'pcr_info') && Elastic::indices()->exists(['index' => 'pcr_info'])){
                Elastic::indices()->delete(['index' => 'pcr_info']);
                $this->line('pcr_info removed');
            }
            if(($clear == 'all' || $clear == 'dp3t_config') && Elastic::indices()->exists(['index' => 'dp3t_config'])){
                Elastic::indices()->delete(['index' => 'dp3t_config']);
                $this->line('dp3t_config removed');
            }
            if(($clear == 'all' || $clear == 'recommendations') && Elastic::indices()->exists(['index' => 'recommendations'])){
                Elastic::indices()->delete(['index' => 'recommendations']);
                $this->line('recommendations removed');
            }
            if(($clear == 'all' || $clear == 'contact_tracing_manual') && Elastic::indices()->exists(['index' => 'contact_tracing_manual'])){
                Elastic::indices()->delete(['index' => 'contact_tracing_manual']);
                $this->line('contact_tracing_manual removed');
            }
            if(($clear == 'all' || $clear == 'pcr_results') && Elastic::indices()->exists(['index' => 'pcr_results'])){
                Elastic::indices()->delete(['index' => 'pcr_results']);
                $this->line('pcr_results removed');
            }
            if(($clear == 'all' || $clear == 'jobs') && Elastic::indices()->exists(['index' => 'jobs'])){
                Elastic::indices()->delete(['index' => 'jobs']);
                $this->line('jobs removed');
            }
        }
        if(!Elastic::indices()->exists(['index' => 'users'])){
            Elastic::indices()->create([
                'index' => 'users',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/users.json'))),
            ]);
            $this->info('users created');
        }
        if(!Elastic::indices()->exists(['index' => 'tests'])){
            Elastic::indices()->create([
                'index' => 'tests',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/tests.json'))),
            ]);
            $this->info('tests created');
        }
        if(!Elastic::indices()->exists(['index' => 'suburbs'])){
            Elastic::indices()->create([
                'index' => 'suburbs',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/suburbs.json'))),
            ]);
            $this->info('suburbs created');
        }
        if(!Elastic::indices()->exists(['index' => 'states'])){
            Elastic::indices()->create([
                'index' => 'states',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/states.json'))),
            ]);
            $this->info('states created');
        }
        if(!Elastic::indices()->exists(['index' => 'profiles'])){
            Elastic::indices()->create([
                'index' => 'profiles',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/profiles.json'))),
            ]);
            $this->info('profiles created');
        }
        if(!Elastic::indices()->exists(['index' => 'postal_codes'])){
            Elastic::indices()->create([
                'index' => 'postal_codes',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/postalCodes.json'))),
            ]);
            $this->info('postal_codes created');
        }
        if(!Elastic::indices()->exists(['index' => 'otps'])){
            Elastic::indices()->create([
                'index' => 'otps',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/otps.json'))),
            ]);
            $this->info('otps created');
        }
        if(!Elastic::indices()->exists(['index' => 'municipalities'])){
            Elastic::indices()->create([
                'index' => 'municipalities',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/municipalities.json'))),
            ]);
            $this->info('municipalities created');
        }
        if(!Elastic::indices()->exists(['index' => 'hospitals'])){
            Elastic::indices()->create([
                'index' => 'hospitals',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/hospitals.json'))),
            ]);
            $this->info('hospitals created');
        }
        if(!Elastic::indices()->exists(['index' => 'hospitals_tests'])){
            Elastic::indices()->create([
                'index' => 'hospitals_tests',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/hospitalsTests.json'))),
            ]);
            $this->info('hospitals_tests created');
        }
        if(!Elastic::indices()->exists(['index' => 'admins'])){
            Elastic::indices()->create([
                'index' => 'admins',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/admins.json'))),
            ]);
            $this->info('admins created');
        }
        if(!Elastic::indices()->exists(['index' => 'messages'])){
            Elastic::indices()->create([
                'index' => 'messages',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/messages.json'))),
            ]);
            $this->info('messages created');
        }
        if(!Elastic::indices()->exists(['index' => 'exit_requests'])){
            Elastic::indices()->create([
                'index' => 'exit_requests',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/exitRequest.json'))),
            ]);
            $this->info('exit_requests created');
        }
        if(!Elastic::indices()->exists(['index' => 'bluetraces'])){
            Elastic::indices()->create([
                'index' => 'bluetraces',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/bluetraces.json'))),
            ]);
            $this->info('bluetraces created');
        }
        if(!Elastic::indices()->exists(['index' => 'hospitals_publics'])){
            Elastic::indices()->create([
                'index' => 'hospitals_publics',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/hospitalsPublics.json'))),
            ]);
            $this->info('hospitals_publics created');
        }
        if(!Elastic::indices()->exists(['index' => 'logs'])){
            Elastic::indices()->create([
                'index' => 'logs',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/logs.json'))),
            ]);
            $this->info('logs created');
        }
        if(!Elastic::indices()->exists(['index' => 'states_info'])){
            Elastic::indices()->create([
                'index' => 'states_info',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/statesInfo.json'))),
            ]);
            $this->info('states_info created');
        }
        if(!Elastic::indices()->exists(['index' => 'municipalities_info'])){
            Elastic::indices()->create([
                'index' => 'municipalities_info',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/municipalitiesInfo.json'))),
            ]);
            $this->info('municipalities_info created');
        }
        if(!Elastic::indices()->exists(['index' => 'logs_error'])){
            Elastic::indices()->create([
                'index' => 'logs_error',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/logsError.json'))),
            ]);
            $this->info('logs_error created');
        }
        if(!Elastic::indices()->exists(['index' => 'cases'])){
            Elastic::indices()->create([
                'index' => 'cases',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/cases.json'))),
            ]);
            $this->info('cases created');
        }
        if(!Elastic::indices()->exists(['index' => 'dp3t'])){
            Elastic::indices()->create([
                'index' => 'dp3t',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/dp3t.json'))),
            ]);
            $this->info('dp3t created');
        }
        if(!Elastic::indices()->exists(['index' => 'pcr_info'])){
            Elastic::indices()->create([
                'index' => 'pcr_info',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/pcrInfo.json'))),
            ]);
            $this->info('pcr_info created');
        }
        if(!Elastic::indices()->exists(['index' => 'dp3t_config'])){
            Elastic::indices()->create([
                'index' => 'dp3t_config',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/dp3tconfig.json'))),
            ]);
            $this->info('dp3t_config created');
        }
        if(!Elastic::indices()->exists(['index' => 'recommendations'])){
            Elastic::indices()->create([
                'index' => 'recommendations',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/recommendations.json'))),
            ]);
            $this->info('recommendations created');
        }
        if(!Elastic::indices()->exists(['index' => 'contact_tracing_manual'])){
            Elastic::indices()->create([
                'index' => 'contact_tracing_manual',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/contactTracingManual.json'))),
            ]);
            $this->info('contact_tracing_manual created');
        }
        if(!Elastic::indices()->exists(['index' => 'pcr_results'])){
            Elastic::indices()->create([
                'index' => 'pcr_results',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/pcrResults.json'))),
            ]);
            $this->info('pcr_results created');
        }
        if(!Elastic::indices()->exists(['index' => 'jobs'])){
            Elastic::indices()->create([
                'index' => 'jobs',
                'body' => json_decode(file_get_contents(base_path('resources/elasticsearch/jobs.json'))),
            ]);
            $this->info('jobs created');
        }
        
    }
}
