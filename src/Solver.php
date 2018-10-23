<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

use Ctefan\Kiwi\Exception\DuplicateConstraintException;
use Ctefan\Kiwi\Exception\DuplicateEditVariableException;
use Ctefan\Kiwi\Exception\InternalSolverException;
use Ctefan\Kiwi\Exception\RequiredFailureException;
use Ctefan\Kiwi\Exception\UnknownConstraintException;
use Ctefan\Kiwi\Exception\UnknownEditVariableException;
use Ctefan\Kiwi\Exception\UnsatisfiableConstraintException;

class Solver
{
    /**
     * @var \SplObjectStorage<Constraint, Tag>
     */
    protected $constraints;
    
    /**
     * @var \SplObjectStorage<Symbol, Row>
     */
    protected $rows;
    
    /**
     * @var \SplObjectStorage<Variable, Symbol>
     */
    protected $variables;
    
    /**
     * @var \SplObjectStorage<Variable, EditInfo>
     */
    protected $edits;
    
    /**
     * @var array<Symbol>
     */
    protected $infeasibleRows;
    
    /**
     * @var Row
     */
    protected $objective;
    
    /**
     * @var Row
     */
    protected $artificial;
    
    public function __construct()
    {
        $this->constraints = new \SplObjectStorage();
        $this->rows = new \SplObjectStorage();
        $this->variables = new \SplObjectStorage();
        $this->edits = new \SplObjectStorage();
        $this->infeasibleRows = [];
        $this->objective = new Row();
    }

    /**
     * Add a constraint to the solver.
     *
     * @param Constraint $constraint
     * @throws DuplicateConstraintException The given constraint has already been added to the solver.
     * @throws UnsatisfiableConstraintException The given constraint is required and cannot be satisfied.
     * @throws InternalSolverException
     */
    public function addConstraint(Constraint $constraint) : void
    {
        if (true === $this->constraints->contains($constraint)) {
            throw new DuplicateConstraintException($constraint);
        }

        // Creating a row causes symbols to reserved for the variables in the constraint.
        // If this method exits with an exception, then its possible those variables will linger in the var map.
		// Since its likely that those variables will be used in other constraints and since exceptional conditions are uncommon,
		// I'm not too worried about aggressive cleanup of the var map.
        $tag = new Tag();
        $row = $this->createRow($constraint, $tag);
        $subject = $this->chooseSubject($row, $tag);

        // If chooseSubject() could find a valid entering symbol, one last option is available if the entire row is composed of dummy variables.
		// If the constant of the row is zero, then this represents redundant constraints and the new dummy marker can enter the basis.
		// If the constant is non-zero, then it represents an unsatisfiable constraint.
        if ($subject->getType() === Symbol::INVALID && true === $this->allDummies($row)) {
            if (false === Util::isNearZero($row->getConstant())) {
                throw new UnsatisfiableConstraintException($constraint);
            } else {
                $subject = $tag->getMarker();
            }
        }

        // If an entering symbol still isn't found, then the row must be added using an artificial variable.
		// If that fails, then the row represents an unsatisfiable constraint.
        if ($subject->getType() === Symbol::INVALID) {
            if (false === $this->addWithArtificialVariable($row)) {
                throw new UnsatisfiableConstraintException($constraint);
            }
        } else {
            $row->solveForSymbol($subject);
            $this->substitute($subject, $row);
            $this->rows->attach($subject, $row);
        }

        $this->constraints->attach($constraint, $tag);

        // Optimizing after each constraint is added performs less aggregate work due to a smaller average system size.
		// It also ensures the solver remains in a consistent state.
        $this->optimize($this->objective);
    }

