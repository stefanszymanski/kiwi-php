<?php

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
    
    public function __construct()
    {
        $this->marker = new Symbol();
        $this->other = new Symbol();
    }
    
    public function getMarker(): Symbol
    {
        return $this->marker;
    }
    
    public function setMarker(Symbol $marker): void
    {
        $this->marker = $marker;
    }
    
    public function getOther(): Symbol
    {
        return $this->other;
    }
    
    public function setOther(Symbol $other): void
    {
        $this->other = $other;
    }
}