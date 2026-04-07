<?php

declare(strict_types=1);

namespace App\Exceptions\ERP;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class ErpException extends Exception
{
    protected string $errorCode;
    protected array $context = [];
    protected int $httpStatus = 422;

    public function __construct(string $message = '', array $context = [], ?Exception $previous = null)
    {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function render(\Illuminate\Http\Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => [
                'code'    => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->context,
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                'timestamp'  => now()->toIso8601String(),
            ],
        ], $this->httpStatus);
    }
}