    /**
     * Remove a constraint from the solver.
     *
     * @param Constraint $constraint
     * @throws UnknownConstraintException The given constraint has not been added to the solver.
     * @throws InternalSolverException
     */
    public function removeConstraint(Constraint $constraint) : void
    {
        if (false === $this->constraints->contains($constraint)) {
            throw new UnknownConstraintException($constraint);
        }
        
        $tag = $this->constraints->offsetGet($constraint);
        $this->constraints->offsetUnset($constraint);

        // Remove the error effects from the objective function before pivoting,
        // or substitutions into the objective will lead to incorrect solver results.
        $this->removeConstraintEffects($constraint, $tag);

        // If the marker is basic, simply drop the row. Otherwise, pivot the marker into the basis and then drop the row.
        if (true === $this->rows->contains($tag->getMarker())) {
            $this->rows->offsetUnset($tag->getMarker());
        } else {
            $leaving = $this->getMarkerLeavingSymbol($tag->getMarker());
            if ($leaving->getType() === Symbol::INVALID) {
                throw new InternalSolverException('Internal solver error');
            }
            $row = $this->rows->offsetGet($leaving);
            $this->rows->offsetUnset($leaving);
            $row->solveForSymbols($leaving, $tag->getMarker());
            $this->substitute($tag->getMarker(), $row);
        }

        // Optimizing after each constraint is removed ensures that the solver remains consistent.
		// It makes the solver API easier to use at a small tradeoff for speed.
        $this->optimize($this->objective);
    }

    /**
     * Test whether a constraint has been added to the solver.
     *
     * @param Constraint $constraint
     * @return bool
     */
    public function hasConstraint(Constraint $constraint) : bool
    {
        return $this->constraints->contains($constraint);
    }

    /**
     * Add an edit variable to the solver.
     *
	 * This method should be called before the 'suggestValue' method is used to supply a suggested value
     * for the given edit variable.
     *
     * @param Variable $variable
     * @param float $strength
     * @throws DuplicateEditVariableException The given edit variable has already been added to the solver.
     * @throws RequiredFailureException The given strength is >= required.
     * @throws InternalSolverException
     */
    public function addEditVariable(Variable $variable, float $strength) : void
    {
        if ($this->edits->contains($variable)) {
            throw new DuplicateEditVariableException();
        }
        
        $strength = Strength::clip($strength);
        
        if (Strength::required() === $strength) {
            throw new RequiredFailureException();
        }
        
        $terms = [];
        $terms[] = new Term($variable);
        $constraint = new Constraint(new Expression($terms), RelationalOperator::EQ, $strength);
        
        try {
            $this->addConstraint($constraint);
        } catch (DuplicateConstraintException $e) {
            // TODO log
        } catch (UnsatisfiableConstraintException $e) {
            // TODO log
        }
        
        $info = new EditInfo($constraint, $this->constraints->offsetGet($constraint), 0.0);
        $this->edits->attach($variable, $info);
    }

    /**
     * Remove an edit variable from the solver.
     *
     * @param Variable $variable
     * @throws UnknownEditVariableException The given edit variable has not been added to the solver.
     * @throws InternalSolverException
     */
    public function removeEditVariable(Variable $variable) : void
    {
        if (false === $this->edits->contains($variable)) {
            throw new UnknownEditVariableException();
        }
        
        $info = $this->edits->offsetGet($variable);
        
        try {
            $this->removeConstraint($info->getConstraint());
        } catch (UnknownConstraintException $e) {
            // TODO log
        }
        
        $this->edits->offsetUnset($variable);
    }

    /**
     * Test whether an edit variable has been added to the solver.
     *
     * @param Variable $variable
     * @return bool
     */
    public function hasEditVariable(Variable $variable) : bool
    {
        return $this->edits->contains($variable);
    }

    /**
     * Suggest a value for the given edit variable.
     *
     * This method should be used after an edit variable as been added to the solver
     * in order to suggest the value for that variable.
     *
     * @param Variable $variable
     * @param float $value
     * @throws UnknownEditVariableException The given edit variable has not been added to the solver.
     * @throws InternalSolverException
     */
    public function suggestValue(Variable $variable, float $value) : void
    {
        if (false === $this->edits->contains($variable)) {
            throw new UnknownEditVariableException();
        }
        
        $info = $this->edits->offsetGet($variable);
        $delta = $value - $info->getConstant();
        $info->setContant($value);

        // Check first if the positive error variable is basic.
        if (true === $this->rows->contains($info->getTag()->getMarker())) {
            $row = $this->rows->offsetGet($info->getTag()->getMarker());
            if (0.0 > $row->add(-$delta)) {
                $this->infeasibleRows[] = $info->getTag()->getMarker();
            }
            $this->dualOptimize();
            return;
        }

        // Check next if the negative error variable is basic.
        if (true === $this->rows->contains($info->getTag()->getOther())) {
            $row = $this->rows->offsetGet($info->getTag()->getOther());
            if (0.0 > $row->add($delta)) {
                $this->infeasibleRows[] = $info->getTag()->getOther();
            }
            $this->dualOptimize();
            return;
        }

        // Otherwise update each row where the error variables exist.
        foreach ($this->rows as $symbol) {
            $currentRow = $this->rows->offsetGet($symbol);
            $coefficient = $currentRow->getCoefficientForSymbol($info->getTag()->getMarker());
            if (0.0 !== $coefficient && 0.0 > $currentRow->add($delta * $coefficient) && Symbol::EXTERNAL !== $symbol->getType()) {
                $this->infeasibleRows[] = $symbol;
            }
        }
        
        $this->dualOptimize();
    }

