<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

/**
 * A linear constraint equation.
 *
 * A constraint equation is composed of an expression, an operator, and a strength.
 * The right hand side of the equation is implicitly zero.
 */
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

    /**
     * Constraint constructor.
     *
     * @param Expression $expression
     * @param int $operator
     * @param float|null $strength
     */
    public function __construct(Expression $expression, int $operator, ?float $strength = null)
    {
        $this->expression = self::reduce($expression);
        $this->operator = $operator;
        $this->strength = $strength ? Strength::clip($strength) : Strength::required();
    }

    /**
     * Create a Constraint from another Constraint.
     *
     * @param self $otherConstraint
     * @param float $strength
     * @return Constraint
     */
    static public function createFromConstraint(self $otherConstraint, float $strength): self
    {
        return new self($otherConstraint->getExpression(), $otherConstraint->getOperator(), $strength);
    }

    /**
     * @param Expression $expression
     * @return Expression
     */
    static private function reduce(Expression $expression): Expression
    {
        $variables = new \SplObjectStorage();
        foreach ($expression->getTerms() as $term) {
            $variable = $term->getVariable();
            if (false === $variables->contains($variable)) {
                $value = 0.0;
            } else {
                $value = $variables->offsetGet($variable);
            }
            $value += $term->getCoefficient();
            $variables->attach($variable, $value);
        }

        $reducedTerms = [];
        foreach ($variables as $variable) {
            $reducedTerms[] = new Term($variable, $variables->offsetGet($variable));
        }

        return new Expression($reducedTerms, $expression->getConstant());
    }

    /**
     * @return Expression
     */
    public function getExpression(): Expression
    {
        return $this->expression;
    }

    /**
     * @param Expression $expression
     */
    public function setExpression(Expression $expression): void
    {
        $this->expression = $expression;
    }

    /**
     * @return float
     */
    public function getStrength(): float
    {
        return $this->strength;
    }

    /**
     * @param float $strength
     * @return Constraint
     */
    public function setStrength(float $strength): self
    {
        $this->strength = $strength;
        return $this;
    }

    /**
     * @return int
     */
    public function getOperator(): int
    {
        return $this->operator;
    }

    /**
     * @param int $operator
     */
    public function setOperator(int $operator): void
    {
        $this->operator = $operator;
    }
}