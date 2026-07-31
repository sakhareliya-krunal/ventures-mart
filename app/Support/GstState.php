<?php

namespace App\Support;

class GstState
{
    /**
     * Known aliases → canonical title-case state name.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'gj' => 'Gujarat',
        'gujarat' => 'Gujarat',
    ];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        if ($collapsed === '') {
            return null;
        }

        $key = mb_strtolower($collapsed);

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        return mb_convert_case($collapsed, MB_CASE_TITLE, 'UTF-8');
    }

    public static function sellerState(): string
    {
        return self::normalize((string) config('gst.seller_state', 'Gujarat')) ?? 'Gujarat';
    }

    public static function isSameAsSeller(?string $destination): bool
    {
        $normalized = self::normalize($destination);

        if ($normalized === null) {
            return false;
        }

        return mb_strtolower($normalized) === mb_strtolower(self::sellerState());
    }
}
