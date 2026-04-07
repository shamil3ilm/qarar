<?php

declare(strict_types=1);

namespace App\Queries\Contracts;

interface Query
{
    /**
     * Execute the query and return the result.
     */
    public function execute(): mixed;
}
