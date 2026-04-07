<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\DunningBlock;
use App\Models\Accounting\DunningLevel;
use App\Models\Accounting\DunningNotice;
use App\Models\Accounting\DunningNoticeItem;
use App\Models\Accounting\DunningRun;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DunningService
{
    /**
     * Execute a dunning run for the given organization and date.
     * Evaluates all overdue contacts and generates dunning notices.
     */
    public function runDunning(Organization $organization, Carbon $runDate): DunningRun
    {
        return DB::transaction(function () use ($organization, $runDate) {
            $run = DunningRun::create([
                'organization_id' => $organization->id,
                'run_date'        => $runDate->toDateString(),
                'status'          => DunningRun::STATUS_DRAFT,
                'total_customers' => 0,
                'total_amount'    => 0,
                'created_by'      => auth()->id(),
            ]);

            // Step 1: DB-level customer summary (max days overdue + total) — avoids
            //         loading all invoice rows into memory at once.
            $runDateStr = $runDate->toDateString();

            $customerSummaries = DB::table('invoices')
                ->where('organization_id', $organization->id)
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->where('due_date', '<', $runDateStr)
                ->where('amount_due', '>', 0)
                ->selectRaw('customer_id, MAX(DATEDIFF(?, due_date)) as max_days_overdue, SUM(amount_due) as total_overdue, MIN(currency_code) as currency_code', [$runDateStr])
                ->groupBy('customer_id')
                ->get();

            $totalCustomers = 0;
            $totalAmount    = '0';

            foreach ($customerSummaries as $summary) {
                $customerId = $summary->customer_id;

                $contact = Contact::find($customerId);
                if (!$contact) {
                    continue;
                }

                // Skip if customer has an active dunning block
                $activeBlock = DunningBlock::where('organization_id', $organization->id)
                    ->where('contact_id', $customerId)
                    ->active()
                    ->first();

                $level = DunningLevel::forDaysOverdue($organization->id, (int) $summary->max_days_overdue);

                if (!$level) {
                    continue;
                }

                $notice = DunningNotice::create([
                    'dunning_run_id'   => $run->id,
                    'contact_id'       => $customerId,
                    'dunning_level_id' => $level->id,
                    'total_overdue'    => $summary->total_overdue,
                    'currency_code'    => $summary->currency_code ?? 'SAR',
                    'notice_date'      => $runDateStr,
                    'status'           => $activeBlock ? DunningNotice::STATUS_BLOCKED : DunningNotice::STATUS_PENDING,
                    'blocking_reason'  => $activeBlock ? $activeBlock->reason : null,
                ]);

                // Step 2: fetch only this customer's invoices (small per-customer set).
                $invoices = Invoice::where('organization_id', $organization->id)
                    ->where('customer_id', $customerId)
                    ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                    ->where('due_date', '<', $runDateStr)
                    ->where('amount_due', '>', 0)
                    ->get();

                foreach ($invoices as $invoice) {
                    DunningNoticeItem::create([
                        'dunning_notice_id'  => $notice->id,
                        'invoice_id'         => $invoice->id,
                        'invoice_number'     => $invoice->invoice_number,
                        'invoice_date'       => $invoice->invoice_date->toDateString(),
                        'due_date'           => $invoice->due_date->toDateString(),
                        'original_amount'    => $invoice->total,
                        'outstanding_amount' => $invoice->amount_due,
                        'days_overdue'       => $invoice->getDaysPastDue(),
                    ]);
                }

                $totalCustomers++;
                $totalAmount = bcadd($totalAmount, (string) $summary->total_overdue, 4);
            }

            $run->update([
                'total_customers' => $totalCustomers,
                'total_amount'    => $totalAmount,
            ]);

            return $run->fresh(['notices']);
        });
    }

    /**
     * Post a dunning run (mark as posted).
     */
    public function postRun(DunningRun $run): DunningRun
    {
        if (!$run->isDraft()) {
            throw new InvalidArgumentException('Only draft dunning runs can be posted.');
        }

        $run->update([
            'status'    => DunningRun::STATUS_POSTED,
            'posted_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Send (mark as sent) all pending notices in the given run.
     */
    public function sendNotices(DunningRun $run): int
    {
        if (!$run->isPosted()) {
            throw new InvalidArgumentException('Only posted dunning runs can have notices sent.');
        }

        $sent = 0;

        $run->notices()
            ->where('status', DunningNotice::STATUS_PENDING)
            ->with(['contact', 'dunningLevel', 'items'])
            ->get()
            ->each(function (DunningNotice $notice) use (&$sent) {
                // In a real system this would dispatch a mailable/notification.
                // Here we mark the notice as sent.
                $notice->update([
                    'status'  => DunningNotice::STATUS_SENT,
                    'sent_at' => now(),
                ]);
                $sent++;
            });

        return $sent;
    }

    /**
     * Place a dunning block on a customer.
     */
    public function blockCustomer(Contact $contact, array $data): DunningBlock
    {
        return DunningBlock::create([
            'organization_id' => $contact->organization_id,
            'contact_id'      => $contact->id,
            'blocked_until'   => $data['blocked_until'] ?? null,
            'reason'          => $data['reason'],
            'blocked_by'      => auth()->id(),
        ]);
    }

    /**
     * Release an existing dunning block.
     */
    public function releaseBlock(DunningBlock $block, string $releaseReason): DunningBlock
    {
        if (!$block->isActive()) {
            throw new InvalidArgumentException('This dunning block is already released or expired.');
        }

        $block->update([
            'released_at'    => now(),
            'released_by'    => auth()->id(),
            'release_reason' => $releaseReason,
        ]);

        return $block->fresh();
    }
}
