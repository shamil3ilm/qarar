<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\CompliPayClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupZatcaIntegration extends Command
{
    protected $signature = 'zatca:setup';

    protected $description = 'Configure and verify the ZATCA compliance integration';

    public function handle(): int
    {
        if (!(bool) config('zatca-integration.enabled', true)) {
            $this->info('ZATCA integration is disabled');

            return self::SUCCESS;
        }

        $url = (string) config('zatca-integration.url', '');
        $apiKey = (string) config('zatca-integration.api_key', '');
        $webhookSecret = (string) config('zatca-integration.webhook_secret', '');

        // Validate configuration
        if (empty($url)) {
            $this->warn('ZATCA_INTEGRATION_URL is not set.');
        }

        if (empty($apiKey)) {
            $this->warn('ZATCA_INTEGRATION_API_KEY is not set.');
        }

        if (empty($webhookSecret)) {
            $this->warn('ZATCA_INTEGRATION_WEBHOOK_SECRET is not set.');
        }

        // Test connectivity
        $connectivityStatus = 'FAILED';

        try {
            $response = Http::timeout(10)->get(rtrim($url, '/') . '/health');
            $connectivityStatus = $response->successful() ? 'OK' : 'FAILED (HTTP ' . $response->status() . ')';
        } catch (\Exception $e) {
            $connectivityStatus = 'FAILED (' . $e->getMessage() . ')';
        }

        if (str_starts_with($connectivityStatus, 'OK')) {
            $this->info('Connectivity test passed.');
        } else {
            $this->error('Connectivity test failed: ' . $connectivityStatus);
        }

        // Register webhook
        $webhookStatus = 'FAILED';

        try {
            /** @var CompliPayClient $client */
            $client = app(CompliPayClient::class);

            $result = $client->registerWebhook(
                url('/api/v1/webhooks/zatca'),
                ['invoice.cleared', 'invoice.reported', 'invoice.rejected', 'invoice.issued'],
                $webhookSecret
            );

            $webhookStatus = $result->status !== 'error' ? 'OK' : 'FAILED (' . ($result->message ?? 'unknown') . ')';
        } catch (\Exception $e) {
            $webhookStatus = 'FAILED (' . $e->getMessage() . ')';
        }

        if (str_starts_with($webhookStatus, 'OK')) {
            $this->info('Webhook registered successfully.');
        } else {
            $this->error('Webhook registration failed: ' . $webhookStatus);
        }

        // Output summary table
        $maskedApiKey = !empty($apiKey)
            ? substr($apiKey, 0, 8) . '****'
            : 'NOT SET';

        $maskedWebhookSecret = !empty($webhookSecret) ? '****' : 'NOT SET';

        $this->table(
            ['Setting', 'Value'],
            [
                ['URL', $url ?: 'NOT SET'],
                ['API Key', $maskedApiKey],
                ['Webhook Secret', $maskedWebhookSecret],
                ['Connectivity', $connectivityStatus],
                ['Webhook Registration', $webhookStatus],
            ]
        );

        return self::SUCCESS;
    }
}
