<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Router;

use Psr\Http\Message\ServerRequestInterface;

class DefaultRouter implements Router
{
    /**
     * @var Route[]
     */
    private $routes = [];

    /**
     * @param Route[]|Route $routes
     */
    public function __construct($routes = [])
    {
        if ($routes instanceof Route) {
            $routes = [$routes];
        }

        $this->routes = $routes;
    }

    public function addRoute(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function match(ServerRequestInterface $request): ServerRequestInterface
    {
        foreach ($this->routes as $route) {
            if ($matchedRequest = $route->match($request)) {
                return $matchedRequest;
            }
        }

        return $request;
    }
}
