<?php

namespace Tourze\Symfony\CronJob\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\Symfony\CronJob\Twig\CronJobExtension;

class ContainerConfigurationTest extends TestCase
{
    public function testServiceConfigurationExists(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        
        // 加载服务配置
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services.yaml');
        
        // 验证服务定义存在
        $this->assertTrue($container->hasDefinition(CronJobExtension::class));
    }
    
    public function testServiceInstantiationWithDefaultInterval(): void
    {
        // 创建模拟的 UrlGeneratorInterface
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        // 不设置环境变量，使用默认值
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
        $extension = new CronJobExtension($urlGenerator);
        
        // 验证默认值是否生效
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');
            
        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 60000;', $result);
    }
    
    public function testServiceInstantiationWithCustomInterval(): void
    {
        // 创建模拟的 UrlGeneratorInterface
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        // 使用自定义值
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '180000';
        $extension = new CronJobExtension($urlGenerator);
        
        // 验证自定义值是否生效
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');
            
        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 180000;', $result);
        
        // 清理环境变量
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
    }
    
    public function testTwigExtensionTag(): void
    {
        $container = new ContainerBuilder();
        
        // 加载服务配置
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services.yaml');
        
        // 获取服务定义
        $definition = $container->getDefinition(CronJobExtension::class);
        
        // 验证 Twig 扩展标签
        $tags = $definition->getTags();
        $this->assertArrayHasKey('twig.extension', $tags);
    }
    
    public function testEnvParameterProcessing(): void
    {
        // 设置环境变量
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '90000';
        
        // 创建服务实例
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new CronJobExtension($urlGenerator);
        
        // 验证环境变量值被使用
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');
            
        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 90000;', $result);
        
        // 清理环境变量
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
    }
    
    public function testEnvParameterDefaultValue(): void
    {
        // 确保环境变量未设置
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
        
        // 创建服务实例，使用默认值
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new CronJobExtension($urlGenerator);
        
        // 验证使用默认值
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');
            
        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 60000;', $result);
    }
}