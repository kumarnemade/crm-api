<?php

namespace App\Http\Requests;

use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ActivityType::class)],
            'body' => ['required', 'string', 'max:65535'],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_at.before_or_equal' => 'An activity can only be logged once it has happened.',
        ];
    }

    /**
     * occurred_at is normalised to UTC because Eloquent stores a date's wall-clock
     * time and would otherwise drop any offset the client sent.
     *
     * @return array<string, mixed>
     */
    public function activityAttributes(): array
    {
        return [
            ...$this->validated(),
            'user_id' => $this->user()->id,
            'occurred_at' => $this->date('occurred_at')?->utc() ?? now(),
        ];
    }
}
