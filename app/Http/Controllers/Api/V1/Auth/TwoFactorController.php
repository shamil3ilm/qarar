<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function __construct(
        private \PragmaRX\Google2FALaravel\Google2FA $google2fa
    ) {}

    /**
     * Begin 2FA setup: generate a TOTP secret and QR code URL.
     * The user must call enable() with a valid code to activate 2FA.
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        $secret = $this->google2fa->generateSecretKey();
        $companyName = config('app.name');

        $qrUrl = $this->google2fa->getQRCodeUrl($companyName, $user->email, $secret);

        // The 'encrypted' cast on two_factor_secret handles storage encryption automatically.
        // 2FA is not active until the user confirms with a valid TOTP code in enable().
        $user->two_factor_secret = $secret;
        $user->save();

        return $this->success([
            'secret'           => $secret,
            'qr_code_url'      => $qrUrl,
            'manual_entry_key' => $secret,
        ], '2FA setup initiated. Scan the QR code and verify with enable.');
    }

    /**
     * Confirm 2FA setup by verifying a valid TOTP code.
     * Issues recovery codes and marks 2FA as enabled.
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        if ($user->two_factor_secret === null) {
            return $this->error(
                '2FA setup has not been initiated. Call /2fa/setup first.',
                'TWO_FACTOR_NOT_INITIATED',
                400
            );
        }

        $rateLimitKey = 'totp_attempts:' . $user->id;
        if (Cache::get($rateLimitKey, 0) >= 5) {
            return $this->error('Too many 2FA attempts. Try again in 5 minutes.', 'TOO_MANY_ATTEMPTS', 429);
        }

        // two_factor_secret is automatically decrypted by the model cast on read
        if (!$this->google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            Cache::add($rateLimitKey, 0, now()->addMinutes(5));
            Cache::increment($rateLimitKey);

            return $this->error(
                'Invalid verification code.',
                'INVALID_OTP',
                422
            );
        }

        Cache::forget($rateLimitKey);

        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->two_factor_enabled        = true;
        $user->two_factor_recovery_codes = $hashedCodes;
        $user->two_factor_confirmed_at   = now();
        $user->save();

        $user->notify(new \App\Notifications\Auth\TwoFactorStatusNotification(true));

        return $this->success([
            'enabled'        => true,
            'recovery_codes' => $plainCodes,
        ], '2FA enabled successfully. Store your recovery codes in a safe place — they will not be shown again.');
    }

    /**
     * Disable 2FA after verifying the user's current password.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        if (!Hash::check($request->password, $user->password)) {
            return $this->error(
                'Incorrect password.',
                'INVALID_PASSWORD',
                400
            );
        }

        $user->two_factor_enabled        = false;
        $user->two_factor_secret         = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at   = null;
        $user->save();

        $user->notify(new \App\Notifications\Auth\TwoFactorStatusNotification(false));

        return $this->success(['disabled' => true], '2FA has been disabled.');
    }

    /**
     * Replace all recovery codes with a fresh set.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        if (!$user->two_factor_enabled) {
            return $this->error(
                '2FA is not enabled on this account.',
                'TWO_FACTOR_NOT_ENABLED',
                400
            );
        }

        if (!Hash::check($request->password, $user->password)) {
            return $this->error(
                'Incorrect password.',
                'INVALID_PASSWORD',
                400
            );
        }

        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->two_factor_recovery_codes = $hashedCodes;
        $user->save();

        return $this->success([
            'recovery_codes' => $plainCodes,
        ], 'Recovery codes regenerated. Store them safely — they will not be shown again.');
    }

    /**
     * Generate 8 random recovery codes.
     *
     * Each code is 10 uppercase alphanumeric characters split by a hyphen
     * (e.g. "ABCD-EF123"). Returns [plaintext array, hashed array].
     *
     * @return array{0: string[], 1: string[]}
     */
    private function generateRecoveryCodes(): array
    {
        $plain  = [];
        $hashed = [];

        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(6));
            $plain[]  = $raw;
            $hashed[] = Hash::make($raw);
        }

        return [$plain, $hashed];
    }
}
