<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\EmailLog;
use App\Models\Core\EmailTemplate;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send an email using a template.
     */
    public function sendTemplate(
        string $templateCode,
        string $toEmail,
        array $data,
        ?string $toName = null,
        ?int $organizationId = null,
        ?Model $emailable = null,
        array $attachments = [],
        string $language = 'en',
        ?int $userId = null
    ): EmailLog {
        $organizationId = $organizationId ?? auth()->user()?->organization_id;
        $userId = $userId ?? auth()->id();

        // Get the template
        $template = EmailTemplate::getTemplate($templateCode, $organizationId, $language);

        if (!$template) {
            throw new \RuntimeException("Email template not found: {$templateCode}");
        }

        // Render the template
        $rendered = $template->render($data);

        // Create log entry
        $log = EmailLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'template_code' => $templateCode,
            'emailable_type' => $emailable ? get_class($emailable) : null,
            'emailable_id' => $emailable?->id,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $rendered['subject'],
            'body_preview' => substr(strip_tags($rendered['body_html']), 0, 500),
            'attachments' => $attachments ? array_map(fn($a) => $a['name'] ?? basename($a['path'] ?? ''), $attachments) : null,
            'status' => EmailLog::STATUS_PENDING,
        ]);

        try {
            // Send the email
            $this->send(
                $toEmail,
                $toName,
                $rendered['subject'],
                $rendered['body_html'],
                $rendered['body_text'],
                $attachments,
                $rendered['from_name'],
                $rendered['reply_to'],
                $rendered['cc'],
                $rendered['bcc']
            );

            $log->markAsSent();
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            $log->markAsFailed($e->getMessage());
            throw $e;
        }

        return $log;
    }

    /**
     * Queue an email for later sending.
     */
    public function queueTemplate(
        string $templateCode,
        string $toEmail,
        array $data,
        ?string $toName = null,
        ?int $organizationId = null,
        ?Model $emailable = null,
        array $attachments = [],
        string $language = 'en',
        ?int $userId = null
    ): EmailLog {
        $organizationId = $organizationId ?? auth()->user()?->organization_id;
        $userId = $userId ?? auth()->id();

        // Get the template
        $template = EmailTemplate::getTemplate($templateCode, $organizationId, $language);

        if (!$template) {
            throw new \RuntimeException("Email template not found: {$templateCode}");
        }

        // Render the template
        $rendered = $template->render($data);

        // Create log entry
        $log = EmailLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'template_code' => $templateCode,
            'emailable_type' => $emailable ? get_class($emailable) : null,
            'emailable_id' => $emailable?->id,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $rendered['subject'],
            'body_preview' => substr(strip_tags($rendered['body_html']), 0, 500),
            'attachments' => $attachments ? array_map(fn($a) => $a['name'] ?? basename($a['path'] ?? ''), $attachments) : null,
            'status' => EmailLog::STATUS_QUEUED,
        ]);

        // Dispatch job — only after the surrounding DB transaction commits
        \App\Jobs\SendQueuedEmail::dispatch($log->id, $rendered, $attachments)->afterCommit();

        return $log;
    }

    /**
     * Send a raw email without template.
     */
    public function sendRaw(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $toName = null,
        ?int $organizationId = null,
        ?Model $emailable = null,
        array $attachments = [],
        ?int $userId = null
    ): EmailLog {
        $organizationId = $organizationId ?? auth()->user()?->organization_id;
        $userId = $userId ?? auth()->id();

        // Create log entry
        $log = EmailLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'emailable_type' => $emailable ? get_class($emailable) : null,
            'emailable_id' => $emailable?->id,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'body_preview' => substr(strip_tags($bodyHtml), 0, 500),
            'attachments' => $attachments ? array_map(fn($a) => $a['name'] ?? basename($a['path'] ?? ''), $attachments) : null,
            'status' => EmailLog::STATUS_PENDING,
        ]);

        try {
            $this->send($toEmail, $toName, $subject, $bodyHtml, strip_tags($bodyHtml), $attachments);
            $log->markAsSent();
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            $log->markAsFailed($e->getMessage());
            throw $e;
        }

        return $log;
    }

    /**
     * Low-level send method.
     */
    protected function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        array $attachments = [],
        ?string $fromName = null,
        ?string $replyTo = null,
        ?string $cc = null,
        ?string $bcc = null
    ): void {
        $mailable = new class($subject, $bodyHtml, $bodyText, $attachments, $fromName, $replyTo) extends Mailable {
            public function __construct(
                public string $emailSubject,
                public string $bodyHtml,
                public ?string $bodyText,
                public array $emailAttachments,
                public ?string $fromName,
                public ?string $replyToEmail
            ) {}

            public function build()
            {
                $mail = $this->subject($this->emailSubject)
                    ->html($this->bodyHtml);

                if ($this->bodyText) {
                    $mail->text('emails.plain', ['content' => $this->bodyText]);
                }

                if ($this->fromName) {
                    $mail->from(config('mail.from.address'), $this->fromName);
                }

                if ($this->replyToEmail) {
                    if (!filter_var($this->replyToEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException("Invalid reply-to email address: {$this->replyToEmail}");
                    }
                    $mail->replyTo($this->replyToEmail);
                }

                foreach ($this->emailAttachments as $attachment) {
                    if (isset($attachment['path'])) {
                        $allowedBasePath = storage_path('app');
                        $realPath = realpath($attachment['path']);
                        if ($realPath === false || !str_starts_with($realPath, $allowedBasePath)) {
                            throw new \App\Exceptions\ApiException("Attachment path is not allowed: {$attachment['path']}");
                        }
                        $mail->attach($attachment['path'], [
                            'as' => $attachment['name'] ?? null,
                            'mime' => $attachment['mime'] ?? null,
                        ]);
                    } elseif (isset($attachment['data'])) {
                        $mail->attachData(
                            $attachment['data'],
                            $attachment['name'],
                            ['mime' => $attachment['mime'] ?? 'application/pdf']
                        );
                    }
                }

                return $mail;
            }
        };

        $to = $toName ? [$toEmail => $toName] : $toEmail;
        $message = Mail::to($to);

        if ($cc) {
            $message->cc(array_map('trim', explode(',', $cc)));
        }

        if ($bcc) {
            $message->bcc(array_map('trim', explode(',', $bcc)));
        }

        $message->send($mailable);
    }

    /**
     * Get email statistics for an organization.
     */
    public function getStatistics(int $organizationId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = EmailLog::where('organization_id', $organizationId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $sent = (clone $query)->whereIn('status', [EmailLog::STATUS_SENT, EmailLog::STATUS_DELIVERED, EmailLog::STATUS_OPENED, EmailLog::STATUS_CLICKED])->count();
        $failed = (clone $query)->whereIn('status', [EmailLog::STATUS_FAILED, EmailLog::STATUS_BOUNCED])->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'opened' => $opened,
            'clicked' => $clicked,
            'delivery_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 2) : 0,
            'click_rate' => $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0,
        ];
    }

    /**
     * Dispatch a queued email notification for a sent invoice.
     *
     * Uses the 'invoice_sent' template when available. Falls back to a
     * structured log entry when no template is configured, so callers
     * never receive an exception from a missing template at this stage.
     *
     * Called from GenerateInvoiceDocumentJob — runs on a queue worker,
     * not in the HTTP request lifecycle.
     */
    public function sendInvoiceEmail(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        if ($customer === null || empty($customer->email)) {
            Log::info('EmailService: sendInvoiceEmail skipped — no customer email', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        Log::info('EmailService: sendInvoiceEmail called', ['invoice_id' => $invoice->id]);

        // Dispatch a queued email notification for the invoice.
        // Implementation depends on the mailable/notification setup;
        // wire a concrete template here once 'invoice_sent' is defined.
        try {
            $this->queueTemplate(
                'invoice_sent',
                $customer->email,
                [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name'  => $customer->getDisplayName(),
                    'total'          => $invoice->total,
                    'due_date'       => $invoice->due_date?->toDateString(),
                    'currency'       => $invoice->currency_code,
                ],
                $customer->getDisplayName(),
                $invoice->organization_id,
                $invoice,
            );
        } catch (\RuntimeException $e) {
            // Template not yet configured — log and skip rather than surfacing
            // an error that would fail the GenerateInvoiceDocumentJob retry loop.
            Log::warning('EmailService: invoice_sent template not found, skipping email', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retry a failed email.
     *
     * EmailLog does not store the original rendered body or template data, so
     * it is impossible to re-render and re-send the email automatically.
     * Callers must re-invoke sendTemplate() / sendRaw() with fresh data.
     */
    public function retry(EmailLog $log, ?int $userId = null): EmailLog
    {
        if (!$log->isFailed()) {
            throw new \RuntimeException('Can only retry failed emails');
        }

        throw new \App\Exceptions\ApiException(
            'Cannot retry: original email data not available. Re-send manually.'
        );
    }
}