    /**
     * Update the values of the external solver variables.
     */
    public function updateVariables() : void
    {
        foreach ($this->variables as $variable) {
            $symbol = $this->variables->offsetGet($variable);
            if (false === $this->rows->contains($symbol)) {
                $variable->setValue(0.0);
            } else {
                $row = $this->rows->offsetGet($symbol);
                $variable->setValue($row->getConstant());
            }
        }
    }

    /**
     * Remove the effects of a constraint on the objective function.
     *
     * @param Constraint $constraint
     * @param Tag $tag
     */
    protected function removeConstraintEffects(Constraint $constraint, Tag $tag) : void
    {
        if ($tag->getMarker()->getType() === Symbol::ERROR) {
            $this->removeMarkerEffects($tag->getMarker(), $constraint->getStrength());
        } elseif ($tag->getOther()->getType() === Symbol::ERROR) {
            $this->removeMarkerEffects($tag->getOther(), $constraint->getStrength());
        }
    }

    /**
     * Remove the effects of an error marker on the objective function.
     *
     * @param Symbol $marker
     * @param float $strength
     */
    protected function removeMarkerEffects(Symbol $marker, float $strength) : void
    {
        if (true === $this->rows->contains($marker)) {
            $row = $this->rows->offsetGet($marker);
            $this->objective->insert($row, -$strength);
        } else {
            $this->objective->insert($marker, -$strength);
        }
    }

    /**
     * Compute the leaving symbol for a marker variable.
     *
	 * This method will return a symbol corresponding to a basic row which holds the given marker variable.
	 * The row will be chosen according to the following precedence:
	 * 1) The row with a restricted basic varible and a negative coefficient for the marker with the smallest ratio of -constant / coefficient.
	 * 2) The row with a restricted basic variable and the smallest ratio of constant / coefficient.
	 * 3) The last unrestricted row which contains the marker.
	 * If the marker does not exist in any row, an invalid symbol will be returned.
	 * This indicates an internal solver error since the marker should exist somewhere in the tableau.
     *
     * @param Symbol $marker
     * @return Symbol
     */
    protected function getMarkerLeavingSymbol(Symbol $marker) : Symbol
    {
        $max = PHP_FLOAT_MAX;
        $ratio1 = $max;
        $ratio2 = $max;
        
        $first = new Symbol();
        $second = new Symbol();
        $third = new Symbol();
        
        foreach ($this->rows as $symbol) {
            $candidateRow = $this->rows->offsetGet($symbol);
            $coefficient = $candidateRow->getCoefficientForSymbol($marker);
            if (0.0 === $coefficient) {
                continue;
            }
            if ($symbol->getType() === Symbol::EXTERNAL) {
                $third = $symbol;
            } elseif (0.0 > $coefficient) {
                $ratio = -$candidateRow->getConstant() / $coefficient;
                if ($ratio < $ratio1) {
                    $ratio1 = $ratio;
                    $first = $symbol;
                }
            } else {
                $ratio = $candidateRow->getConstant() / $coefficient;
                if ($ratio < $ratio2) {
                    $ratio2 = $ratio;
                    $second = $symbol;
                }
            }
        }
        
        if ($first->getType() !== Symbol::INVALID) {
            return $first;
        }
        if ($second->getType() !== Symbol::INVALID) {
            return $second;
        }
        return $third;
    }

