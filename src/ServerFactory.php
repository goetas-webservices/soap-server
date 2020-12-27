<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer;

use GoetasWebservices\SoapServices\Metadata\Loader\MetadataLoaderInterface;
use GoetasWebservices\SoapServices\Metadata\MetadataUtils;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGeneratorInterface;
use GoetasWebservices\SoapServices\SoapServer\Router\Router;
use Http\Discovery\Psr17FactoryDiscovery;
use JMS\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ServerFactory
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var ResponseFactoryInterface
     */
    protected $messageFactory;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var ContainerInterface
     */
    private $controllerContainer;

    /**
     * @var ArgumentsGeneratorInterface
     */
    private $argumentsGenerator;

    /**
     * @var MetadataLoaderInterface
     */
    private $metadataLoader;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(MetadataLoaderInterface $metadataLoader, SerializerInterface $serializer, Router $router)
    {
        $this->serializer = $serializer;
        $this->router = $router;
        $this->metadataLoader = $metadataLoader;
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function setControllerContainer(ContainerInterface $controllerContainer): void
    {
        $this->controllerContainer = $controllerContainer;
    }

    public function setMessageFactory(ResponseFactoryInterface $messageFactory): void
    {
        $this->messageFactory = $messageFactory;
    }

    public function setMetadataReader(MetadataLoaderInterface $reader): void
    {
        $this->metadataLoader = $reader;
    }

    private function getSoapService(string $wsdl, ?string $serviceName = null): array
    {
        $servicesMetadata = $this->metadataLoader->load($wsdl);

        return MetadataUtils::getService($serviceName, $servicesMetadata);
    }

    private function getMessageFactory(): ResponseFactoryInterface
    {
        if (!$this->messageFactory) {
            $this->messageFactory =  Psr17FactoryDiscovery::findResponseFactory();
        }

        return $this->messageFactory;
    }

    private function getStreamFactory(): StreamFactoryInterface
    {
        if (!$this->streamFactory) {
            $this->streamFactory =  Psr17FactoryDiscovery::findStreamFactory();
        }

        return $this->streamFactory;
    }

    public function setArgumentsGenerator(ArgumentsGeneratorInterface $argumentsGenerator): void
    {
        $this->argumentsGenerator = $argumentsGenerator;
    }

    public function getServer(string $wsdl, ?string $serviceName = null): Server
    {
        $service = $this->getSoapService($wsdl, $serviceName);

        $server = new Server(
            $service,
            $this->serializer,
            $this->getMessageFactory(),
            $this->getStreamFactory(),
            $this->router,
            $this->controllerContainer
        );

        if ($this->argumentsGenerator) {
            $server->setArgumentsGenerator($this->argumentsGenerator);
        }

        return $server;
    }
}
