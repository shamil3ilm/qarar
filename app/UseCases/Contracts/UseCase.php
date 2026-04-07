<?php

declare(strict_types=1);

namespace App\UseCases\Contracts;

interface UseCase
{
    public function handle(array $data): mixed;
}
