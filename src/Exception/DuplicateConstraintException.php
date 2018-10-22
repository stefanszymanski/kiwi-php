<?php

namespace Ctefan\Kiwi\Exception;

use Ctefan\Kiwi\Constraint;
use Throwable;

class DuplicateConstraintException extends Exception
{
    protected $constraint;

    public function __construct(Constraint $constraint, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->constraint = $constraint;
        parent::__construct($message, $code, $previous);
    }

}