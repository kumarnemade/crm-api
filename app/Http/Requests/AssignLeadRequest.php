<?php

namespace App\Http\Requests;

use App\Rules\IsRep;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', new IsRep],
        ];
    }
}
