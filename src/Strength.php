<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class Strength
{
    static public function required() : float
    {
        return self::create(1000.0, 1000.0, 1000.0);
    }
    
    static public function strong() : float
    {
        return self::create(1.0, 0.0, 0.0);
    }
    
    static public function medium() : float
    {
        return self::create(0.0, 1.0, 0.0);
    }
    
    static public function weak() : float
    {
        return self::create(0.0, 0.0, 1.0);
    }
    
    static public function create(float $a, float $b, float $c, float $w = 1.0) : float
    {
        $strength = 0.0;
        $strength += max(0.0, min(1000.0, $a * $w)) * 1000000.0;
        $strength += max(0.0, min(1000.0, $b * $w)) * 1000.0;
        $strength += max(0.0, min(1000.0, $c * $w));
        return $strength;
    }
    
    static public function clip(float $strength) : float
    {
        return max(0.0, min(self::required(), $strength));
    }
}