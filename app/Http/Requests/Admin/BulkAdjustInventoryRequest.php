<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'adjustments' => ['required', 'array', 'min:1', 'max:100'],
            'adjustments.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'adjustments.*.operation' => ['required', Rule::in(['receive', 'decrease', 'set_count', 'set_available', 'damage'])],
            'adjustments.*.quantity' => ['required', 'integer', 'min:0'],
            'adjustments.*.reason' => ['required', 'string', 'max:500'],
            'adjustments.*.expected_version' => ['required', 'integer', 'min:0'],
            'adjustments.*.idempotency_key' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }
}
