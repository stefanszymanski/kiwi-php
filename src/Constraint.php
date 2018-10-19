<?php

namespace Ctefan\Kiwi;

class Constraint
{
    /**
     * @var Expression
     */
    protected $expression;
    
    /**
     * @var float
     */
    protected $strength;
    
    /**
     * @var int
     */
    protected $operator;
    
    public function __construct(Expression $expression, int $operator, ?float $strength)
    {
        $this->expression = self::reduce($expression);
        $this->operator = $operator;
        $this->strength = $strength ? Strength::clip($strength) : Stength::required();
    }
    
    static public function createFromConstraint(self $otherConstraint, float $strength) : self
    {
        return new self($otherConstraint->getExpression(), $otherConstraint->getOperator(), $strength);
    }
    
    static private function reduce(Expression $expression) : Expression
    {
        $variables = new \SplObjectStorage();
        foreach ($expression->getTerms() as $term) {
            $variable = $term->getVariable();
            if (false === $variables->contains($variable)) {
                $value = 0.0;
            } else {
                $value = $variables->offsetGet($variable);
            }
            $value += $term->getCoefficent();
            $variables->attach($variable, $value);
        }
        
        $reducedTerms = [];
        foreach ($variables as $variable) {
            $reducedTerms[] = new Term($variable, $variables->offsetGet($variable));
        }
        
        return new Expression($reducedTerms, $expression->getConstant());
    }
    
    public function getExpression(): Expression
    {
        return $this->expression;
    }
    
    public function setExpression(Expression $expression): void
    {
        $this->expression = $expression;
    }
    
    public function getStrength(): float
    {
        return $this->strength;
    }
    
    public function setStrength(float $strength): void
    {
        $this->strength = $strength;
    }
    
    public function getOperator(): int
    {
        return $this->operator;
    }
    
    public function setOperator(int $operator): void
    {
        $this->operator = $operator;
    }
}