<?php

namespace GoetasWebservices\SoapServices\Metadata\Loader;

interface MetadataLoaderInterface
{
    /**
     * @param $wsdl
     * @return array
     */
    public function load($wsdl);
}
