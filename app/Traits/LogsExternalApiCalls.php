<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Core\ApiCallLog;
use Illuminate\Support\Facades\Log;

/**
 * Wraps an HTTP call in ApiCallLog persistence + structured logging.
 *
 * Usage:
 *   $response = $this->loggedApiCall('GET', $url, fn() => Http::get($url), $orgId);
 */
trait LogsExternalApiCalls
{
    private string $apiServiceName = 'UnknownService';

    protected function loggedApiCall(
        string   $method,
        string   $url,
        callable $call,
        ?int     $organizationId = null,
        array    $requestBody    = [],
        array    $requestHeaders = [],
    ): mixed {
        $start          = microtime(true);
        $status         = 'success';
        $errorMessage   = null;
        $responseStatus = null;
        $responseBody   = null;

        try {
            $result = $call();

            if (method_exists($result, 'status')) {
                $responseStatus = $result->status();
                $responseBody   = $this->safeJson($result->body());
                if ($responseStatus >= 400) {
                    $status       = 'error';
                    $errorMessage = "HTTP {$responseStatus}";
                }
            }

            return $result;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $status       = 'timeout';
            $errorMessage = $e->getMessage();
            throw $e;
        } catch (\Throwable $e) {
            $status       = 'error';
            $errorMessage = $e->getMessage();
            throw $e;
        } finally {
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            try {
                ApiCallLog::create([
                    'organization_id' => $organizationId,
                    'service'         => $this->apiServiceName,
                    'method'          => strtoupper($method),
                    'url'             => $url,
                    'request_headers' => $this->sanitizeHeaders($requestHeaders),
                    'request_body'    => $this->sanitizeBody($requestBody),
                    'response_status' => $responseStatus,
                    'response_body'   => $responseBody,
                    'duration_ms'     => $durationMs,
                    'status'          => $status,
                    'error_message'   => $errorMessage,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ApiCallLog persist failed', ['error' => $e->getMessage()]);
            }
        }
    }

    private function safeJson(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : ['raw' => substr($body, 0, 500)];
        } catch (\JsonException) {
            return ['raw' => substr($body, 0, 500)];
        }
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key', 'x-auth-token'];
        $result    = [];
        foreach ($headers as $k => $v) {
            $result[$k] = in_array(strtolower($k), $sensitive, true) ? '[REDACTED]' : $v;
        }
        return $result;
    }

    private function sanitizeBody(array $body): array
    {
        $sensitive = ['password', 'secret', 'token', 'api_key', 'private_key'];
        foreach ($sensitive as $key) {
            if (isset($body[$key])) {
                $body[$key] = '[REDACTED]';
            }
        }
        return $body;
    }
}
