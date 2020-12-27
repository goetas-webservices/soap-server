<?php

namespace GoetasWebservices\SoapServices\Metadata\Arguments;

use Doctrine\Instantiator\Instantiator;
use GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Handler\HeaderPlaceholder;
use GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Header;
use GoetasWebservices\SoapServices\SoapServer\Serializer\Handler\HeaderHandlerInterface;
use JMS\Serializer\Accessor\DefaultAccessorStrategy;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Serializer;

class ArgumentsReader implements ArgumentsReaderInterface
{
    /**
     * @var Serializer
     */
    private $serializer;
    /**
     * @var HeaderHandler
     */
    private $headerHandler;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param array $args
     * @param array $message
     * @return null|object
     */
    public function readArguments(array $args, array $message)
    {
        $envelope = array_filter($args, function ($item) use ($message) {
            return $item instanceof $message['message_fqcn'];
        });
        if ($envelope) {
            return reset($envelope);
        }

        $instantiator = new Instantiator();
        $envelope = $instantiator->instantiate($message['message_fqcn']);

        if (!count($message['parts'])) {
            return $envelope;
        }

        $args = $this->handleHeaders($args, $message, $envelope);
        if ($args[0] instanceof $message['part_fqcn']) {
            $envelope->setBody($args[0]);
            return $envelope;
        }

        $body = $instantiator->instantiate($message['part_fqcn']);
        $envelope->setBody($body);
        $factory = $this->serializer->getMetadataFactory();
        $classMetadata = $factory->getMetadataForClass($message['part_fqcn']);

        if (count($message['parts']) > 1) {

            if (count($message['parts']) !== count($args)) {
                throw new \Exception("Expected to have exactly " . count($message['parts']) . " arguments, supplied " . count($args));
            }

            foreach ($message['parts'] as $paramName => $elementName) {
                $propertyMetadata = $classMetadata->propertyMetadata[$paramName];
                $this->setValue($body, array_shift($args), $propertyMetadata);
            }
            return $envelope;
        }

        $propertyName = key($message['parts']);
        $propertyMetadata = $classMetadata->propertyMetadata[$propertyName];

        if ($args[0] instanceof $propertyMetadata->type['name']) {
            $this->setValue($body, reset($args), $propertyMetadata);
            return $envelope;
        }

        $instance2 = $instantiator->instantiate($propertyMetadata->type['name']);
        $classMetadata2 = $factory->getMetadataForClass($propertyMetadata->type['name']);
        $this->setValue($body, $instance2, $propertyMetadata);

        foreach ($classMetadata2->propertyMetadata as $propertyMetadata2) {
            if (!count($args)) {
                throw new \Exception("Not enough arguments provided. Can't find a parameter to set " . $propertyMetadata2->name);
            }
            $value = array_shift($args);
            $this->setValue($instance2, $value, $propertyMetadata2);
        }
        return $envelope;
    }

    /**
     * @param array $args
     * @param array $message
     * @param $envelope
     * @return array
     */
    private function handleHeaders(array $args, array $message, $envelope)
    {
        $headers = array_filter($args, function ($item) use ($message) {
            return $item instanceof $message['headers_fqcn'];
        });
        if ($headers) {
            $envelope->setHeader(reset($headers));
        } else {

            $headers = array_filter($args, function ($item) {
                return $item instanceof Header;
            });
            if (count($headers)) {
                $headerPlaceholder = new HeaderPlaceholder();
                foreach ($headers as $headerInfo) {
//                    $this->headerHandler->addHeaderData($headerPlaceholder, $headerInfo);
                }
                $envelope->setHeader($headerPlaceholder);
            }
        }

        $args = array_filter($args, function ($item) use ($message) {
            return !($item instanceof Header) && !($item instanceof $message['headers_fqcn']);
        });
        return $args;
    }


    private function setValue($target, $value, PropertyMetadata $propertyMetadata): void
    {
        $context = DeserializationContext::create();
        $accessor = new DefaultAccessorStrategy();

        $accessor->setValue($target, $value, $propertyMetadata, $context);
    }
}
