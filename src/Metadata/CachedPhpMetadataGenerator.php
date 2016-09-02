<?php
namespace GoetasWebservices\SoapServices\Metadata;

use Psr\Cache\CacheItemPoolInterface;

class CachedPhpMetadataGenerator implements PhpMetadataGeneratorInterface
{
    /**
     * @var PhpMetadataGeneratorInterface
     */
    private $generator;
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(PhpMetadataGeneratorInterface $generator, CacheItemPoolInterface $cache)
    {
        $this->generator = $generator;
        $this->cache = $cache;
    }

    public function addNamespace($ns, $phpNamespace)
    {
        $this->generator->addNamespace($ns, $phpNamespace);
    }

    public function generateServices($wsdl)
    {
        $item = $this->cache->getItem(sha1($wsdl));
        if (!$item->isHit()) {
            $services = $this->generator->generateServices($wsdl);
            $item->set($services);
            $this->cache->save($item);
        }
        return $item->get();
    }
}

