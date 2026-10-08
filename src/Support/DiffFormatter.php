<?php

namespace Digit7s\FilamentAuditToolkit\Support;

final class DiffFormatter
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function format(array $before, array $after): string
    {
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        $lines = [];

        foreach ($keys as $key) {
            $beforeExists = array_key_exists($key, $before);
            $afterExists = array_key_exists($key, $after);
            $beforeValue = $beforeExists ? $before[$key] : null;
            $afterValue = $afterExists ? $after[$key] : null;

            if ($beforeExists === $afterExists && $beforeValue === $afterValue) {
                continue;
            }

            $lines[] = '- '.$key.': '.self::encode($beforeValue, $beforeExists);
            $lines[] = '+ '.$key.': '.self::encode($afterValue, $afterExists);
        }

        return $lines === [] ? 'No changed values.' : implode("\n", $lines);
    }

    private static function encode(mixed $value, bool $exists): string
    {
        if (! $exists) {
            return '[missing]';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '[UNSERIALIZABLE]' : $encoded;
    }
}
