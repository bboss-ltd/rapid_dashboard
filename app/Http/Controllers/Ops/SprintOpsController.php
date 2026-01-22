<?php

namespace App\Http\Controllers\Ops;

use App\Domains\Sprints\Actions\GetSprintOpsDataAction;
use App\Http\Controllers\Controller;
use App\Models\Sprint;

class SprintOpsController extends Controller
{
    public function show(Sprint $sprint, GetSprintOpsDataAction $opsData)
    {
        return response()->json($opsData->run($sprint));
    }
}
