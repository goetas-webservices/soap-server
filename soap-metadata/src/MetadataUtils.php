<?php

namespace GoetasWebservices\SoapServices\Metadata;

use GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException;
use GoetasWebservices\XML\WSDLReader\Exception\ServiceNotFoundException;

class MetadataUtils {

    /**
     * @param $serviceName
     * @param array $services
     * @return array
     * @throws ServiceNotFoundException
     */
    public static function getService($serviceName, array $services)
    {
        if ($serviceName && isset($services[$serviceName])) {
            return $services[$serviceName];
        } elseif ($serviceName) {
            throw new ServiceNotFoundException("The service named $serviceName can not be found");
        } else {
            return reset($services);
        }
    }

    /**
     * @param string $portName
     * @param array $service
     * @return array
     * @throws PortNotFoundException
     */
    public static  function getPort($portName, array $service)
    {
        if ($portName && isset($service[$portName])) {
            return $service[$portName];
        } elseif ($portName) {
            throw new PortNotFoundException("The port named $portName can not be found");
        } else {
            return reset($service);
        }
    }
}

