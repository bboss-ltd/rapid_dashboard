<?php

use App\Domains\Wallboard\Actions\BuildWallboardViewDataAction;
use App\Models\Sprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('utilisation ranges start at midnight in the factory timezone, not UTC', function () {
    config([
        'services.fourjaw.base_url' => 'https://fourjaw.test/api/v1',
        'wallboard.utilisation.timezone' => 'Europe/London',
        'wallboard.utilisation.summary_days' => 2,
    ]);

    Carbon::setTestNow('2026-10-23T00:30:00Z');

    Http::fake([
        'fourjaw.test/api/v1/status-periods/current' => Http::response(['items' => []]),
        'fourjaw.test/api/v1/uptime/absolute/by-asset' => Http::response(['meta' => [], 'items' => []]),
    ]);

    app(BuildWallboardViewDataAction::class)->run(Sprint::factory()->create(), []);

    $ranges = Http::recorded()
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), '/uptime/absolute/by-asset'))
        ->map(fn (Request $request) => [$request['start_timestamp'], $request['end_timestamp']])
        ->values()
        ->all();

    expect($ranges)->toBe([
        ['2026-10-21T00:00:00+01:00', '2026-10-22T23:59:59+01:00'],
        ['2026-10-23T00:00:00+01:00', '2026-10-23T01:30:00+01:00'],
    ]);
});
