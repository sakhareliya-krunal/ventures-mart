<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use App\Services\GoogleIdTokenVerifier;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $google,
        private readonly CartService $cart,
        private readonly WishlistService $wishlist,
    ) {
    }

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
            'intent' => ['required', Rule::in(['login', 'register'])],
        ]);

        try {
            $profile = $this->google->verifyAccessToken($validated['access_token']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'invalid_credential',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $user = User::query()
            ->where(function ($query) use ($profile) {
                $query
                    ->where('google_id', $profile['sub'])
                    ->orWhere('email', $profile['email']);
            })
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $profile['name'] ?: Str::before($profile['email'], '@'),
                'email' => $profile['email'],
                'google_id' => $profile['sub'],
                'avatar' => $profile['picture'],
                'password' => null,
                'email_verified_at' => now(),
            ]);

            return $this->completeLogin($request, $user, 201);
        }

        $this->syncGoogleProfile($user, $profile);

        return $this->completeLogin($request, $user);
    }

    /**
     * @param  array{sub: string, email: string, name: ?string, picture: ?string}  $profile
     */
    private function syncGoogleProfile(User $user, array $profile): void
    {
        $user->fill([
            'google_id' => $user->google_id ?: $profile['sub'],
            'avatar' => $profile['picture'] ?: $user->avatar,
            'name' => $user->name ?: ($profile['name'] ?: Str::before($profile['email'], '@')),
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();
    }

    private function completeLogin(Request $request, User $user, int $status = 200)
    {
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $this->cart->mergeGuestIntoUser($request, $user);
        $this->wishlist->mergeGuestIntoUser($request, $user);

        return response()->json([
            'user' => new UserResource($user->fresh()),
        ], $status);
    }
}
