<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

/**
 * Symbolic constraint strengths.
 *
 * Required constraints must be satisfied. Remaining strengths are satisfied with declining priority.
 */
class Strength
{
    /**
     * @return float
     */
    static public function required() : float
    {
        return self::create(1000.0, 1000.0, 1000.0);
    }

    /**
     * @return float
     */
    static public function strong() : float
    {
        return self::create(1.0, 0.0, 0.0);
    }

    /**
     * @return float
     */
    static public function medium() : float
    {
        return self::create(0.0, 1.0, 0.0);
    }

    /**
     * @return float
     */
    static public function weak() : float
    {
        return self::create(0.0, 0.0, 1.0);
    }

    /**
     * Create a new symbolic strength.
     *
     * @param float $a
     * @param float $b
     * @param float $c
     * @param float $w
     * @return float
     */
    static public function create(float $a, float $b, float $c, float $w = 1.0) : float
    {
        $strength = 0.0;
        $strength += max(0.0, min(1000.0, $a * $w)) * 1000000.0;
        $strength += max(0.0, min(1000.0, $b * $w)) * 1000.0;
        $strength += max(0.0, min(1000.0, $c * $w));
        return $strength;
    }

    /**
     * Clamp a symbolic strength to the allowed min and max.
     *
     * @param float $strength
     * @return float
     */
    static public function clip(float $strength) : float
    {
        return max(0.0, min(self::required(), $strength));
    }
}