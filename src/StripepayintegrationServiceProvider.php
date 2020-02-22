<?php

namespace Devrahul\Stripepayintegration;

use Illuminate\Support\ServiceProvider;

Class StripepayintegrationServiceProvider extends ServiceProvider
{


    public function boot()
    {
        
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        
        $this->publishes([
            __DIR__.'/Http/Controllers/API/v1/' => app_path('Http/Controllers/API/v1/'),
        ]);
        $this->publishes([
            __DIR__.'/routes/' => app_path('routes/'),
        ]);
        $this->publishes([
            __DIR__.'/migrations' => database_path('migrations')
        ], 'migrations');

        

    }

    public function register()
    {
        
    }

}