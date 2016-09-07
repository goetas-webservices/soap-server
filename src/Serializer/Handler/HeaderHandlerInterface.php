<?php
namespace GoetasWebservices\SoapServices\SoapServer\Serializer\Handler;

interface HeaderHandlerInterface
{

    /**
     * @return boolean[]
     */
    public function getHeadersToUnderstand();

    /**
     * @return void
     */
    public function resetHeadersToUnderstand();
}

