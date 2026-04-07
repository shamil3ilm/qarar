<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEvent extends Model
{
    use HasUuid;

    // Disable automatic timestamp management; this model is immutable (insert-only).
    public $timestamps = false;

    // Keep created_at so Eloquent populates it on insert via useCurrent() default.
    const CREATED_AT = 'created_at';

    // Auth events
    const USER_REGISTERED   = 'user.registered';
    const USER_LOGIN        = 'user.login';
    const USER_LOGOUT       = 'user.logout';
    const USER_LOGIN_FAILED = 'user.login_failed';
    const USER_PASSWORD_RESET = 'user.password_reset';
    const USER_2FA_ENABLED  = 'user.2fa_enabled';
    const USER_2FA_DISABLED = 'user.2fa_disabled';
    const EMAIL_VERIFIED    = 'email.verified';

    // Session events
    const SESSION_STARTED = 'session.started';
    const SESSION_ENDED   = 'session.ended';

    // Billing events
    const SUBSCRIPTION_CREATED = 'subscription.created';
    const PAYMENT_SUCCEEDED    = 'payment.succeeded';
    const PAYMENT_FAILED       = 'payment.failed';

    // Sales events
    const INVOICE_CREATED    = 'invoice.created';
    const INVOICE_SENT       = 'invoice.sent';
    const INVOICE_PAID       = 'invoice.paid';
    const PAYMENT_RECEIVED   = 'payment.received';
    const QUOTATION_CREATED  = 'quotation.created';

    // Purchase events
    const PURCHASE_ORDER_CREATED  = 'purchase_order.created';
    const PURCHASE_ORDER_APPROVED = 'purchase_order.approved';
    const BILL_CREATED            = 'bill.created';

    // HR events
    const LEAVE_REQUESTED   = 'leave.requested';
    const LEAVE_APPROVED    = 'leave.approved';
    const LEAVE_REJECTED    = 'leave.rejected';
    const PAYROLL_PROCESSED = 'payroll.processed';

    // Inventory events
    const STOCK_ADJUSTED   = 'stock.adjusted';
    const LOW_STOCK_ALERT  = 'stock.low_alert';

    // Security events
    const ACCOUNT_LOCKED      = 'account.locked';
    const PASSWORD_CHANGED    = 'security.password_changed';
    const TWO_FACTOR_ENABLED  = 'security.two_factor_enabled';
    const TWO_FACTOR_DISABLED = 'security.two_factor_disabled';
    const API_KEY_CREATED     = 'security.api_key_created';

    // Billing/subscription events
    const SUBSCRIPTION_STARTED   = 'subscription.started';
    const SUBSCRIPTION_RENEWED   = 'subscription.renewed';
    const SUBSCRIPTION_CANCELLED = 'subscription.cancelled';

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'event_type',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
