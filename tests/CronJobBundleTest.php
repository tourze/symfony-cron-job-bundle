<?php

namespace Tourze\Symfony\CronJob\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\AsyncCommandBundle\AsyncCommandBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\Symfony\CronJob\CronJobBundle;

class CronJobBundleTest extends TestCase
{
    public function test_bundle_implements_dependency_interface()
    {
        $this->assertInstanceOf(BundleDependencyInterface::class, new CronJobBundle());
    }

    public function test_get_bundle_dependencies_returns_async_bundle()
    {
        $dependencies = CronJobBundle::getBundleDependencies();

        $this->assertArrayHasKey(AsyncCommandBundle::class, $dependencies);
        $this->assertEquals(['all' => true], $dependencies[AsyncCommandBundle::class]);
    }
}
