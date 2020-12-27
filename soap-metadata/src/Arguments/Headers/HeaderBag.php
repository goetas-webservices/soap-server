<?php

namespace GoetasWebservices\SoapServices\Metadata\Arguments\Headers;

class HeaderBag
{
    private $headers = [];
    private $mustUnderstandHeaders = [];
    public function __construct()
    {

    }

    public function hasHeader(object $header)
    {
        return isset($this->headers[spl_object_id($header)]);
    }

    public function addHeader(object $header)
    {
        $this->headers[spl_object_id($header)] = $header;
    }

    public function getHeaders():array
    {
        return $this->headers;
    }

    public function addMustUnderstandHeader(object $header)
    {
        $this->mustUnderstandHeaders[spl_object_id($header)] = $header;
        $this->headers[spl_object_id($header)] = $header;
    }

    public function isMustUnderstandHeader(object $header)
    {
        return $this->mustUnderstandHeaders[spl_object_id($header)];
    }

    public function removeMustUnderstandHeader(object $header)
    {
        unset($this->mustUnderstandHeaders[spl_object_id($header)]);
    }

    /**
     * @return object[]
     */
    public function getMustUnderstandHeader(): array
    {
        return $this->mustUnderstandHeaders;
    }
}
