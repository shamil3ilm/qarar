<?php

declare(strict_types=1);

namespace App\Services\Aml;

use App\Jobs\RunAmlEscalationJob;
use App\Models\Aml\AmlCddRecord;
use App\Models\Aml\AmlRiskScore;
use App\Models\Aml\AmlScreeningCache;
use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmlMonitoringService
{
    /**
     * Threshold (in SAR/USD equivalent) that triggers a THRESHOLD_BREACH flag.
     */
    private const TRANSACTION_THRESHOLD = 10000.0;

    /**
     * Range for structuring detection (transactions just below a round number).
     */
    private const STRUCTURING_LOWER = 8000.0;
    private const STRUCTURING_UPPER = 9999.99;
    private const STRUCTURING_COUNT = 3;
    private const STRUCTURING_WINDOW_DAYS = 7;

    /**
     * PEP job-title keywords (case-insensitive).
     */
    private const PEP_KEYWORDS = [
        'minister',
        'president',
        'senator',
        'governor',
        'director general',
        'chief justice',
    ];

    /**
     * Countries classified as high-risk for geographic scoring.
     */
    private const HIGH_RISK_COUNTRIES = ['IR', 'KP', 'SY', 'CU', 'SD', 'VE', 'MM'];

    /**
     * Screen a transaction (invoice or payment) for AML concerns.
     * All operations are non-blocking; errors are logged and swallowed.
     */
    public function screenTransaction(
        string $transactionType,
        int    $transactionId,
        float  $amount,
        string $currency,
        int    $organizationId,
        ?int   $contactId = null,
    ): void {
        try {
            $flags = [];

            // 1. Threshold breach
            if ($amount >= self::TRANSACTION_THRESHOLD) {
                $flags[] = $this->createTransactionFlag(
                    organizationId:    $organizationId,
                    transactionType:   $transactionType,
                    transactionId:     $transactionId,
                    amount:            $amount,
                    currency:          $currency,
                    flagReason:        AmlTransactionFlag::THRESHOLD_BREACH,
                    amlScore:          30,
                    contactId:         $contactId,
                    context:           ['threshold' => self::TRANSACTION_THRESHOLD, 'amount' => $amount],
                );
            }

            // 2. Structuring pattern
            if ($contactId !== null && $this->isStructuringPattern($organizationId, $contactId, $transactionType)) {
                $flags[] = $this->createTransactionFlag(
                    organizationId:    $organizationId,
                    transactionType:   $transactionType,
                    transactionId:     $transactionId,
                    amount:            $amount,
                    currency:          $currency,
                    flagReason:        AmlTransactionFlag::STRUCTURING,
                    amlScore:          50,
                    contactId:         $contactId,
                    context:           ['window_days' => self::STRUCTURING_WINDOW_DAYS],
                );
            }

            // 3. Rapid movement (payment received within 1 hour of invoice)
            if ($transactionType === 'payment' && $contactId !== null) {
                if ($this->isRapidMovement($organizationId, $contactId)) {
                    $flags[] = $this->createTransactionFlag(
                        organizationId:    $organizationId,
                        transactionType:   $transactionType,
                        transactionId:     $transactionId,
                        amount:            $amount,
                        currency:          $currency,
                        flagReason:        AmlTransactionFlag::RAPID_MOVEMENT,
                        amlScore:          20,
                        contactId:         $contactId,
                        context:           ['window_minutes' => 60],
                    );
                }
            }

            // 4. High-risk contact
            if ($contactId !== null && $this->isHighRiskContact($organizationId, $contactId)) {
                $flags[] = $this->createTransactionFlag(
                    organizationId:    $organizationId,
                    transactionType:   $transactionType,
                    transactionId:     $transactionId,
                    amount:            $amount,
                    currency:          $currency,
                    flagReason:        AmlTransactionFlag::HIGH_RISK_CONTACT,
                    amlScore:          40,
                    contactId:         $contactId,
                    context:           ['risk_level' => 'critical'],
                );
            }

            // 5. If multiple flags found, escalate
            if (count($flags) >= 2) {
                try {
                    RunAmlEscalationJob::dispatch($transactionType, $transactionId, $organizationId)->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('AML escalation job dispatch failed', [
                        'transaction_id' => $transactionId,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('AML transaction screening failed', [
                'transaction_type' => $transactionType,
                'transaction_id'   => $transactionId,
                'error'            => $e->getMessage(),
            ]);
            // Re-throw so compliance-critical failures are not silently swallowed by callers.
            throw $e;
        }
    }

    /**
     * Screen a contact against sanctions lists and PEP lists.
     */
    public function screenContact(Contact $contact): AmlScreeningResult
    {
        $dataHash = $this->buildDataHash($contact);

        // Check cache: if contact data unchanged, return cached result
        $cached = DB::table('aml_screening_cache')
            ->where('contact_id', $contact->id)
            ->where('list_type', 'ofac')
            ->where('data_hash', $dataHash)
            ->first();

        if ($cached !== null) {
            return new AmlScreeningResult(
                sanctionsHit: (bool) $cached->is_match,
                pepHit:       false,
                matchDetails: json_decode($cached->match_details ?? '[]', true) ?? [],
                dataHash:     $dataHash,
                fromCache:    true,
            );
        }

        $matchDetails = [];
        $sanctionsHit = false;
        $pepHit       = false;

        // Sanctions check: high-risk country national
        $countryCode = $contact->billing_country_code ?? null;
        if ($countryCode !== null && in_array(strtoupper($countryCode), self::HIGH_RISK_COUNTRIES, true)) {
            $sanctionsHit   = true;
            $matchDetails[] = [
                'type'    => 'high_risk_country',
                'value'   => $countryCode,
                'list'    => 'ofac',
            ];
        }

        // PEP check: job title inspection
        $jobTitle = $contact->notes ?? ''; // fallback — contacts may store designation in notes
        foreach (self::PEP_KEYWORDS as $keyword) {
            if (stripos($jobTitle, $keyword) !== false) {
                $pepHit         = true;
                $matchDetails[] = [
                    'type'    => 'pep_keyword',
                    'keyword' => $keyword,
                ];
                break;
            }
        }

        // Persist / update screening cache
        $this->upsertScreeningCache($contact, $dataHash, $sanctionsHit || $pepHit, $matchDetails);

        return new AmlScreeningResult(
            sanctionsHit: $sanctionsHit,
            pepHit:       $pepHit,
            matchDetails: $matchDetails,
            dataHash:     $dataHash,
            fromCache:    false,
        );
    }

    /**
     * Compute and persist an AML risk score for the given contact.
     */
    public function updateRiskScore(Contact $contact): void
    {
        try {
            $breakdown = $this->buildScoreBreakdown($contact);
            $total     = min(100, array_sum($breakdown));
            $riskLevel = AmlRiskScore::getRiskLevel($total);

            $screeningResult = $this->screenContact($contact);

            AmlRiskScore::updateOrCreate(
                [
                    'organization_id' => $contact->organization_id,
                    'contact_id'      => $contact->id,
                ],
                [
                    'score'            => $total,
                    'risk_level'       => $riskLevel,
                    'score_breakdown'  => $breakdown,
                    'sanctions_hit'    => $screeningResult->sanctionsHit,
                    'pep_hit'          => $screeningResult->pepHit,
                    'sanctions_details'=> !empty($screeningResult->matchDetails)
                        ? json_encode($screeningResult->matchDetails)
                        : null,
                    'last_screened_at' => now(),
                    'score_updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('AML risk score update failed', [
                'contact_id' => $contact->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a Suspicious Activity Report.
     */
    public function createSar(
        int    $organizationId,
        int    $contactId,
        string $activityType,
        array  $transactionIds,
        string $description,
        int    $createdBy,
    ): AmlSuspiciousActivity {
        $contact = Contact::where('organization_id', $organizationId)->find($contactId);

        return AmlSuspiciousActivity::create([
            'organization_id'        => $organizationId,
            'report_type'            => AmlSuspiciousActivity::SAR,
            'status'                 => AmlSuspiciousActivity::STATUS_DRAFT,
            'contact_id'             => $contactId,
            'contact_name'           => $contact ? ($contact->company_name ?? $contact->contact_name) : null,
            'related_transaction_ids' => $transactionIds,
            'description'            => $description,
            'activity_type'          => $activityType,
            'created_by'             => $createdBy,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isStructuringPattern(int $organizationId, int $contactId, string $transactionType): bool
    {
        $since = now()->subDays(self::STRUCTURING_WINDOW_DAYS);

        return PaymentReceived::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('customer_id', $contactId)
            ->where('created_at', '>=', $since)
            ->whereBetween('amount', [self::STRUCTURING_LOWER, self::STRUCTURING_UPPER])
            ->count() >= self::STRUCTURING_COUNT;
    }

    private function isRapidMovement(int $organizationId, int $contactId): bool
    {
        return Invoice::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('customer_id', $contactId)
            ->where('created_at', '>=', now()->subHour())
            ->exists();
    }

    private function isHighRiskContact(int $organizationId, int $contactId): bool
    {
        return AmlRiskScore::where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->where('risk_level', AmlRiskScore::CRITICAL)
            ->exists();
    }

    private function createTransactionFlag(
        int     $organizationId,
        string  $transactionType,
        int     $transactionId,
        float   $amount,
        string  $currency,
        string  $flagReason,
        int     $amlScore,
        ?int    $contactId,
        array   $context,
    ): AmlTransactionFlag {
        return AmlTransactionFlag::create([
            'organization_id'  => $organizationId,
            'transaction_type' => $transactionType,
            'transaction_id'   => $transactionId,
            'amount'           => $amount,
            'currency'         => $currency,
            'flag_reason'      => $flagReason,
            'status'           => AmlTransactionFlag::STATUS_FLAGGED,
            'aml_score'        => $amlScore,
            'context'          => $context,
            'contact_id'       => $contactId,
            'transaction_date' => now(),
        ]);
    }

    private function buildScoreBreakdown(Contact $contact): array
    {
        $breakdown = [
            'transaction_velocity' => 0,
            'geographic_risk'      => 0,
            'contact_age'          => 0,
            'pep_hit'              => 0,
            'sanctions_hit'        => 0,
            'unpaid_invoices_ratio' => 0,
            'kyc_status'           => 0,
        ];

        // Transaction velocity: many payments in 30 days
        $recentPayments = PaymentReceived::withoutGlobalScope('organization')
            ->where('customer_id', $contact->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentPayments > 20) {
            $breakdown['transaction_velocity'] = 20;
        } elseif ($recentPayments > 10) {
            $breakdown['transaction_velocity'] = 10;
        }

        // Geographic risk
        $country = $contact->billing_country_code ?? null;
        if ($country !== null && in_array(strtoupper($country), self::HIGH_RISK_COUNTRIES, true)) {
            $breakdown['geographic_risk'] = 20;
        }

        // Contact age: new contact (<30 days) with recent large transaction
        $contactAgeInDays = (int) $contact->created_at?->diffInDays(now());
        if ($contactAgeInDays < 30) {
            $recentLargeInvoice = Invoice::withoutGlobalScope('organization')
                ->where('customer_id', $contact->id)
                ->where('total', '>=', self::TRANSACTION_THRESHOLD)
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();

            if ($recentLargeInvoice) {
                $breakdown['contact_age'] = 10;
            }
        }

        // PEP hit
        $jobTitle = $contact->notes ?? '';
        foreach (self::PEP_KEYWORDS as $keyword) {
            if (stripos($jobTitle, $keyword) !== false) {
                $breakdown['pep_hit'] = 25;
                break;
            }
        }

        // Sanctions hit
        if ($country !== null && in_array(strtoupper($country), self::HIGH_RISK_COUNTRIES, true)) {
            $breakdown['sanctions_hit'] = 50;
        }

        // Unpaid invoices ratio (>50% unpaid = +10)
        $totalInvoices = Invoice::withoutGlobalScope('organization')
            ->where('customer_id', $contact->id)
            ->count();

        if ($totalInvoices > 0) {
            $unpaidInvoices = Invoice::withoutGlobalScope('organization')
                ->where('customer_id', $contact->id)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->count();

            $ratio = $unpaidInvoices / $totalInvoices;
            if ($ratio > 0.5) {
                $breakdown['unpaid_invoices_ratio'] = 10;
            }
        }

        // KYC/CDD status: no completed CDD record = +10
        $hasCdd = DB::table('aml_cdd_records')
            ->where('contact_id', $contact->id)
            ->where('status', 'completed')
            ->exists();

        if (!$hasCdd) {
            $breakdown['kyc_status'] = 10;
        }

        return $breakdown;
    }

    private function buildDataHash(Contact $contact): string
    {
        return md5(implode('|', [
            $contact->company_name ?? '',
            $contact->contact_name ?? '',
            $contact->email ?? '',
            $contact->billing_country_code ?? '',
            $contact->notes ?? '',
        ]));
    }

    private function upsertScreeningCache(
        Contact $contact,
        string  $dataHash,
        bool    $isMatch,
        array   $matchDetails,
    ): void {
        DB::table('aml_screening_cache')->upsert(
            [
                [
                    'organization_id' => $contact->organization_id,
                    'contact_id'      => $contact->id,
                    'list_type'       => 'ofac',
                    'is_match'        => $isMatch,
                    'match_details'   => json_encode($matchDetails),
                    'data_hash'       => $dataHash,
                    'screened_at'     => now(),
                ],
            ],
            ['contact_id', 'list_type'],
            ['is_match', 'match_details', 'data_hash', 'screened_at']
        );
    }
}
