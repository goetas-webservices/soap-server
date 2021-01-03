<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Exception;

use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\Fault as Fault11;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages\FaultBody as Fault11Body;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault as Fault11Part;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\Fault as Fault12;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Messages\FaultBody as Fault12Body;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Parts\Fault as Fault12Part;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12\Parts\FaultCode as Fault12Code;

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

    public static function to12Fault(\Throwable $e, bool $debug = false): Fault12
    {
        $faultEnvelope = new Fault12();
        $faultBody = new Fault12Body();
        $faultEnvelope->setBody($faultBody);

        $fault = new Fault12Part();
        if (!$e instanceof FaultException) {
            $e = new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        $faultCode = new Fault12Code();

        if ($e instanceof VersionMismatchException) {
            $faultCode->setValue('SOAP:VersionMismatch');
        } elseif ($e instanceof MustUnderstandException) {
            $faultCode->setValue('SOAP:MustUnderstand');
        } elseif ($e instanceof ClientException) {
            $faultCode->setValue('SOAP:Sender');
        } else {
            $faultCode->setValue('SOAP:Receiver');
        }

        if (0 !== $e->getCode()) {
            $subFaultCode = new Fault12Code();
            $subFaultCode->setValue((string) $e->getCode());

            $faultCode->setSubcode($subFaultCode);
        }

        $fault->setCode($faultCode);
        if ($debug) {
            $fault->setReason(array_merge([$e->getMessage()], explode("\n", $e->getTraceAsString())));
        } else {
            $fault->setReason(explode("\n", $e->getMessage()));
        }

        // @todo implement detail wrapping
        $fault->setDetail($e->getDetail());

        $faultBody->setFault($fault);

        return $faultEnvelope;
    }

    public static function to11Fault(\Throwable $e, bool $debug = false): Fault11
    {
        $faultEnvelope = new Fault11();
        $faultBody = new Fault11Body();
        $faultEnvelope->setBody($faultBody);

        $fault = new Fault11Part();
        if (!$e instanceof FaultException) {
            $e = new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        if ($e instanceof ClientException) {
            $fault->setCode('SOAP:Client');
        } elseif ($e instanceof VersionMismatchException) {
            $fault->setCode('SOAP:VersionMismatch');
        } elseif ($e instanceof MustUnderstandException) {
            $fault->setCode('SOAP:MustUnderstand');
        } else {
            $fault->setCode('SOAP:Server');
        }

        if ($debug) {
            $fault->setString(implode("\n", array_merge([$e->getMessage()], explode("\n", $e))));
        } else {
            $fault->setString($e->getMessage());
        }

        // @todo implement detail wrapping
        $fault->setDetail($e->getDetail());

        $faultBody->setFault($fault);

        return $faultEnvelope;
    }
}
