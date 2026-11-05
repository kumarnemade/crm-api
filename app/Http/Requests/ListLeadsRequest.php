<?php

namespace App\Http\Requests;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ListLeadsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sortOptions = Arr::flatten(Arr::map(Lead::SORTABLE_COLUMNS, fn (string $column) => [$column, "-{$column}"]));

        return [
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            'assigned_to' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in($sortOptions)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'Sort by created_at or expected_value; prefix with "-" for descending.',
        ];
    }
}
