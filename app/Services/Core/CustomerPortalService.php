<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\PortalActivityLog;
use App\Models\Core\PortalDocumentAccess;
use App\Models\Core\PortalSession;
use App\Models\Core\PortalUser;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Notifications\Core\PortalPasswordResetNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class CustomerPortalService
{
    private const SESSION_TTL_HOURS       = 8;
    private const RESET_TOKEN_TTL_MINUTES = 60;

    /**
     * Register a new portal user linked to an existing contact.
     */
    public function register(int $contactId, string $email, string $password): PortalUser
    {
        $contact = Contact::findOrFail($contactId);

        if (PortalUser::where('organization_id', $contact->organization_id)
            ->where('email', $email)
            ->exists()
        ) {
            throw new RuntimeException('A portal account with this email already exists.');
        }

        return PortalUser::create([
            'organization_id' => $contact->organization_id,
            'contact_id'      => $contactId,
            'email'           => $email,
            'password_hash'   => Hash::make($password),
            'is_active'       => true,
        ]);
    }

    /**
     * Authenticate a portal user and create a session token.
     *
     * @return array{token: string, portal_user: PortalUser, expires_at: string}
     */
    public function login(int $organizationId, string $email, string $password): array
    {
        $portalUser = PortalUser::where('organization_id', $organizationId)
            ->where('email', $email)
            ->active()
            ->first();

        if (! $portalUser || ! Hash::check($password, $portalUser->password_hash)) {
            throw new RuntimeException('Invalid email or password.');
        }

        $token   = Str::random(64);
        $expires = now()->addHours(self::SESSION_TTL_HOURS);

        PortalSession::create([
            'organization_id'  => $organizationId,
            'portal_user_id'   => $portalUser->id,
            'session_token'    => $token,
            'ip_address'       => request()->ip(),
            'user_agent'       => request()->userAgent(),
            'expires_at'       => $expires,
            'last_activity_at' => now(),
        ]);

        $portalUser->update([
            'last_login_at' => now(),
            'login_count'   => $portalUser->login_count + 1,
        ]);

        $this->logActivity($portalUser->id, 'login', 'User logged into the portal.');

        return [
            'token'       => $token,
            'portal_user' => $portalUser->fresh(),
            'expires_at'  => $expires->toISOString(),
        ];
    }

    /**
     * Invalidate a portal session token.
     */
    public function logout(string $token): void
    {
        PortalSession::where('session_token', $token)->delete();
    }

    /**
     * Get invoices for the contact linked to a portal user.
     */
    public function getCustomerInvoices(int $portalUserId): Collection
    {
        $portalUser = PortalUser::with('contact')->findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_INVOICE, 0);

        return Invoice::where('customer_id', $portalUser->contact_id)
            ->whereIn('status', ['sent', 'partial', 'paid', 'overdue'])
            ->orderByDesc('invoice_date')
            ->get();
    }

    /**
     * Get sales orders for the contact linked to a portal user.
     */
    public function getCustomerOrders(int $portalUserId): Collection
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_ORDER, 0);

        return SalesOrder::where('customer_id', $portalUser->contact_id)
            ->orderByDesc('order_date')
            ->get();
    }

    /**
     * Get quotations for the contact linked to a portal user.
     */
    public function getCustomerQuotations(int $portalUserId): Collection
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_QUOTATION, 0);

        return Quotation::where('customer_id', $portalUser->contact_id)
            ->orderByDesc('quotation_date')
            ->get();
    }

    /**
     * Build an account statement summary for the portal user's contact.
     *
     * @return array{contact: Contact, outstanding_balance: float, invoices: Collection}
     */
    public function getCustomerStatement(int $portalUserId): array
    {
        $portalUser = PortalUser::with('contact')->findOrFail($portalUserId);
        $contact    = $portalUser->contact;

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_STATEMENT, 0);
        $this->logActivity($portalUserId, 'statement_viewed', 'Customer viewed account statement.');

        $invoices = Invoice::where('customer_id', $contact->id)
            ->orderByDesc('invoice_date')
            ->get();

        return [
            'contact'             => $contact,
            'outstanding_balance' => $contact->getOutstandingBalance(),
            'invoices'            => $invoices,
        ];
    }

    /**
     * Issue a password-reset token and (in production) dispatch an email.
     */
    public function requestPasswordReset(string $email, int $organizationId): void
    {
        $portalUser = PortalUser::where('organization_id', $organizationId)
            ->where('email', $email)
            ->active()
            ->first();

        // Silently succeed even if user not found to prevent enumeration
        if (! $portalUser) {
            return;
        }

        $token = Str::random(64);

        $portalUser->update([
            'password_reset_token'      => hash('sha256', $token),
            'password_reset_expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ]);

        $this->logActivity($portalUser->id, 'password_reset_requested', 'Password reset token generated.');

        $portalUser->notify(new PortalPasswordResetNotification($token));
    }

    /**
     * Reset a portal user's password using a valid token.
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        $portalUser = PortalUser::where('password_reset_token', hash('sha256', $token))->first();

        if (! $portalUser || ! $portalUser->isPasswordResetTokenValid()) {
            throw new RuntimeException('Invalid or expired password reset token.');
        }

        $portalUser->update([
            'password_hash'             => Hash::make($newPassword),
            'password_reset_token'      => null,
            'password_reset_expires_at' => null,
        ]);

        // Invalidate all active sessions after password change
        PortalSession::where('portal_user_id', $portalUser->id)->delete();

        $this->logActivity($portalUser->id, 'password_reset', 'Password was reset via token.');
    }

    /**
     * Append an activity log entry for a portal user.
     */
    public function logActivity(int $portalUserId, string $type, string $description, array $metadata = []): void
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        PortalActivityLog::create([
            'organization_id' => $portalUser->organization_id,
            'portal_user_id'  => $portalUserId,
            'activity_type'   => $type,
            'description'     => $description,
            'metadata'        => $metadata ?: null,
            'created_at'      => now(),
        ]);
    }

    /**
     * Resolve a portal user from a session token.
     */
    public function getUserFromToken(string $token): ?PortalUser
    {
        $session = PortalSession::where('session_token', $token)
            ->active()
            ->first();

        if (! $session) {
            return null;
        }

        $session->update(['last_activity_at' => now()]);

        return $session->portalUser;
    }

    /**
     * Dashboard summary for a portal user's contact.
     *
     * @return array{open_invoices_count: int, open_invoices_total: float, outstanding_balance: float, recent_orders: Collection}
     */
    public function getDashboard(int $portalUserId): array
    {
        $portalUser = PortalUser::with('contact')->findOrFail($portalUserId);
        $contactId  = $portalUser->contact_id;

        $openInvoices = Invoice::where('customer_id', $contactId)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount_due), 0) as total')
            ->first();

        $recentOrders = SalesOrder::where('customer_id', $contactId)
            ->orderByDesc('order_date')
            ->limit(5)
            ->get(['id', 'uuid', 'order_number', 'order_date', 'status', 'total']);

        return [
            'open_invoices_count' => (int) ($openInvoices->count ?? 0),
            'open_invoices_total' => (float) ($openInvoices->total ?? 0),
            'outstanding_balance' => $portalUser->contact?->getOutstandingBalance() ?? 0.0,
            'recent_orders'       => $recentOrders,
        ];
    }

    /**
     * Paginated invoices with optional filters.
     */
    public function getCustomerInvoicesPaginated(int $portalUserId, array $filters = []): LengthAwarePaginator
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_INVOICE, 0);

        $query = Invoice::where('customer_id', $portalUser->contact_id)
            ->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_PAID,
                Invoice::STATUS_OVERDUE,
            ])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['start_date']), fn ($q) => $q->where('invoice_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn ($q) => $q->where('invoice_date', '<=', $filters['end_date']))
            ->orderByDesc('invoice_date');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Get a single invoice for a contact, verifying ownership.
     */
    public function getCustomerInvoice(int $portalUserId, int $invoiceId): ?Invoice
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $invoice = Invoice::where('customer_id', $portalUser->contact_id)
            ->with('lines')
            ->find($invoiceId);

        if ($invoice) {
            $this->logActivity($portalUserId, 'invoice_viewed', "Viewed invoice #{$invoice->invoice_number}");
        }

        return $invoice;
    }

    /**
     * Paginated sales orders with optional filters.
     */
    public function getCustomerOrdersPaginated(int $portalUserId, array $filters = []): LengthAwarePaginator
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_ORDER, 0);

        $query = SalesOrder::where('customer_id', $portalUser->contact_id)
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('order_date');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Get a single sales order for a contact, verifying ownership.
     */
    public function getCustomerOrder(int $portalUserId, int $orderId): ?SalesOrder
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        return SalesOrder::where('customer_id', $portalUser->contact_id)
            ->with('lines')
            ->find($orderId);
    }

    /**
     * Paginated quotations.
     */
    public function getCustomerQuotationsPaginated(int $portalUserId, array $filters = []): LengthAwarePaginator
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $this->recordDocumentAccess($portalUser, PortalDocumentAccess::TYPE_QUOTATION, 0);

        $query = Quotation::where('customer_id', $portalUser->contact_id)
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('quotation_date');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Accept a quotation on behalf of the customer, verifying ownership.
     */
    public function acceptQuotation(int $portalUserId, Quotation $quotation): Quotation
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        if ($quotation->customer_id !== $portalUser->contact_id) {
            throw new \InvalidArgumentException('Quotation does not belong to this customer.');
        }

        if (! in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT], true)) {
            throw new \InvalidArgumentException('Quotation cannot be accepted in its current status.');
        }

        $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
        $this->logActivity($portalUserId, 'quotation_accepted', "Accepted quotation #{$quotation->quotation_number}");

        return $quotation->fresh();
    }

    /**
     * Decline a quotation on behalf of the customer, verifying ownership.
     */
    public function declineQuotation(int $portalUserId, Quotation $quotation): Quotation
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        if ($quotation->customer_id !== $portalUser->contact_id) {
            throw new \InvalidArgumentException('Quotation does not belong to this customer.');
        }

        if (! in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT], true)) {
            throw new \InvalidArgumentException('Quotation cannot be declined in its current status.');
        }

        $quotation->update(['status' => Quotation::STATUS_DECLINED]);
        $this->logActivity($portalUserId, 'quotation_declined', "Declined quotation #{$quotation->quotation_number}");

        return $quotation->fresh();
    }

    /**
     * Paginated payment history for a customer.
     */
    public function getCustomerPayments(int $portalUserId, array $filters = []): LengthAwarePaginator
    {
        $portalUser = PortalUser::findOrFail($portalUserId);

        $query = PaymentReceived::where('customer_id', $portalUser->contact_id)
            ->when(isset($filters['start_date']), fn ($q) => $q->where('payment_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn ($q) => $q->where('payment_date', '<=', $filters['end_date']))
            ->orderByDesc('payment_date');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Total outstanding balance across all open invoices for a contact.
     */
    public function getOutstandingBalance(int $portalUserId): float
    {
        $portalUser = PortalUser::with('contact')->findOrFail($portalUserId);

        return $portalUser->contact?->getOutstandingBalance() ?? 0.0;
    }

    private function recordDocumentAccess(PortalUser $portalUser, string $type, int $documentId): void
    {
        PortalDocumentAccess::create([
            'organization_id' => $portalUser->organization_id,
            'portal_user_id'  => $portalUser->id,
            'document_type'   => $type,
            'document_id'     => $documentId,
            'accessed_at'     => now(),
            'ip_address'      => request()->ip(),
        ]);
    }
}
