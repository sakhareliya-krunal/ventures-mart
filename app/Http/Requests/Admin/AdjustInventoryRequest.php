<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['receive', 'decrease', 'set_count', 'set_available', 'damage'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
