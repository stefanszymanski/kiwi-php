<?php
declare(strict_types=1);

namespace Ctefan\Kiwi;

class Tag
{
    /**
     * @var Symbol
     */
    protected $marker;
    
    /**
     * @var Symbol
     */
    protected $other;

    /**
     * Tag constructor.
     */
    public function __construct()
    {
        $this->marker = new Symbol();
        $this->other = new Symbol();
    }

    /**
     * @return Symbol
     */
    public function getMarker(): Symbol
    {
        return $this->marker;
    }

    /**
     * @param Symbol $marker
     */
    public function setMarker(Symbol $marker): void
    {
        $this->marker = $marker;
    }

    /**
     * @return Symbol
     */
    public function getOther(): Symbol
    {
        return $this->other;
    }

    /**
     * @param Symbol $other
     */
    public function setOther(Symbol $other): void
    {
        $this->other = $other;
    }
}