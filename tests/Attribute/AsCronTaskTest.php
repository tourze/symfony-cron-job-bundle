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

    public function test_default_constructor_parameters()
    {
        $reflection = new \ReflectionClass(AsCronTask::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        
        // 检查 expression 参数
        $this->assertEquals('expression', $parameters[0]->getName());
        $this->assertEquals('* * * * *', $parameters[0]->getDefaultValue());
        
        // 检查 lockTtl 参数
        $this->assertEquals('lockTtl', $parameters[1]->getName());
        $this->assertNull($parameters[1]->getDefaultValue());
    }

    public function test_constructor_sets_all_parameters()
    {
        $cronExpression = '0 0 * * *';
        $lockTtl = 1800;
        
        $attribute = new AsCronTask($cronExpression, $lockTtl);

        // 验证标签名称常量
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);

        // 通过反射获取父类的 tags 属性来验证参数
        $reflection = new \ReflectionClass($attribute);
        $parentReflection = $reflection->getParentClass();
        $tagsProperty = $parentReflection->getProperty('tags');
        $tagsProperty->setAccessible(true);
        $tags = $tagsProperty->getValue($attribute);
        
        $this->assertEquals($cronExpression, $tags[0][AsCronTask::TAG_NAME]['expression']);
        $this->assertEquals($lockTtl, $tags[0][AsCronTask::TAG_NAME]['lockTtl']);
    }

    public function test_tag_name_constant_value()
    {
        $this->assertEquals('tourze.job-cron.schedule', AsCronTask::TAG_NAME);
    }
}
