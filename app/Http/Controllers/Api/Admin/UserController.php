<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        if (
            array_key_exists('is_admin', $validated)
            && $user->id === $request->user()->id
            && ! $validated['is_admin']
        ) {
            throw ValidationException::withMessages([
                'is_admin' => 'You cannot remove your own admin access.',
            ]);
        }

        if (array_key_exists('is_admin', $validated)) {
            $user->forceFill(['is_admin' => (bool) $validated['is_admin']]);
            unset($validated['is_admin']);
        }

        if ($validated !== []) {
            $user->fill($validated);
        }

        $user->save();

        return new UserResource($user->fresh());
    }
}
