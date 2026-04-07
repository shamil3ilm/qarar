<?php

declare(strict_types=1);

namespace App\Contracts;

interface VersionedRule
{
    /** The date from which this rule version is effective */
    public function effectiveFrom(): \DateTimeInterface;

    /** Version identifier, e.g. 'v1', 'v2' */
    public function version(): string;
}
