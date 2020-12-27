<?php

namespace GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Handler;

use GoetasWebservices\SoapServices\Metadata\Arguments\Headers\Header;
use GoetasWebservices\SoapServices\Metadata\Arguments\Headers\HeaderBag;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\XmlDeserializationVisitor;
use JMS\Serializer\XmlSerializationVisitor;
use Symfony\Component\DependencyInjection\SimpleXMLElement;

class HeaderHandler implements SubscribingHandlerInterface
{
    const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const SOAP_12 = 'http://www.w3.org/2003/05/soap-envelope';


    protected $headerData = [];

    public static function getSubscribingMethods()
    {
        return array(
            array(
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'xml',
                'type' => HeaderPlaceholder::class,
                'method' => 'serializeHeaderPlaceholder'
            ),
            array(
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'xml',
                'type' => HeaderPlaceholder::class,
                'method' => 'deserializeHeaderPlaceholder'
            ),
            array(
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'xml',
                'type' => 'GoetasWebservices\SoapServices\SoapEnvelope\Header',
                'method' => 'deserializeHeader'
            ),
        );
    }

    public function deserializeHeaderPlaceholder(XmlDeserializationVisitor $visitor, \SimpleXMLElement $data, array $type, DeserializationContext $context)
    {
        $type = array('name' => $type['params'][0], 'params' => []);

        return $context->getNavigator()->accept($data, $type, $context);
    }

    public function deserializeHeader(XmlDeserializationVisitor $visitor, \SimpleXMLElement $data, array $type, DeserializationContext $context)
    {
        $type = array('name' => $type['params'][0], 'params' => []);

        $return = $context->getNavigator()->accept($data, $type, $context);

        $mustUnderstandAttr = $data->attributes(self::SOAP_12)->mustUnderstand ?: $data->attributes(self::SOAP)->mustUnderstand;
        $mustUnderstand = $mustUnderstandAttr !== null && $visitor->visitBoolean($mustUnderstandAttr, [], $context);
        /**
         * @var HeaderBag $headerBag
         */
        $headerBag = $context->getAttribute('headers_bag');

        if ($mustUnderstand) {
            $headerBag->addMustUnderstandHeader($return);
        } else {
            $headerBag->addHeader($return);
        }

        return $return;
    }

    public function addHeaderData(HeaderPlaceholder $reference, $data)
    {
        $this->headerData[spl_object_hash($reference)][] = $data;
    }

    public function serializeHeaderPlaceholder(XmlSerializationVisitor $visitor, HeaderPlaceholder $data, array $type, SerializationContext $context)
    {
        /**
         * @var HeaderBag $headerBag
         */
        $headerBag = $context->getAttribute('headers_bag');

        $factory = $context->getMetadataFactory();
        /**
         * @var $header Header
         */
        foreach ($this->headerData[spl_object_hash($data)] as $header) {
            /**
             * @var $classMetadata \JMS\Serializer\Metadata\ClassMetadata
             */
            $classMetadata = $factory->getMetadataForClass(get_class($header->getData()));

            $name = ($pos = strpos($classMetadata->xmlRootName, ':')) !== false ? substr($classMetadata->xmlRootName, $pos + 1) : $classMetadata->xmlRootName;

            $metadata = new StaticPropertyMetadata($classMetadata->name, $name, $header->getData());
            $metadata->xmlNamespace = $classMetadata->xmlRootNamespace;
            $metadata->serializedName = $name;

            $visitor->visitProperty($metadata, $header->getData(), $context);

            $this->handleOptions($visitor, $header);
        }
    }

    private function handleOptions(XmlSerializationVisitor $visitor, $header)
    {
        $options = $header->getOptions();
        if (!count($options)) {
            return;
        }
        /**
         * @var $currentNode \DOMNode
         */
        $currentNode = $visitor->getCurrentNode();
        foreach ($options as $option => $value) {
            if (in_array($option, ['mustUnderstand', 'required', 'role', 'actor'])) {

                if ($currentNode->ownerDocument->documentElement->namespaceURI === self::SOAP_12) {
                    $envelopeNS = self::SOAP_12;
                } else {
                    $envelopeNS = self::SOAP;
                }
                $this->setAttributeOnNode($currentNode->lastChild, $option, $value, $envelopeNS);
            }
        }
    }

    private function setAttributeOnNode(\DOMElement $node, $name, $value, $namespace)
    {
        if (!($prefix = $node->lookupPrefix($namespace)) && !($prefix = $node->ownerDocument->lookupPrefix($namespace))) {
            $prefix = 'ns-' . substr(sha1($namespace), 0, 8);
        }
        $node->setAttributeNS($namespace, $prefix . ':' . $name, is_bool($value) || is_null($value) ? ($value ? 'true' : 'false') : $value);
    }
}



