<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ctefan\Kiwi\Solver;
use Ctefan\Kiwi\Variable;
use Ctefan\Kiwi\Symbolics;
use Ctefan\Kiwi\Expression;

class ExpressionVariableTest extends TestCase
{
    public function testLessThanOrEqual(): void
    {
        $solver = new Solver();
        $x = new Variable('x');

        $solver->addConstraint(Symbolics::lessThanOrEquals(new Expression([], 100.0), $x));
        $solver->updateVariables();

        $this::assertLessThanOrEqual(100.0, $x->getValue());

        $solver->addConstraint(Symbolics::equals($x, 110.0));
        $solver->updateVariables();

        $this::assertEquals(110.0, $x->getValue());
    }

    /**
     * @expectedException \Ctefan\Kiwi\Exception\UnsatisfiableConstraintException
     */
    public function testLessThanOrEqualUnsatisfiable(): void
    {
        $solver = new Solver();
        $x = new Variable('x');

        $solver->addConstraint(Symbolics::lessThanOrEquals(new Expression([], 100.0), $x));
        $solver->updateVariables();

        $this::assertLessThanOrEqual(100.0, $x->getValue());

        $solver->addConstraint(Symbolics::equals($x, 10.0));
        $solver->updateVariables();
    }

    public function testGreaterThanOrEqual(): void
    {
        $solver = new Solver();
        $x = new Variable('x');

        $solver->addConstraint(Symbolics::greaterThanOrEquals(new Expression([], 100.0), $x));
        $solver->updateVariables();

        $this::assertGreaterThanOrEqual(100.0, $x->getValue());

        $solver->addConstraint(Symbolics::equals($x, 90.0));
        $solver->updateVariables();

        $this->assertEquals(90.0, $x->getValue());
    }

    /**
     * @expectedException \Ctefan\Kiwi\Exception\UnsatisfiableConstraintException
     */
    public function testGreaterThanOrEqualUnsatisfiable(): void
    {
        $solver = new Solver();
        $x = new Variable('x');

        $solver->addConstraint(Symbolics::greaterThanOrEquals(new Expression([], 100.0), $x));
        $solver->updateVariables();

        $this::assertGreaterThanOrEqual(100.0, $x->getValue());

        $solver->addConstraint(Symbolics::equals($x, 110.0));
        $solver->updateVariables();
    }
}