# goetas-webservices / soap-server

[![Build Status](https://travis-ci.org/goetas-webservices/soap-server.svg?branch=master)](https://travis-ci.org/goetas-webservices/soap-server)

PHP implementation of SOAP 1.1 and 1.2 server specifications.

Strengths: 

- Pure PHP, no dependencies on `ext-soap`
- Extensible (JMS event listeners support)
- PSR-7 HTTP messaging
- PSR-15 HTTP server handlers
- No WSDL/XSD parsing on production
- IDE type hinting support

Only document/literal style is supported and the webservice should follow
the [WS-I](https://en.wikipedia.org/wiki/WS-I_Basic_Profile) guidelines.

There are no plans to support the deprecated rpc and encoded styles.
Webservices not following the WS-I specifications might work, but they are officially not supported.

## Demo 

[goetas-webservices/soap-server-demo](https://github.com/goetas-webservices/soap-server-demo) is a demo project
that shows how to produce a SOAP server in a generic PHP web application.


Installation
-----------

The recommended way to install goetas-webservices / soap-server is using [Composer](https://getcomposer.org/):

Add this packages to your `composer.json` file.

```
{
    "require": {
        "goetas-webservices/soap-server": "^0.1",
    },
    "require-dev": {
        "goetas-webservices/wsdl2php": "^0.4",
    },
}
```

# How to

To improve performance, this library is based on the concept that all the SOAP/WSDL 
metadata has to be compiled into PHP compatible metadata (in reality is a big plain PHP array,
so is really fast).

To do this we have to define a configuration file (in this case called `config.yml`) that
holds some important information. 

Here is an example:

```yml
# config.yml

soap_server:
   namespaces:
    'http://www.example.org/test/': 'TestNs/MyApp'
  destinations_php:
    'TestNs/MyApp': soap/src
  destinations_jms:
    'TestNs/MyApp': soap/metadata
  aliases:
    'http://www.example.org/test/':
      MyCustomXSDType:  'MyCustomMappedPHPType'

  metadata:
    'test.wsdl': ~
```

This file has some important sections:

### WSDL Specific

* `metadata` specifies where are placed WSDL files that will be used to generate al the required PHP metadata.

 
### XML/XSD Specific
 
* `namespaces` (required) defines the mapping between XML namespaces and PHP namespaces.
 (in the example we have the `http://www.example.org/test/` XML namespace mapped to `TestNs\MyApp`)


* `destinations_php` (required) specifies the directory where to save the PHP classes that belongs to 
 `TestNs\MyApp` PHP namespace. (in this example `TestNs\MyApp` classes will ne saved into `soap/src` directory.
 

* `destinations_jms` (required) specifies the directory where to save JMS Serializer metadata files 
 that belongs to `TestNs\MyApp` PHP namespace. 
 (in this example `TestNs\MyApp` metadata will ne saved into `soap/metadata` directory.
 
 
* `aliases` (optional) specifies some mappings that are handled by custom JMS serializer handlers.
 Allows to specify to do not generate metadata for some XML types, and assign them directly a PHP class.
 For that PHP class is necessary to create a custom JMS serialize/deserialize handler.
 
 
 
## Metadata generation
 
In order to be able to use the SOAP server we have to generate some metadata and PHP classes.
 
To do it we can run:

```sh
bin/soap-server generate \
 tests/config.yml \
 --dest-class=GlobalWeather/Container/SoapServerContainer \
 soap/src-gw/Container 
```

* `bin/soap-server generate` is the command we are running
* `tests/config.yml` is a path to our configuration file
* `--dest-class=GlobalWeather/Container/SoapServerContainer` allows to specify the fully qualified class name of the 
 container class that will hold all the webservice metadata.
* `soap/src/Container` is the path where to save the container class that holds all the webservice metadata
 (you will have to configure the auto loader to load  it)
  
 
## Using the server

Once all the metadata are generated we can use our SOAP server.

Let's see a minimal example:

```php
// composer auto loader
require __DIR__ . '/vendor/autoload.php';

// instantiate the main container class
// the name was defined by --dest-class=GlobalWeather/Container/SoapServerContainer
// parameter during the generation process
$container = new SoapServerContainer();

// create a JMS serializer instance
$serializer = SoapContainerBuilder::createSerializerBuilderFromContainer($container)->build();
// get the metadata from the container
$metadata = $container->get('goetas_webservices.soap.metadata_reader');

$handler = new class() {
    function someAction($someParam) 
    {
        return 'OK 123';
    }
    
    function anotherAction($someParam) 
    {
        return 'OK 456';
    }
}; 

$router = new DefaultRouter(new ConfiguredRoute($handler));

$factory = new ServerFactory($metadata, $serializer, $router);

 // get the soap server
$server = $factory->getServer('test.wsdl');

// create psr7 request
$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals();

// let the server handle the request
$response = $server->handle($request);

// send the response to the client (using laminas/laminas-httphandlerrunner)
$emitter = new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter();
$emitter->emit($response);
```

## Note 

The code in this project is provided under the 
[MIT](https://opensource.org/licenses/MIT) license. 
For professional support 
contact [goetas@gmail.com](mailto:goetas@gmail.com) 
or visit [https://www.goetas.com](https://www.goetas.com)
