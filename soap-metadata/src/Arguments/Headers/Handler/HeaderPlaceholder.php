<?php

namespace GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Handler;


class HeaderPlaceholder
{
    private $header;

    public function __construct($header)
    {
        $this->header = $header;
    }

    public function getHeader()
    {
        return $this->header;
    }
}


