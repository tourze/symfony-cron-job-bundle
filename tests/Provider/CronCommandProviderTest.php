<?php

namespace Tourze\Symfony\CronJob\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;

/**
 * @internal
 */
#[CoversClass(CronCommandProvider::class)]
#[RunTestsInSeparateProcesses]
final class CronCommandProviderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 不需要额外的设置
    }

    public function testTagNameConstantValue(): void
    {
        $this->assertEquals('cron-command-provider', CronCommandProvider::TAG_NAME);
    }

    public function testInterfaceHasGetCommandsMethod(): void
    {
        $reflectionClass = new \ReflectionClass(CronCommandProvider::class);
        $this->assertTrue($reflectionClass->hasMethod('getCommands'));

        $method = $reflectionClass->getMethod('getCommands');
        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        if ($returnType instanceof \ReflectionNamedType) {
            $this->assertEquals('iterable', $returnType->getName());
        } else {
            self::fail('Expected ReflectionNamedType for return type');
        }
    }

    public function testIsAutoconfiguredWithTag(): void
    {
        $reflectionClass = new \ReflectionClass(CronCommandProvider::class);
        $attributes = $reflectionClass->getAttributes();

        $this->assertGreaterThan(0, count($attributes));

        $found = false;
        foreach ($attributes as $attribute) {
            if ('Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag' === $attribute->getName()) {
                $arguments = $attribute->getArguments();
                $this->assertCount(1, $arguments);
                $this->assertEquals(CronCommandProvider::TAG_NAME, $arguments['name']);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Interface should be tagged with AutoconfigureTag');
    }
}
