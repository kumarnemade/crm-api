<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasLeadFieldRules;
use App\Rules\IsRep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    use HasLeadFieldRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->leadFieldRules(),
            'status' => ['prohibited'],
            'assigned_to' => [Rule::prohibitedIf(! $this->user()->isManager()), 'nullable', 'integer', new IsRep],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.prohibited' => 'New leads always start as "new". Update the status after creating the lead.',
            'assigned_to.prohibited' => 'Leads you create are assigned to you automatically.',
        ];
    }

    /**
     * A rep's lead is assigned to them, otherwise they could not see what they just created.
     *
     * @return array<string, mixed>
     */
    public function leadAttributes(): array
    {
        return $this->user()->isManager()
            ? $this->validated()
            : [...$this->validated(), 'assigned_to' => $this->user()->id];
    }
}
