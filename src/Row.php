<?php

namespace Ctefan\Kiwi;

class Row
{
    /**
     * @var float
     */
    protected $constant;
    
    /**
     * @var \SplObjectStorage<Symbol, float>
     */
    protected $cells = [];
    
    public function __construct(float $constant = 0.0)
    {
        $this->constant = $constant;
        $this->cells = new \SplObjectStorage();
    }
    
    static public function createFromRow(self $otherRow) : self
    {
        $row = new self($otherRow->getConstant());
        $row->setCells($otherRow->getCells());
        return $row;
    }
    
    public function add(float $value) : float
    {
        return $this->constant += $value;
    }
    
    public function insert(Symbol $symbol, float $coefficient = 1.0) : void
    {
        if (true === $this->cells->contains($symbol)) {
            $existingCoefficient = $this->cells->offsetGet($symbol);
            $coefficient += $existingCoefficient;
        }
        
        if (true === Util::isNearZero($coefficient)) {
            $this->cells->offsetUnset($symbol);
        } else {
            $this->cells->attach($symbol, $coefficient);
        }
    }
    
    public function insertOtherRow(self $otherRow, float $coefficient = 1.0) : void
    {
        $this->constant += $otherRow->getConstant() * $coefficient;
        
        foreach ($otherRow->getCells() as $symbol) {
            $_coefficient = $otherRow->getCells()->offsetGet($symbol) * $coefficient;
            
            // TODO check if I implement this correctly
            if (false === $this->cells->contains($symbol)) {
                $this->cells->attach($symbol, 0.0);
            }
            $temp = $this->cells->offsetGet($symbol) + $_coefficient;
            if (false === Util::isNearZero($temp)) {
                $this->cells->attach($symbol, $temp);
            }
        }
    }
    
    public function remove(Symbol $symbol) : void
    {
        $this->cells->offsetUnset($symbol);
    }
    
    public function reverseSign() : void
    {
        $this->constant = -$this->constant;
        
        $newCells = new \SplObjectStorage();
        foreach ($this->cells as $symbol) {
            $value = -$this->cells->offsetGet($symbol);
            $newCells->attach($symbol, $value);
        }
        $this->cells = $newCells;
    }
    
    public function solveForSymbol(Symbol $symbol) : void
    {
        $coefficient = -1.0 /  $this->cells->offsetGet($symbol);
        $this->cells->offsetUnset($symbol);
        $this->constant *= $coefficient;
        
        $newCells = new \SplObjectStorage();
        foreach ($this->cells as $symbol) {
            $value = $this->cells->offsetGet($symbol) * $coefficient;
            $newCells->attach($symbol, $value);
        }
        $this->cells = $newCells;
    }
    
    public function solveForSymbols(Symbol $lhs, Symbol $rhs) : void
    {
        $this->insert($lhs, -1.0);
        $this->solveForSymbol($rhs);
    }
    
    public function getCoefficientForSymbol(Symbol $symbol) : float
    {
        if (true === $this->cells->contains($symbol)) {
            return $this->cells->offsetGet($symbol);
        } else {
            return 0.0;
        }
    }
    
    public function substitute(Symbol $symbol, Row $row) : void
    {
        if (true === $this->cells->contains($symbol)) {
            $coefficient = $this->cells->offsetGet($symbol);
            $this->cells->offsetUnset($symbol);
            $this->insertOtherRow($row, $coefficient);
        }
    }
    
    public function getConstant(): float
    {
        return $this->constant;
    }
    
    public function setConstant(float $constant): void
    {
        $this->constant = $constant;
    }
    
    public function getCells() : \SplObjectStorage
    {
        return $this->cells;
    }
    
    public function setCells(\SplObjectStorage $cells): void
    {
        $this->cells = $cells;
    }
}