<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Core\Role;
use App\Models\User;
use App\Models\Core\UserEvent;
use App\Jobs\RunFraudChecksJob;
use App\Services\Auth\LoginAttemptService;
use App\Services\Auth\TokenBlacklistService;
use App\Services\Core\OtpService;
use App\Services\Core\UserEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private TokenBlacklistService $tokenBlacklistService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        // Normalize email (case-insensitive, trim whitespace)
        $email = $this->normalizeEmail($request->email);
        $ipAddress = $request->ip();

        // Check rate limiting
        $rateCheck = $this->loginAttemptService->isAllowed($email, $ipAddress);
        if (!$rateCheck['allowed']) {
            return $this->error(
                $rateCheck['message'],
                $rateCheck['reason'],
                429
            );
        }

        $credentials = [
            'email' => $email,
            'password' => $request->password,
        ];

        // Check if user exists and is active
        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            $this->loginAttemptService->recordAttempt($email, $ipAddress, false);
            return $this->invalidCredentialsResponse($rateCheck['remaining_attempts'] ?? null);
        }

        // Check if soft-deleted
        if ($user->deleted_at !== null) {
            $this->loginAttemptService->recordAttempt($email, $ipAddress, false);
            return $this->invalidCredentialsResponse($rateCheck['remaining_attempts'] ?? null);
        }

        if (!$user->is_active) {
            $this->loginAttemptService->recordAttempt($email, $ipAddress, false);
            return $this->error(
                'Your account is not active. Please contact support.',
                'ACCOUNT_INACTIVE',
                401
            );
        }

        // Attempt authentication
        if (!$token = auth('api')->attempt($credentials)) {
            $this->loginAttemptService->recordAttempt($email, $ipAddress, false);
            return $this->invalidCredentialsResponse($rateCheck['remaining_attempts'] ?? null);
        }

        // Record successful login attempt
        $this->loginAttemptService->recordAttempt($email, $ipAddress, true);

        // If 2FA is enabled, issue a short-lived challenge token instead of a full JWT
        if ($user->two_factor_enabled && $user->two_factor_secret !== null) {
            // Invalidate the just-issued JWT — user must complete 2FA before getting a real token
            auth('api')->logout();

            $challengeToken = encrypt(json_encode([
                'user_id' => $user->id,
                'expires' => now()->addMinutes(10)->timestamp,
            ]));

            return $this->success(
                ['requires_2fa' => true, 'challenge_token' => $challengeToken],
                '2FA verification required.'
            );
        }

        $user->recordLogin();

        app(UserEventService::class)->track(UserEvent::USER_LOGIN, ['email' => $email], $user->id, $user->organization_id, $request);

        // Dispatch geographic fraud check asynchronously — non-blocking
        try {
            RunFraudChecksJob::dispatch(
                'login',
                $user->id,
                [
                    'user_id'      => $user->id,
                    'ip_address'   => $request->ip(),
                    'country_code' => $request->header('CF-IPCountry') ?? $request->header('X-Country-Code'),
                    'email'        => $user->email,
                ],
                $user->organization_id,
                $user->id,
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Fraud check dispatch failed for login', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $this->respondWithToken($token, $user);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code'            => 'required|string',
        ]);

        // Decrypt and parse the challenge token
        try {
            $payload = json_decode(decrypt($request->challenge_token), true);
        } catch (\Exception) {
            return $this->error('Invalid or tampered challenge token.', 'INVALID_CHALLENGE_TOKEN', 400);
        }

        if (!isset($payload['user_id'], $payload['expires'])) {
            return $this->error('Invalid challenge token structure.', 'INVALID_CHALLENGE_TOKEN', 400);
        }

        if (now()->timestamp > $payload['expires']) {
            return $this->error('Challenge token has expired. Please log in again.', 'CHALLENGE_TOKEN_EXPIRED', 401);
        }

        // Enforce single-use atomically: Cache::add only sets the key if it does not already exist
        // and returns false if the key is already present, preventing replay attacks.
        $tokenHash = hash('sha256', $request->challenge_token);
        $usedKey = 'twofa_token_used:' . $tokenHash;
        if (!Cache::add($usedKey, true, 600)) {
            return $this->error('Challenge token already used.', 'CHALLENGE_TOKEN_REPLAYED', 401);
        }

        $user = User::find($payload['user_id']);

        if (!$user || !$user->is_active) {
            return $this->error('User not found or inactive.', 'USER_NOT_FOUND', 404);
        }

        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            return $this->error('Two-factor authentication setup is incomplete.', 'TWO_FACTOR_SETUP_INCOMPLETE', 403);
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $code = $request->code;

        // Try TOTP verification first
        $verified = $user->two_factor_secret !== null
            && $google2fa->verifyKey($user->two_factor_secret, $code);

        // Fall back to recovery codes if TOTP did not match
        if (!$verified && is_array($user->two_factor_recovery_codes)) {
            $recoveryIndex = null;

            foreach ($user->two_factor_recovery_codes as $index => $hashed) {
                if (Hash::check($code, $hashed)) {
                    $recoveryIndex = $index;
                    break;
                }
            }

            if ($recoveryIndex !== null) {
                $verified = true;

                // Consume the used recovery code so it cannot be reused
                $remaining = array_values(
                    array_filter(
                        $user->two_factor_recovery_codes,
                        fn ($v, $k) => $k !== $recoveryIndex,
                        ARRAY_FILTER_USE_BOTH
                    )
                );
                $user->two_factor_recovery_codes = $remaining;
                $user->save();
            }
        }

        if (!$verified) {
            return $this->error('Invalid verification code.', 'INVALID_OTP', 422);
        }

        // Issue the real JWT now that 2FA is satisfied
        $token = auth('api')->login($user);
        $user->recordLogin();

        return $this->respondWithToken($token, $user, 'Login successful');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Normalize email
        $email = $this->normalizeEmail($request->email);

        return DB::transaction(function () use ($request, $email) {
            // Create unique slug (handle race condition)
            $baseSlug = Str::slug($request->organization_name);
            $slug = $baseSlug . '-' . Str::random(6);

            // Create organization
            $organization = Organization::create([
                'name' => trim($request->organization_name),
                'slug' => $slug,
                'country_code' => $request->country_code,
                'tax_scheme' => $this->getTaxScheme($request->country_code),
                'base_currency' => $this->getDefaultCurrency($request->country_code),
                'email' => $email,
                'is_active' => true,
                'activated_at' => now(),
            ]);

            // Create default branch (bypass global scope)
            $branch = new Branch();
            $branch->uuid = (string) Str::uuid();
            $branch->organization_id = $organization->id;
            $branch->name = 'Head Office';
            $branch->code = 'HO';
            $branch->country_code = $request->country_code;
            $branch->is_default = true;
            $branch->is_active = true;
            $branch->saveQuietly(); // Skip audit for initial creation

            // Create user
            $user = User::create([
                'organization_id'          => $organization->id,
                'name'                     => trim($request->name),
                'email'                    => $email,
                'password'                 => $request->password,
                'is_active'                => true,
                'timezone'                 => $this->getDefaultTimezone($request->country_code),
                'registration_source'      => $request->registration_source,
                'utm_source'               => $request->utm_source,
                'utm_medium'               => $request->utm_medium,
                'utm_campaign'             => $request->utm_campaign,
                'utm_term'                 => $request->utm_term,
                'utm_content'              => $request->utm_content,
                'referral_code'            => $request->referral_code,
                'registration_device_type' => $request->registration_device_type,
                'invited_by_user_id'       => $request->invited_by_user_id,
            ]);

            // Set registration_ip explicitly (not via mass-assignment) so it always reflects
            // the real server-side IP and can never be spoofed via the request payload.
            $user->registration_ip = $request->ip();
            $user->save();

            // Attach user to branch
            $user->branches()->attach($branch->id, ['is_default' => true]);

            // Assign admin role
            $adminRole = Role::withoutGlobalScopes()
                ->where('slug', 'admin')
                ->whereNull('organization_id')
                ->first();

            if ($adminRole) {
                $user->roles()->attach($adminRole->id);
            }

            // Enable default modules for the new organization
            $defaultModules = ['core', 'accounting', 'inventory', 'sales', 'purchase', 'hr', 'crm', 'manufacturing'];
            foreach ($defaultModules as $moduleCode) {
                OrganizationModule::create([
                    'organization_id' => $organization->id,
                    'module_code' => $moduleCode,
                    'is_enabled' => true,
                    'enabled_at' => now(),
                ]);
            }

            // Generate token
            $token = auth('api')->login($user);

            app(UserEventService::class)->track(UserEvent::USER_REGISTERED, ['organization' => $organization->name, 'country' => $request->country_code], $user->id, $organization->id, $request);

            $user->notify(new \App\Notifications\Auth\WelcomeNotification($organization->name));
            $user->sendEmailVerificationNotification();

            return $this->respondWithToken($token, $user, 'Registration successful', 201);
        });
    }

    public function me(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->unauthorized('User not found');
        }

        $user->load(['organization', 'branches', 'roles.permissions']);

        return $this->success([
            'user' => new UserResource($user),
            'permissions' => $user->getAllPermissions(),
            'default_branch' => $user->getDefaultBranch()?->only(['id', 'uuid', 'name', 'code']),
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            // Blacklist the old token first
            $this->tokenBlacklistService->blacklistCurrentToken('refresh');

            // Get new token
            $token = auth('api')->refresh();
            $user = auth('api')->user();

            return $this->respondWithToken($token, $user, 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->error('Token refresh failed. Please login again.', 'TOKEN_REFRESH_FAILED', 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if ($user) {
            app(UserEventService::class)->track(UserEvent::USER_LOGOUT, [], $user->id, $user->organization_id, $request);
        }

        try {
            // Blacklist the token so it can't be reused
            $this->tokenBlacklistService->blacklistCurrentToken('logout');

            // Invalidate in JWT
            auth('api')->logout();
        } catch (\Exception $e) {
            // Token might already be invalid, that's okay
        }

        return $this->success(null, 'Successfully logged out');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $rateLimitKey = 'password_reset_request:' . md5(strtolower($request->input('email', '')));
        if (Cache::get($rateLimitKey, 0) >= 3) {
            return $this->error('Too many password reset requests. Try again later.', 'TOO_MANY_REQUESTS', 429);
        }
        Cache::add($rateLimitKey, 0, now()->addHour());
        Cache::increment($rateLimitKey);

        $email = $this->normalizeEmail($request->email);
        $user = User::where('email', $email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return $this->success(null, 'If an account with that email exists, a password reset code has been sent.');
        }

        // Generate 6-digit code
        $code = OtpService::generateCode();

        // Store in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        // Send notification with the code
        $user->notify(new \App\Notifications\Auth\PasswordResetNotification($code));

        return $this->success(null, 'If an account with that email exists, a password reset code has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $rateLimitKey = 'password_reset_verify:' . md5(strtolower($request->input('email', '')));
        if (Cache::get($rateLimitKey, 0) >= 10) {
            return $this->error('Too many reset attempts. Try again later.', 'TOO_MANY_REQUESTS', 429);
        }
        Cache::add($rateLimitKey, 0, now()->addMinutes(10));
        Cache::increment($rateLimitKey);

        $email = $this->normalizeEmail($request->email);

        // Update the user's password — only active (non-deleted) accounts may reset
        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            return $this->error('Invalid or expired password reset token.', 'INVALID_RESET_TOKEN', 400);
        }

        if ($user->deleted_at !== null) {
            return $this->error('Invalid or expired password reset token.', 'INVALID_RESET_TOKEN', 422);
        }

        // Validate the token and update the password atomically so two concurrent
        // requests cannot both consume the same token (race condition prevention).
        try {
            DB::transaction(function () use ($user, $request, $email) {
                // Acquire a row-level lock so concurrent requests must wait.
                $record = DB::table('password_reset_tokens')
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if (!$record) {
                    throw new \RuntimeException('INVALID_RESET_TOKEN');
                }

                // Check expiration (60 minutes)
                $createdAt = Carbon::parse($record->created_at);
                if ($createdAt->addMinutes(60)->isPast()) {
                    DB::table('password_reset_tokens')->where('email', $email)->delete();
                    throw new \RuntimeException('RESET_TOKEN_EXPIRED');
                }

                // Verify the code
                if (!Hash::check($request->token, $record->token)) {
                    throw new \RuntimeException('INVALID_RESET_TOKEN');
                }

                $user->password = $request->password;
                $user->save();

                // Delete the token only after the password update succeeds
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            });
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'RESET_TOKEN_EXPIRED' => $this->error('Reset code has expired. Please request a new one.', 'RESET_TOKEN_EXPIRED', 400),
                default               => $this->error('Invalid or expired reset code.', 'INVALID_RESET_TOKEN', 400),
            };
        }

        $user->notify(new \App\Notifications\Auth\PasswordChangedNotification());

        // Invalidate all existing sessions
        $this->tokenBlacklistService->blacklistAllUserTokens($user->id, 'password_reset');

        return $this->success(null, 'Password has been reset successfully. Please login with your new password.');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = $this->normalizeEmail($request->email);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->error('Invalid verification code.', 'INVALID_VERIFICATION_CODE', 400);
        }

        if ($user->email_verified_at !== null) {
            return $this->success(null, 'Email is already verified.');
        }

        if ($user->email_verification_code === null) {
            return $this->error('No verification code has been sent. Please request a new one.', 'NO_VERIFICATION_CODE', 400);
        }

        // Check expiration (60 minutes)
        if ($user->email_verification_code_sent_at !== null) {
            $sentAt = Carbon::parse($user->email_verification_code_sent_at);
            if ($sentAt->addMinutes(60)->isPast()) {
                $user->email_verification_code = null;
                $user->email_verification_code_sent_at = null;
                $user->save();
                return $this->error('Verification code has expired. Please request a new one.', 'VERIFICATION_CODE_EXPIRED', 400);
            }
        }

        if (!Hash::check($request->code, $user->email_verification_code)) {
            return $this->error('Invalid verification code.', 'INVALID_VERIFICATION_CODE', 400);
        }

        $user->email_verified_at = now();
        $user->email_verification_code = null;
        $user->email_verification_code_sent_at = null;
        $user->save();

        app(UserEventService::class)->track(UserEvent::EMAIL_VERIFIED, [], $user->id, $user->organization_id, $request);

        return $this->success(null, 'Email verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $this->normalizeEmail($request->email);
        $user = User::where('email', $email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return $this->success(null, 'If an account with that email exists, a verification code has been sent.');
        }

        if ($user->email_verified_at !== null) {
            return $this->success(null, 'Email is already verified.');
        }

        // Rate limit: don't allow resend within 2 minutes
        if ($user->email_verification_code_sent_at !== null) {
            $sentAt = Carbon::parse($user->email_verification_code_sent_at);
            if (!$sentAt->addMinutes(2)->isPast()) {
                return $this->error(
                    'Please wait before requesting another verification code.',
                    'VERIFICATION_RATE_LIMITED',
                    429
                );
            }
        }

        // Generate 6-digit code
        $code = OtpService::generateCode();

        $user->email_verification_code = Hash::make($code);
        $user->email_verification_code_sent_at = now();
        $user->save();

        // Send notification
        $user->notify(new \App\Notifications\Auth\EmailVerificationNotification($code));

        return $this->success(null, 'If an account with that email exists, a verification code has been sent.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect', 'INVALID_PASSWORD', 400);
        }

        DB::transaction(function () use ($user, $request) {
            // Update password
            $user->password = $request->new_password;
            $user->save();

            // Invalidate all tokens for this user (logout from all devices)
            $this->tokenBlacklistService->blacklistAllUserTokens($user->id, 'password_change');

            // Blacklist current token
            $this->tokenBlacklistService->blacklistCurrentToken('password_change');
        });

        $user->notify(new \App\Notifications\Auth\PasswordChangedNotification());

        // Generate new token
        auth('api')->logout();
        $token = auth('api')->login($user);

        return $this->respondWithToken(
            $token,
            $user,
            'Password changed successfully. All other sessions have been logged out.'
        );
    }

    protected function respondWithToken(
        string $token,
        User $user,
        string $message = 'Login successful',
        int $statusCode = 200
    ): JsonResponse {
        // Eager-load the organization so UserResource emits `user.organization`
        // (null for super-admins with no organization). The frontend relies on
        // this to establish tenant context after authentication.
        $user->loadMissing('organization');

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ], $message, $statusCode);
    }

    protected function invalidCredentialsResponse(?int $remainingAttempts): JsonResponse
    {
        return $this->error('Invalid credentials.', 'INVALID_CREDENTIALS', 401);
    }

    protected function normalizeEmail(string $email): string
    {
        // Lowercase and trim whitespace
        return strtolower(trim($email));
    }

    protected function getTaxScheme(string $countryCode): string
    {
        return match ($countryCode) {
            'IN' => 'GST',
            'SA', 'AE', 'BH', 'OM', 'QA', 'KW' => 'VAT',
            default => 'NONE',
        };
    }

    protected function getDefaultCurrency(string $countryCode): string
    {
        return match ($countryCode) {
            'SA' => 'SAR',
            'AE' => 'AED',
            'IN' => 'INR',
            'QA' => 'QAR',
            'OM' => 'OMR',
            'BH' => 'BHD',
            'KW' => 'KWD',
            default => 'USD',
        };
    }

    protected function getDefaultTimezone(string $countryCode): string
    {
        return match ($countryCode) {
            'SA', 'QA', 'BH', 'KW' => 'Asia/Riyadh',
            'AE', 'OM' => 'Asia/Dubai',
            'IN' => 'Asia/Kolkata',
            default => 'UTC',
        };
    }
}
