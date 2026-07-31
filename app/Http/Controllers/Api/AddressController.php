<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Support\GstState;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedAddress($request);
        $user = $request->user();

        if ($user->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        $address = $user->addresses()->create($validated);

        if (! empty($validated['is_default'])) {
            $this->clearOtherDefaults($user->id, $address->id);
        }

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        $validated = $this->validatedAddress($request);
        $address->fill($validated)->save();

        if (! empty($validated['is_default'])) {
            $this->clearOtherDefaults($request->user()->id, $address->id);
        }

        return new AddressResource($address->fresh());
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = $request->user()->addresses()->orderByDesc('id')->first();
            if ($next) {
                $next->forceFill(['is_default' => true])->save();
            }
        }

        return response()->json(['message' => 'Address deleted.']);
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        $address->forceFill(['is_default' => true])->save();
        $this->clearOtherDefaults($request->user()->id, $address->id);

        return new AddressResource($address->fresh());
    }

    private function validatedAddress(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $validated['label'] = trim((string) ($validated['label'] ?? '')) ?: 'Home';
        $validated['state'] = GstState::normalize($validated['state']) ?? $validated['state'];

        return $validated;
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }

    private function clearOtherDefaults(int $userId, int $exceptId): void
    {
        Address::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
