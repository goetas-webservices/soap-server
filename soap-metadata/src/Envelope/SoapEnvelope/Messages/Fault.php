<?php

namespace GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages;

use GoetasWebservices\SoapServices\SoapServer\Exception\ClientException;
use GoetasWebservices\SoapServices\SoapServer\Exception\FaultException;
use GoetasWebservices\SoapServices\SoapServer\Exception\MustUnderstandException;
use GoetasWebservices\SoapServices\SoapServer\Exception\ServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\SoapServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\VersionMismatchException;

/**
 * Class representing Body
 */
class Fault
{

    /**
     * @var \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\FaultBody $body
     */
    private $body = null;

    /**
     * Gets as body
     *
     * @return \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\FaultBody
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets a new body
     *
     * @param \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\FaultBody $body
     * @return self
     */
    public function setBody(\GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\FaultBody $body)
    {
        $this->body = $body;
        return $this;
    }

    public static function fromException(\Throwable $e, bool $debug = false): self
    {
        $faultEnvelope = new self();
        $faultBody = new FaultBody();
        $faultEnvelope->setBody($faultBody);

        $fault = new \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault();
        if (!$e instanceof FaultException) {
            $e = new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        if ($e instanceof ClientException) {
            $fault->setCode('SOAP:Client');
        }elseif ($e instanceof VersionMismatchException) {
            $fault->setCode('SOAP:VersionMismatch');
        }elseif ($e instanceof MustUnderstandException) {
            $fault->setCode('SOAP:MustUnderstand');
        } else {
            $fault->setCode('SOAP:Server');
        }

        if ($debug) {
            $fault->setString(implode("\n", array_merge([$e->getMessage()], explode("\n", (string)$e))));
        } else {
            $fault->setString($e->getMessage());
        }

        // @todo implement detail wrapping
        $fault->setDetail($e->getDetail());

        $faultBody->setFault($fault);
        return $faultEnvelope;
    }
}

