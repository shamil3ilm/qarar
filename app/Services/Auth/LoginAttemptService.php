<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Jobs\TrackUserEvent;
use App\Models\Core\UserEvent;
use App\Models\User;
use App\Notifications\Auth\AccountLockedNotification;
use App\Notifications\Auth\BruteForceAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginAttemptService
{
    // Configurable thresholds
    private const MAX_ATTEMPTS_PER_EMAIL = 5;
    private const MAX_ATTEMPTS_PER_IP = 20;
    private const LOCKOUT_MINUTES = 15;
    private const ATTEMPT_WINDOW_MINUTES = 15;

    /**
     * Record a login attempt
     */
    public function recordAttempt(string $email, string $ipAddress, bool $successful): void
    {
        DB::table('login_attempts')->insert([
            'email' => strtolower($email),
            'ip_address' => $ipAddress,
            'successful' => $successful,
            'attempted_at' => now(),
        ]);

        // Update rate limit counters
        if (!$successful) {
            $this->incrementCounter("login_attempts:email:{$email}");
            $this->incrementCounter("login_attempts:ip:{$ipAddress}");

            // After inserting the failed attempt row, dispatch the event
            TrackUserEvent::dispatch(UserEvent::USER_LOGIN_FAILED, ['email' => $email], null, null, $ipAddress, null)->afterCommit();

            // Dispatch security notifications based on threshold
            $this->dispatchSecurityNotifications($email, $ipAddress);
        } else {
            // Clear counters on successful login
            $this->clearCounters($email, $ipAddress);
        }
    }

    /**
     * Check if login is allowed (not rate limited)
     */
    public function isAllowed(string $email, string $ipAddress): array
    {
        $email = strtolower($email);

        // Check email-based limit
        $emailAttempts = $this->getAttemptCount("login_attempts:email:{$email}");
        if ($emailAttempts >= self::MAX_ATTEMPTS_PER_EMAIL) {
            $remainingSeconds = $this->getRemainingLockoutSeconds("login_attempts:email:{$email}");
            return [
                'allowed' => false,
                'reason' => 'TOO_MANY_ATTEMPTS',
                'message' => "Too many login attempts for this email. Please try again in {$this->formatTime($remainingSeconds)}.",
                'retry_after' => $remainingSeconds,
            ];
        }

        // Check IP-based limit
        $ipAttempts = $this->getAttemptCount("login_attempts:ip:{$ipAddress}");
        if ($ipAttempts >= self::MAX_ATTEMPTS_PER_IP) {
            $remainingSeconds = $this->getRemainingLockoutSeconds("login_attempts:ip:{$ipAddress}");
            return [
                'allowed' => false,
                'reason' => 'TOO_MANY_ATTEMPTS_IP',
                'message' => "Too many login attempts from this IP. Please try again in {$this->formatTime($remainingSeconds)}.",
                'retry_after' => $remainingSeconds,
            ];
        }

        return [
            'allowed' => true,
            'remaining_attempts' => min(
                self::MAX_ATTEMPTS_PER_EMAIL - $emailAttempts,
                self::MAX_ATTEMPTS_PER_IP - $ipAttempts
            ),
        ];
    }

    /**
     * Get recent attempts for an email (for security logging)
     */
    public function getRecentAttempts(string $email, int $hours = 24): array
    {
        return DB::table('login_attempts')
            ->where('email', strtolower($email))
            ->where('attempted_at', '>', now()->subHours($hours))
            ->orderByDesc('attempted_at')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Cleanup old attempts (run via scheduler)
     */
    public function cleanupOldAttempts(int $daysToKeep = 7): int
    {
        return DB::table('login_attempts')
            ->where('attempted_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    private function incrementCounter(string $key): void
    {
        $ttl = now()->addMinutes(self::LOCKOUT_MINUTES);

        // Atomically initialise (no-op if key already exists) then increment.
        Cache::add($key, 0, $ttl);
        Cache::increment($key);

        // Store the expiry timestamp alongside the counter so we can compute
        // remaining lockout seconds on any cache driver (file, array, etc.)
        // without relying on the non-portable Store::ttl() method.
        $expiryKey = $key . ':expiry';
        if (!Cache::has($expiryKey)) {
            Cache::put($expiryKey, $ttl->timestamp, $ttl);
        }
    }

    private function getAttemptCount(string $key): int
    {
        return Cache::get($key, 0);
    }

    private function getRemainingLockoutSeconds(string $key): int
    {
        $expiryTimestamp = Cache::get($key . ':expiry');
        $remainingSeconds = $expiryTimestamp ? max(0, $expiryTimestamp - now()->timestamp) : 0;

        // Fall back to the full lockout window when the expiry key has already expired
        // or was never written (e.g. counter was set by a prior code version).
        return $remainingSeconds > 0 ? $remainingSeconds : self::LOCKOUT_MINUTES * 60;
    }

    private function clearCounters(string $email, string $ipAddress): void
    {
        Cache::forget("login_attempts:email:{$email}");
        Cache::forget("login_attempts:email:{$email}:expiry");
        Cache::forget("login_attempts:ip:{$ipAddress}");
        Cache::forget("login_attempts:ip:{$ipAddress}:expiry");
    }

    private function formatTime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }

        $minutes = ceil($seconds / 60);
        return "{$minutes} minute" . ($minutes > 1 ? 's' : '');
    }

    /**
     * Dispatch security notifications when thresholds are crossed.
     * - At exactly MAX_ATTEMPTS_PER_EMAIL: account is now locked → AccountLockedNotification
     * - At the brute-force warning threshold (5+, before lockout): BruteForceAlertNotification
     */
    private function dispatchSecurityNotifications(string $email, string $ipAddress): void
    {
        $emailAttempts = $this->getAttemptCount("login_attempts:email:{$email}");

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return;
        }

        try {
            if ($emailAttempts >= self::MAX_ATTEMPTS_PER_EMAIL) {
                // Threshold crossed — account is now locked
                $user->notify(new AccountLockedNotification($ipAddress, self::LOCKOUT_MINUTES));
            } elseif ($emailAttempts >= self::MAX_ATTEMPTS_PER_EMAIL - 2) {
                // Brute-force warning threshold: warn at 3 failures, before the lockout at 5
                $user->notify(new BruteForceAlertNotification($ipAddress, $emailAttempts, $email));
            }
        } catch (\Throwable $e) {
            Log::warning('LoginAttemptService: failed to dispatch security notification', [
                'email' => $email,
                'ip'    => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
