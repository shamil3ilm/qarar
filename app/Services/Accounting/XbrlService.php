<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\XbrlFiling;
use App\Models\Accounting\XbrlFilingElement;
use App\Models\Accounting\XbrlTaxonomy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * XBRL regulatory filing service (SAP FI-GL XBRL / iXBRL output).
 *
 * Supports:
 * - Taxonomy management (IFRS, local GCC/India GAAP)
 * - Filing creation from trial balance data
 * - Validation (basic structural checks)
 * - XML generation (iXBRL inline XBRL)
 * - Submission tracking
 */
class XbrlService
{
    // -------------------------------------------------------------------------
    // Taxonomy management
    // -------------------------------------------------------------------------

    public function createTaxonomy(int $organizationId, array $data, int $userId): XbrlTaxonomy
    {
        $exists = XbrlTaxonomy::where('namespace', $data['namespace'])->exists();

        if ($exists) {
            throw new InvalidArgumentException("A taxonomy with namespace '{$data['namespace']}' already exists.");
        }

        return XbrlTaxonomy::create([
            'organization_id' => $organizationId,
            'name'            => $data['name'],
            'version'         => $data['version'],
            'namespace'       => $data['namespace'],
            'schema_location' => $data['schema_location'] ?? null,
            'description'     => $data['description'] ?? null,
            'is_active'       => true,
            'created_by'      => $userId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Filing creation
    // -------------------------------------------------------------------------

    /**
     * Create a new XBRL filing and auto-populate elements from the trial balance.
     */
    public function createFiling(
        int $organizationId,
        FiscalYear $fiscalYear,
        XbrlTaxonomy $taxonomy,
        array $data,
        int $userId
    ): XbrlFiling {
        return DB::transaction(function () use ($organizationId, $fiscalYear, $taxonomy, $data, $userId): XbrlFiling {
            $filing = XbrlFiling::create([
                'organization_id'    => $organizationId,
                'fiscal_year_id'     => $fiscalYear->id,
                'taxonomy_id'        => $taxonomy->id,
                'report_type'        => $data['report_type'] ?? XbrlFiling::REPORT_ANNUAL,
                'period_start'       => $data['period_start'] ?? $fiscalYear->start_date,
                'period_end'         => $data['period_end'] ?? $fiscalYear->end_date,
                'status'             => XbrlFiling::STATUS_DRAFT,
                'created_by'         => $userId,
            ]);

            // Seed elements from trial balance if requested
            if ($data['seed_from_trial_balance'] ?? false) {
                $this->seedElementsFromTrialBalance($filing, $fiscalYear, $organizationId);
            }

            return $filing->fresh(['taxonomy', 'fiscalYear', 'elements']);
        });
    }

    /**
     * Add or update a tagged element in a filing.
     */
    public function upsertElement(XbrlFiling $filing, array $data): XbrlFilingElement
    {
        if (! $filing->isDraft()) {
            throw new InvalidArgumentException('Elements can only be edited in draft filings.');
        }

        $element = XbrlFilingElement::firstOrNew([
            'xbrl_filing_id' => $filing->id,
            'concept'        => $data['concept'],
            'context_ref'    => $data['context_ref'],
        ]);

        $element->fill([
            'unit_ref'     => $data['unit_ref'] ?? null,
            'value'        => (string) $data['value'],
            'decimals'     => $data['decimals'] ?? null,
            'period_type'  => $data['period_type'] ?? 'instant',
            'balance_type' => $data['balance_type'] ?? null,
            'sequence'     => $data['sequence'] ?? 0,
        ]);

        $element->save();

        return $element;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate a draft filing and return any errors found.
     * On success (no errors), transitions the filing to 'validated' status.
     */
    public function validate(XbrlFiling $filing): XbrlFiling
    {
        if ($filing->status !== XbrlFiling::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft filings can be validated.');
        }

        $errors = $this->runValidationChecks($filing);

        if (! empty($errors)) {
            $filing->update([
                'validation_errors' => $errors,
            ]);

            return $filing->fresh(['elements']);
        }

        $filing->update([
            'status'            => XbrlFiling::STATUS_VALIDATED,
            'validation_errors' => [],
        ]);

        return $filing->fresh(['elements']);
    }

    // -------------------------------------------------------------------------
    // XML generation
    // -------------------------------------------------------------------------

    /**
     * Generate iXBRL XML for a validated filing and store it on the record.
     */
    public function generateXml(XbrlFiling $filing): XbrlFiling
    {
        if ($filing->status !== XbrlFiling::STATUS_VALIDATED) {
            throw new InvalidArgumentException('Only validated filings can have XML generated.');
        }

        $filing->load(['taxonomy', 'elements', 'fiscalYear']);

        $xml = $this->buildIxbrlXml($filing);

        $filing->update(['xml_content' => $xml]);

        return $filing->fresh();
    }

    // -------------------------------------------------------------------------
    // Submission
    // -------------------------------------------------------------------------

    public function markSubmitted(XbrlFiling $filing, string $externalReference): XbrlFiling
    {
        if (! $filing->canBeSubmitted()) {
            throw new InvalidArgumentException('Only validated filings can be submitted.');
        }

        $filing->update([
            'status'             => XbrlFiling::STATUS_SUBMITTED,
            'external_reference' => $externalReference,
            'submitted_at'       => now(),
        ]);

        return $filing->fresh();
    }

    public function markAccepted(XbrlFiling $filing): XbrlFiling
    {
        if ($filing->status !== XbrlFiling::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted filings can be accepted.');
        }

        $filing->update([
            'status'      => XbrlFiling::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return $filing->fresh();
    }

    public function markRejected(XbrlFiling $filing, array $errors): XbrlFiling
    {
        if ($filing->status !== XbrlFiling::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted filings can be rejected.');
        }

        $filing->update([
            'status'            => XbrlFiling::STATUS_REJECTED,
            'validation_errors' => $errors,
        ]);

        return $filing->fresh();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Seed XBRL filing elements from the trial balance for the fiscal year.
     * Maps account types to standard IFRS concepts as a starting point.
     */
    private function seedElementsFromTrialBalance(
        XbrlFiling $filing,
        FiscalYear $fiscalYear,
        int $organizationId
    ): void {
        $conceptMap = [
            'asset'      => 'ifrs-full:Assets',
            'liability'  => 'ifrs-full:Liabilities',
            'equity'     => 'ifrs-full:Equity',
            'income'     => 'ifrs-full:Revenue',
            'expense'    => 'ifrs-full:OperatingExpense',
        ];

        $contextRef = "duration_{$fiscalYear->start_date}_{$fiscalYear->end_date}";
        $sequence   = 0;

        foreach ($conceptMap as $accountType => $concept) {
            $balance = Account::where('organization_id', $organizationId)
                ->where('account_type', $accountType)
                ->sum('current_balance');

            if ((float) $balance == 0.0) {
                continue;
            }

            XbrlFilingElement::create([
                'xbrl_filing_id' => $filing->id,
                'concept'        => $concept,
                'context_ref'    => $contextRef,
                'unit_ref'       => 'SAR',
                'value'          => number_format(abs((float) $balance), 2, '.', ''),
                'decimals'       => 2,
                'period_type'    => 'instant',
                'balance_type'   => in_array($accountType, ['income', 'asset'], true) ? 'debit' : 'credit',
                'sequence'       => $sequence++,
            ]);
        }
    }

    /**
     * Run basic validation checks on the filing elements.
     * Returns an array of error messages (empty = valid).
     */
    private function runValidationChecks(XbrlFiling $filing): array
    {
        $errors = [];

        if ($filing->elements()->count() === 0) {
            $errors[] = 'Filing has no tagged elements.';
        }

        // Check that key IFRS concepts are present
        $requiredConcepts = ['ifrs-full:Assets', 'ifrs-full:Liabilities', 'ifrs-full:Equity'];
        $presentConcepts  = $filing->elements()->pluck('concept')->unique()->toArray();

        foreach ($requiredConcepts as $concept) {
            if (! in_array($concept, $presentConcepts, true)) {
                $errors[] = "Required concept '{$concept}' is missing.";
            }
        }

        return $errors;
    }

    /**
     * Build a minimal iXBRL XML document from the filing elements.
     */
    private function buildIxbrlXml(XbrlFiling $filing): string
    {
        $taxonomy = $filing->taxonomy;
        $period   = $filing->period_end->format('Y-m-d');

        $elements = $filing->elements->map(function (XbrlFilingElement $el): string {
            $decimals = $el->decimals !== null ? " decimals=\"{$el->decimals}\"" : '';
            $unitRef  = $el->unit_ref ? " unitRef=\"{$el->unit_ref}\"" : '';
            $concept  = htmlspecialchars($el->concept, ENT_XML1);
            $value    = htmlspecialchars($el->value, ENT_XML1);

            return <<<XML
        <ix:nonFraction name="{$concept}" contextRef="{$el->context_ref}"{$unitRef}{$decimals}>{$value}</ix:nonFraction>
XML;
        })->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml"
      xmlns:ix="http://www.xbrl.org/2013/inlineXBRL"
      xmlns:xbrli="http://www.xbrl.org/2003/instance"
      xmlns:ifrs-full="{$taxonomy->namespace}">
  <head>
    <title>XBRL Filing — {$filing->report_type} — {$period}</title>
  </head>
  <body>
    <div id="xbrl-data">
{$elements}
    </div>
  </body>
</html>
XML;
    }
}
