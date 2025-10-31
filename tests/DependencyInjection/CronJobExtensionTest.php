<?php

namespace Tourze\Symfony\CronJob\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\Symfony\CronJob\DependencyInjection\CronJobExtension;

/**
 * @internal
 */
#[CoversClass(CronJobExtension::class)]
final class CronJobExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $extension = new CronJobExtension();
        $configs = [];

        $extension->load($configs, $container);

        $this->assertGreaterThan(0, count($container->getDefinitions()));
    }
}
