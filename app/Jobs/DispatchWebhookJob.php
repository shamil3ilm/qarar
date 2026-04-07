<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Core\WebhookDelivery;
use App\Services\Core\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 600, 1800, 3600];
    public int $timeout = 60;
    public int $uniqueFor = 300; // 5 minutes

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function __construct(
        protected int $deliveryId
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);

        if (!$delivery) {
            return;
        }

        // Skip if webhook has been deleted or is no longer active
        if (!$delivery->webhook) {
            Log::warning('DispatchWebhookJob: webhook no longer exists', ['delivery_id' => $this->deliveryId]);
            $delivery->update(['status' => 'failed', 'error_message' => 'Webhook was deleted']);
            return;
        }

        if (!$delivery->webhook->is_active) {
            $delivery->markAsFailed('Webhook is disabled');
            return;
        }

        $webhookService->sendWebhook($delivery);
    }

    public function failed(\Throwable $exception): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if ($delivery) {
            $delivery->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
