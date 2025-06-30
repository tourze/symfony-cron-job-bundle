<?php

namespace Tourze\Symfony\CronJob\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;
use Tourze\Symfony\CronJob\Service\AttributeControllerLoader;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }

    public function testAutoload(): void
    {
        $collection = $this->loader->autoload();
        
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function testLoad(): void
    {
        $collection = $this->loader->load('test-resource');
        
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertEquals($this->loader->autoload(), $collection);
    }

    public function testSupports(): void
    {
        $this->assertFalse($this->loader->supports('any-resource'));
        $this->assertFalse($this->loader->supports('any-resource', 'any-type'));
    }
}