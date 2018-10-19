<?php

namespace Ctefan\Kiwi;

class Term
{
     protected $variable;
     
     protected $coefficient;
     
     public function __construct(Variable $variable, float $coefficient = 1.0)
     {
         $this->variable = $variable;
         $this->coefficient = $coefficient;
     }
     
     public function getValue() : float
     {
         return $this->coefficient * $this->variable->getValue();
     }
    
    public function getVariable(): Variable
    {
        return $this->variable;
    }
    
    public function setVariable(Variable $variable): void
    {
        $this->variable = $variable;
    }
    
    public function getCoefficient(): float
    {
        return $this->coefficient;
    }
    
    public function setCoefficient(float $coefficient): void
    {
        $this->coefficient = $coefficient;
    }
}