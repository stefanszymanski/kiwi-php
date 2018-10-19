<?php

namespace Ctefan\Kiwi;

class Util
{
    private const EPS = 1.0e-8;
    
    static public function isNearZero(float $value) : bool
    {
        return $value < 0.0
            ? -$value < self::EPS
            : $value < self::EPS;
    }
}