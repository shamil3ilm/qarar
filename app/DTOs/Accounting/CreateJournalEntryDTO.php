<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\DTOs\Contracts\DataTransferObject;

final readonly class CreateJournalEntryDTO implements DataTransferObject
{
    /** @param list<JournalLineDTO> $lines */
    public function __construct(
        public int     $organizationId,
        public string  $entryDate,
        public array   $lines,
        public ?string $description  = null,
        public ?string $reference    = null,
        public ?int    $fiscalYearId = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            organizationId: (int) $data['organization_id'],
            entryDate:      $data['entry_date'],
            lines:          array_map(
                fn(array $l) => JournalLineDTO::fromArray($l),
                $data['lines'] ?? []
            ),
            description:    $data['description'] ?? null,
            reference:      $data['reference'] ?? null,
            fiscalYearId:   isset($data['fiscal_year_id']) ? (int) $data['fiscal_year_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'entry_date'      => $this->entryDate,
            'lines'           => array_map(fn(JournalLineDTO $l) => $l->toArray(), $this->lines),
            'description'     => $this->description,
            'reference'       => $this->reference,
            'fiscal_year_id'  => $this->fiscalYearId,
        ];
    }

    public function totalDebit(): float
    {
        return array_sum(array_map(fn(JournalLineDTO $l) => $l->debit, $this->lines));
    }

    public function totalCredit(): float
    {
        return array_sum(array_map(fn(JournalLineDTO $l) => $l->credit, $this->lines));
    }

    /** A journal entry is balanced when total debits = total credits */
    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.001;
    }
}
