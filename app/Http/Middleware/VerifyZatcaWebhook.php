<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyZatcaWebhook
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 60; // 1 minute

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('zatca-integration.webhook_secret', '');
        if (empty($secret)) {
            \Illuminate\Support\Facades\Log::critical('ZATCA webhook secret is not configured', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Webhook endpoint not configured.'], 503);
        }

        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');

        if (empty($signature) || empty($timestamp)) {
            return $this->missingHeaders();
        }

        if (!$this->isTimestampFresh((string) $timestamp)) {
            return $this->invalidSignature('Webhook timestamp is stale or invalid');
        }

        $rawBody = $request->getContent();

        if (!$this->isSignatureValid((string) $signature, $rawBody, $secret)) {
            return $this->invalidSignature('Webhook signature verification failed');
        }

        return $next($request);
    }

    private function isTimestampFresh(string $timestamp): bool
    {
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $diff = abs(time() - (int) $timestamp);

        return $diff <= self::TIMESTAMP_TOLERANCE_SECONDS;
    }

    private function isSignatureValid(string $signature, string $rawBody, string $secret): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    private function missingHeaders(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MISSING_WEBHOOK_HEADERS',
                'message' => 'Required webhook headers X-Webhook-Signature and X-Webhook-Timestamp are missing',
            ],
        ], 400);
    }

    private function invalidSignature(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' => $message,
            ],
        ], 401);
    }
}