    /**
     * Create a new Row object for the given constraint.
     *
	 * The terms in the constraint will be converted to cells in the row.
	 * Any term in the constraint with a coefficient of zero is ignored.
	 * This method uses the 'getVarSymbol' method to get the symbol for the variables added to the row.
	 * If the symbol for a given cell variable is basic, the cell variable will be substituted with the basic row.
	 * The necessary slack and error variables will be added to the row.
	 * If the constant for the row is negative, the sign for the row will be inverted so the constant becomes positive.
	 * The tag will be updated with the marker and error symbols to use for tracking the movement of the constraint in the tableau.
     *
     * @param Constraint $constraint
     * @param Tag $tag
     * @return Row
     */
    protected function createRow(Constraint $constraint, Tag $tag) : Row
    {
        $expression = $constraint->getExpression();
        $row = new Row($expression->getConstant());

        // Substitute the current basic variables into the row.
        foreach ($expression->getTerms() as $term) {
            if (false === Util::isNearZero($term->getCoefficient())) {
                $symbol = $this->getVariableSymbol($term->getVariable());
                if (false === $this->rows->contains($symbol)) {
                    $row->insert($symbol, $term->getCoefficient());
                } else {
                    $otherRow = $this->rows->offsetGet($symbol);
                    $row->insertOtherRow($otherRow, $term->getCoefficient());
                }
                
            }
        }

        // Add the necessary slack, error, and dummy variables.
        switch ($constraint->getOperator()) {
            case RelationalOperator::LE:
            case RelationalOperator::GE:
                $coefficient = $constraint->getOperator() === RelationalOperator::LE ? 1.0 : -1.0;
                $slack = new Symbol(Symbol::SLACK);
                $tag->setMarker($slack);
                $row->insert($slack, $coefficient);
                if ($constraint->getStrength() < Strength::required()) {
                    $error = new Symbol(Symbol::ERROR);
                    $tag->setOther($error);
                    $row->insert($error, -$coefficient);
                    $this->objective->insert($error, $constraint->getStrength());
                }
                break;
            case RelationalOperator::EQ:
                if ($constraint->getStrength() < Strength::required()) {
                    $errPlus = new Symbol(Symbol::ERROR);
                    $errMinus = new Symbol(Symbol::ERROR);
                    $tag->setMarker($errPlus);
                    $tag->setOther($errMinus);
                    $row->insert($errPlus, -1.0);
                    $row->insert($errMinus, 1.0);
                    $this->objective->insert($errPlus, $constraint->getStrength());
                    $this->objective->insert($errMinus, $constraint->getStrength());
                } else {
                    $dummy = new Symbol(Symbol::DUMMY);
                    $tag->setMarker($dummy);
                    $row->insert($dummy);
                }
                break;
        }

        // Ensure the row as a positive constant.
        if ($row->getConstant() < 0.0) {
            $row->reverseSign();
        }
        
        return $row;
    }

    /**
     * Choose the subject for solving for the row.
     *
	 * This method will choose the best subject for using as the solve target for the row.
	 * An invalid symbol will be returned if there is no valid target.
	 * The symbols are chosen according to the following precedence:
	 * 1) The first symbol representing an external variable.
	 * 2) A negative slack or error tag variable.
	 * If a subject cannot be found, an invalid symbol will be returned.
     *
     * @param Row $row
     * @param Tag $tag
     * @return Symbol
     */
    protected function chooseSubject(Row $row, Tag $tag) : Symbol
    {
        foreach ($row->getCells() as $symbol) {
            if ($symbol->getType() === Symbol::EXTERNAL) {
                return $symbol;
            }
            if ($tag->getMarker()->getType() === Symbol::SLACK || $tag->getMarker()->getType() === Symbol::ERROR) {
                if ($row->getCoefficientForSymbol($tag->getMarker()) < 0.0) {
                    return $tag->getMarker();
                }
            }
            if ($tag->getOther() !== null && ($tag->getOther()->getType() === Symbol::SLACK || $tag->getOther()->getType() === Symbol::ERROR)) {
                if ($row->getCoefficientForSymbol($tag->getOther()) < 0.0) {
                    return $tag->getOther();
                }
            }
        }
        return new Symbol();
    }

