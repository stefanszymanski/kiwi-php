<?php
declare(strict_types=1);

namespace Ctefan\Kiwi\Exception;

use Ctefan\Kiwi\Constraint;
use Throwable;

class UnsatisfiableConstraintException extends Exception
{
    /**
     * @var Constraint
     */
    protected $constraint;

    /**
     * UnsatisfiableConstraintException constructor.
     *
     * @param Constraint $constraint
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(Constraint $constraint, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->constraint = $constraint;
        parent::__construct($message, $code, $previous);
    }
}