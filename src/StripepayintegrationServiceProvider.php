<?php

namespace Devrahul\Stripepayintegration;

use Illuminate\Support\ServiceProvider;

Class StripepayintegrationServiceProvider extends ServiceProvider
{


    public function boot()
    {
        
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        
        $this->publishes([
            __DIR__.'/Http/' => app_path('Http/'),
        ]);
        $this->publishes([
            __DIR__.'/routes/' => app_path('Http/routes/'),
        ]);
       

        

    }

    public function register()
    {
        
    }

}