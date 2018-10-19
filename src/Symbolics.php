<?php

namespace Ctefan\Kiwi;

class Symbolics
{
    protected function __construct(){}
    
    static public function multiplyVariable(Variable $variable, float $coefficient) : Term
    {
        return new Term($variable, $coefficient);
    }
    
    static public function divideVariable(Variable $variable, float $denominator) : Term
    {
        return self::multiplyVariable($variable, 1.0 / $denominator);
    }
    
    static public function negateVariable(Variable $variable) : Term
    {
        return self::multiply($variable, -1.0);
    }
    
    static public function multiplyTerm(Term $term, float $coefficient) : Term
    {
        return new Term($term->getVariable(), $term->getCoefficient() * $coefficient);
    }
    
    static public function divideTerm(Term $term, float $denominator) : Term
    {
        return self::multiplyTerm($term, 1.0 / $denominator);
    }
    
    static public function negateTerm(Term $term) : Term
    {
        return self::multiplyTerm($term, -1.0);
    }
    
    static public function multiplyExpression(Expression $expression, float $coefficient) : Expression
    {
        $terms = [];
        foreach ($expression->getTerms() as $term) {
            $terms[] = self::multiplyTerm($term, $coefficient);
        }
        return new Expression($terms, $expression->getConstant() * $coefficient);
    }
    
    static public function multiplyExpressions(Expression $a, Expression $b) : Expression
    {
        if (true === $a->isConstant()) {
            return self::multiplyExpresion($b, $a->getConstant());
        } elseif (true === $b->isConstant()) {
            return self::multiplyExpression($a, $b->getConstant());
        } else {
            // TODO throw exception
        }
    }
    
    static public function divideExpression(Expression $expression, float $denominator) : Expression
    {
        return self::multiplyExpression($expression, 1.0 / $denominator);
    }
    
    static public function divideExpressions(Expression $a, Expression $b) : Expression
    {
        if (true === $b->isConstant()) {
            return self::divideExpression($a, $b->getConstant());
        } else {
            // TODO throw exception
        }
    }
    
    static public function negateExpression(Expression $expression) : Expression
    {
        return self::multiplyExpression($expression, -1.0);
    }
    
    static public function addExpressions(Expression $a, Expression $b) : Expression
    {
        $terms = [];
        $terms += $a->getTerms();
        $terms += $b->getTerms();
        return new Expression($terms, $a->getConstant() + $b->getConstant());
    }
    
    static public function addTermToExpression(Expression $expression, Term $term) : Expression
    {
        $terms = [];
        $terms += $expression->getTerms();
        $terms[] = $term;
        return new Expression($terms, $expression->getConstant());
    }
    
    static public function addVariableToExpression(Expression $expression, Variable $variable) : Expression
    {
        return self::addTermToExpression($expression, new Term($variable));
    }
    
    static public function addConstantToExpression(Expression $expression, float $constant) : Expression
    {
        return new Expression($expression->getTerms(), $expression->getConstant() + $constant);
    }
    
    static public function subtractExpressions(Expression $a, Expression $b) : Expression
    {
        return self::addExpressions($a, self::negateExpression($b));
    }
    
    static public function subtractTermFromExpression(Expression $expression, Term $term) : Expression
    {
        return self::addTermToExpression($expression, self::negateTerm($term));
    }
    
    static public function subtractVariableFromExpression(Expression $expression, Variable $variable) : Expression
    {
        return self::addVariableToExpression($expression, self::negateVariable($variable));
    }
    
    static public function subtractContantFromExpression(Expression $expression, float $constant) : Expression
    {
        return self::addConstantToExpression($expression, -$constant);
    }
    
    static public function addExpressionToTerm(Term $term, Expression $expression) : Expression
    {
        return self::addTermToExpression($expression, $term);
    }
    
    static public function addTerms(Term $a, Term $b) : Expression
    {
        $terms = [$a, $b];
        return new Expression($terms);
    }
    
    static public function addConstantToTerm(Term $term, float $constant) : Expression
    {
        return new Expression($term, $constant);
    }
    
   static public function subtractExpressionFromTerm(Term $term, Expression $expression) : Expression
   {
       return self::addTermToExpression(self::negateExpression($expression), $term);
   }
   
   static public function subtractTerms(Term $a, Term $b) : Expression
   {
       return self::addTerms($a, self::negateTerm($b));
   }
   
   // TODO continue implementing with "public static Expression subtract(Term term, Variable variable)"
}