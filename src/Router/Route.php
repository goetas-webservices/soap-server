<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Router;

use Psr\Http\Message\ServerRequestInterface;

interface Route
{
    public function match(ServerRequestInterface $request): ServerRequestInterface;
}
