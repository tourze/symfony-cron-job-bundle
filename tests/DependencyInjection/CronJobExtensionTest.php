<?php

namespace Tourze\Symfony\CronJob\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\Symfony\CronJob\DependencyInjection\CronJobExtension;

class CronJobExtensionTest extends TestCase
{
    public function test_load_registers_services()
    {
        $container = new ContainerBuilder();
        $extension = new CronJobExtension();

        $extension->load([], $container);

        // 验证容器中已注册的服务定义
        $this->assertTrue($container->hasDefinition('Tourze\Symfony\CronJob\Command\CronRunCommand')
            || $container->hasAlias('Tourze\Symfony\CronJob\Command\CronRunCommand'));
        $this->assertTrue($container->hasDefinition('Tourze\Symfony\CronJob\Command\CronStartCommand')
            || $container->hasAlias('Tourze\Symfony\CronJob\Command\CronStartCommand'));
        $this->assertTrue($container->hasDefinition('Tourze\Symfony\CronJob\Command\AddCronJobCommand')
            || $container->hasAlias('Tourze\Symfony\CronJob\Command\AddCronJobCommand'));
    }

    public function test_extension_config_path()
    {
        $extension = new CronJobExtension();
        $reflectionClass = new \ReflectionClass($extension);
        $method = $reflectionClass->getMethod('load');

        $this->assertTrue($method->isPublic());

        // 检查方法源代码以验证配置路径
        $fileName = $reflectionClass->getFileName();
        $fileContent = file_get_contents($fileName);
        $this->assertStringContainsString('/../Resources/config', $fileContent);
        $this->assertStringContainsString('services.yaml', $fileContent);
    }
}
