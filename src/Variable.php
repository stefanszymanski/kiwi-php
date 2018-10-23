<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

/**
 * The primary user constraint variable.
 */
class Variable
{
    /**
     * @var string
     */
    protected $name;
    
    /**
     * @var float
     */
    protected $value;

    /**
     * Variable constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * @param float $value
     */
    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}