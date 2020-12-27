<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Arguments;

class ArgumentsGenerator implements ArgumentsGeneratorInterface
{
    public function expandArguments(object $envelope): array
    {
        return $this->findArguments($envelope);
    }

    private function findArguments(object $envelope): array
    {
        $arguments = [$envelope];
        $envelopeItems = $this->getObjectProperties($envelope);
        $arguments = $this->smartAdd($arguments, $envelopeItems);

        foreach ($envelopeItems as $envelopeItemName => $envelopeItem) {
            $messageSubItems = $this->getObjectProperties($envelopeItem);
            $arguments = $this->smartAdd($arguments, $messageSubItems);

            if ('body' !== $envelopeItemName) {
                continue;
            }

            foreach ($messageSubItems as $messageSubSubItems) {
                $messageSubSubItems = $this->getObjectProperties($messageSubSubItems);
                $arguments = $this->smartAdd($arguments, $messageSubSubItems);
            }
        }

        return $arguments;
    }

    private function smartAdd(array $arguments, array $messageItems): array
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
     * @param mixed $object
     *
     * @return array
     */
    private function getObjectProperties($object): array
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
