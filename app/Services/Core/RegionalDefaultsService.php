<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * Provides region-specific setting defaults for each supported country.
 *
 * Defaults are applied by SettingsService::initializeByCountry() and do NOT
 * modify config/erp.php.  Any key not listed here falls back to the
 * SettingsService hard-coded definitions.
 */
class RegionalDefaultsService
{
    /**
     * Returns the full set of default settings for a given country code.
     * Any key not present falls back to the SettingsService hard-coded defaults.
     */
    public function getDefaultsForCountry(string $countryCode): array
    {
        return match (strtoupper($countryCode)) {
            'SA'    => $this->saudiArabia(),
            'AE'    => $this->uae(),
            'QA'    => $this->qatar(),
            'OM'    => $this->oman(),
            'BH'    => $this->bahrain(),
            'KW'    => $this->kuwait(),
            'IN'    => $this->india(),
            default => $this->global(),
        };
    }

    /**
     * Returns the region label for a country code.
     */
    public function getRegionLabel(string $countryCode): string
    {
        return match (strtoupper($countryCode)) {
            'SA', 'AE', 'QA', 'OM', 'BH', 'KW' => 'GCC',
            'IN'                                  => 'India',
            default                               => 'Global',
        };
    }

    /**
     * Returns all supported country codes with their labels and metadata.
     *
     * @return array<string, array{label: string, region: string, currency: string, tax_scheme: string}>
     */
    public function getSupportedCountries(): array
    {
        return [
            'SA' => ['label' => 'Saudi Arabia',          'region' => 'GCC',   'currency' => 'SAR', 'tax_scheme' => 'VAT'],
            'AE' => ['label' => 'United Arab Emirates',  'region' => 'GCC',   'currency' => 'AED', 'tax_scheme' => 'VAT'],
            'QA' => ['label' => 'Qatar',                 'region' => 'GCC',   'currency' => 'QAR', 'tax_scheme' => 'VAT'],
            'OM' => ['label' => 'Oman',                  'region' => 'GCC',   'currency' => 'OMR', 'tax_scheme' => 'VAT'],
            'BH' => ['label' => 'Bahrain',               'region' => 'GCC',   'currency' => 'BHD', 'tax_scheme' => 'VAT'],
            'KW' => ['label' => 'Kuwait',                'region' => 'GCC',   'currency' => 'KWD', 'tax_scheme' => 'VAT'],
            'IN' => ['label' => 'India',                 'region' => 'India', 'currency' => 'INR', 'tax_scheme' => 'GST'],
        ];
    }

    // ---------------------------------------------------------------
    // Per-country definitions
    // ---------------------------------------------------------------

    private function saudiArabia(): array
    {
        return [
            // Org — first_day_of_week uses integer (0=Sun…6=Sat) per SettingsService definition
            'org.date_format'         => 'd/m/Y',
            'org.time_format'         => 'H:i',
            'org.first_day_of_week'   => 0,  // Sunday
            // Accounting
            'accounting.default_currency'         => 'SAR',
            'accounting.fiscal_year_start_month'  => 1,
            'accounting.multi_currency_enabled'   => true,
            'accounting.require_journal_approval' => true,
            // Invoice
            'invoice.prefix'             => 'INV-',
            'invoice.due_days'           => 30,
            'invoice.show_tax_breakdown' => true,
            // Tax
            'tax.default_rate'    => 15.0,
            'tax.inclusive_pricing' => false,
            // Security
            'security.require_2fa'             => false,
            'security.session_timeout_minutes' => 60,
        ];
    }

    private function uae(): array
    {
        return [
            'org.date_format'         => 'd/m/Y',
            'org.time_format'         => 'H:i',
            'org.first_day_of_week'   => 1,  // Monday
            'accounting.default_currency'         => 'AED',
            'accounting.fiscal_year_start_month'  => 1,
            'accounting.multi_currency_enabled'   => true,
            'accounting.require_journal_approval' => false,
            'invoice.prefix'             => 'INV-',
            'invoice.due_days'           => 30,
            'invoice.show_tax_breakdown' => true,
            'tax.default_rate'    => 5.0,
            'tax.inclusive_pricing' => false,
            'security.require_2fa'             => false,
            'security.session_timeout_minutes' => 60,
        ];
    }

    private function qatar(): array
    {
        return array_merge($this->uae(), [
            'accounting.default_currency' => 'QAR',
            'tax.default_rate'            => 0.0,
            'org.first_day_of_week'       => 0,  // Sunday
        ]);
    }

    private function oman(): array
    {
        return array_merge($this->uae(), [
            'accounting.default_currency' => 'OMR',
            'tax.default_rate'            => 5.0,
            'org.first_day_of_week'       => 0,  // Sunday
        ]);
    }

    private function bahrain(): array
    {
        return array_merge($this->uae(), [
            'accounting.default_currency' => 'BHD',
            'tax.default_rate'            => 10.0,
            'org.first_day_of_week'       => 0,  // Sunday
        ]);
    }

    private function kuwait(): array
    {
        return array_merge($this->uae(), [
            'accounting.default_currency' => 'KWD',
            'tax.default_rate'            => 0.0,
            'org.first_day_of_week'       => 0,  // Sunday
        ]);
    }

    private function india(): array
    {
        return [
            'org.date_format'         => 'd/m/Y',
            'org.time_format'         => 'H:i',
            'org.first_day_of_week'   => 1,  // Monday
            'accounting.default_currency'         => 'INR',
            'accounting.fiscal_year_start_month'  => 4,  // April start (Indian FY)
            'accounting.multi_currency_enabled'   => true,
            'accounting.require_journal_approval' => true,
            'invoice.prefix'             => 'INV-',
            'invoice.due_days'           => 30,
            'invoice.show_tax_breakdown' => true,
            'tax.default_rate'     => 18.0,  // Standard GST slab
            'tax.inclusive_pricing' => false,
            'security.require_2fa'             => false,
            'security.session_timeout_minutes' => 30,
        ];
    }

    private function global(): array
    {
        return [
            'org.date_format'         => 'Y-m-d',
            'org.time_format'         => 'H:i',
            'org.first_day_of_week'   => 1,  // Monday
            'accounting.default_currency'        => 'SAR',
            'accounting.fiscal_year_start_month' => 1,
            'tax.default_rate'    => 0.0,
            'tax.inclusive_pricing' => false,
            'invoice.due_days'    => 30,
            'security.require_2fa'             => false,
            'security.session_timeout_minutes' => 60,
        ];
    }
}
