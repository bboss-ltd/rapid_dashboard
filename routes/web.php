<?php

use App\Http\Controllers\Ops\SprintOpsController;
use App\Http\Controllers\Reports\SprintBurndownController;
use App\Http\Controllers\Reports\SprintRolloverController;
use App\Http\Controllers\Reports\SprintSummaryController;
use App\Http\Controllers\Reports\SprintVelocityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';


Route::resource('sprints', App\Http\Controllers\SprintController::class);

Route::resource('report-definitions', App\Http\Controllers\ReportDefinitionController::class)->only('index', 'show');

Route::resource('report-runs', App\Http\Controllers\ReportRunController::class)->except('edit', 'update', 'destroy');

Route::resource('report-schedules', App\Http\Controllers\ReportScheduleController::class);

Route::resource('dashboards', App\Http\Controllers\DashboardController::class)->only('index', 'show');


Route::resource('sprints', App\Http\Controllers\SprintController::class)->except('create', 'edit', 'destroy');

Route::resource('sprint-snapshots', App\Http\Controllers\SprintSnapshotController::class)->only('index', 'show');


Route::resource('sprints', App\Http\Controllers\SprintController::class)->except('create', 'edit', 'destroy');

Route::resource('sprint-snapshots', App\Http\Controllers\SprintSnapshotController::class)->only('index', 'show');

Route::prefix('reports')->group(function () {
    Route::get('/sprints/{sprint}/burndown.json', [SprintBurndownController::class, 'json']);
    Route::get('/sprints/{sprint}/burndown.csv', [SprintBurndownController::class, 'csv']);

    Route::get('/sprints/{sprint}/rollover.json', [SprintRolloverController::class, 'json']);
    Route::get('/sprints/{sprint}/rollover.csv', [SprintRolloverController::class, 'csv']);
    
    Route::get('/sprints/{sprint}/summary.json', [SprintSummaryController::class, 'json']);

    Route::get('/velocity.json', [SprintVelocityController::class, 'json']);
    Route::get('/velocity.csv', [SprintVelocityController::class, 'csv']);


});

Route::get('/ops/sprints/{sprint}', [SprintOpsController::class, 'show']);
