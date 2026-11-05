<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assigned_to', 'name', 'email', 'phone', 'company', 'source', 'status', 'expected_value'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const array SORTABLE_COLUMNS = ['created_at', 'expected_value'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LeadStatus::New->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_to' => 'integer',
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'expected_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isManager(), fn (Builder $query) => $query->where('assigned_to', $user->id));
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, int|string $repId) => $query->where('assigned_to', $repId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereAny(['name', 'email', 'company'], 'like', "%{$term}%"));
    }

    /**
     * Sort by a whitelisted column; a leading "-" means descending. The id tie-breaker
     * keeps pagination stable when many leads share a value.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query->orderBy(ltrim($sort, '-'), $direction)->orderBy('id', $direction);
    }
}
