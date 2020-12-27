<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Exception;

interface FaultException extends \Throwable
{
    public function getDetail(): ?object;
}