    /**
     * Add the row to the tableau using an artificial variable.
     *
	 * This will return false if the constraint cannot be satisfied.
     *
     * @param Row $row
     * @return bool
     * @throws InternalSolverException
     */
    protected function addWithArtificialVariable(Row $row) : bool
    {
        // Create and add the artificial variable to the tableau.
        $artificial = new Symbol(Symbol::SLACK);
        $this->rows->attach($artificial, Row::createFromRow($row));
        
        $this->artificial = Row::createFromRow($row);

        // Optimize the artificial objective.
		// This is successful only if the artificial objective is optimized to zero.
        $this->optimize($this->artificial);
        $success = Util::isNearZero($this->artificial->getConstant());
        $this->artificial = null;

        // If the artificial variable is basic, pivot the row so that it becomes non-basic.
		// If the row is constant, exit early.
        if (true === $this->rows->contains($artificial)) {
            $rowPointer = $this->rows->offsetGet($artificial);

            $deleteQueue = [];
            foreach ($this->rows as $symbol) {
                if ($this->rows->offsetGet($symbol) === $rowPointer) {
                    $deleteQueue[] = $symbol;
                }
            }
            while (false === empty($deleteQueue)) {
                $this->rows->offsetUnset(array_pop($deleteQueue));
            }
            
            if (0 === $rowPointer->getCells()->count()) {
                return $success;
            }
            
            $entering = $this->anyPivotableSymbol($rowPointer);
            if ($entering->getType() === Symbol::INVALID) {
                return false;
            }
            
            $rowPointer->solveForSymbols($artificial, $entering);
            $this->substitute($entering, $rowPointer);
            $this->rows->attach($entering, $rowPointer);
        }

        // Remove the artificial variable from the tableau.
        foreach ($this->rows as $symbol) {
            $rowEntry = $this->rows->offsetGet($symbol);
            $rowEntry->remove($artificial);
        }

        $this->objective->remove($artificial);
        
        return $success;
    }

    /**
     * Substitute the parametric symbol with the given row.
     *
	 * This method will substitute all instances of the parametric symbol in the tableau
     * and the objective function with the given row.
     *
     * @param Symbol $symbol
     * @param Row $row
     */
    protected function substitute(Symbol $symbol, Row $row) : void
    {
        foreach ($this->rows as $currentSymbol) {
            $currentRow = $this->rows->offsetGet($currentSymbol);
            $currentRow->substitute($symbol, $row);
            if ($currentSymbol->getType() !== Symbol::EXTERNAL && $currentRow->getConstant() < 0.0) {
                $this->infeasibleRows[] = $currentSymbol;
            }
        }
        
        $this->objective->substitute($symbol, $row);
        
        if (null !== $this->artificial) {
            $this->artificial->substitute($symbol, $row);
        }
    }

    /**
     * Optimize the system for the given objective function.
	 * This method performs iterations of Phase 2 of the simplex method until the objective function reaches a minimum.
     *
     * @param Row $objective
     * @throws InternalSolverException The value of the objective function is unbounded.
     */
    protected function optimize(Row $objective) : void
    {
        while (true) {
            $entering = $this->getEnteringSymbol($objective);
            if ($entering->getType() === Symbol::INVALID) {
                return;
            }
            
            $leaving = $this->getLeavingSymbol($entering);
            if ($leaving->getType() === Symbol::INVALID) {
                throw new InternalSolverException('The objective is unbounded.');
            }

            // Pivot the entering symbol into the basis.
            $row = $this->rows->offsetGet($leaving);
            $this->rows->offsetUnset($leaving);
            $row->solveForSymbols($leaving, $entering);
            $this->rows->attach($entering, $row);
            $this->substitute($entering, $row);
        }
    }

    /**
     * Optimize the system using the dual of the simplex method.
     *
	 * The current state of the system should be such that the objective function is optimal, but not feasible.
	 * This method will perform an iteration of the dual simplex method to make the solution both optimal and feasible.
     *
     * @throws InternalSolverException The system cannot be dual optimized.
     */
    protected function dualOptimize() : void
    {
        while (false === empty($this->infeasibleRows)) {
            $leaving = array_shift($this->infeasibleRows);
            if ($this->rows->contains($leaving)) {
                $row = $this->rows->offsetGet($leaving);
                if ($row->getConstant() < 0.0) {
                    $entering = $this->getDualEnteringSymbol($row);
                    if ($entering->getType() === Symbol::INVALID) {
                        throw new InternalSolverException('Internal solver error');
                    }
                    // Pivot the entering symbol into the basis.
                    $this->rows->offsetUnset($leaving);
                    $row->solveForSymbols($leaving, $entering);
                    $this->substitute($entering, $row);
                    $this->rows->attach($entering, $row);
                }
            }
            
        }
    }

