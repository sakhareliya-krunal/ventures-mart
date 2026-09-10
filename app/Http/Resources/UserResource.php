<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => self::normalizeEmail($this->email),
            'avatar' => $this->avatar,
            'has_password' => filled($this->password),
            'is_admin' => $this->isAdmin(),
        ];
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }

        $trimmed = preg_split('/LOCAL_ADMIN|PASSWORD=|\r|\n/', $email)[0] ?? $email;
        $trimmed = trim($trimmed);

        if (preg_match('/^([^\s@]+@[^\s@]+\.[^\s@]+)/', $trimmed, $matches)) {
            return $matches[1];
        }

        return $trimmed;
    }
}
