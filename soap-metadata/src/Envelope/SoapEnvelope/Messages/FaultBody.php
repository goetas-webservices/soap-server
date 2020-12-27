<?php

namespace GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Messages;

/**
 * Class representing Fault
 */
class FaultBody
{

    /**
     * @var \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault $fault
     */
    private $fault = null;

    /**
     * Gets as fault
     *
     * @return \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault
     */
    public function getFault()
    {
        return $this->fault;
    }

    /**
     * Sets a new fault
     *
     * @param \GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault $fault
     * @return self
     */
    public function setFault(\GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault $fault)
    {
        $this->fault = $fault;
        return $this;
    }


}

