<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Lead;

class LeadActivityController extends Controller
{
    public function __invoke(StoreActivityRequest $request, Lead $lead): ActivityResource
    {
        $activity = $lead->activities()->create($request->activityAttributes());

        return ActivityResource::make($activity->load('user'));
    }
}
