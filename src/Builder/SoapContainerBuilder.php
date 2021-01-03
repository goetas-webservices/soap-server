<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Builder;

use GoetasWebservices\SoapServices\Metadata\Builder\SoapContainerBuilder as BaseSoapContainerBuilder;
use GoetasWebservices\SoapServices\SoapServer\DependencyInjection\SoapServerExtension;

class SoapContainerBuilder extends BaseSoapContainerBuilder
{
    public function __construct(?string $configFile = null)
    {
        parent::__construct($configFile);
        $this->addExtension(new SoapServerExtension());
    }
}
