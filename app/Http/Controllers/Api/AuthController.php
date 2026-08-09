<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly WishlistService $wishlist,
    ) {
    }

    public function register(RegisterRequest $request)
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->cart->mergeGuestIntoUser($request, $user);
        $this->wishlist->mergeGuestIntoUser($request, $user);

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'), true)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $this->cart->mergeGuestIntoUser($request, $user);
        $this->wishlist->mergeGuestIntoUser($request, $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $recaller = Auth::guard('web')->getRecallerName();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Always expire the remember-me cookie so SPA clients cannot stay signed in after logout.
        cookie()->queue(cookie()->forget($recaller));

        // Clear cached guards (Sanctum may be the default after auth:sanctum).
        Auth::forgetGuards();
        Auth::shouldUse(config('auth.defaults.guard', 'web'));

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return new UserResource($request->user());
    }
}
