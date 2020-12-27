<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Router;

use Psr\Http\Message\ServerRequestInterface;

class CallbackRoute implements Route
{
    /**
     * @var callable
     */
    private $matcher;

    /**
     * @var callable
     */
    private $controller;

    public function __construct(?callable $matcher, callable $controller)
    {
        $this->matcher = $matcher;
        $this->controller = $controller;
    }

    public function match(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!$this->matcher || call_user_func($this->matcher, $request)) {
            return $request->withAttribute('_controller', $this->controller);
        }

        return $request;
    }
}
