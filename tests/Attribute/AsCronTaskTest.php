<?php

namespace Tourze\Symfony\CronJob\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

/**
 * @internal
 */
#[CoversClass(AsCronTask::class)]
final class AsCronTaskTest extends TestCase
{
    public function testExtendsAutoconfigureTag(): void
    {
        $attribute = new AsCronTask();
        $this->assertInstanceOf(AutoconfigureTag::class, $attribute);
    }

    public function testDefaultConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(AsCronTask::class);
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            self::fail('Constructor should exist for AsCronTask class');
        }
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);

        // 检查 expression 参数
        $this->assertEquals('expression', $parameters[0]->getName());
        $this->assertEquals('* * * * *', $parameters[0]->getDefaultValue());

        // 检查 lockTtl 参数
        $this->assertEquals('lockTtl', $parameters[1]->getName());
        $this->assertNull($parameters[1]->getDefaultValue());
    }

    public function testConstructorSetsAllParameters(): void
    {
        $cronExpression = '0 0 * * *';
        $lockTtl = 1800;

        $attribute = new AsCronTask($cronExpression, $lockTtl);

        // 验证标签名称常量
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);

        // 通过反射获取父类的 tags 属性来验证参数
        $reflection = new \ReflectionClass($attribute);
        $parentReflection = $reflection->getParentClass();
        if (false === $parentReflection) {
            self::fail('Parent class should exist for AsCronTask');
        }
        $tagsProperty = $parentReflection->getProperty('tags');
        $tagsProperty->setAccessible(true);
        $tags = $tagsProperty->getValue($attribute);

        // 类型安全验证和断言
        $this->assertIsArray($tags);
        $this->assertArrayHasKey(0, $tags);
        $this->assertIsArray($tags[0]);
        $this->assertArrayHasKey(AsCronTask::TAG_NAME, $tags[0]);
        $tagData = $tags[0][AsCronTask::TAG_NAME];
        $this->assertIsArray($tagData);
        $this->assertArrayHasKey('expression', $tagData);
        $this->assertArrayHasKey('lockTtl', $tagData);

        $this->assertEquals($cronExpression, $tagData['expression']);
        $this->assertEquals($lockTtl, $tagData['lockTtl']);
    }

    public function testTagNameConstantValue(): void
    {
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);
    }
}
