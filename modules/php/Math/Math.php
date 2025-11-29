<?php

namespace Bga\Games\SuperTeam\Math;

class Math
{
    public static function clamp($value, $min, $max)
    {
        return max($min, min($value, $max));
    }
    public static function pointFromLineDistance(Vector2 $point, Vector2 $lineStart, Vector2 $lineDir) : float
    {
        if ($lineDir->size() === 0)
            return 0;
        else
            return abs(self::crossProduct2D($lineDir, Vector2::subtract($point, $lineStart))) / $lineDir->size();
    }

    public static function pointOntoLineProjectionSize(Vector2 $point, Vector2 $lineStart, Vector2 $lineDir) : float
    {
        return self::dotProduct($lineDir, Vector2::subtract($point, $lineStart)) / pow($lineDir->size(), 2);
    }

    public static function dotProduct(Vector2 $lhs, Vector2 $rhs) : float
    {
        return $lhs->x * $rhs->x + $lhs->y * $rhs->y;
    }

    public static function crossProduct2D(Vector2 $lhs, Vector2 $rhs) : float
    {
        return $lhs->x * $rhs->y - $lhs->y * $rhs->x;
    }

    public static function lerp(float $a, float $b, float $t) : float
    {
        return $a + ($b - $a) * $t;
    }
}