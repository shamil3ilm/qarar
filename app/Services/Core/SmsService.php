<?php

declare(strict_types=1);

namespace App\Services\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = (string) config('sms.driver', 'log');
    }

    /**
     * Send an SMS message. Failures are logged but never rethrown — SMS delivery
     * is best-effort and must not abort the caller's workflow.
     */
    public function send(string $to, string $message): void
    {
        try {
            match ($this->driver) {
                'log'    => $this->sendViaLog($to, $message),
                'vonage' => $this->sendViaVonage($to, $message),
                'twilio' => $this->sendViaTwilio($to, $message),
                default  => throw new \InvalidArgumentException(
                    "Unsupported SMS driver: [{$this->driver}]"
                ),
            };
        } catch (\InvalidArgumentException $e) {
            // Configuration error — re-throw so it surfaces during development
            throw $e;
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'driver'  => $this->driver,
                'to'      => $to,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendViaLog(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }

    private function sendViaVonage(string $to, string $message): void
    {
        $client = new Client(['timeout' => 10]);

        $response = $client->post('https://rest.nexmo.com/sms/json', [
            'form_params' => [
                'api_key'    => config('sms.vonage.api_key'),
                'api_secret' => config('sms.vonage.api_secret'),
                'to'         => $to,
                'from'       => config('sms.vonage.from'),
                'text'       => $message,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);

        // Vonage returns 200 even on logical failure; check the message status
        $status = $body['messages'][0]['status'] ?? '-1';
        if ($status !== '0') {
            Log::warning('Vonage SMS rejected', [
                'to'     => $to,
                'status' => $status,
                'error'  => $body['messages'][0]['error-text'] ?? 'unknown',
            ]);
        }
    }

    private function sendViaTwilio(string $to, string $message): void
    {
        $sid    = (string) config('sms.twilio.sid');
        $token  = (string) config('sms.twilio.token');
        $client = new Client(['timeout' => 10]);

        $client->post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            [
                'auth'        => [$sid, $token],
                'form_params' => [
                    'To'   => $to,
                    'From' => config('sms.twilio.from'),
                    'Body' => $message,
                ],
            ]
        );
    }
}
