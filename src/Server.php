<?php
namespace GoetasWebservices\SoapServices\SoapServer;

use GoetasWebservices\SoapServices\SoapCommon as SoapCommon;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGenerator;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGeneratorInterface;
use GoetasWebservices\SoapServices\SoapServer\Exception\MustUnderstandException;
use GoetasWebservices\SoapServices\SoapServer\Exception\ServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\SoapServerException;
use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandlerInterface;
use Http\Message\MessageFactory;
use JMS\Serializer\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server
{
    /**
     * @var Serializer
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
     * @var ArgumentsGeneratorInterface
     */
    private $argumentsGenerator;
    /**
     * @var array
     */
    protected $serviceDefinition;

    public function __construct(array $serviceDefinition, Serializer $serializer, MessageFactory $messageFactory, HeaderHandlerInterface $headerHandler)
    {
        $this->serializer = $serializer;
        $this->messageFactory = $messageFactory;
        $this->serviceDefinition = $serviceDefinition;
        $this->headerHandler = $headerHandler;
    }

    public function setArgumentsGenerator(ArgumentsGeneratorInterface $argumentsGenerator)
    {
        $this->argumentsGenerator = $argumentsGenerator;
    }

    protected function getArgumentsGenerator()
    {
        if (!$this->argumentsGenerator){
            $this->argumentsGenerator = new ArgumentsGenerator();
        }
        return $this->argumentsGenerator;
    }
    /**
     * @param ServerRequestInterface $request
     * @param object $handler
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, $handler)
    {
        try {
            $soapOperation = $this->findOperation($request, $this->serviceDefinition);

            $message = $this->extractMessage($request, $soapOperation['input']['message_fqcn']);

            $function = $this->getCallable($handler, $soapOperation);

            $arguments = $this->getArgumentsGenerator()->expandArguments($message, $function);

            $this->understandHeaders($arguments);

            $result = call_user_func_array($function, $arguments);

        } catch (\Exception $e) {
            $fault = new SoapCommon\SoapEnvelope\Parts\Fault();
            if (!$e instanceof SoapServerException) {
                $e = new ServerException($e->getMessage(), $e->getCode(), $e);
            }
            $fault->setException($e);
            // @todo $fault->setDetail() set detail to trace in debug mode
            // @todo $fault->setActor() allow to set the current server actor
        }
        if (isset($fault)) {
            $wrappedResult = $this->wrapResult($fault, SoapCommon\SoapEnvelope\Messages\Fault::class);
        } else {
            $wrappedResult = $this->wrapResult($result, $soapOperation['output']['message_fqcn']);
        }

        return $this->reply($wrappedResult);
    }

    /**
     * @param $arguments
     * @throws MustUnderstandException
     */
    protected function understandHeaders($arguments)
    {
        $toUnderstand = $this->headerHandler->getHeadersToUnderstand();
        foreach ($arguments as $argument) {
            if (is_object($argument)) {
                unset($toUnderstand[spl_object_hash($argument)]);
            }
        }
        $this->headerHandler->resetHeadersToUnderstand();
        if (count($toUnderstand)) {
            throw new MustUnderstandException(
                "MustUnderstand headers:[" . implode(', ', array_map([$this, 'getXmlNamesDescription'], $toUnderstand)) . "] are not understood"
            );
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $serviceDefinition
     * @return array
     */
    private function findOperation(ServerRequestInterface $request, array $serviceDefinition)
    {
        $action = trim($request->getHeaderLine('Soap-Action'), '"');
        foreach ($serviceDefinition['operations'] as $operation) {
            if ($operation['action'] === $action) {
                return $operation;
            }
        }
        throw new ServerException("Can not find an operation to run $action service call");
    }

    /**
     * @param object|null $input
     * @param string $class
     * @return object
     * @throws \Exception
     */
    private function wrapResult($input, $class)
    {
        if (!$input instanceof $class) {
            $instantiator = new Instantiator();
            $factory = $this->serializer->getMetadataFactory();
            $previous = null;
            $previousProperty = null;
            $nextClass = $class;
            $originalInput = $input;
            $i = 0;
            while ($i++ < 4) {
                /**
                 * @var $classMetadata ClassMetadata
                 */
                if ($previousProperty && in_array($nextClass, ['double', 'string', 'float', 'integer', 'boolean'])) {
                    $previousProperty->setValue($previous, $originalInput);
                    break;
                }
                $classMetadata = $factory->getMetadataForClass($nextClass);
                if ($input === null && !$classMetadata->propertyMetadata) {
                    return $instantiator->instantiate($classMetadata->name);
                } elseif (!$classMetadata->propertyMetadata) {
                    throw new \Exception("Can not determine how to associate the message");
                }
                $instance = $instantiator->instantiate($classMetadata->name);
                /**
                 * @var $propertyMetadata PropertyMetadata
                 */
                $propertyMetadata = reset($classMetadata->propertyMetadata);

                if ($previous) {
                    $previousProperty->setValue($previous, $instance);
                } else {
                    $input = $instance;
                }
                if ($originalInput instanceof $propertyMetadata->type['name']) {
                    $propertyMetadata->setValue($instance, $originalInput);
                    break;
                }
                $previous = $instance;
                $nextClass = $propertyMetadata->type['name'];
                $previousProperty = $propertyMetadata;
            }
        }

        return $input;
    }

    private function getXmlNamesDescription($object)
    {
        $factory = $this->serializer->getMetadataFactory();
        $classMetadata = $factory->getMetadataForClass(get_class($object));
        return "{{$classMetadata->xmlRootNamespace}}$classMetadata->xmlRootName";
    }

    protected function extractMessage(ServerRequestInterface $request, $class)
    {
        return $this->serializer->deserialize((string)$request->getBody(), $class, 'xml');
    }

    protected function reply($envelope)
    {
        $message = $this->serializer->serialize($envelope, 'xml');
        $response = $this->messageFactory->createResponse(200, null, [], $message);
        return $response->withAddedHeader("Content-Type", "text/xml; charset=utf-8");
    }

    /**
     * @param $handler
     * @param array $soapOperation
     * @return array|callable
     * @throws ServerException
     */
    private function getCallable($handler, array $soapOperation)
    {
        if (is_callable($handler)) {
            return $handler;
        } elseif (method_exists($handler, $soapOperation['method'])) {
            return [$handler, $soapOperation['method']];
        } else {
            throw new ServerException("Can not find a valid callback to invoke " . $soapOperation['method']);
        }
    }
}