    /**
     * Compute the entering variable for a pivot operation.
     *
	 * This method will return first symbol in the objective function which is non-dummy and has a coefficient less than zero.
	 * If no symbol meets the criteria, it means the objective function is at a minimum, and an invalid symbol is returned.
     *
     * @param Row $objective
     * @return Symbol
     */
    protected function getEnteringSymbol(Row $objective) : Symbol
    {
        foreach ($objective->getCells() as $symbol) {
            $coefficient = $objective->getCells()->offsetGet($symbol);
            if ($symbol->getType() !== Symbol::DUMMY && 0.0 > $coefficient) {
                return $symbol;
            }
        }
        return new Symbol();
    }

    /**
     * Compute the entering symbol for the dual optimize operation.
     *
	 * This method will return the symbol in the row which has a positive coefficient
     * and yields the minimum ratio for its respective symbol in the objective function.
	 * The provided row must be infeasible.
	 * If no symbol is found which meets the criteria, an invalid symbol is returned.
     *
     * @param Row $row
     * @return Symbol
     */
    protected function getDualEnteringSymbol(Row $row) : Symbol
    {
        $entering = new Symbol();
        $ratio = PHP_FLOAT_MAX;
        
        foreach ($row->getCells() as $symbol) {
            if ($symbol->getType() !== Symbol::DUMMY) {
                $currentCoefficient = $row->getCells()->offsetGet($symbol);
                if (0.0 > $currentCoefficient) {
                    $coefficient = $this->objective->getCoefficientForSymbol($symbol);
                    $currentRatio = $coefficient / $currentCoefficient;
                    if ($currentRatio < $ratio) {
                        $ratio = $currentRatio;
                        $entering = $symbol;
                    }
                }
            }
        }
        
        return $entering;
    }

    /**
     * Get the first Slack or Error symbol in the row.
     *
	 * If no such symbol is present, an Invalid symbol will be returned.
     *
     * @param Row $row
     * @return Symbol
     */
    protected function anyPivotableSymbol(Row $row) : Symbol
    {
        $symbol = new Symbol();
        foreach ($row->getCells() as $_symbol) {
            if ($_symbol->getType() === Symbol::SLACK || $_symbol->getType() === Symbol::ERROR) {
                $symbol = $_symbol;
            }
        }
        return $symbol;
    }

    /**
     * Compute the symbol for pivot exit row.
     *
	 * This method will return the symbol for the exit row in the row map.
	 * If no appropriate exit symbol is found, an invalid symbol will be returned.
	 * This indicates that the objective function is unbounded.
     *
     * @param Symbol $entering
     * @return Symbol
     */
    protected function getLeavingSymbol(Symbol $entering) : Symbol
    {
        $ratio = PHP_FLOAT_MAX;
        $symbol = new Symbol();
        
        foreach ($this->rows as $currentSymbol) {
            if ($currentSymbol->getType() === Symbol::EXTERNAL) {
                continue;
            }
            $currentRow = $this->rows->offsetGet($currentSymbol);
            $temp = $currentRow->getCoefficientForSymbol($entering);
            if (0.0 > $temp) {
                $tempRatio = -$currentRow->getConstant() / $temp;
                if ($tempRatio < $ratio) {
                    $ratio = $tempRatio;
                    $symbol = $currentSymbol;
                }
            }
        }
        
        return $symbol;
    }

    /**
     * Get the symbol for the given variable.
     *
	 * If a symbol does not exist for the variable, one will be created.
     *
     * @param Variable $variable
     * @return Symbol
     */
    protected function getVariableSymbol(Variable $variable) : Symbol
    {
        if (true === $this->variables->contains($variable)) {
            $symbol = $this->variables->offsetGet($variable);
        } else {
            $symbol = new Symbol(Symbol::EXTERNAL);
            $this->variables->attach($variable, $symbol);
        }
        return $symbol;
    }

    /**
     * Test whether a row is composed of all dummy variables.
     *
     * @param Row $row
     * @return bool
     */
    protected function allDummies(Row $row) : bool
    {
        foreach ($row->getCells() as $symbol) {
            if ($symbol->getType() !== Symbol::DUMMY) {
                return false;
            }
        }
        return true;
    }
}