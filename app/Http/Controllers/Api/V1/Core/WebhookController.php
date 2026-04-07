<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Webhook;
use App\Services\Core\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Get available webhook events.
     */
    public function events(): JsonResponse
    {
        return $this->success([
            'events' => Webhook::EVENTS,
            'events_by_module' => Webhook::getEventsByModule(),
        ], 'Webhook events retrieved successfully');
    }

    /**
     * List organization webhooks.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $webhooks = $this->webhookService->getWebhooks($user->organization_id);

        return $this->success(
            $webhooks->map(fn ($webhook) => [
                'id' => $webhook->id,
                'uuid' => $webhook->uuid,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'is_active' => $webhook->is_active,
                'success_count' => $webhook->success_count,
                'failure_count' => $webhook->failure_count,
                'success_rate' => $webhook->success_rate,
                'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
                'last_success_at' => $webhook->last_success_at?->toIso8601String(),
                'last_failure_at' => $webhook->last_failure_at?->toIso8601String(),
                'created_at' => $webhook->created_at->toIso8601String(),
            ]),
            'Webhooks retrieved successfully'
        );
    }

    /**
     * Get webhook details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        return $this->success([
            'id' => $webhook->id,
            'uuid' => $webhook->uuid,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'secret_masked' => $webhook->masked_secret,
            'events' => $webhook->events,
            'headers' => $webhook->headers,
            'is_active' => $webhook->is_active,
            'retry_count' => $webhook->retry_count,
            'timeout_seconds' => $webhook->timeout_seconds,
            'content_type' => $webhook->content_type,
            'success_count' => $webhook->success_count,
            'failure_count' => $webhook->failure_count,
            'success_rate' => $webhook->success_rate,
            'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
            'last_success_at' => $webhook->last_success_at?->toIso8601String(),
            'last_failure_at' => $webhook->last_failure_at?->toIso8601String(),
            'created_at' => $webhook->created_at->toIso8601String(),
            'created_by' => $webhook->creator?->name,
        ], 'Webhook retrieved successfully');
    }

    /**
     * Create a webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:*,' . implode(',', array_keys(Webhook::EVENTS)),
            'headers' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
            'retry_count' => 'sometimes|integer|min:0|max:10',
            'timeout_seconds' => 'sometimes|integer|min:5|max:60',
        ]);

        $url = $request->input('url');
        $parsedUrl = parse_url($url);
        if (!in_array($parsedUrl['scheme'] ?? '', ['https', 'http'], true)) {
            return $this->error('Webhook URL must use HTTP or HTTPS scheme.', 'INVALID_WEBHOOK_URL', 422);
        }
        if (app()->isProduction() && ($parsedUrl['scheme'] ?? '') !== 'https') {
            return $this->error('Webhook URL must use HTTPS in production.', 'INVALID_WEBHOOK_URL', 422);
        }

        $host = $parsedUrl['host'] ?? '';
        $privatePatterns = ['localhost', '127.', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.', '0.', '::1', '169.254.'];
        foreach ($privatePatterns as $pattern) {
            if (str_starts_with($host, $pattern) || $host === $pattern) {
                return $this->error('Webhook URL cannot target private/local addresses.', 'OPERATION_FAILED', 422);
            }
        }

        $user = $request->user();

        $webhook = $this->webhookService->create(
            $user->organization_id,
            $user,
            $request->get('name'),
            $request->get('url'),
            $request->get('events'),
            [
                'headers' => $request->get('headers'),
                'is_active' => $request->get('is_active', true),
                'retry_count' => $request->get('retry_count', 3),
                'timeout_seconds' => $request->get('timeout_seconds', 30),
            ]
        );

        return $this->created([
            'id' => $webhook->id,
            'uuid' => $webhook->uuid,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'secret' => $webhook->secret,
            'events' => $webhook->events,
        ], 'Webhook created successfully. Please save the secret securely.');
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:*,' . implode(',', array_keys(Webhook::EVENTS)),
            'headers' => 'sometimes|array|nullable',
            'is_active' => 'sometimes|boolean',
            'retry_count' => 'sometimes|integer|min:0|max:10',
            'timeout_seconds' => 'sometimes|integer|min:5|max:60',
        ]);

        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $webhook = $this->webhookService->update($webhook, $request->only([
            'name', 'url', 'events', 'headers', 'is_active', 'retry_count', 'timeout_seconds',
        ]));

        return $this->success([
            'id' => $webhook->id,
            'uuid' => $webhook->uuid,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
        ], 'Webhook updated successfully.');
    }

    /**
     * Delete a webhook.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $this->webhookService->delete($webhook);

        return $this->success(null, 'Webhook deleted successfully.');
    }

    /**
     * Test a webhook endpoint.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $result = $this->webhookService->test($webhook);

        return $this->success(
            $result,
            $result['success'] ? 'Webhook test successful!' : 'Webhook test failed.'
        );
    }

    /**
     * Regenerate webhook secret.
     */
    public function regenerateSecret(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $newSecret = $webhook->regenerateSecret();

        return $this->success([
            'secret' => $newSecret,
        ], 'Webhook secret regenerated. Please update your integration.');
    }

    /**
     * Toggle webhook active status.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $webhook->update(['is_active' => !$webhook->is_active]);

        return $this->success([
            'is_active' => $webhook->is_active,
        ], $webhook->is_active ? 'Webhook enabled.' : 'Webhook disabled.');
    }

    /**
     * Get delivery history for a webhook.
     */
    public function deliveries(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->get('limit', 50), 100);

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $deliveries = $this->webhookService->getDeliveryHistory($webhook, $limit);

        return $this->success(
            $deliveries->map(fn ($d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'event_type' => $d->event_type,
                'status' => $d->status,
                'http_status' => $d->http_status,
                'duration_ms' => $d->duration_ms,
                'attempt' => $d->attempt,
                'error_message' => $d->error_message,
                'created_at' => $d->created_at->toIso8601String(),
            ]),
            'Delivery history retrieved successfully'
        );
    }

    /**
     * Get delivery details.
     */
    public function deliveryDetails(Request $request, int $id, int $deliveryId): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $delivery = $webhook->deliveries()->findOrFail($deliveryId);

        return $this->success([
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'event_type' => $delivery->event_type,
            'payload' => $delivery->payload,
            'status' => $delivery->status,
            'http_status' => $delivery->http_status,
            'response_body' => $delivery->response_body,
            'response_headers' => $delivery->response_headers,
            'duration_ms' => $delivery->duration_ms,
            'attempt' => $delivery->attempt,
            'error_message' => $delivery->error_message,
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'created_at' => $delivery->created_at->toIso8601String(),
        ], 'Delivery details retrieved successfully');
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(Request $request, int $id, int $deliveryId): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $delivery = $webhook->deliveries()->findOrFail($deliveryId);

        if ($delivery->isSuccess()) {
            return $this->error('Cannot retry successful delivery', 'VALIDATION_ERROR', 400);
        }

        $this->webhookService->retryDelivery($delivery);

        return $this->success(null, 'Delivery queued for retry.');
    }

    /**
     * Get recent webhook events.
     */
    public function events_history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->get('limit', 50), 100);

        $events = $this->webhookService->getEventHistory($user->organization_id, $limit);

        return $this->success(
            $events->map(fn ($e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'event_type' => $e->event_type,
                'resource_type' => $e->resource_type,
                'resource_id' => $e->resource_id,
                'webhooks_triggered' => $e->webhooks_triggered,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
            'Event history retrieved successfully'
        );
    }
}
