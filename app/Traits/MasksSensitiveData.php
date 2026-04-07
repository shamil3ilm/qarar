<?php

declare(strict_types=1);

namespace App\Traits;

trait MasksSensitiveData
{
    /**
     * Mask a tax number, showing only the last 4 characters.
     * Example: "123456782341" → "****2341"
     */
    protected function maskTaxNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . mb_substr($value, -4);
    }

    /**
     * Mask an IBAN, preserving the country prefix (2 chars) and showing only the last 4 digits.
     * Example: "SA4480000400608010167519" → "SA** **** **** 7519"
     */
    protected function maskIban(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stripped = str_replace(' ', '', $value);
        $length = mb_strlen($stripped);

        if ($length < 4) {
            return str_repeat('*', $length);
        }

        $countryPrefix = mb_substr($stripped, 0, 2);
        $last4 = mb_substr($stripped, -4);
        $middleLength = $length - 6;

        if ($middleLength <= 0) {
            return $countryPrefix . '** ' . $last4;
        }

        // Group masked middle portion into blocks of 4 for readability
        $maskedMiddle = str_repeat('*', $middleLength + 2);
        $chunked = implode(' ', str_split($maskedMiddle, 4));

        return $countryPrefix . substr($chunked, 2) . ' ' . $last4;
    }

    /**
     * Mask an email address, obscuring the local part after the first character.
     * Example: "john.doe@example.com" → "j***@example.com"
     */
    protected function maskEmail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $atPos = mb_strpos($value, '@');

        if ($atPos === false) {
            return $value;
        }

        $local = mb_substr($value, 0, $atPos);
        $domain = mb_substr($value, $atPos);

        if (mb_strlen($local) <= 1) {
            return $local . '***' . $domain;
        }

        return mb_substr($local, 0, 1) . str_repeat('*', mb_strlen($local) - 1) . $domain;
    }

    /**
     * Mask a national ID, showing only the first and last character.
     * Example: "1234567894" → "1******94" (first + masked middle + last)
     */
    protected function maskNationalId(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 1)
            . str_repeat('*', $length - 2)
            . mb_substr($value, -1);
    }

    /**
     * Mask a bank account number, showing only the last 4 characters.
     * Example: "00608010167519" → "****7519"
     */
    protected function maskBankAccount(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . mb_substr($value, -4);
    }
}
