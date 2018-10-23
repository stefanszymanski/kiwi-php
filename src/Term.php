<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class Term
{
    /**
     * @var Variable
     */
     protected $variable;

    /**
     * @var float
     */
     protected $coefficient;

    /**
     * Term constructor.
     *
     * @param Variable $variable
     * @param float $coefficient
     */
    public function __construct(Variable $variable, float $coefficient = 1.0)
     {
         $this->variable = $variable;
         $this->coefficient = $coefficient;
     }

    /**
     * @return float
     */
    public function getValue() : float
     {
         return $this->coefficient * $this->variable->getValue();
     }

    /**
     * @return Variable
     */
    public function getVariable(): Variable
    {
        return $this->variable;
    }

    /**
     * @param Variable $variable
     */
    public function setVariable(Variable $variable): void
    {
        $this->variable = $variable;
    }

    /**
     * @return float
     */
    public function getCoefficient(): float
    {
        return $this->coefficient;
    }

    /**
     * @param float $coefficient
     */
    public function setCoefficient(float $coefficient): void
    {
        $this->coefficient = $coefficient;
    }
}