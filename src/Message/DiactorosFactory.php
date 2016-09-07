<?php
namespace GoetasWebservices\SoapServices\SoapServer\Message;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class DiactorosFactory implements MessageFactoryInterface
{
    /**
     * @param string $xml
     * @return ResponseInterface
     */
    public function getResponse($xml)
    {
        return new Response(self::toStream($xml));
    }

    /**
     * @param string $str
     * @return Stream
     */
    private static function toStream($str)
    {
        $body = new Stream('php://memory', 'w');
        $body->write($str);
        $body->rewind();
        return $body;
    }
}
