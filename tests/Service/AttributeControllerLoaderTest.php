<?php

namespace Tourze\Symfony\CronJob\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\Symfony\CronJob\Service\AttributeControllerLoader;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private ?AttributeControllerLoader $loader = null;

    protected function onSetUp(): void
    {
        // 空实现，因为不需要额外的设置
    }

    private function initializeLoader(): void
    {
        if (null !== $this->loader) {
            return;
        }

        $loader = self::getService(AttributeControllerLoader::class);
        self::assertInstanceOf(AttributeControllerLoader::class, $loader);
        $this->loader = $loader;
    }

    public function testAutoload(): void
    {
        $this->initializeLoader();
        if (null === $this->loader) {
            self::fail('Loader not initialized');
        }
        $collection = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function testLoad(): void
    {
        $this->initializeLoader();
        $loader = $this->loader;
        if (null === $loader) {
            self::fail('Loader not initialized');
        }
        $collection = $loader->load('test-resource');

        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertEquals($loader->autoload(), $collection);
    }

    public function testSupports(): void
    {
        $this->initializeLoader();
        $loader = $this->loader;
        if (null === $loader) {
            self::fail('Loader not initialized');
        }
        $this->assertFalse($loader->supports('any-resource'));
        $this->assertFalse($loader->supports('any-resource', 'any-type'));
    }
}
