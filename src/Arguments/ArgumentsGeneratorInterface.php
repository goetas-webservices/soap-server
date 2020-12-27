<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Arguments;

interface ArgumentsGeneratorInterface
{
    public function expandArguments(object $envelope): array;
}
