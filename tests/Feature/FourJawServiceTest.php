<?php

use App\Services\FourJaw\FourJawService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.fourjaw.base_url' => 'https://fourjaw.test/api/v1',
        'services.fourjaw.token' => 'test-api-key',
        'fourjaw.machines' => [
            'parent-1' => [
                ['id' => 'machine-1', 'display_name' => 'Config Machine 1'],
                ['id' => 'machine-2', 'display_name' => 'Config Machine 2'],
            ],
        ],
    ]);
});

test('current statuses are fetched with the api key and mapped from the v1 response', function () {
    Carbon::setTestNow('2026-10-23T12:00:00Z');

    Http::fake([
        'fourjaw.test/api/v1/status-periods/current' => Http::response([
            'items' => [
                [
                    'machine_id' => 'machine-1',
                    'machine_name' => 'Amada EM2510',
                    'has_data' => true,
                    'status' => [
                        'classification' => 'DOWNTIME',
                        'downtime_reason_name' => 'Planned Maintenance',
                        'start_timestamp' => '2026-10-23T11:15:00Z',
                    ],
                ],
            ],
            'page' => 1,
            'page_size' => 0,
            'total_items' => 1,
            'total_pages' => 1,
        ]),
    ]);

    $statuses = app(FourJawService::class)->getCurrentStatuses();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-api-key')
        && $request->data() === ['asset_ids' => ['machine-1', 'machine-2'], 'page_size' => 0]);

    expect($statuses)->toBe([
        [
            'id' => 'machine-1',
            'name' => 'Amada EM2510',
            'status_classification' => 'DOWNTIME',
            'status' => 'maintenance',
            'duration_minutes' => 45.0,
            'has_data' => true,
        ],
        [
            'id' => 'machine-2',
            'name' => 'Config Machine 2',
            'status_classification' => 'UNKNOWN',
            'status' => 'unknown',
            'duration_minutes' => null,
            'has_data' => false,
        ],
    ]);
});

test('utilisation summary posts an exact range for the given assets', function () {
    Http::fake([
        'fourjaw.test/api/v1/uptime/absolute/by-asset' => Http::response([
            'meta' => ['total_uptime_hours' => 10, 'total_uptime_percentage' => 42.5],
            'items' => [],
        ]),
    ]);

    $summary = app(FourJawService::class)->getUtilisationSummary(
        Carbon::parse('2026-10-20T00:00:00Z'),
        Carbon::parse('2026-10-23T00:00:00Z'),
        ['machine-1', 'machine-2'],
    );

    Http::assertSent(fn (Request $request) => $request->data() === [
        'start_timestamp' => '2026-10-20T00:00:00+00:00',
        'end_timestamp' => '2026-10-23T00:00:00+00:00',
        'asset_ids' => ['machine-1', 'machine-2'],
        'shifts' => 'on_shift',
        'exact_range' => true,
    ]);

    expect($summary['meta']['total_uptime_percentage'])->toBe(42.5);
});

test('client errors are not retried', function () {
    Http::fake([
        '*' => Http::response(['title' => 'Unauthorized'], 401),
    ]);

    expect(fn () => app(FourJawService::class)->getCurrentStatuses())
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});
