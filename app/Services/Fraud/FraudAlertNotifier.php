<?php

declare(strict_types=1);

namespace App\Services\Fraud;

use App\Models\Core\Role;
use App\Models\Fraud\FraudAlert;
use App\Models\User;
use App\Notifications\Fraud\FraudAlertNotification;
use Illuminate\Support\Facades\Log;

class FraudAlertNotifier
{
    /**
     * Notify all admin and super-admin users in the organization about a fraud alert.
     */
    public function notifyAdmins(FraudAlert $alert): void
    {
        try {
            $adminUsers = User::where('organization_id', $alert->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', function ($query): void {
                    $query->whereIn('slug', ['admin', 'super_admin']);
                })
                ->get();

            foreach ($adminUsers as $user) {
                try {
                    $user->notify(new FraudAlertNotification($alert));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send fraud alert notification to user', [
                        'user_id'  => $user->id,
                        'alert_id' => $alert->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('FraudAlertNotifier::notifyAdmins failed', [
                'alert_id' => $alert->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
