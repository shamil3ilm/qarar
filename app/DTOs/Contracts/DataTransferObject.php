<?php

declare(strict_types=1);

namespace App\DTOs\Contracts;

interface DataTransferObject
{
    public static function fromArray(array $data): static;
    public function toArray(): array;
}
