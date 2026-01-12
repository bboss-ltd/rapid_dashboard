<?php

use App\Http\Controllers\Ops\SprintOpsController;
use App\Http\Controllers\ReportUiController;
use App\Http\Controllers\Reports\SprintBurndownController;
use App\Http\Controllers\Reports\SprintRolloverController;
use App\Http\Controllers\Reports\SprintSummaryController;
use App\Http\Controllers\Reports\SprintVelocityController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\SprintSnapshotController;
use App\Http\Controllers\Wallboard\WallboardController;
use App\Livewire\Reports\VelocityTable;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';

/**
 * Public (or separately protected)
 */
Route::middleware(['wallboard.access'])->group(function () {
    Route::get('/wallboard', [WallboardController::class, 'index'])->name('wallboard.index');
    Route::get('/wallboard/sprints/{sprint}', [WallboardController::class, 'sprint'])->name('wallboard.sprint');
});

/**
 * Authenticated admin area
 */
Route::middleware(['auth', 'verified'])->group(function () {
    // Sprints (index/show only)
    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');

    // Nested snapshots under sprint (index/show)
    Route::get('/sprints/{sprint}/snapshots', [SprintSnapshotController::class, 'index'])->name('sprints.snapshots.index');
    Route::get('/sprints/{sprint}/snapshots/{snapshot}', [SprintSnapshotController::class, 'show'])->name('sprints.snapshots.show');

    // Reports UI pages
    Route::get('/reports', [ReportUiController::class, 'index'])->name('reports.index');
    Route::get('/reports/velocity', VelocityTable::class)->name('reports.velocity');
    Route::get('/reports/sprints/{sprint}/summary', [ReportUiController::class, 'sprintSummary'])->name('reports.sprint.summary');

    // Existing CRUD modules (leave these as resources if you’re using them)
    Route::resource('report-definitions', App\Http\Controllers\ReportDefinitionController::class)->only(['index', 'show']);
    Route::resource('report-runs', App\Http\Controllers\ReportRunController::class)->except(['create', 'edit', 'update', 'destroy']); // adjust if needed
    Route::resource('report-schedules', App\Http\Controllers\ReportScheduleController::class);

    // If you have a DashboardController module (not the wallboard), keep it here
    Route::resource('dashboards', App\Http\Controllers\DashboardController::class)->only(['index', 'show']);
});

/**
 * Report exports (JSON/CSV) – choose whether these should be auth-protected.
 * If you want them protected, move this group inside the auth middleware above.
 */
Route::prefix('reports')->group(function () {
    Route::get('/sprints/{sprint}/burndown.json', [SprintBurndownController::class, 'json'])->name('reports.export.burndown.json');
    Route::get('/sprints/{sprint}/burndown.csv', [SprintBurndownController::class, 'csv'])->name('reports.export.burndown.csv');

    Route::get('/sprints/{sprint}/rollover.json', [SprintRolloverController::class, 'json'])->name('reports.export.rollover.json');
    Route::get('/sprints/{sprint}/rollover.csv', [SprintRolloverController::class, 'csv'])->name('reports.export.rollover.csv');

    Route::get('/sprints/{sprint}/summary.json', [SprintSummaryController::class, 'json'])->name('reports.export.summary.json');

    Route::get('/velocity.json', [SprintVelocityController::class, 'json'])->name('reports.export.velocity.json');
    Route::get('/velocity.csv', [SprintVelocityController::class, 'csv'])->name('reports.export.velocity.csv');
});

/**
 * Ops / debug (choose whether to protect this)
 */
Route::get('/ops/sprints/{sprint}', [SprintOpsController::class, 'show'])->name('ops.sprints.show');
