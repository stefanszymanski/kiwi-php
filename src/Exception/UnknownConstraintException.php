<?php
declare(strict_types=1);

namespace Ctefan\Kiwi\Exception;

class UnknownConstraintException extends Exception
{
    protected $constraint;

    public function __construct(Constraint $constraint, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->constraint = $constraint;
        parent::__construct($message, $code, $previous);
    }
}