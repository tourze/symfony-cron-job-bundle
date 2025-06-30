<?php

namespace Tourze\Symfony\CronJob\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;

class CronCommandProviderTest extends TestCase
{
    public function test_tag_name_constant_value()
    {
        $this->assertEquals('cron-command-provider', CronCommandProvider::TAG_NAME);
    }

    public function test_interface_has_get_commands_method()
    {
        $reflectionClass = new \ReflectionClass(CronCommandProvider::class);
        $this->assertTrue($reflectionClass->hasMethod('getCommands'));

        $method = $reflectionClass->getMethod('getCommands');
        $this->assertTrue($method->isPublic());
        
        $returnType = $method->getReturnType();
        if ($returnType instanceof \ReflectionNamedType) {
            $this->assertEquals('iterable', $returnType->getName());
        } else {
            $this->fail('Expected ReflectionNamedType for return type');
        }
    }

    public function test_is_autoconfigured_with_tag()
    {
        $reflectionClass = new \ReflectionClass(CronCommandProvider::class);
        $attributes = $reflectionClass->getAttributes();

        $this->assertGreaterThan(0, count($attributes));

        $found = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag') {
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
