<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

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
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function getValue(): float
    {
        return $this->value;
    }
    
    public function setValue(float $value): void
    {
        $this->value = $value;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
}