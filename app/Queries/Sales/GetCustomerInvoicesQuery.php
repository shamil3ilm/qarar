<?php

declare(strict_types=1);

namespace App\Queries\Sales;

use App\Models\Sales\Invoice;
use App\Queries\Contracts\Query;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Encapsulates the query for retrieving a customer's invoices with filters.
 *
 * Usage:
 *   $paginator = (new GetCustomerInvoicesQuery(
 *       organizationId: $orgId,
 *       customerId: $customerId,
 *       status: 'sent',
 *       perPage: 25,
 *   ))->execute();
 */
class GetCustomerInvoicesQuery implements Query
{
    public function __construct(
        private readonly int     $organizationId,
        private readonly ?int    $customerId = null,
        private readonly ?string $status = null,
        private readonly ?string $fromDate = null,
        private readonly ?string $toDate = null,
        private readonly int     $perPage = 25,
        private readonly string  $sortBy = 'invoice_date',
        private readonly string  $sortDir = 'desc',
    ) {}

    public function execute(): LengthAwarePaginator
    {
        $allowedSorts = ['invoice_date', 'due_date', 'total', 'invoice_number', 'created_at'];
        $sortBy = in_array($this->sortBy, $allowedSorts, true) ? $this->sortBy : 'invoice_date';

        return Invoice::with(['customer:id,name,email', 'lines:id,invoice_id,description,quantity,unit_price,tax_rate'])
            ->where('organization_id', $this->organizationId)
            ->when($this->customerId, fn($q) => $q->where('customer_id', $this->customerId))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->fromDate, fn($q) => $q->where('invoice_date', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->where('invoice_date', '<=', $this->toDate))
            ->orderBy($sortBy, $this->sortDir === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);
    }
}
