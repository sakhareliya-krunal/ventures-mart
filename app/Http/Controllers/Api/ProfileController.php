<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->fill($validated)->save();

        return new UserResource($user->fresh());
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = filled($user->password);

        $validated = $request->validate([
            'current_password' => [$hasPassword ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($hasPassword && ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json([
            'message' => $hasPassword ? 'Password updated.' : 'Password set.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
