<?php
namespace GoetasWebservices\SoapServices\SoapServer\Arguments;

use ArgumentsResolver\InDepthArgumentsResolver;

class ArgumentsGenerator implements ArgumentsGeneratorInterface
{
    /**
     * @param mixed $envelope
     * @param callable|null $callable
     * @return array
     */
    public function expandArguments($envelope, callable $callable = null)
    {
        $arguments = $this->findArguments($envelope);
        if ($callable !== null) {
            $arguments = (new InDepthArgumentsResolver($callable))->resolve($arguments);
        }
        return $arguments;
    }

    private function findArguments($envelope)
    {
        $arguments = [$envelope];
        $envelopeItems = $this->getObjectProperties($envelope);
        $arguments = $this->smartAdd($arguments, $envelopeItems);

        foreach ($envelopeItems as $envelopeItem) {
            $messageSubItems = $this->getObjectProperties($envelopeItem);
            $arguments = $this->smartAdd($arguments, $messageSubItems);
            foreach ($messageSubItems as $messageSubSubItems) {
                $messageSubSubItems = $this->getObjectProperties($messageSubSubItems);
                $arguments = $this->smartAdd($arguments, $messageSubSubItems);
            }
        }
        return $arguments;
    }

    private function smartAdd($arguments, $messageItems)
    {
        foreach ($messageItems as $name => $messageItem) {
            if (isset($arguments[$name])) {
                $arguments[] = $arguments[$name];
                $arguments[$name] = $messageItem;
            } else {
                $arguments[$name] = $messageItem;
            }
        }
        return $arguments;
    }

    /**
     * @param object|null $object
     * @return array
     */
    private function getObjectProperties($object)
    {
        if (!is_object($object)) {
            return [];
        }
        $ref = new \ReflectionObject($object);
        $args = [];
        do {
            foreach ($ref->getProperties() as $prop) {
                $prop->setAccessible(true);
                $args[$prop->getName()] = $prop->getValue($object);
            }
        } while ($ref = $ref->getParentClass());

        return $args;
    }
}
