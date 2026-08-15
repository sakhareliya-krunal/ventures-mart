<?php

namespace App\Http\Requests;

use App\Services\MetaConversionsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_name' => ['required', 'string', Rule::in(MetaConversionsService::BROWSER_EVENTS)],
            'event_id' => ['required', 'string', 'max:64'],
            'event_source_url' => ['nullable', 'string', 'max:2048'],
            'custom_data' => ['nullable', 'array'],
            'custom_data.content_ids' => ['nullable', 'array'],
            'custom_data.content_ids.*' => ['nullable', 'string', 'max:64'],
            'custom_data.product_id' => ['nullable'],
            'custom_data.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'custom_data.search_string' => ['nullable', 'string', 'max:200'],
            'custom_data.contents' => ['nullable', 'array'],
            'fbp' => ['nullable', 'string', 'max:120'],
            'fbc' => ['nullable', 'string', 'max:255'],
        ];
    }
}
