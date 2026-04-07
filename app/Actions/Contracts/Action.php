<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

interface Action
{
    public function execute(array $payload): mixed;
}
