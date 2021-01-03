<?php

declare(strict_types=1);

namespace Example;

use GoetasWebservices\SoapServices\SoapServer\Builder\SoapContainerBuilder;
use GoetasWebservices\SoapServices\SoapServer\Router\CallbackRoute;
use GoetasWebservices\SoapServices\SoapServer\Router\DefaultRouter;
use GoetasWebservices\SoapServices\SoapServer\ServerFactory;
use GuzzleHttp\Psr7\ServerRequest;
use TestNs\Container\SoapServerContainer;
use TestNs\GetSimple;

/**
 * @var $loader \Composer\Autoload\ClassLoader
 */
$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('TestNs\\', __DIR__ . '/../soap/src');

$container = new SoapServerContainer();

$serializer = SoapContainerBuilder::createSerializerBuilderFromContainer($container)->build();
$metadata = $container->get('goetas_webservices.soap.metadata_reader');

$router = new DefaultRouter(new CallbackRoute(null, static function (GetSimple $in) {
    if ('some string' !== $in->getIn()) {
        echo sprintf("\$in 'some string', found %s\n", $in->getIn());
        exit(-128);
    }

    return 'OK 123';
}));
$factory = new ServerFactory($metadata, $serializer, $router);

$server = $factory->getServer('tests/Fixtures/Soap/test.wsdl');

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

$request = new ServerRequest(
    'POST',
    '/',
    ['Content-Type' => 'application/soap+xml; action="http://www.example.org/test/getSimple"'],
    trim($requestString)
);

$response = $server->handle($request);

if ('application/soap+xml; charset=utf-8' !== $response->getHeaderLine('Content-Type')) {
    echo sprintf("Content-Type is not 'application/soap+xml; charset=utf-8', found %s\n", $response->getHeaderLine('Content-Type'));
    exit(-127);
}

if (false === strpos((string) $response->getBody(), 'OK 123')) {
    echo "Unexpected response:\n";
    echo $response->getBody();
    exit(-127);
}
