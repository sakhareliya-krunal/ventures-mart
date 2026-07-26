<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->latest('id');

        $role = $request->string('role')->trim()->toString();
        if ($role === 'admin') {
            $query->where('is_admin', true);
        } elseif ($role === 'customer') {
            $query->where('is_admin', false);
        }

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();

        return (new UserResource($user->fresh()))->response()->setStatusCode(201);
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
