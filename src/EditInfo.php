<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class EditInfo
{
    /**
     * @var Tag
     */
    protected $tag;

    /**
     * @var Constraint
     */
    protected $constraint;

    /**
     * @var float
     */
    protected $constant;

    /**
     * EditInfo constructor.
     *
     * @param Constraint $constraint
     * @param Tag $tag
     * @param float $constant
     */
    public function __construct(Constraint $constraint, Tag $tag, float $constant)
    {
        $this->constraint = $constraint;
        $this->tag = $tag;
        $this->constant = $constant;
    }

    /**
     * @return Tag
     */
    public function getTag(): Tag
    {
        return $this->tag;
    }

    /**
     * @param Tag $tag
     */
    public function setTag(Tag $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * @return Constraint
     */
    public function getConstraint(): Constraint
    {
        return $this->constraint;
    }

    /**
     * @param Constraint $constraint
     */
    public function setConstraint(Constraint $constraint): void
    {
        $this->constraint = $constraint;
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
}