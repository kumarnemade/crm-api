<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyRepOfAssignment implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function handle(): void
    {
        Log::info('Lead assigned to rep.', [
            'lead_id' => $this->lead->id,
            'rep_id' => $this->lead->assigned_to,
        ]);
    }
}
