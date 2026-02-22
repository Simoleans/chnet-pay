<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Http::macro('withApiHeadersBDV', function () {
            return Http::withHeaders([
                'x-api-key'    => config('app.bdv.api_key'),
                'Content-Type' => 'application/json',
            ]);
        });
    }
}
