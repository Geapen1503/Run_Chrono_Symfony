<?php

namespace App\Edition\Support;

/**
 * Équivalent legacy strToNoAccent (catalogue).
 */
final class LegacyStringHelper
{
    public static function slugify(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value;
    }

    public static function strToNoAccent(string $var): string
    {
        $var = htmlentities($var, \ENT_QUOTES, 'UTF-8');
        $var = preg_replace(
            '/&([A-Za-z])(acute|grave|circ|tilde|uml|ring|cedil|slash);/',
            '$1',
            $var,
        ) ?? $var;
        $var = preg_replace('/&([^;]+);/', '', $var) ?? $var;

        return $var;
    }
}