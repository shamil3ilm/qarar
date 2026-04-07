<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\DTOs\Contracts\DataTransferObject;

final readonly class JournalLineDTO implements DataTransferObject
{
    public function __construct(
        public int     $accountId,
        public float   $debit,
        public float   $credit,
        public ?string $description  = null,
        public ?int    $costCenterId = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            accountId:    (int)   $data['account_id'],
            debit:        (float) ($data['debit'] ?? 0.0),
            credit:       (float) ($data['credit'] ?? 0.0),
            description:  $data['description'] ?? null,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'account_id'     => $this->accountId,
            'debit'          => $this->debit,
            'credit'         => $this->credit,
            'description'    => $this->description,
            'cost_center_id' => $this->costCenterId,
        ];
    }

    /** A line is balanced if exactly one of debit/credit is non-zero */
    public function isBalanced(): bool
    {
        return ($this->debit > 0) xor ($this->credit > 0);
    }
}
