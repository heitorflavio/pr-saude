<?php

namespace App\Providers;

use App\Contracts\GeradorTokenPulseira;
use App\Services\Pulseira\TokenPulseiraService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A implementacao e final de proposito: ninguem deve poder sobrescrever a
        // geracao de um identificador de seguranca por heranca. O contrato existe para
        // que E1 do UC-01 (rollback na falha do token) seja testavel mesmo assim.
        $this->app->singleton(GeradorTokenPulseira::class, TokenPulseiraService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
