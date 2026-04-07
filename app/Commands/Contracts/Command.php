<?php

declare(strict_types=1);

namespace App\Commands\Contracts;

interface Command
{
    /** Convert back to array for service layer compatibility */
    public function toArray(): array;
}
