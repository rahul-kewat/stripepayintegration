<?php

namespace Devrahul\Stripepayintegration;

use Illuminate\Support\ServiceProvider;

Class StripepayintegrationServiceProvider extends ServiceProvider
{


    public function boot()
    {
        
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        
        $this->publishes([
            __DIR__.'/Http/Controllers/API/v1/' => app_path('Http/Controllers/API/v1/'),
        ]);
        $this->publishes([
            __DIR__.'/Http/Requests/' => app_path('Http/Requests/'),
        ]);
        $this->publishes([
            __DIR__.'/routes/' => app_path('Http/routes/'),
        ]);
       

        

    }

    public function register()
    {
        
    }

}