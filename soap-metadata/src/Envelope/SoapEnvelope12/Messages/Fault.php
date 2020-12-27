<?php

namespace GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages;

use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Parts\FaultCode;
use GoetasWebservices\SoapServices\SoapServer\Exception\ClientException;
use GoetasWebservices\SoapServices\SoapServer\Exception\FaultException;
use GoetasWebservices\SoapServices\SoapServer\Exception\MustUnderstandException;
use GoetasWebservices\SoapServices\SoapServer\Exception\ServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\SoapServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\VersionMismatchException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class representing Body
 */
class Fault
{

    /**
     * @var \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\FaultBody $body
     */
    private $body = null;

    /**
     * Gets as body
     *
     * @return \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\FaultBody
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets a new body
     *
     * @param \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\FaultBody $body
     * @return self
     */
    public function setBody(\GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\FaultBody $body)
    {
        $this->body = $body;
        return $this;
    }

    public static function fromException(\Throwable $e, bool $debug = false): self
    {
        $faultEnvelope = new self();
        $faultBody = new FaultBody();
        $faultEnvelope->setBody($faultBody);

        $fault = new \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Parts\Fault();
        if (!$e instanceof FaultException) {
            $e = new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        $faultCode = new FaultCode();

        if ($e instanceof VersionMismatchException) {
            $faultCode->setValue('SOAP:VersionMismatch');
        }elseif ($e instanceof MustUnderstandException) {
            $faultCode->setValue('SOAP:MustUnderstand');
        }elseif ($e instanceof ClientException) {
            $faultCode->setValue('SOAP:Sender');
        } else {
            $faultCode->setValue('SOAP:Receiver');
        }

        if ($e->getCode() !== 0) {
            $subFaultCode = new FaultCode();
            $subFaultCode->setValue($e->getCode());

            $faultCode->setSubcode($subFaultCode);
        }

        $fault->setCode($faultCode);
        if ($debug) {
            $fault->setReason(array_merge([$e->getMessage()], explode("\n", (string)$e)));
        } else {
            $fault->setReason(explode("\n", $e->getMessage()));
        }

        // @todo implement detail wrapping
        $fault->setDetail($e->getDetail());


        $faultBody->setFault($fault);
        return $faultEnvelope;
    }
}

