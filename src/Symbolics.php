<?php

namespace Ctefan\Kiwi;

use Ctefan\Kiwi\Exception\NonlinearExpressionException;

class Symbolics
{
    protected function __construct(){}
    
    static public function multiply($a, $b)
    {
        return self::_callMethodForArguments($a, $b, 'multiply%sWith$s');
    }
    
    static public function divide($a, $b)
    {
        return self::_callMethodForArguments($a, $b, 'divide%2$sBy$1$s');
    }
    
    static public function negate($subject)
    {
        return self::_callMethodForArguments($subject, null, 'negate%s');
    }
    
    static public function add($a, $b) : Expression
    {
        return self::_callMethodForArguments($a, $b, 'add%2$sTo%1$s');
    }
    
    static public function subtract($a, $b) : Expression
    {
        return self::_callMethodForArguments($a, $b, 'subtract$2$sFrom%1$s');
    }
    
    static public function equals($a, $b) : Constraint
    {
        return self::_callMethodForArguments($a, $b, 'equals%s%s');
    }
    
    static public function lessThanOrEquals($a, $b) : Constraint
    {
        return self::_callMethodForArguments($a, $b, 'lessThanOrEquals%s%s');
    
    }
    
    static public function greaterThanOrEquals($a, $b) : Constraint
    {
        return self::_callMethodForArguments($a, $b, 'greaterThanOrEquals%s%s');
    }

    static private $names = [
        Expression::class => 'Expression',
        Term::class => 'Term',
        Variable::class => 'Variable',
        'double' => 'Constant',
        'integer' => 'Constant',
        'NULL' => '',
    ];
    
    static private function _callMethodForArguments($a, $b, string $format)
    {
        $methodName = sprintf(
            $format,
            self::$names[is_object($a) ? get_class($a) : gettype($a)],
            self::$names[is_object($b) ? get_class($b) : gettype($b)]
        );
        if (false === method_exists(self::class, $methodName)) {
            throw new \InvalidArgumentException();
        }
        return self::$methodName($a, $b);
    }
    

    /*
     * Term factory methods.
     */
    
    static public function multiplyVariableWithConstant(Variable $variable, float $coefficient) : Term
    {
        return new Term($variable, $coefficient);
    }
    
    static public function divideVariableByConstant(Variable $variable, float $denominator) : Term
    {
        return self::multiplyVariableWithConstant($variable, 1.0 / $denominator);
    }
    
    static public function negateVariable(Variable $variable) : Term
    {
        return self::multiply($variable, -1.0);
    }
    
    static public function multiplyTermWithConstant(Term $term, float $coefficient) : Term
    {
        return new Term($term->getVariable(), $term->getCoefficient() * $coefficient);
    }
    
    static public function divideTermByConstant(Term $term, float $denominator) : Term
    {
        return self::multiplyTermWithConstant($term, 1.0 / $denominator);
    }
    
    static public function negateTerm(Term $term) : Term
    {
        return self::multiplyTermWithConstant($term, -1.0);
    }
    
    
    /*
     * Expression factory methods.
     */
    
    static public function multiplyExpressionWithConstant(Expression $expression, float $coefficient) : Expression
    {
        $terms = [];
        foreach ($expression->getTerms() as $term) {
            $terms[] = self::multiplyTermWithConstant($term, $coefficient);
        }
        return new Expression($terms, $expression->getConstant() * $coefficient);
    }
    
    static public function multiplyExpressionWithExpression(Expression $a, Expression $b) : Expression
    {
        if (true === $a->isConstant()) {
            return self::multiplyExpressionWithConstant($b, $a->getConstant());
        } elseif (true === $b->isConstant()) {
            return self::multiplyExpressionWithConstant($a, $b->getConstant());
        } else {
            throw new NonlinearExpressionException();
        }
    }
    
    static public function divideExpressioByConstant(Expression $expression, float $denominator) : Expression
    {
        return self::multiplyExpressionWithConstant($expression, 1.0 / $denominator);
    }
    
    static public function divideExpressionByExpression(Expression $a, Expression $b) : Expression
    {
        if (true === $b->isConstant()) {
            return self::divideExpressioByConstant($a, $b->getConstant());
        } else {
            throw new NonlinearExpressionException();
        }
    }
    
    static public function negateExpression(Expression $expression) : Expression
    {
        return self::multiplyExpressionWithConstant($expression, -1.0);
    }
    
