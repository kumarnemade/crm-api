<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListLeadsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Jobs\NotifyRepOfAssignment;
use App\Models\Lead;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function index(ListLeadsRequest $request): AnonymousResourceCollection
    {
        $leads = Lead::query()
            ->visibleTo($request->user())
            ->filter($request->validated())
            ->sorted($request->validated('sort', '-created_at'))
            ->with('assignedRep')
            ->withCount('activities')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    public function store(StoreLeadRequest $request): LeadResource
    {
        $lead = Lead::create($request->leadAttributes());

        if ($lead->assigned_to !== null && $lead->assigned_to !== $request->user()->id) {
            NotifyRepOfAssignment::dispatch($lead);
        }

        return LeadResource::make($lead->load('assignedRep'));
    }

    public function show(Lead $lead): LeadResource
    {
        $lead->load([
            'assignedRep',
            'activities' => fn ($query) => $query->with('user')->latest('occurred_at'),
        ]);

        return LeadResource::make($lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        return LeadResource::make($lead->load('assignedRep'));
    }
}
