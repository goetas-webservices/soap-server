<?php
namespace GoetasWebservices\SoapServices\SoapServer\Message;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class GuzzleFactory implements MessageFactoryInterface
{
    /**
     * @param string $xml
     * @return ResponseInterface
     */
    public function getResponse($xml)
    {
        return new Response(200, [], $xml);
    }
}
