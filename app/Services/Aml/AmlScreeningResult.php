<?php

declare(strict_types=1);

namespace App\Services\Aml;

readonly class AmlScreeningResult
{
    /**
     * @param array<string, mixed> $matchDetails
     */
    public function __construct(
        public readonly bool   $sanctionsHit,
        public readonly bool   $pepHit,
        public readonly array  $matchDetails,
        public readonly string $dataHash,
        public readonly bool   $fromCache,
    ) {}

    public static function clean(string $dataHash): self
    {
        return new self(
            sanctionsHit: false,
            pepHit:       false,
            matchDetails: [],
            dataHash:     $dataHash,
            fromCache:    false,
        );
    }
}
