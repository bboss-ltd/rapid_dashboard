<?php

namespace App\Providers;

use App\Domains\Estimation\EstimateConverter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EstimateConverter::class, function () {
            return new EstimateConverter(config('estimation'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
