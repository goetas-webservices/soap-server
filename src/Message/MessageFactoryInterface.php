<?php
namespace GoetasWebservices\SoapServices\SoapServer\Message;

use Psr\Http\Message\ResponseInterface;

interface MessageFactoryInterface
{
    /**
     * @param string $message
     * @return ResponseInterface
     */
    public function getResponse($message);
}
