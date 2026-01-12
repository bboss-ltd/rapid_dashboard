<?php

use App\Domains\Sprints\Actions\DetectAndCloseSprintAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use App\Models\Sprint;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Sprint::query()
        ->whereNull('closed_at')
        ->each(function ($sprint) {
            app(PollBoardActionsAction::class)->run($sprint);
            app(DetectAndCloseSprintAction::class)->run($sprint);
        });
})->everyFiveMinutes();

Schedule::call(function () {
    if (!config('trello_sync.take_ad_hoc_snapshots')) return;

    Sprint::query()
        ->whereNull('closed_at')
        ->each(function ($sprint) {
            app(TakeSprintSnapshotAction::class)->run($sprint, 'ad_hoc', 'scheduled');
        });
})->hourly();
