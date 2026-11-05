<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLeadRequest;
use App\Http\Resources\LeadResource;
use App\Jobs\NotifyRepOfAssignment;
use App\Models\Lead;

class LeadAssignmentController extends Controller
{
    public function __invoke(AssignLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        if ($lead->wasChanged('assigned_to')) {
            NotifyRepOfAssignment::dispatch($lead);
        }

        return LeadResource::make($lead->load('assignedRep'));
    }
}
