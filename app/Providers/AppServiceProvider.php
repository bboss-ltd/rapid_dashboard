<?php

namespace App\Providers;

use App\Domains\Estimation\EstimatePointsResolver;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EstimatePointsResolver::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            Log::info('artisan.command.starting', [
                'command' => $event->command,
                'input' => $event->input?->getArguments(),
                'options' => $event->input?->getOptions(),
            ]);
        });
    }
}
