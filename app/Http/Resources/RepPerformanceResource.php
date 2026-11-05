<?php

namespace App\Http\Resources;

use App\Enums\LeadStatus;
use App\Models\User;
use App\Reports\RepPerformanceReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class RepPerformanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rep' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
            'total_leads' => $this->total_leads,
            'leads_by_status' => collect(LeadStatus::cases())->mapWithKeys(fn (LeadStatus $status) => [
                $status->value => $this->{RepPerformanceReport::statusColumn($status)},
            ]),
            'total_expected_value' => $this->total_expected_value,
            'won_expected_value' => $this->won_expected_value,
            'activity_count' => $this->activity_count,
        ];
    }
}
