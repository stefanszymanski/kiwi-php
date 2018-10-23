<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class Symbol
{
    public const INVALID = 0;
    public const EXTERNAL = 1;
    public const SLACK = 2;
    public const ERROR = 3;
    public const DUMMY = 4;
    
    /**
     * @var int
     */
    protected $type;

    /**
     * Symbol constructor.
     *
     * @param int $type
     */
    public function __construct(int $type = self::INVALID)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }
}