<?php
namespace GoetasWebservices\SoapServices\SoapServer;

use GoetasWebservices\SoapServices\SoapCommon\Metadata\PhpMetadataGenerator;
use GoetasWebservices\SoapServices\SoapCommon\Metadata\PhpMetadataGeneratorInterface;

use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandlerInterface;
use GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException;
use GoetasWebservices\XML\WSDLReader\Exception\ServiceNotFoundException;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use JMS\Serializer\SerializerInterface;

class ServerFactory
{
    protected $namespaces = [];
    protected $metadata = [];
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var HeaderHandlerInterface
     */
    protected $headerHandler;

    /**
     * @var PhpMetadataGeneratorInterface
     */
    private $generator;

    public function __construct(array $namespaces, SerializerInterface $serializer)
    {
        $this->setSerializer($serializer);

        foreach ($namespaces as $namespace => $phpNamespace) {
            $this->addNamespace($namespace, $phpNamespace);
        }
    }

    /**
     * @param HeaderHandlerInterface $headerHandler
     */
    public function setHeaderHandler(HeaderHandlerInterface $headerHandler)
    {
        $this->headerHandler = $headerHandler;
    }

    /**
     * @param MessageFactory $messageFactory
     */
    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function setMetadataGenerator(PhpMetadataGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    private function getSoapService($wsdl, $portName = null, $serviceName = null)
    {
        $generator = $this->generator ?: new PhpMetadataGenerator();

        foreach ($this->namespaces as $ns => $phpNs) {
            $generator->addNamespace($ns, $phpNs);
        }

        $services = $generator->generateServices($wsdl);
        $service = $this->getService($serviceName, $services);

        return $this->getPort($portName, $service);
    }

    public function addNamespace($uri, $phpNs)
    {
        $this->namespaces[$uri] = $phpNs;
    }

    public function getServer($wsdl, $portName = null, $serviceName = null)
    {
        $this->messageFactory = $this->messageFactory ?: MessageFactoryDiscovery::find();
        $headerHandler = $this->headerHandler ?: new HeaderHandler();
        $service = $this->getSoapService($wsdl, $portName, $serviceName);

        return new Server($service, $this->serializer, $this->messageFactory, $headerHandler);
    }

    /**
     * @param $serviceName
     * @param array $services
     * @return array
     * @throws ServiceNotFoundException
     */
    private function getService($serviceName, array $services)
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
    private function getPort($portName, array $service)
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
