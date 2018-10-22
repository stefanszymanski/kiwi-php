<?php

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
    
    public function addConstraint(Constraint $constraint) : void
    {
        if (true === $this->constraints->contains($constraint)) {
            throw new DuplicateConstraintException($constraint);
        }
        
        $tag = new Tag();
        $row = $this->createRow($constraint, $tag);
        $subject = $this->chooseSubject($row, $tag);

        if ($subject->getType() === Symbol::INVALID && true === $this->allDummies($row)) {
            if (false === Util::isNearZero($row->getConstant())) {
                throw new UnsatisfiableConstraintException($constraint);
            } else {
                $subject = $tag->getMarker();
            }
        }
        
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
        $this->optimize($this->objective);
    }
    
    public function removeConstraint(Constraint $constraint) : void
    {
        if (false === $this->constraints->contains($constraint)) {
            throw new UnknownConstraintException($constraint);
        }
        
        $tag = $this->constraints->offsetGet($constraint);
        $this->constraints->offsetUnset($constraint);
        $this->removeConstraintEffects($constraint, $tag);
        
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
        
        $this->optimize($this->objective);
    }
    
    public function hasConstraint(Constraint $constraint) : bool
    {
        return $this->constraints->contains($constraint);
    }
    
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
    
    public function hasEditVariable(Variable $variable) : bool
    {
        return $this->edits->contains($variable);
    }
    
    public function suggestValue(Variable $variable, float $value) : void
    {
        if (false === $this->edits->contains($variable)) {
            throw new UnknownEditVariableException();
        }
        
        $info = $this->edits->offsetGet($variable);
        $delta = $value - $info->getConstant();
        $info->setContant($value);
        
        if (true === $this->rows->contains($info->getTag()->getMarker())) {
            $row = $this->rows->offsetGet($info->getTag()->getMarker());
            if (0.0 > $row->add(-$delta)) {
                $this->infeasibleRows[] = $info->getTag()->getMarker();
            }
            $this->dualOptimize();
            return;
        }
        
        if (true === $this->rows->contains($info->getTag()->getOther())) {
            $row = $this->rows->offsetGet($info->getTag()->getOther());
            if (0.0 > $row->add($delta)) {
                $this->infeasibleRows[] = $info->getTag()->getOther();
            }
            $this->dualOptimize();
            return;
        }
        
        foreach ($this->rows as $symbol) {
            $currentRow = $this->rows->offsetGet($symbol);
            $coefficient = $currentRow->getCoefficientForSymbol($info->getTag()->getMarker());
            if (0.0 !== $coefficient && 0.0 > $currentRow->add($delta * $coefficient) && Symbol::EXTERNAL !== $symbol->getType()) {
                $this->infeasibleRows[] = $symbol;
            }
        }
        
        $this->dualOptimize();
    }
    
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
    
    protected function removeConstraintEffects(Constraint $constraint, Tag $tag) : void
    {
        if ($tag->getMarker()->getType() === Symbol::ERROR) {
            $this->removeMarkerEffects($tag->getMarker(), $constraint->getStrength());
        } elseif ($tag->getOther()->getType() === Symbol::ERROR) {
            $this->removeMarkerEffects($tag->getOther(), $constraint->getStrength());
        }
    }
    
    protected function removeMarkerEffects(Symbol $marker, float $strength) : void
    {
        if (true === $this->rows->contains($marker)) {
            $row = $this->rows->offsetGet($marker);
            $this->objective->insert($row, -$strength);
        } else {
            $this->objective->insert($marker, -$strength);
        }
    }
    
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
    
    protected function createRow(Constraint $constraint, Tag $tag) : Row
    {
        $expression = $constraint->getExpression();
        $row = new Row($expression->getConstant());
        
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
        
        if ($row->getConstant() < 0.0) {
            $row->reverseSign();
        }
        
        return $row;
    }
    
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
    
    protected function addWithArtificialVariable(Row $row) : bool
    {
        $artificial = new Symbol(Symbol::SLACK);
        $this->rows->attach($artificial, Row::createFromRow($row));
        
        $this->artificial = Row::createFromRow($row);
        
        $this->optimize($this->artificial);
        
        $success = Util::isNearZero($this->artificial->getConstant());
        $this->artificial = null;
        
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
        
        foreach ($this->rows as $symbol) {
            $rowEntry = $this->rows->offsetGet($symbol);
            $rowEntry->remove($artificial);
        }
        
        return $success;
    }
    
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
            
            $row = $this->rows->offsetGet($leaving);
            $this->rows->offsetUnset($leaving);
            $row->solveForSymbols($leaving, $entering);
            $this->rows->attach($entering, $row);
            $this->substitute($entering, $row);
        }
    }
    
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
                    $this->rows->offsetUnset($leaving);
                    $row->solveForSymbols($leaving, $entering);
                    $this->substitute($entering, $row);
                    $this->rows->attach($entering, $row);
                }
            }
            
        }
    }
    
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