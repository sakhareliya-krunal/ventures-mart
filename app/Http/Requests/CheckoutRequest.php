<?php

namespace App\Http\Requests;

use App\Support\GstState;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:30'],
            'payment_method' => ['required', 'string', 'in:razorpay,cod'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('district')) {
            $this->merge([
                'district' => trim((string) $this->input('district')),
            ]);
        }

        if ($this->has('state')) {
            $this->merge([
                'state' => GstState::normalize((string) $this->input('state')) ?? $this->input('state'),
            ]);
        }
    }
}
