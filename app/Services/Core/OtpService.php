<?php

declare(strict_types=1);

namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private const KEY_PREFIX = 'otp:';

    /**
     * Generate a cryptographically random 6-digit code string.
     * Use this when the code must be stored outside the cache (e.g. DB-backed resets).
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function generate(string $key, int $ttlMinutes = 60): string
    {
        $code = static::generateCode();

        try {
            Cache::put(
                self::KEY_PREFIX . $key,
                Hash::make($code),
                now()->addMinutes($ttlMinutes)
            );
        } catch (\Throwable $e) {
            Log::error('OtpService: failed to store OTP in cache', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
            // Re-throw: a code that can't be stored cannot be verified — caller must handle
            throw $e;
        }

        return $code;
    }

    public function verify(string $key, string $code): bool
    {
        try {
            $hashed = Cache::get(self::KEY_PREFIX . $key);
        } catch (\Throwable $e) {
            Log::error('OtpService: failed to read OTP from cache', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if ($hashed === null) {
            return false;
        }

        if (!Hash::check($code, $hashed)) {
            return false;
        }

        Cache::forget(self::KEY_PREFIX . $key);

        return true;
    }

    public function exists(string $key): bool
    {
        return Cache::has(self::KEY_PREFIX . $key);
    }

    public function delete(string $key): void
    {
        Cache::forget(self::KEY_PREFIX . $key);
    }
}
