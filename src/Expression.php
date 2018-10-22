<?php

namespace Ctefan\Kiwi;

class Expression
{
    /**
     * @var array|Term[]
     */
    protected $terms;
    
    /**
     * @var float
     */
    protected $constant;
    
    public function __construct(array $terms = [], float $constant = 0.0)
    {
        $this->terms = $terms;
        $this->constant = $constant;
    }
    
    static public function createFromTerm(Term $term) : self
    {
        return new self([$term], 0.0);
    }
    
    public function getValue() : float
    {
        $value = $this->constant;
        foreach ($this->terms as $term) {
            $value += $term->getValue();
        }
        return $value;
    }
    
    public function isConstant() : bool
    {
        return 0 === count($this->terms);
    }

    /**
     * @return array|Term[]
     */
    public function getTerms() : array
    {
        return $this->terms;
    }
    
    public function setTerms(array $terms): void
    {
        $this->terms = $terms;
    }
    
    public function getConstant(): float
    {
        return $this->constant;
    }
    
    public function setConstant(float $constant): void
    {
        $this->constant = $constant;
    }
}