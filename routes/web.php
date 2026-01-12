<?php

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
