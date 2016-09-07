<?php

namespace GoetasWebservices\SoapServices\SoapServer\Arguments;

interface ArgumentsGeneratorInterface
{
    /**
     * @param mixed $envelope
     * @param callable|null $callable
     * @return array
     */
    public function expandArguments($envelope, callable $callable = null);
}
