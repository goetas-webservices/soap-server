<?php
namespace GoetasWebservices\SoapServices\SoapServer;

use GoetasWebservices\SoapServices\SoapCommon as SoapCommon;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGenerator;
use GoetasWebservices\SoapServices\SoapServer\Arguments\ArgumentsGeneratorInterface;
use GoetasWebservices\SoapServices\SoapServer\Exception\MustUnderstandException;
use GoetasWebservices\SoapServices\SoapServer\Exception\ServerException;
use GoetasWebservices\SoapServices\SoapServer\Exception\SoapServerException;
use GoetasWebservices\SoapServices\SoapServer\Message\MessageFactoryInterface;
use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandlerInterface;
use JMS\Serializer\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server
{
    use SoapCommon\ResultWrapperTrait;
    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var MessageFactoryInterface
     */
    protected $httpFactory;

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

    public function __construct(array $serviceDefinition, Serializer $serializer, MessageFactoryInterface $httpFactory, HeaderHandlerInterface $headerHandler)
    {
        $this->serializer = $serializer;
        $this->httpFactory = $httpFactory;
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
            $wrappedResult = $this->wrapResult($this->serializer, $fault, SoapCommon\SoapEnvelope\Messages\Fault::class);
        } else {
            $wrappedResult = $this->wrapResult($this->serializer, $result, $soapOperation['output']['message_fqcn']);
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
        $response = $this->httpFactory->getResponse($message);
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
