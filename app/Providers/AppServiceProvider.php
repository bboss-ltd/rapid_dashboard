<?php

namespace App\Providers;

use App\Domains\Estimation\EstimatePointsResolver;
use App\Domains\Wallboard\Events\WallboardSynced;
use App\Domains\Wallboard\Listeners\LogWallboardSync;
use App\Support\ReleaseInfo;
use App\Support\Revision;
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
        if (!config('app.revision')) {
            config(['app.revision' => Revision::current()]);
        }
        if (!config('app.released_at')) {
            config(['app.released_at' => ReleaseInfo::releasedAt()?->toIso8601String()]);
        }

        Event::listen(WallboardSynced::class, LogWallboardSync::class);

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            Log::info('artisan.command.starting', [
                'command' => $event->command,
                'input' => $event->input?->getArguments(),
                'options' => $event->input?->getOptions(),
            ]);
        });
    }
}
