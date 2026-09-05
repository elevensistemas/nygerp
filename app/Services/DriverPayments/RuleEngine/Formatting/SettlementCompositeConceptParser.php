<?php

namespace App\Services\DriverPayments\RuleEngine\Formatting;

class SettlementCompositeConceptParser
{
    public static function parse(?string $conceptName): ?array
    {
        if ($conceptName === null) {
            return null;
        }

        $normalizedExpression = SettlementConceptNormalizer::normalize($conceptName);
        if ($normalizedExpression === '') {
            return null;
        }

        $chunks = preg_split('/\s*\+\s*/', $normalizedExpression);
        if (! is_array($chunks) || empty($chunks)) {
            return null;
        }

        $terms = [];
        $containsExpressionSyntax = count($chunks) > 1;

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }

            $weight = 1.0;
            $baseConcept = $chunk;

            if (preg_match('/^(\d+)\s*\/\s*(\d+)\s+(.+)$/', $chunk, $matches)) {
                $numerator = (int) $matches[1];
                $denominator = (int) $matches[2];

                if ($denominator <= 0) {
                    return null;
                }

                $weight = $numerator / $denominator;
                $baseConcept = trim((string) $matches[3]);
                $containsExpressionSyntax = true;
            }

            $baseConcept = self::cleanupBaseConcept($baseConcept);
            if ($baseConcept === '') {
                return null;
            }

            $terms[] = [
                'weight' => $weight,
                'concept_name' => $baseConcept,
                'concept_key' => SettlementConceptNormalizer::normalize($baseConcept),
            ];
        }

        if (empty($terms) || ! $containsExpressionSyntax) {
            return null;
        }

        return [
            'expression' => $normalizedExpression,
            'terms' => $terms,
        ];
    }

    private static function cleanupBaseConcept(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/\.+$/', '', (string) $value);

        return trim((string) $value);
    }
}
