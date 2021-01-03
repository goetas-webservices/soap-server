<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\Tests\DependencyInjection;

use GoetasWebservices\SoapServices\SoapServer\Builder\SoapContainerBuilder;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testDI(): void
    {
        $builder = new SoapContainerBuilder(__DIR__ . '/../Fixtures/Soap/config.yml');
        $debugContainer = $builder->getDebugContainer();

        $tempDir = sys_get_temp_dir();

        $builder->dumpContainerForProd($tempDir, $debugContainer);
        $this->assertFileExists($tempDir . '/SoapContainer.php');
    }
}
