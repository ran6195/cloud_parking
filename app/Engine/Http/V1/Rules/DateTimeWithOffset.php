<?php

namespace App\Engine\Http\V1\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Data e ora ISO 8601 con offset obbligatorio (es. 2026-12-20T08:00:00+01:00).
 */
final class DateTimeWithOffset implements ValidationRule
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(:(\d{2}))?(Z|[+-]\d{2}:\d{2})$/';

    public static function parse(string $value): ?DateTimeImmutable
    {
        if (preg_match(self::PATTERN, $value, $m) !== 1
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[7] > 59) {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::parse($value) === null) {
            $fail('Data e ora devono essere in formato ISO 8601 con offset, ad es. 2026-12-20T08:00:00+01:00.');
        }
    }
}
