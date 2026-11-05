<?php

namespace App\Http\Requests\Concerns;

use App\Enums\LeadSource;
use Illuminate\Validation\Rule;

trait HasLeadFieldRules
{
    /**
     * @return array<string, mixed>
     */
    protected function leadFieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'expected_value' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
