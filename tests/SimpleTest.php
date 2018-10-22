<?php

use PHPUnit\Framework\TestCase;
use Ctefan\Kiwi\Solver;
use Ctefan\Kiwi\Variable;
use Ctefan\Kiwi\Symbolics;
use Ctefan\Kiwi\Strength;

class SimpleTest extends TestCase
{
    protected const EPSILON = 1.0e-8;

    public function testCreateVariable() : void
    {
        $solver = new Solver();
        $x = new Variable('x');

        $solver->addConstraint(Symbolics::equals(Symbolics::add($x, 2.0), 20.0));
        $solver->updateVariables();

        $this->assertEquals(18, $x->getValue());
    }

    public function testAddVariables() : void
    {
        $solver = new Solver();
        $x = new Variable('x');
        $y = new Variable('y')
        ;
        $solver->addConstraint(Symbolics::equals($x, 20.0));
        $solver->addConstraint(Symbolics::equals(Symbolics::add($x, 2.0), Symbolics::add($y, 10.0)));
        $solver->updateVariables();

        $this->assertEquals(12, $y->getValue());
        $this->assertEquals(20, $x->getValue());
    }

    public function testEqualVariables()
    {
        $solver = new Solver();
        $x = new Variable('x');
        $y = new Variable('y');
        $solver->addConstraint(Symbolics::equals($x, $y));
        $solver->updateVariables();
        $this->assertEquals($x->getValue(), $y->getValue());
    }

    public function testVariablesWithStrengths()
    {
        $solver = new Solver();
        $x = new Variable('x');
        $y = new Variable('y');

        $solver->addConstraint(Symbolics::lessThanOrEquals($x, $y));
        $solver->addConstraint(Symbolics::equals($y, Symbolics::add($x, 3.0)));
        $solver->addConstraint(Symbolics::equals($x, 10.0)->setStrength(Strength::weak()));
        $solver->addConstraint(Symbolics::equals($y, 10.0)->setStrength(Strength::weak()));
        $solver->updateVariables();

        if (abs($x->getValue() - 10.0) < self::EPSILON) {
            $this->assertEquals(10.0, $x->getValue());
            $this->assertEquals(13.0, $y->getValue());
        } else {
            $this->assertEquals(7.0, $x->getValue());
            $this->assertEquals(10.0, $y->getValue());
        }
    }

    public function testAddAndDeleteConstraints()
    {
        $solver = new Solver();
        $x = new Variable('x');

        $constraint100 = Symbolics::lessThanOrEquals($x, 100.0)->setStrength(Strength::weak());
        $solver->addConstraint($constraint100);
        $solver->updateVariables();
        $this->assertEquals(100.0, $x->getValue());

        $constraint10 = Symbolics::lessThanOrEquals($x, 10.0);
        $constraint20 = Symbolics::lessThanOrEquals($x, 20.0);

        $solver->addConstraint($constraint10);
        $solver->addConstraint($constraint20);
        $solver->updateVariables();
        $this->assertEquals(10.0, $x->getValue());

        $solver->removeConstraint($constraint10);
        $solver->updateVariables();
        $this->assertEquals(20.0, $x->getValue());

        $solver->removeConstraint($constraint20);
        $solver->updateVariables();
        $this->assertEquals(100.0, $x->getValue());

        // TODO continue to implement this test and further tests
    }
}