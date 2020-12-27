<?php

namespace GoetasWebservices\SoapServices\Metadata\Generator;

use GoetasWebservices\XML\SOAPReader\Soap\Service;

interface MetadataGeneratorInterface
{
    /**
     * @param Service[] $services
     * @return array
     */
    public function generate(array $services);
}

