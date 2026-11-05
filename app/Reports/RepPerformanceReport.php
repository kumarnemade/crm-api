<?php

namespace App\Reports;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * One query for all reps. Leads and activities are aggregated in separate subqueries:
 * joining them directly would repeat each lead once per activity and inflate the sums.
 */
class RepPerformanceReport
{
    /**
     * @return Collection<int, User>
     */
    public function for(User $viewer): Collection
    {
        $repId = $viewer->isManager() ? null : $viewer->id;

        $countColumns = ['total_leads', ...Arr::map(LeadStatus::cases(), self::statusColumn(...)), 'activity_count'];
        $valueColumns = ['total_expected_value', 'won_expected_value'];

        return User::query()
            ->where('role', UserRole::Rep)
            ->when($repId, fn ($query) => $query->whereKey($repId))
            ->leftJoinSub($this->leadTotals($repId), 'lead_totals', 'lead_totals.rep_id', '=', 'users.id')
            ->leftJoinSub($this->activityTotals($repId), 'activity_totals', 'activity_totals.rep_id', '=', 'users.id')
            ->select('users.id', 'users.name')
            ->selectRaw(collect([...$countColumns, ...$valueColumns])
                ->map(fn (string $column) => "COALESCE({$column}, 0) AS {$column}")
                ->implode(', '))
            ->orderBy('users.name')
            ->withCasts([
                ...array_fill_keys($countColumns, 'integer'),
                ...array_fill_keys($valueColumns, 'decimal:2'),
            ])
            ->get();
    }

    public static function statusColumn(LeadStatus $status): string
    {
        return "{$status->value}_count";
    }

    private function leadTotals(?int $repId): Builder
    {
        $statusCounts = Arr::map(LeadStatus::cases(), fn (LeadStatus $status) => sprintf(
            "SUM(CASE WHEN status = '%s' THEN 1 ELSE 0 END) AS %s",
            $status->value,
            self::statusColumn($status),
        ));

        return DB::table('leads')
            ->whereNotNull('assigned_to')
            ->when($repId, fn ($query) => $query->where('assigned_to', $repId))
            ->groupBy('assigned_to')
            ->select('assigned_to as rep_id')
            ->selectRaw('COUNT(*) AS total_leads')
            ->selectRaw('SUM(expected_value) AS total_expected_value')
            ->selectRaw(sprintf(
                "SUM(CASE WHEN status = '%s' THEN expected_value ELSE 0 END) AS won_expected_value",
                LeadStatus::Won->value,
            ))
            ->selectRaw(implode(', ', $statusCounts));
    }

    private function activityTotals(?int $repId): Builder
    {
        return DB::table('activities')
            ->join('leads', 'leads.id', '=', 'activities.lead_id')
            ->whereNotNull('leads.assigned_to')
            ->when($repId, fn ($query) => $query->where('leads.assigned_to', $repId))
            ->groupBy('leads.assigned_to')
            ->select('leads.assigned_to as rep_id')
            ->selectRaw('COUNT(*) AS activity_count');
    }
}
