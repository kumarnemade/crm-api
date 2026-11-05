<?php

namespace App\Rules;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsRep implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! User::whereKey($value)->where('role', UserRole::Rep)->exists()) {
            $fail('Leads can only be assigned to an existing rep.');
        }
    }
}
