<?php

namespace GoetasWebservices\SoapServices\SoapServer\Tests;

use GoetasWebservices\SoapServices\SoapCommon\SoapEnvelope\Messages\Fault;
use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\SoapServer\Server;
use GoetasWebservices\SoapServices\SoapServer\ServerFactory;
use GoetasWebservices\WsdlToPhp\Tests\Generator;
use JMS\Serializer\Handler\HandlerRegistryInterface;

abstract class AbstractServerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Generator
     */
    protected static $generator;
    /**
     * @var Server
     */
    protected static $server;

    public static function setUpBeforeClass()
    {
        $namespaces = [
            'http://www.example.org/test/' => "Ex"
        ];

        self::$generator = new Generator($namespaces);

        self::$generator->generate([__DIR__ . '/../Fixtures/Soap/test.wsdl']);
        self::$generator->registerAutoloader();
        $headerHandler = new HeaderHandler();

        $ref = new \ReflectionClass(Fault::class);

        $serializer = self::$generator->buildSerializer(function (HandlerRegistryInterface $h) use ($headerHandler) {
            $h->registerSubscribingHandler($headerHandler);
        }, [
            'GoetasWebservices\SoapServices\SoapCommon\SoapEnvelope' => dirname($ref->getFileName()) . '/../../Resources/metadata/jms'
        ]);

        $factory = new ServerFactory($namespaces, $serializer);
        $factory->setHeaderHandler($headerHandler);

        self::$server = $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl');
    }

    public static function tearDownAfterClass()
    {
        self::$generator->unRegisterAutoloader();
        //self::$generator->cleanDirectories();
    }
}
