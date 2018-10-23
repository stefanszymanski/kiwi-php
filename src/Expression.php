<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

/**
 * An expression of terms and a constant.
 */
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

    /**
     * Expression constructor.
     *
     * @param array $terms
     * @param float $constant
     */
    public function __construct(array $terms = [], float $constant = 0.0)
    {
        $this->terms = $terms;
        $this->constant = $constant;
    }

    /**
     * Create a new Expression from a Term.
     *
     * @param Term $term
     * @return Expression
     */
    static public function createFromTerm(Term $term) : self
    {
        return new self([$term], 0.0);
    }

    /**
     * @return float
     */
    public function getValue() : float
    {
        $value = $this->constant;
        foreach ($this->terms as $term) {
            $value += $term->getValue();
        }
        return $value;
    }

    /**
     * @return bool
     */
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

    /**
     * @param array $terms
     */
    public function setTerms(array $terms): void
    {
        $this->terms = $terms;
    }

    /**
     * @return float
     */
    public function getConstant(): float
    {
        return $this->constant;
    }

    /**
     * @param float $constant
     */
    public function setConstant(float $constant): void
    {
        $this->constant = $constant;
    }
}