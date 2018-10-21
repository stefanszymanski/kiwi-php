<?php

namespace Ctefan\Kiwi;

class Solver
{
    /**
     * @var \SplObjectStorage
     */
    protected $constraints;
    
    /**
     * @var \SplObjectStorage
     */
    protected $rows;
    
    /**
     * @var \SplObjectStorage
     */
    protected $variables;
    
    /**
     * @var \SplObjectStorage
     */
    protected $edits;
    
    /**
     * @var array
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
            // TODO throw exception
        }
        
        $tag = new Tag();
        $row = $this->createRow($constraint, $tag);
        $subject = $this->chooseSubject($row, $tag);
        
        if ($subject->getType() === Symbol::INVALID && $this->allDummies($row)) {
            if (false === Util::isNearZero($row->getConstant())) {
                // TODO throw exception
            } else {
                $subject = $tag->getMarker();
            }
        }
        
        if ($subject->getType() === Symbol::INVALID) {
            if (false === $this->addWithArtificialVariable($row)) {
                // TODO throw exception
            }
        } else {
            $row->solveFor($subject);
            $this->substitute($subject, $row);
            $this->rows->attach($subject, $row);
        }
        
        $this->constraints->attach($constraint, $tag);
        $this->optimize($subject);
    }
    
    public function removeConstraint(Constraint $constraint) : void
    {
        if (false === $this->constraints->contains($constraint)) {
            // TODO throw exception
        }
        
        $tag = $this->constraints->offsetGet($constraint);
        $this->constraints->offsetUnset($constraint);
        $this->removeConstraintEffects($constraint, $tag);
        
        if (true === $this->rows->contains($tag->getMarker())) {
            $row = $this->rows->offsetGet($tag->getMarker());
            $this->rows->offsetUnset($row);
        } else {
            $row = $this->getMarkerLeavingRow($tag->getMarker());
            if (null === $row) {
                // TODO throw exception
            }
            
            $leaving = null;
            foreach ($this->rows as $symbol) {
                if (true === $this->rows->contains($symbol) && $this->rows->offsetGet($symbol) === $row) {
                    $leaving = $symbol;
                }
                
            }
            if (null === $leaving) {
                // TODO throw exception
            }
            $this->rows->offsetUnset($leaving);
            $row->solveFor($leaving, $tag->getMarker());
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
            // TODO throw exception
        }
        
        $strength = Strength::clip($strength);
        
        if (Strength::required() === $strength) {
            // TODO throw exception
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
            // TODO throw exception
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
            // TODO throw exception
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
    
    protected function getMarkerLeavingRow(Symbol $marker) : Row
    {
        $max = PHP_FLOAT_MAX;
        $r1 = $max;
        $r2 = $max;
        
        $first = null;
        $second = null;
        $third = null;
        
        foreach ($this->rows as $symbol) {
            $candidateRow = $this->rows->offsetGet($symbol);
            $coefficient = $candidateRow->coefficientFor($marker);
            if (0.0 === $coefficient) {
                continue;
            }
            if ($symbol->getType() === Symbol::EXTERNAL) {
                $third = $candidateRow;
            } elseif (0.0 > $coefficient) {
                $r = -$candidateRow->getConstant() / $coefficient;
                if ($r < $r1) {
                    $r1 = $r;
                    $first = $candidateRow;
                }
            } else {
                $r = $candidateRow->getConstant() / $coefficient;
                if ($r < $r2) {
                    $r2 = $r;
                    $second = $candidateRow;
                }
            }
            
        }
        
        if (null !== $first) {
            return $first;
        }
        if (null !== $second) {
            return $second;
        }
        return $third;
    }
    
    protected function createRow(Constraint $constraint, Tag $tag) : Row
    {
        // TODO continue to implement
    }
}