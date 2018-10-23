<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class EditInfo
{
    protected $tag;
    
    protected $constraint;
    
    protected $constant;
    
    public function __construct(Constraint $constraint, Tag $tag, float $constant)
    {
        $this->constraint = $constraint;
        $this->tag = $tag;
        $this->constant = $constant;
    }
    
    public function getTag(): Tag
    {
        return $this->tag;
    }
    
    public function setTag(Tag $tag): void
    {
        $this->tag = $tag;
    }
    
    public function getConstraint(): Constraint
    {
        return $this->constraint;
    }
    
    public function setConstraint(Constraint $constraint): void
    {
        $this->constraint = $constraint;
    }
    
    public function getConstant(): float
    {
        return $this->constant;
    }
    
    public function setConstant(float $constant): void
    {
        $this->constant = $constant;
    }
}