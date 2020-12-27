<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Exception;

class SoapServerException extends \Exception implements FaultException
{
    /**
     * @var object
     */
    private $detail;

    public function setDetail(object $detail): void
    {
        $this->detail = $detail;
    }

    public function getDetail(): ?object
    {
        return $this->detail;
    }
}
