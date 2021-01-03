<?php
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Tests;

use Ex\AuthHeader;
use Ex\GetReturnMultiParamResponse;
use Ex\GetSimple;
use Ex\GetSimpleResponse;
use Ex\SoapEnvelope12\Messages\GetSimpleInput as GetSimpleInputMessage;
use Ex\SoapEnvelope12\Messages\GetSimpleOutput as GetSimpleOutputMessage;
use Ex\SoapParts\GetReturnMultiParamOutput;
use Ex\SoapParts\GetSimpleInput;
use Ex\SoapParts\GetSimpleOutput;
use GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope\Parts\Fault;
use GoetasWebservices\SoapServices\Metadata\Generator\MetadataGenerator;
use GoetasWebservices\SoapServices\Metadata\Headers\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\Metadata\Headers\Header;
use GoetasWebservices\SoapServices\Metadata\Headers\HeadersIncoming;
use GoetasWebservices\SoapServices\Metadata\Headers\HeadersOutgoing;
use GoetasWebservices\SoapServices\Metadata\Loader\DevMetadataLoader;
use GoetasWebservices\SoapServices\SoapServer\Exception\FaultException;
use GoetasWebservices\SoapServices\SoapServer\Router\CallbackRoute;
use GoetasWebservices\SoapServices\SoapServer\Router\ConfiguredRoute;
use GoetasWebservices\SoapServices\SoapServer\Router\DefaultRouter;
use GoetasWebservices\SoapServices\SoapServer\Router\Route;
use GoetasWebservices\SoapServices\SoapServer\Server;
use GoetasWebservices\SoapServices\SoapServer\ServerFactory;
use GoetasWebservices\WsdlToPhp\Tests\Generator;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use GoetasWebservices\Xsd\XsdToPhp\Naming\ShortNamingStrategy;
use GuzzleHttp\Psr7\ServerRequest;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\SerializerBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ServerRequestResponsesTest extends TestCase
{
    /**
     * @var string[]
     */
    protected static $namespaces = ['http://www.example.org/test/' => 'Ex'];

    /**
     * @var DefaultRouter
     */
    private $router;

    /**
     * @var ServerFactory
     */
    private $factory;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var Generator
     */
    protected static $generator;

    public static function setUpBeforeClass(): void
    {
        self::$generator = new Generator(self::$namespaces, [], __DIR__ . '/tmp');
        self::$generator->generate([__DIR__ . '/../Fixtures/Soap/test.wsdl']);
        self::$generator->registerAutoloader();
    }

    public static function tearDownAfterClass(): void
    {
        self::$generator->unRegisterAutoloader();
        //self::$generator->cleanDirectories();
    }

    public function setUp(): void
    {
        $ref = new \ReflectionClass(Fault::class);

        $serializer = self::$generator->buildSerializer(static function (SerializerBuilder $builder): void {
            $headerHandler = new HeaderHandler();
            $builder->configureListeners(static function (EventDispatcherInterface $d) use ($builder, $headerHandler): void {
                $builder->addDefaultListeners();
                $d->addSubscriber($headerHandler);
            });
            $builder->configureHandlers(static function (HandlerRegistryInterface $h) use ($builder, $headerHandler): void {
                $builder->addDefaultHandlers();
                $h->registerSubscribingHandler($headerHandler);
            });
        }, [
            'GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope12' => dirname($ref->getFileName()) . '/../../../Resources/metadata/jms12',
            'GoetasWebservices\SoapServices\Metadata\Envelope\SoapEnvelope' => dirname($ref->getFileName()) . '/../../../Resources/metadata/jms',
        ]);

        $naming = new ShortNamingStrategy();
        $dispatcher = new EventDispatcher();
        $wsdlReader = new DefinitionsReader(null, $dispatcher);
        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);

        $metadataGenerator = new MetadataGenerator($naming, self::$namespaces);
        $metadataGenerator->setUnwrap(true);

        $metadataLoader = new DevMetadataLoader($metadataGenerator, $soapReader, $wsdlReader);

        $this->container = new Container();

        $this->router = new DefaultRouter();
        $this->factory = new ServerFactory($metadataLoader, $serializer, $this->router);
        $this->factory->setControllerContainer($this->container);

        $this->server = $this->factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl');
    }

    /**
     * @return callable[][]
     */
    public function getArguemntExpansionCallbacks(): array
    {
        return [
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());

                    return 'OK';
                },
            ],
            [
                function (string $in) {
                    $this->assertEquals('some string', $in);

                    return 'OK';
                },
            ],
            [
                function (GetSimpleInput $in) {
                    $this->assertEquals('some string', $in->getGetSimple()->getIn());

                    return 'OK';
                },
            ],
            [
                function (GetSimpleInputMessage $in) {
                    $this->assertEquals('some string', $in->getBody()->getGetSimple()->getIn());

                    return 'OK';
                },
            ],
        ];
    }

    /**
     * @return callable[][]
     */
    public function getResponseExpansionCallbacks(): array
    {
        return [
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());

                    return 'OK';
                },
            ],
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());
                    $response = new GetSimpleResponse();
                    $response->setOut('OK');

                    return $response;
                },
            ],
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());

                    $response = new GetSimpleResponse();
                    $response->setOut('OK');

                    $part = new GetSimpleOutput();
                    $part->setGetSimpleResponse($response);

                    return $part;
                },
            ],
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());

                    $response = new GetSimpleResponse();
                    $response->setOut('OK');

                    $part = new GetSimpleOutput();
                    $part->setGetSimpleResponse($response);

                    $message = new GetSimpleOutputMessage();
                    $message->setBody($part);

                    return $message;
                },
            ],
            [
                function (GetSimple $in) {
                    $this->assertEquals('some string', $in->getIn());

                    $response = new GetSimpleResponse();
                    $response->setOut('OK');

                    $part = new GetSimpleOutput();
                    $part->setGetSimpleResponse($response);

                    $message = new GetSimpleOutputMessage();
                    $message->setBody($part);

                    return $message;
                },
            ],
        ];
    }

    /**
     * @dataProvider getArguemntExpansionCallbacks
     */
    public function testArgumentExpansion(callable $hanlder): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, $hanlder));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    /**
     * @dataProvider getResponseExpansionCallbacks
     */
    public function testResponseExpansion(callable $hanlder): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, $hanlder));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function testCustomHeader(): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Header>
                <ns-b3c6b39d:getSimple xmlns:ns-b3c6b39d="http://www.example.org/test/" SOAP:mustUnderstand="true">
                  <in>abc</in>
                </ns-b3c6b39d:getSimple>
              </SOAP:Header>
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, static function (HeadersOutgoing $responseHeaders) {
            $o = new GetSimple();
            $o->setIn('abc');
            $responseHeaders->addHeader(new Header($o, ['mustUnderstand' => true]));

            return 'OK';
        }));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function testSoapActionCompat(): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            [
                'Content-Type' => 'application/soap+xml',
                'SOAPAction' => '"http://www.example.org/test/getSimple"',
            ],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (GetSimple $in) {
            $this->assertEquals('some string', $in->getIn());

            return 'OK';
        }));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function getControllers(): array
    {
        return [
            [
                'callbackInContainer',
                'callbackInContainer',
                static function (GetSimple $in): string {
                    return 'OK';
                },
            ],
            [
                'callbackInContainer',
                'callbackInContainer::foo',
                new class () {
                    public function foo(GetSimple $in): string
                    {
                        return 'OK';
                    }
                },
            ],
            [
                'callbackInContainer',
                'callbackInContainer::*',
                new class () {
                    public function getSimple(GetSimple $in): string
                    {
                        return 'OK';
                    }
                },
            ],
            [
                'callbackInContainer',
                new class () {
                    public function getSimple(GetSimple $in): string
                    {
                        return 'OK';
                    }
                },
                null,
            ],
        ];
    }

    /**
     * @param callable|string|null $controller
     *
     * @dataProvider getControllers
     */
    public function testConfiguredRoute(string $serviceId, $controller, ?object $service): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            [
                'Content-Type' => 'application/soap+xml',
                'SOAPAction' => '"http://www.example.org/test/getSimple"',
            ],
            trim($requestString)
        );

        $this->container->set($serviceId, $service);
        $this->router->addRoute(new ConfiguredRoute($controller, ['action' => 'http://www.example.org/test/getSimple']));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function testSoap11VersionMismatch(): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
              <SOAP:Body>
                <SOAP:Fault>
                  <faultcode>SOAP:Client</faultcode>
                  <faultstring>The requested action http://www.example.org/test/getSimple is not supported using the 1.1 version protocol, but is supported using the 1.2 protocol.</faultstring>
                </SOAP:Fault>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            [
                'Content-Type' => 'text/xml',
                'SOAPAction' => '"http://www.example.org/test/getSimple"',
            ],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (GetSimple $in) {
            $this->assertEquals('some string', $in->getIn());

            return 'OK';
        }));

        $response = $this->server->handle($request);

        $this->assertSame('text/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function getRoutes(): array
    {
        return [
            [null],
            [
                new ConfiguredRoute('abc', ['action' => 'http://www.example.org/test/XXXX']),
            ],
            [
                new CallbackRoute(static function () {
                    return false;
                }, static function (GetSimple $in): void {
                }),
            ],
        ];
    }

    /**
     * @dataProvider getRoutes
     */
    public function testNoRoute(?Route $route): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <SOAP:Fault>
                  <SOAP:Code>
                    <SOAP:Value>SOAP:Receiver</SOAP:Value>
                  </SOAP:Code>
                  <SOAP:Reason>
                    <SOAP:Text>Can not find an handler to run http://www.example.org/test/getSimple</SOAP:Text>
                  </SOAP:Reason>
                </SOAP:Fault>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            '/',
            [
                'Content-Type' => 'application/soap+xml',
                'SOAPAction' => '"http://www.example.org/test/getSimple"',
            ],
            trim($requestString)
        );

        if ($route) {
            $this->router->addRoute($route);
        }

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function testSoapActionAsUri(): void
    {
        $requestString = '
            <SOAP:Envelope
                xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                xmlns:test="http://www.example.org/test/">
               <soapenv:Body>
                  <test:getSimple>
                     <in>some string</in>
                  </test:getSimple>
               </soapenv:Body>
            </SOAP:Envelope>';
        $responseString = '
            <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
              <SOAP:Body>
                <ns-b3c6b39d:getSimpleResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
                  <out><![CDATA[OK]]></out>
                </ns-b3c6b39d:getSimpleResponse>
              </SOAP:Body>
            </SOAP:Envelope>';

        $request = new ServerRequest(
            'POST',
            'http://www.example.org/test/getSimple',
            [
                'Content-Type' => 'application/soap+xml',
                'SOAPAction' => '""',
            ],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (GetSimple $in) {
            $this->assertEquals('some string', $in->getIn());

            return 'OK';
        }));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());

        $request = new ServerRequest(
            'POST',
            'http://www.example.org/test/getSimple',
            ['Content-Type' => 'application/soap+xml, action=""'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (GetSimple $in) {
            $this->assertEquals('some string', $in->getIn());

            return 'OK';
        }));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString(trim($responseString), (string) $response->getBody());
    }

    public function getDebugModes(): array
    {
        return [
            [true],
            [false],
            [null],
        ];
    }

    public function testHeader(): void
    {
        $requestString = trim('
        <soapenv:Envelope
             xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
             xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:authHeader>
                 <user>username</user>
                 <pwd>pwd</pwd>
              </test:authHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeader>
                 <in>input</in>
              </test:requestHeader>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <ns-b3c6b39d:requestHeaderResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:requestHeaderResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/requestHeader"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (AuthHeader $authHeader, HeadersIncoming $bag) {
            $this->assertEquals('username', $authHeader->getUser());
            $this->assertEquals('pwd', $authHeader->getPwd());

            return 'A';
        }));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }

    public function getHeadersToUnderstandPayload(): array
    {
        return [
            [
                'MustUnderstand headers: authHeader{http://www.example.org/test/} are not understood',
                '
                <soapenv:Envelope
                     xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                     xmlns:test="http://www.example.org/test/">
                   <soapenv:Header>
                      <test:authHeader soapenv:mustUnderstand="true">
                         <user>username</user>
                         <pwd>pwd</pwd>
                      </test:authHeader>
                   </soapenv:Header>
                   <soapenv:Body>
                      <test:requestHeader>
                         <in>input</in>
                      </test:requestHeader>
                   </soapenv:Body>
                </soapenv:Envelope>',
                function ($in, AuthHeader $authHeader, HeadersIncoming $bag) {
                    $this->assertEquals('input', $in);

                    $this->assertEquals('username', $authHeader->getUser());
                    $this->assertEquals('pwd', $authHeader->getPwd());

                    return 'A';
                },
            ],
            [
                'MustUnderstand headers: someOtherHeader{http://www.example.org/test/} are not understood',
                '
        <soapenv:Envelope
             xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
             xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:someOtherHeader soapenv:mustUnderstand="true">
                 foo
              </test:someOtherHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeader>
                 <in>input</in>
              </test:requestHeader>
           </soapenv:Body>
        </soapenv:Envelope>',
                function ($in, HeadersIncoming $bag) {
                    $this->assertEquals('input', $in);

                    return 'A';
                },
            ],
        ];
    }

    public function getHeadersUnderstoodPayload(): array
    {
        return [
            [
                '
                <soapenv:Envelope
                     xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                     xmlns:test="http://www.example.org/test/">
                   <soapenv:Header>
                      <test:authHeader soapenv:mustUnderstand="true">
                         <user>username</user>
                         <pwd>pwd</pwd>
                      </test:authHeader>
                   </soapenv:Header>
                   <soapenv:Body>
                      <test:requestHeader>
                         <in>input</in>
                      </test:requestHeader>
                   </soapenv:Body>
                </soapenv:Envelope>',
                function ($in, AuthHeader $authHeader, HeadersIncoming $bag) {
                    $this->assertTrue($bag->isMustUnderstandHeader($authHeader));
                    $bag->understoodHeader($authHeader);
                    $this->assertEquals('input', $in);

                    $this->assertEquals('username', $authHeader->getUser());
                    $this->assertEquals('pwd', $authHeader->getPwd());

                    return 'A';
                },
            ],
            [
                '
        <soapenv:Envelope
             xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
             xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:someOtherHeader soapenv:mustUnderstand="true">
                 foo
              </test:someOtherHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeader>
                 <in>input</in>
              </test:requestHeader>
           </soapenv:Body>
        </soapenv:Envelope>',
                function ($in, HeadersIncoming $bag) {
                    $headers = $bag->getRawHeaders();
                    $this->assertCount(1, $headers);
                    $this->assertTrue($bag->isMustUnderstandHeader($headers[0]));
                    $bag->understoodHeader($headers[0]);

                    $this->assertEquals('input', $in);

                    return 'A';
                },
            ],
        ];
    }

    /**
     * @dataProvider getHeadersToUnderstandPayload
     */
    public function testHeaderNotUnderstood(string $expectedMessage, string $requestString, callable $handler): void
    {
        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <SOAP:Fault>
              <SOAP:Code>
                <SOAP:Value>SOAP:MustUnderstand</SOAP:Value>
              </SOAP:Code>
              <SOAP:Reason>
                <SOAP:Text>' . $expectedMessage . '</SOAP:Text>
              </SOAP:Reason>
            </SOAP:Fault>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/requestHeader"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, $handler));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }

    /**
     * @dataProvider getHeadersUnderstoodPayload
     */
    public function testHeaderUnderstood(string $requestString, callable $handler): void
    {
        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <ns-b3c6b39d:requestHeaderResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:requestHeaderResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/requestHeader"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, $handler));

        $response = $this->server->handle($request);

        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testBasicFault(?bool $debug): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $expectedResponseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
          <SOAP:Fault>
              <SOAP:Code>
                <SOAP:Value>SOAP:Receiver</SOAP:Value>
                <SOAP:Subcode>
                    <SOAP:Value>5</SOAP:Value>
                </SOAP:Subcode>
              </SOAP:Code>
              <SOAP:Reason>
                <SOAP:Text>Generic error</SOAP:Text>
              </SOAP:Reason>
          </SOAP:Fault>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, static function (GetSimple $in): void {
            throw new \Exception('Generic error', 5);
        }));

        if (null !== $debug) {
            $this->server->setDebug($debug);
        }

        $response = $this->server->handle($request);

        $responseString = (string) $response->getBody();

        if ($debug) {
            $responseString = preg_replace('~(<SOAP:Reason>)(.*)(</SOAP:Reason>)~s', '\\1<SOAP:Text>Generic error</SOAP:Text>\\3', $responseString);
        }

        $this->assertXmlStringEqualsXmlString(trim($expectedResponseString), $responseString);
    }

    public function testVersionNotDetected(): void
    {
        $expectedResponseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
          <SOAP:Fault>
              <SOAP:Code>
                <SOAP:Value>SOAP:VersionMismatch</SOAP:Value>
              </SOAP:Code>
              <SOAP:Reason>
                <SOAP:Text>The request is not a valid SOAP (1.1 or 1.2) message</SOAP:Text>
              </SOAP:Reason>
          </SOAP:Fault>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'text/html']
        );

        $response = $this->server->handle($request);

        $responseString = (string) $response->getBody();
        $this->assertXmlStringEqualsXmlString(trim($expectedResponseString), $responseString);
    }

    public function testInvalidAction(): void
    {
        $expectedResponseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
          <SOAP:Fault>
              <SOAP:Code>
                <SOAP:Value>SOAP:Receiver</SOAP:Value>
              </SOAP:Code>
              <SOAP:Reason>
                <SOAP:Text>Can not find a valid SOAP operation to fulfill 123 action</SOAP:Text>
              </SOAP:Reason>
          </SOAP:Fault>
          </SOAP:Body>
        </SOAP:Envelope>');

        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="123"'],
            $requestString
        );

        $response = $this->server->handle($request);

        $responseString = (string) $response->getBody();
        $this->assertXmlStringEqualsXmlString(trim($expectedResponseString), $responseString);
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testModelFault(?bool $debug): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $expectedResponseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
          <SOAP:Fault>
              <SOAP:Code>
                <SOAP:Value>SOAP:Receiver</SOAP:Value>
                <SOAP:Subcode>
                    <SOAP:Value>5</SOAP:Value>
                </SOAP:Subcode>
              </SOAP:Code>
              <SOAP:Reason>
                <SOAP:Text>Model error</SOAP:Text>
              </SOAP:Reason>
              <SOAP:Detail>
                <some-wrapping-element-to-be-read-from-service-defintion>
                    <out>OK</out>
                </some-wrapping-element-to-be-read-from-service-defintion>
              </SOAP:Detail>
          </SOAP:Fault>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, static function (): void {
            throw new class ('Model error', 5) extends \Exception implements FaultException {
                public function getDetail(): object
                {
                    $response = new GetSimpleResponse();
                    $response->setOut('OK');

                    return $response;
                }
            };
        }));

        if (null !== $debug) {
            $this->server->setDebug($debug);
        }

        $response = $this->server->handle($request);

        $responseString = (string) $response->getBody();

        if ($debug) {
            $responseString = preg_replace('~(<SOAP:Reason>)(.*)(</SOAP:Reason>)~s', '\\1<SOAP:Text>Model error</SOAP:Text>\\3', $responseString);
        }

        $responseString = preg_replace('~(<SOAP:Detail>)(.*)(</SOAP:Detail>)~s', '', $responseString);

        // this replacement should be removed when the fault handling is implemented as it should
        $expectedResponseString = preg_replace('~(<SOAP:Detail>)(.*)(</SOAP:Detail>)~s', '', $expectedResponseString);

        $this->assertXmlStringEqualsXmlString(trim($expectedResponseString), $responseString);

        $this->markTestIncomplete();
    }

    public function testNoOutput(): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:noOutput>
                 <in>B</in>
              </test:noOutput>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope"/>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/noOutput"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function (string $in): void {
            $this->assertEquals('B', $in);
        }));

        $response = $this->server->handle($request);
        $this->assertXmlStringEqualsXmlString((string) $response->getBody(), $responseString);
    }

    public function testNoInput(): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
           <soapenv:Body/>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <ns-b3c6b39d:noInputResponse  xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[B]]></out>
            </ns-b3c6b39d:noInputResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/noInput"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, static function () {
            return 'B';
        }));

        $response = $this->server->handle($request);
        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }

    public function testGetMultiParam(): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getMultiParam>
                 <in>some string</in>
              </test:getMultiParam>
              <other-param>other string</other-param>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <ns-b3c6b39d:getMultiParamResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:getMultiParamResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getMultiParam"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, function ($in, $otherParam) {
            $this->assertEquals('some string', $in);
            $this->assertEquals('other string', $otherParam);

            return 'A';
        }));

        $response = $this->server->handle($request);
        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }

    public function multiParamResponseHandlers(): array
    {
        return [
            [
                function (string $in): GetReturnMultiParamOutput {
                    $this->assertEquals('some string', $in);

                    $p1 = new GetReturnMultiParamResponse();
                    $p1->setOut('D');

                    $p2 = 'C';

                    $out = new GetReturnMultiParamOutput();

                    $out->setGetReturnMultiParamResponse($p1);
                    $out->setOtherParam($p2);

                    return $out;
                },
            ],
            [
                function (string $in): array {
                    $this->assertEquals('some string', $in);

                    $p1 = new GetReturnMultiParamResponse();
                    $p1->setOut('D');

                    $p2 = 'C';

                    return [$p1, $p2];
                },
            ],
        ];
    }

    /**
     * @dataProvider multiParamResponseHandlers
     */
    public function testGetMultiParamResponse(callable $handler): void
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getReturnMultiParam>
                 <in>some string</in>
              </test:getReturnMultiParam>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://www.w3.org/2003/05/soap-envelope">
          <SOAP:Body>
            <ns-b3c6b39d:getReturnMultiParamResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[D]]></out>
            </ns-b3c6b39d:getReturnMultiParamResponse>
            <other-param>C</other-param>
          </SOAP:Body>
        </SOAP:Envelope>
        ');

        $request = new ServerRequest(
            'POST',
            '/',
            ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getReturnMultiParam"'],
            trim($requestString)
        );

        $this->router->addRoute(new CallbackRoute(null, $handler));

        $response = $this->server->handle($request);
        $this->assertXmlStringEqualsXmlString($responseString, (string) $response->getBody());
    }
}
