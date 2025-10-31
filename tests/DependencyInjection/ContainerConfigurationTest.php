<?php

namespace Tourze\Symfony\CronJob\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\Symfony\CronJob\DependencyInjection\CronJobExtension;
use Tourze\Symfony\CronJob\Service\AttributeControllerLoader;
use Tourze\Symfony\CronJob\Service\CronTriggerService;
use Tourze\Symfony\CronJob\Twig\CronJobExtension as TwigCronJobExtension;

/**
 * @internal
 */
#[CoversClass(CronJobExtension::class)]
final class ContainerConfigurationTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testServiceConfigurationExists(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());

        // 加载服务配置
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services.yaml');

        // 验证服务定义存在
        $this->assertTrue($container->hasDefinition(TwigCronJobExtension::class));
    }

    public function testServiceInstantiationWithDefaultInterval(): void
    {
        // 直接实例化扩展类，不使用容器
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new TwigCronJobExtension($urlGenerator);

        // 验证默认值是否生效
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger')
        ;

        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 60000;', $result);
    }

    public function testServiceInstantiationWithCustomInterval(): void
    {
        // 直接实例化扩展类，不使用容器
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new TwigCronJobExtension($urlGenerator);

        // 使用自定义值
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '180000';

        // 验证自定义值是否生效
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger')
        ;

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

        // 验证 Twig 扩展标签
        $this->assertTrue($container->hasDefinition(TwigCronJobExtension::class));
        $definition = $container->getDefinition(TwigCronJobExtension::class);
        $this->assertTrue($definition->hasTag('twig.extension'));
    }

    public function testEnvParameterProcessing(): void
    {
        // 直接实例化扩展类，不使用容器
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new TwigCronJobExtension($urlGenerator);

        // 设置环境变量
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '90000';

        // 验证环境变量值被使用
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger')
        ;

        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 90000;', $result);

        // 清理环境变量
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
    }

    public function testEnvParameterDefaultValue(): void
    {
        // 直接实例化扩展类，不使用容器
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $extension = new TwigCronJobExtension($urlGenerator);

        // 确保环境变量未设置
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);

        // 验证使用默认值
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger')
        ;

        $result = $extension->renderCronAutoTrigger();
        $this->assertStringContainsString('const interval = 60000;', $result);
    }

    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $extension = new CronJobExtension();
        $extension->load([], $container);

        // 验证容器中有服务定义
        $this->assertTrue($container->has(CronTriggerService::class));
        $this->assertTrue($container->has(AttributeControllerLoader::class));
        $this->assertTrue($container->has(TwigCronJobExtension::class));
    }

    protected function setUp(): void
    {
        // 空实现，因为不需要额外的设置
    }
}
