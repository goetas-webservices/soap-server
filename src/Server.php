<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer;

use ArgumentsResolver\InDepthArgumentsResolver;
use GoetasWebservices\SoapServices\Metadata\Arguments\ArgumentsReader;
use GoetasWebservices\SoapServices\Metadata\Arguments\ArgumentsReaderInterface;
use GoetasWebservices\SoapServices\Metadata\Headers\HeadersIncoming;
use GoetasWebservices\SoapServices\Metadata\Headers\HeadersOutgoing;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGenerator;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGeneratorInterface;
use GoetasWebservices\SoapServices\SoapServer\Exception\MustUnderstandException;
use GoetasWebservices\SoapServices\SoapServer\Exception\ServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\SoapServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\VersionMismatchException;
use GoetasWebservices\SoapServices\SoapServer\Router\Router;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Server implements RequestHandlerInterface
{
    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ResponseFactoryInterface
     */
    private $messageFactory;

    /**
     * @var ArgumentsGeneratorInterface
     */
    private $argumentsGenerator;

    /**
     * @var array
     */
    private $ports;

    /**
     * @var ContainerInterface
     */
    private $controllerContainer;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var ArgumentsReader
     */
    private $argumentsReader;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(
        array $ports,
        SerializerInterface $serializer,
        ResponseFactoryInterface $messageFactory,
        StreamFactoryInterface $streamFactory,
        Router $router,
        ?ContainerInterface $controllerContainer = null,
        ?LoggerInterface $logger = null
    ) {
        $this->serializer = $serializer;
        $this->messageFactory = $messageFactory;
        $this->ports = $ports;
        $this->router = $router;
        $this->controllerContainer = $controllerContainer;
        $this->logger = $logger ?: new NullLogger();
        $this->streamFactory = $streamFactory;
    }

    private function getArgumentsReader(): ArgumentsReaderInterface
    {
        if (!$this->argumentsReader) {
            $this->argumentsReader = new ArgumentsReader($this->serializer);
        }

        return $this->argumentsReader;
    }

    public function setArgumentsGenerator(ArgumentsGeneratorInterface $argumentsGenerator): void
    {
        $this->argumentsGenerator = $argumentsGenerator;
    }

    private function getArgumentsGenerator(): ArgumentsGenerator
    {
        if (!$this->argumentsGenerator) {
            $this->argumentsGenerator = new ArgumentsGenerator();
        }

        return $this->argumentsGenerator;
    }

    /**
     * @param object $handler
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $responseHeaders = new HeadersOutgoing();
        $serializationContext = SerializationContext::create()->setAttribute('headers_outgoing', $responseHeaders);
        $version = '1.2';
        try {
            $version = $this->guessVersion($request);
            $soapOperation = $this->findOperation($request, $version);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $envelope = $this->handleException($e, $version);
            $message = $this->serializer->serialize($envelope, 'xml');

            return $this->reply($message, $version);
        }

        $requestHeaders = new HeadersIncoming();
        try {
            $context = DeserializationContext::create()->setAttribute('headers_incoming', $requestHeaders);
            $message = $this->extractMessage($request, $soapOperation['input']['message_fqcn'], $context);

            $request = $request
                ->withAttribute('_soap_operation', $soapOperation)
                ->withAttribute('_message', $message);

            $request = $this->router->match($request);
            $handler = $this->getController($request, $soapOperation);

            $arguments = $this->getArgumentsGenerator()->expandArguments($message);
            $arguments = (new InDepthArgumentsResolver($handler))->resolve(array_merge([$responseHeaders, $requestHeaders], $arguments));

            $result = call_user_func_array($handler, $arguments);
            $this->understandHeaders($requestHeaders);

            $envelope = $this->getArgumentsReader()->readArguments(is_array($result) ? $result : [$result], $soapOperation['output']);
        } catch (\Throwable $e) {
            $envelope = $this->handleException($e, $soapOperation['version']);
        }

        $message = $this->serializer->serialize($envelope, 'xml', $serializationContext);

        return $this->reply($message, $soapOperation['version']);
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    private function handleException(\Throwable $e, string $version): object
    {
        return '1.1' === $version ? SoapServerException::to11Fault($e, $this->debug) : SoapServerException::to12Fault($e, $this->debug);
    }

    /**
     * @throws MustUnderstandException
     */
    private function understandHeaders(HeadersIncoming $requestHeaders): void
    {
        $headers = $requestHeaders->headersNotUnderstood();
        if (count($headers)) {
            $findName = static function (\SimpleXMLElement $node) {
                $domElement = dom_import_simplexml($node);

                return sprintf('%s{%s}', $domElement->localName, $domElement->namespaceURI);
            };

            throw new MustUnderstandException(
                'MustUnderstand headers: ' . implode(', ', array_map($findName, $headers)) . ' are not understood'
            );
        }
    }

    private function guessVersion(ServerRequestInterface $request): string
    {
        $body = (string) $request->getBody();
        if (false !== strpos($body, 'http://schemas.xmlsoap.org/soap/envelope/') || false !== strpos($body, 'http://www.w3.org/2003/05/soap-envelope')) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (false !== strpos($contentType, 'text/xml')) {
                return '1.1';
            }

            if (false !== strpos($contentType, 'application/soap+xml')) {
                return '1.2';
            }
        }

        throw new VersionMismatchException(sprintf('The request is not a valid SOAP (1.1 or 1.2) message'));
    }

    /**
     * @return array
     */
    private function findOperation(ServerRequestInterface $request, string $version): array
    {
        $action = $this->findAction($request, $version);

        foreach ($this->ports as $port) {
            if ($port['version'] !== $version) {
                continue;
            }

            foreach ($port['operations'] as $operation) {
                if ($operation['action'] === $action && $operation['version'] === $version) {
                    return $operation;
                }
            }
        }

        foreach ($this->ports as $port) {
            foreach ($port['operations'] as $operation) {
                if ($operation['action'] === $action) {
                    throw new VersionMismatchException(
                        sprintf(
                            'The requested action %s is not supported using the %s version protocol, but is supported using the %s protocol.',
                            $action,
                            $version,
                            $operation['version']
                        )
                    );
                }
            }
        }

        throw new ServerException(sprintf('Can not find a valid SOAP operation to fulfill %s action', $action));
    }

    private function extractMessage(ServerRequestInterface $request, string $class, DeserializationContext $context): object
    {
        return $this->serializer->deserialize((string) $request->getBody(), $class, 'xml', $context);
    }

    private function reply(string $message, string $version): ResponseInterface
    {
        $response = $this->messageFactory
            ->createResponse()
            ->withBody($this->streamFactory->createStream($message));

        if ('1.1' === $version) {
            return $response->withAddedHeader('Content-Type', 'text/xml; charset=utf-8');
        } else {
            return $response->withAddedHeader('Content-Type', 'application/soap+xml; charset=utf-8');
        }
    }

    private function getController(ServerRequestInterface $request, array $soapOperation): callable
    {
        $controller = $request->getAttribute('_controller');
        $mch = null;

        if (is_callable($controller)) {
            return $controller;
        }

        if (null !== $this->controllerContainer) {
            if (is_string($controller) && preg_match('/^(.+)::(.+)$/', $controller, $mch)) {
                return \Closure::fromCallable([$this->controllerContainer->get($mch[1]), $mch[2]]); // @todo check format
            } elseif (is_string($controller)) {
                return $this->controllerContainer->get($controller); // @todo check format
            }
        }

        $identifier = $soapOperation['action'] ?? $soapOperation['message_fqcn'];

        throw new ServerException(sprintf('Can not find an handler to run %s', $identifier));
    }

    /**
     * @throws ServerException
     */
    private function findAction(ServerRequestInterface $request, string $version): ?string
    {
        $mch = null;
        if ('1.1' === $version) {
            $action = $request->getHeaderLine('SOAPAction');
        } elseif ('1.2' === $version) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (preg_match('/action=(.*)/', $contentType, $mch)) {
                $action = $mch[1];
            } elseif ($request->hasHeader('SOAPAction')) {
                $action = $request->getHeaderLine('SOAPAction');
            }
        } else {
            throw new ServerException('Invalid Format');
        }

        if ('""' === $action) {
            $action = (string) $request->getUri();
        } else {
            $action = trim($action, '"');
        }

        return $action;
    }
}
