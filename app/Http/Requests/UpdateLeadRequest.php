<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use App\Http\Requests\Concerns\HasLeadFieldRules;
use App\Rules\HasActivityBeforeClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    use HasLeadFieldRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...Arr::map($this->leadFieldRules(), fn (array $rules) => ['sometimes', ...$rules]),
            'status' => [
                'sometimes',
                'bail',
                'required',
                Rule::enum(LeadStatus::class),
                new HasActivityBeforeClosing($this->route('lead')),
            ],
            'assigned_to' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.prohibited' => 'Use the assign endpoint to change who a lead is assigned to.',
        ];
    }
}