    static public function addExpressionToExpression(Expression $a, Expression $b) : Expression
    {
        $terms = array_merge($a->getTerms(), $b->getTerms());
        return new Expression($terms, $a->getConstant() + $b->getConstant());
    }
    
    static public function addTermToExpression(Expression $expression, Term $term) : Expression
    {
        $terms = $expression->getTerms();
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
    
    static public function subtractExpressionFromExpression(Expression $a, Expression $b) : Expression
    {
        return self::addExpressionToExpression($a, self::negateExpression($b));
    }
    
    static public function subtractTermFromExpression(Expression $expression, Term $term) : Expression
    {
        return self::addTermToExpression($expression, self::negateTerm($term));
    }
    
    static public function subtractVariableFromExpression(Expression $expression, Variable $variable) : Expression
    {
        return self::addTermToExpression($expression, self::negateVariable($variable));
    }
    
    static public function subtractConstantFromExpression(Expression $expression, float $constant) : Expression
    {
        return self::addConstantToExpression($expression, -$constant);
    }
    
    static public function addExpressionToTerm(Term $term, Expression $expression) : Expression
    {
        return self::addTermToExpression($expression, $term);
    }
    
    static public function addTermToTerm(Term $a, Term $b) : Expression
    {
        $terms = [$a, $b];
        return new Expression($terms);
    }
    
    static public function addVariableToTerm(Term $term, Variable $variable) : Expression
    {
        return self::addTermToTerm($term, new Term($variable));
    }
    
    static public function addConstantToTerm(Term $term, float $constant) : Expression
    {
        return new Expression([$term], $constant);
    }
    
   static public function subtractExpressionFromTerm(Term $term, Expression $expression) : Expression
   {
       return self::addTermToExpression(self::negateExpression($expression), $term);
   }
   
   static public function subtractTermFromTerm(Term $a, Term $b) : Expression
   {
       return self::addTermToTerm($a, self::negateTerm($b));
   }
   
   static public function subtractVariableFromTerm(Term $term, Variable $variable) : Expression
   {
       return self::addTermToTerm($term, self::negateVariable($variable));
   }
   
   static public function subtractConstantFromTerm(Term $term, float $constant) : Expression
   {
       return self::addConstantToTerm($term, -$constant);
   }
   
   static public function addExpressionToVariable(Variable $variable, Expression $expression) : Expression
   {
       return self::addVariableToExpression($expression, $variable);
   }
   
   static public function addTermToVariable(Variable $variable, Term $term) : Expression
   {
       return self::addVariableToTerm($term, $variable);
   }
   
   static public function addVariableToVariable(Variable $a, Variable $b) : Expression
   {
       return self::addVariableToTerm(new Term($a), $b);
   }
   
   static public function addConstantToVariable(Variable $variable, float $constant) : Expression
   {
       return self::addConstantToTerm(new Term($variable), $constant);
   }
   
   static public function subtractExpressionFromVariable(Variable $variable, Expression $expression) : Expression
   {
       return self::addExpressionToVariable($variable, self::negateExpression($expression));
   }
   
   static public function subtractTermFromVariable(Variable $variable, Term $term) : Expression
   {
       return self::addTermToVariable($variable, self::negateTerm($term));
   }
   
   static public function subtractVariableFromVariable(Variable $a, Variable $b) : Expression
   {
       return self::addTermToVariable($a, self::negateVariable($b));
   }
   
   static public function subtractConstantFromVariable(Variable $variable, float $constant) : Expression
   {
       return self::addConstantToVariable($variable, -$constant);
   }
   
   static public function addExpressionToConstant(float $constant, Expression $expression) : Expression
   {
       return self::addConstantToExpression($expression, $constant);
   }
   
   static public function addTermToConstant(float $constant, Term $term) : Expression
   {
       return self::addConstantToTerm($term, $constant);
   }
   
   static public function addVariableToConstant(float $constant, Variable $variable) : Expression
   {
       return self::addConstantToVariable($variable, $constant);
   }
   
   static public function subtractExpressionFromConstant(float $constant, Expression $expression) : Expression
   {
       return self::addConstantToExpression(self::negateExpression($expression), $constant);
   }
   
   static public function subtractTermFromConstant(float $constant, Term $term) : Expression
   {
       return self::addConstantToTerm(self::negateTerm($term), $constant);
   }
   
   static public function subtractVariableFromConstant(float $constant, Variable $variable) : Expression
   {
       return self::addConstantToTerm(self::negateVariable($variable), $constant);
   }
   
   
   /*
    * Constraint factory methods.
    */
   
   static public function equalsExpressionExpression(Expression $a, Expression $b) : Constraint
   {
       return new Constraint(self::subtractExpressionFromExpression($a, $b), RelationalOperator::EQ);
   }
   
   static public function equalsExpressionTerm(Expression $expression, Term $term) : Constraint
   {
    return self::equalsExpressionExpression($expression, Expression::createFromTerm($term));
   }
   
   static public function equalsExpressionVariable(Expression $expression, Variable $variable) : Constraint
   {
       return self::equalsExpressionTerm($expression, new Term($variable));
   }
   
   static public function equalsExpressionConstant(Expression $expression, float $constant) : Constraint
   {
       return self::equalsExpressionExpression($expression, new Expression([], $constant));
   }
   
   static public function lessThanOrEqualsExpressionExpression(Expression $a, Expression $b) : Constraint
   {
       return new Constraint(self::subtractExpressionFromExpression($a, $b), RelationalOperator::LE);
   }
   
   static public function lessThanOrEqualsExpressionTerm(Expression $expression, Term $term) : Constraint
   {
       return self::lessThanOrEqualsExpressionExpression($expression, Expression::createFromTerm($term));
   }
   
   static public function lessThanOrEqualsExpressionVariable(Expression $expression, Variable $variable) : Constraint
   {
       return self::lessThanOrEqualsExpressionTerm($expression, new Term($variable));
   }
   
   static public function lessThanOrEqualsExpressionConstant(Expression $expression, float $constant) : Constraint
   {
       return self::lessThanOrEqualsExpressionExpression($expression, new Expression([], $constant));
   }
   
   static public function greaterThanOrEqualsExpressionExpression(Expression $a, Expression $b) : Constraint
   {
       return new Constraint(self::subtractExpressionFromExpression($a, $b), RelationalOperator::GE);
   }
   
   static public function greaterThanOrEqualsExpressionTerm(Expression $expression, Term $term) : Constraint
   {
       return self::greaterThanOrEqualsExpressionExpression($expression, Expression::createFromTerm($term));
   }
   
   static public function greaterThanOrEqualsExpressionVariable(Expression $expression, Variable $variable) : Constraint
   {
       return self::greaterThanOrEqualsExpressionTerm($expression, new Term($variable));
   }
   
   static public function greaterThanOrEqualsExpressionConstant(Expression $expression, float $constant) : Constraint
   {
       return self::greaterThanOrEqualsExpressionExpression($expression, new Expression([], $constant));
   }
   
   static public function equalsTermExpression(Term $term, Expression $expression) : Constraint
   {
       return self::equalsExpressionExpression(Expression::createFromTerm($term), $expression);
   }
   
   static public function equalsTermTerm(Term $a, Term $b) : Constraint
   {
       return self::equalsExpressionTerm(Expression::createFromTerm($a), $b);
   }
   
   static public function equalsTermVariable(Term $term, Variable $variable) : Constraint
   {
       return self::equalsExpressionVariable(Expression::createFromTerm($term), $variable);
   }
   
   static public function equalsTermConstant(Term $term, float $constant) : Constraint
   {
       return self::equalsExpressionConstant(Expression::createFromTerm($term), $constant);
   }
   
   static public function lessThanOrEqualsTermExpression(Term $term, Expression $expression) : Constraint
   {
       return self::lessThanOrEqualsExpressionExpression(Expression::createFromTerm($term), $expression);
   }
   
   static public function lessThanOrEqualsTermTerm(Term $a, Term $b) : Constraint
   {
       return self::lessThanOrEqualsExpressionTerm(Expression::createFromTerm($a), $b);
   }
   
   static public function lessThanOrEqualsTermVariable(Term $term, Variable $variable) : Constraint
   {
       return self::lessThanOrEqualsExpressionVariable(Expression::createFromTerm($term), $variable);
   }
   
   static public function lessThanOrEqualsTermConstant(Term $term, float $constant) : Constraint
   {
       return self::lessThanOrEqualsExpressionConstant(Expression::createFromTerm($term), $constant);
   }
   
   static public function greaterThanOrEqualsTermExpression(Term $term, Expression $expression) : Constraint
   {
       return self::greaterThanOrEqualsExpressionExpression(Expression::createFromTerm($term), $expression);
   }
   
   static public function greaterThanOrEqualsTermTerm(Term $a, Term $b) : Constraint
   {
       return self::greaterThanOrEqualsExpressionTerm(Expression::createFromTerm($a), $b);
   }
   
   static public function greaterThanOrEqualsTermVariable(Term $term, Variable $variable) : Constraint
   {
       return self::greaterThanOrEqualsExpressionVariable(Expression::createFromTerm($term), $variable);
   }
   
   static public function greaterThanOrEqualsTermConstant(Term $term, float $constant) : Constraint
   {
       return self::greaterThanOrEqualsExpressionConstant(Expression::createFromTerm($term), $constant);
   }
   
   static public function equalsVariableExpression(Variable $variable, Expression $expression) : Constraint
   {
       return self::equalsExpressionVariable($expression, $variable);
   }
   
   static public function equalsVariableTerm(Variable $variable, Term $term) : Constraint
   {
       return self::equalsTermVariable($term, $variable);
   }
   
   static public function equalsVariableVariable(Variable $a, Variable $b) : Constraint
   {
       return self::equalsTermVariable(new Term($a), $b);
   }
   
   static public function equalsVariableConstant(Variable $variable, float $constant) : Constraint
   {
       return self::equalsTermConstant(new Term($variable), $constant);
   }
   
   static public function lessThanOrEqualsVariableExpression(Variable $variable, Expression $expression) : Constraint
   {
       return self::lessThanOrEqualsTermExpression(new Term($variable), $expression);
   }
   
   static public function lessThanOrEqualsVariableTerm(Variable $variable, Term $term) : Constraint
   {
       return self::lessThanOrEqualsTermTerm(new Term($variable), $term);
   }
   
   static public function lessThanOrEqualsVariableVariable(Variable $a, Variable $b) : Constraint
   {
       return self::lessThanOrEqualsTermVariable(new Term($a), $b);
   }
   
   static public function lessThanOrEqualsVariableConstant(Variable $variable, float $constant) : Constraint
   {
       return self::lessThanOrEqualsTermConstant(new Term($variable), $constant);
   }
   
   static public function greaterThanOrEqualsVariableExpression(Variable $variable, Expression $expression) : Constraint
   {
       return self::greaterThanOrEqualsTermExpression(new Term($variable), $expression);
   }
   
   static public function greaterThanOrEqualsVariabeTerm(Variable $variable, Term $term) : Constraint
   {
       return self::greaterThanOrEqualsTermTerm(new Term($variable), $term);
   }
   
   static public function greaterThanOrEqualsVariableVariable(Variable $a, Variable $b) : Constraint
   {
       return self::greaterThanOrEqualsTermVariable(new Term($a), $b);
   }
   
   static public function greaterThanOrEqualsVariableConstant(Variable $variable, float $constant) : Constraint
   {
       return self::greaterThanOrEqualsTermConstant(new Term($variable), $constant);
   }
   
   static public function equalsConstantExpression(float $constant, Expression $expression) : Constraint
   {
       return self::equalsExpressionConstant($expression, $constant);
   }
   
   static public function equalsConstantTerm(float $constant, Term $term) : Constraint
   {
       return self::equalsTermConstant($term, $constant);
   }
   
   static public function equalsConstantVariable(float $constant, Variable $variable) : Constraint
   {
       return self::equalsVariableConstant($variable, $constant);
   }
   
   static public function lessThanOrEqualsConstantExpression(float $constant, Expression $expression) : Constraint
   {
       return self::lessThanOrEqualsExpressionExpression(new Expression([], $constant), $expression);
   }
   
   static public function lessThanOrEqualsConstantTerm(float $constant, Term $term) : Constraint
   {
       return self::lessThanOrEqualsExpressionTerm(new Expression([], $constant), $term);
   }
   
   static public function lessThanOrEqualsConstantVariable(float $constant, Variable $variable) : Constraint
   {
       return self::lessThanOrEqualsExpressionVariable(new Expression([], $constant), $variable);
   }
   
   static public function greaterThanOrEqualsConstantExpression(float $constant, Expression $expression) : Constraint
   {
       return self::greaterThanOrEqualsExpressionExpression(new Expression([], $constant), $expression);
   }
   
   static public function greaterThanOrEqualsConstantTerm(float $constant, Term $term) : Constraint
   {
       return self::greaterThanOrEqualsExpressionTerm(new Expression([], $constant), $term);
   }
   
   static public function greaterThanOrEqualsConstantVariable(float $constant, Variable $variable) : Constraint
   {
       return self::greaterThanOrEqualsExpressionVariable(new Expression([], $constant), $variable);
   }
   
   static public function modifyStrength(Constraint $constraint, float $strength) : Constraint
   {
       return Constraint::createFromConstraint($constraint, $strength);
   }
}