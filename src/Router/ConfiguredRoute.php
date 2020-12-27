<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Router;

use Psr\Http\Message\ServerRequestInterface;

class ConfiguredRoute implements Route
{
    /**
     * @var array
     */
    private $props;

    /**
     * @var string
     */
    private $controller;

    /**
     * @param object|callable|string $controller
     * @param array $props
     */
    public function __construct($controller, array $props = [])
    {
        $this->props = $props;
        $this->controller = $controller;
    }

    public function match(ServerRequestInterface $request): ServerRequestInterface
    {
        $val = $request->getAttribute('_soap_operation');

        foreach ($this->props as $propName => $propValue) {
            if (!isset($val[$propName]) || $val[$propName] !== $propValue) {
                return $request;
            }
        }

        if (is_object($this->controller) && !is_callable($this->controller)) {
            return $request->withAttribute('_controller', \Closure::fromCallable([$this->controller, $val['method']]));
        } elseif (
            is_string($this->controller)
            && preg_match('/^(.+)::(.+)$/', $this->controller, $mch)
            && '*' === $mch[2]
        ) {
            return $request->withAttribute('_controller', $mch[1] . '::' . $val['method']);
        }

        return $request->withAttribute('_controller', $this->controller);
    }
}
