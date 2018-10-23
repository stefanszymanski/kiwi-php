<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

/**
 * An internal tableau row class used by the constraint solver.
 */
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

    /**
     * Row constructor.
     *
     * @param float $constant
     */
    public function __construct(float $constant = 0.0)
    {
        $this->constant = $constant;
        $this->cells = new \SplObjectStorage();
    }

    /**
     * Create a Row from another Row.
     *
     * @param self $otherRow
     * @return Row
     */
    static public function createFromRow(self $otherRow): self
    {
        $row = new self($otherRow->getConstant());
        $row->setCells($otherRow->getCells());
        return $row;
    }

    /**
     * Add a constant value to the row constant.
     *
     * The new value of the constant is returned.
     *
     * @param float $value
     * @return float
     */
    public function add(float $value): float
    {
        return $this->constant += $value;
    }

    /**
     * Insert a symbol into the row with a given coefficient.
     *
     * If the symbol already exists in the row, the coefficient will be added to the existing coefficient.
     * If the resulting coefficient is zero, the symbol will be removed from the row.
     *
     * @param Symbol $symbol
     * @param float $coefficient
     */
    public function insertSymbol(Symbol $symbol, float $coefficient = 1.0): void
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

    /**
     * Insert a row into this row with a given coefficient.
     *
     * The constant and the cells of the other row will be multiplied by the coefficient and added to this row.
     * Any cell with a resulting coefficient of zero will be removed from the row.
     *
     * @param self $row
     * @param float $coefficient
     */
    public function insertRow(self $row, float $coefficient = 1.0): void
    {
        $this->constant += $row->getConstant() * $coefficient;

        foreach ($row->getCells() as $symbol) {
            $_coefficient = $row->getCells()->offsetGet($symbol) * $coefficient;
            $this->insertSymbol($symbol, $_coefficient);
        }
    }

    /**
     * Remove the given symbol from the row.
     *
     * @param Symbol $symbol
     */
    public function remove(Symbol $symbol): void
    {
        $this->cells->offsetUnset($symbol);
    }

    /**
     * Reverse the sign of the constant and all cells in the row.
     */
    public function reverseSign(): void
    {
        $this->constant = -$this->constant;

        $newCells = new \SplObjectStorage();
        foreach ($this->cells as $symbol) {
            $value = -$this->cells->offsetGet($symbol);
            $newCells->attach($symbol, $value);
        }
        $this->cells = $newCells;
    }

    /**
     * Solve the row for the given symbol.
     *
     * This method assumes the row is of the form a * x + b * y + c = 0
     * and (assuming solve for x) will modify the row to represent the right hand side of x = -b/a * y - c / a.
     * The target symbol will be removed from the row, and the constant and other cells
     * will be multiplied by the negative inverse of the target coefficient.
     * The given symbol must exist in the row.
     *
     * @param Symbol $symbol
     */
    public function solveForSymbol(Symbol $symbol): void
    {
        $coefficient = -1.0 / $this->cells->offsetGet($symbol);
        $this->cells->offsetUnset($symbol);
        $this->constant *= $coefficient;

        $newCells = new \SplObjectStorage();
        foreach ($this->cells as $symbol) {
            $value = $this->cells->offsetGet($symbol) * $coefficient;
            $newCells->attach($symbol, $value);
        }
        $this->cells = $newCells;
    }

    /**
     * Solve the row for the given symbols.
     *
     * This method assumes the row is of the form x = b * y + c and will solve the row such that y = x / b - c / b.
     * The rhs symbol will be removed from the row, the lhs added,
     * and the result divided by the negative inverse of the rhs coefficient.
     * The lhs symbol must not exist in the row, and the rhs symbol must exist in the row.
     *
     * @param Symbol $lhs
     * @param Symbol $rhs
     */
    public function solveForSymbols(Symbol $lhs, Symbol $rhs): void
    {
        $this->insertSymbol($lhs, -1.0);
        $this->solveForSymbol($rhs);
    }

    /**
     * Get the coefficient for the given symbol.
     *
     * If the symbol does not exist in the row, zero will be returned.
     *
     * @param Symbol $symbol
     * @return float
     */
    public function getCoefficientForSymbol(Symbol $symbol): float
    {
        if (true === $this->cells->contains($symbol)) {
            return $this->cells->offsetGet($symbol);
        } else {
            return 0.0;
        }
    }

    /**
     * Substitute a symbol with the data from another row.
     *
     * Given a row of the form a * x + b and a substitution of the form x = 3 * y + c the row will be updated
     * to reflect the expression 3 * a * y + a * c + b.
     * If the symbol does not exist in the row, this is a no-op.
     *
     * @param Symbol $symbol
     * @param Row $row
     */
    public function substitute(Symbol $symbol, Row $row): void
    {
        if (true === $this->cells->contains($symbol)) {
            $coefficient = $this->cells->offsetGet($symbol);
            $this->cells->offsetUnset($symbol);
            $this->insertRow($row, $coefficient);
        }
    }

    /**
     * @return float
     */
    public function getConstant(): float
    {
        return $this->constant;
    }

    /**
     * @param float $constant
     */
    public function setConstant(float $constant): void
    {
        $this->constant = $constant;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getCells(): \SplObjectStorage
    {
        return $this->cells;
    }

    /**
     * @param \SplObjectStorage $cells
     */
    public function setCells(\SplObjectStorage $cells): void
    {
        $this->cells = $cells;
    }
}