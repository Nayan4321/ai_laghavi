<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/** Validates output size and works out aspect ratios. */
final class Dimensions
{
    /** Common ratios offered in the form and used when a model only accepts a fixed list. */
    public const PRESETS = ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9', '9:21', '3:2', '2:3', '4:5', '5:4'];

    /**
     * Resolves the form input into [width, height, ratio].
     * With a preset ratio, height is derived from width; with "custom", both are taken as typed.
     * Sizes are rounded to even numbers because video encoders require it.
     */
    public static function resolve(mixed $width, mixed $height, string $aspect, int $min, int $max): array
    {
        $w = self::int($width, 'Width');
        if ($aspect !== '' && $aspect !== 'custom') {
            [$rw, $rh] = self::parseRatio($aspect);
            $h = (int) round($w * $rh / $rw);
        } else {
            $h = self::int($height, 'Height');
        }
        $w = self::even($w);
        $h = self::even($h);
        foreach (['Width' => $w, 'Height' => $h] as $label => $v) {
            if ($v < $min || $v > $max) {
                throw new InvalidArgumentException("$label must be between $min and $max pixels (got $v).");
            }
        }
        return [$w, $h, self::ratio($w, $h)];
    }

    public static function ratio(int $w, int $h): string
    {
        $g = self::gcd($w, $h);
        return ($w / $g) . ':' . ($h / $g);
    }

    /** The preset closest to w:h, for models that only accept a fixed list of ratios. */
    public static function nearest(int $w, int $h, array $choices = self::PRESETS): string
    {
        $target = log($w / $h);
        $best = $choices[0];
        $bestDiff = INF;
        foreach ($choices as $choice) {
            [$a, $b] = self::parseRatio($choice);
            $diff = abs(log($a / $b) - $target);
            if ($diff < $bestDiff) {
                $best = $choice;
                $bestDiff = $diff;
            }
        }
        return $best;
    }

    public static function parseRatio(string $ratio): array
    {
        if (!preg_match('/^(\d{1,3}):(\d{1,3})$/', $ratio, $m) || (int) $m[1] === 0 || (int) $m[2] === 0) {
            throw new InvalidArgumentException("Aspect ratio must look like 16:9 (got '$ratio').");
        }
        return [(int) $m[1], (int) $m[2]];
    }

    private static function int(mixed $v, string $label): int
    {
        if (!is_numeric($v) || (int) $v != $v) {
            throw new InvalidArgumentException("$label must be a whole number of pixels.");
        }
        return (int) $v;
    }

    private static function even(int $v): int
    {
        return $v % 2 === 0 ? $v : $v + 1;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return max(1, $a);
    }
}
