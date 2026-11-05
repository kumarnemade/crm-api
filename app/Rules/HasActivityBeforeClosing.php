<?php

namespace App\Rules;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HasActivityBeforeClosing implements ValidationRule
{
    public function __construct(private readonly Lead $lead) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (LeadStatus::from($value)->isClosed() && ! $this->lead->activities()->exists()) {
            $fail("A lead needs at least one logged activity before it can be marked {$value}.");
        }
    }
}
