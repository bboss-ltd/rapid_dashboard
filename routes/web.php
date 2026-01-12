<?php

use App\Http\Controllers\Reports\SprintBurndownController;
use App\Http\Controllers\Reports\SprintRolloverController;
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
});
