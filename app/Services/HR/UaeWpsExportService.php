<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\PayrollPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UAE-specific WPS (Wage Protection System) SIF file generator.
 *
 * UAE WPS uses the same MOL SIF format as Saudi WPS but with UAE-specific
 * identifiers: the employer bank code is a UAE bank routing code (not a Saudi
 * bank code), and the employer ID should be the UAE trade license number.
 *
 * Delegates to WpsExportService and enforces UAE-specific defaults.
 */
class UaeWpsExportService
{
    public function __construct(private readonly WpsExportService $wps) {}

    /**
     * Generate UAE WPS SIF file content.
     *
     * @param  PayrollPeriod $period          The payroll period to export.
     * @param  string        $uaeBankCode     UAE bank routing code (4-9 chars, e.g. "0003" for ENBD).
     */
    public function generate(PayrollPeriod $period, string $uaeBankCode = '0003'): string
    {
        return $this->wps->generate($period, $uaeBankCode);
    }

    /**
     * Stream UAE WPS SIF file as a download with UAE-specific filename.
     */
    public function download(PayrollPeriod $period, string $uaeBankCode = '0003'): StreamedResponse
    {
        $filename = sprintf(
            'UAE_WPS_%d%02d_%s.txt',
            $period->period_year ?? $period->start_date->year,
            $period->period_month ?? $period->start_date->month,
            now()->format('Ymd')
        );

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($period, $uaeBankCode): void {
                echo $this->generate($period, $uaeBankCode);
            },
            200,
            [
                'Content-Type'        => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * Summary statistics for the period — missing IBANs, ready count, total.
     *
     * @return array{total_employees: int, ready_count: int, missing_iban: int, total_amount: float, currency: string}
     */
    public function getStats(PayrollPeriod $period): array
    {
        return $this->wps->getStats($period);
    }
}
