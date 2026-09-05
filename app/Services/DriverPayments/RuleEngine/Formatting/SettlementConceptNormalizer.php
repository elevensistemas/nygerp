<?php

namespace App\Services\DriverPayments\RuleEngine\Formatting;

class SettlementConceptNormalizer
{
    /**
     * Normalizes a concept string, keeping semantics intact.
     * Example: " 1/2 ZONA 1   +   1/2 FM  " -> "1/2 zona 1 + 1/2 fm"
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        // Replace newlines, tabs, and multiple spaces with a single space.
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
