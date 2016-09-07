<?php

namespace Lequocnam\Orient;

use Illuminate\Support\ServiceProvider;

class OrientServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['db']->extend('orientdb', function ($config) {
            return new Connection($config);
        });
    }
}
