<?php

namespace Tourze\Symfony\CronJob\Tests\Twig;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\Symfony\CronJob\Twig\CronJobExtension;
use Twig\TwigFunction;

class CronJobExtensionTest extends TestCase
{
    private CronJobExtension $extension;
    private UrlGeneratorInterface&MockObject $urlGenerator;

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('cron_auto_trigger', $functions[0]->getName());
    }

    public function testRenderCronAutoTriggerWithDefaults(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('cron_trigger', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://example.com/cron/trigger');

        $result = $this->extension->renderCronAutoTrigger();

        $this->assertStringContainsString('<script>', $result);
        $this->assertStringContainsString('http://example.com/cron/trigger', $result);
        $this->assertStringContainsString('const interval = 60000;', $result);
        $this->assertStringContainsString('const debug = false;', $result);
        $this->assertStringContainsString('const maxRetries = 3;', $result);
        $this->assertStringContainsString('const retryDelay = 5000;', $result);
        $this->assertStringContainsString('setInterval(triggerCron, interval);', $result);
    }

    public function testRenderCronAutoTriggerWithCustomInterval(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $this->extension->renderCronAutoTrigger(30000);

        $this->assertStringContainsString('const interval = 30000;', $result);
    }

    public function testRenderCronAutoTriggerWithDebugEnabled(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $this->extension->renderCronAutoTrigger(null, ['debug' => true]);

        $this->assertStringContainsString('const debug = true;', $result);
        $this->assertStringContainsString('console.log(\'[CronJob] Trigger response:\', data);', $result);
        $this->assertStringContainsString('console.error(\'[CronJob] Trigger error:\', error);', $result);
    }

    public function testRenderCronAutoTriggerWithCustomOptions(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $this->extension->renderCronAutoTrigger(null, [
            'maxRetries' => 5,
            'retryDelay' => 10000,
        ]);

        $this->assertStringContainsString('const maxRetries = 5;', $result);
        $this->assertStringContainsString('const retryDelay = 10000;', $result);
    }

    public function testJavaScriptStructure(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $this->extension->renderCronAutoTrigger();

        // 验证立即执行函数
        $this->assertStringContainsString('(function() {', $result);
        $this->assertStringContainsString('})();', $result);

        // 验证 fetch 请求配置
        $this->assertStringContainsString('method: \'POST\'', $result);
        $this->assertStringContainsString('\'Content-Type\': \'application/json\'', $result);
        $this->assertStringContainsString('\'X-Requested-With\': \'XMLHttpRequest\'', $result);
        $this->assertStringContainsString('source: \'auto-trigger\'', $result);

        // 验证错误处理
        $this->assertStringContainsString('if (!response.ok)', $result);
        $this->assertStringContainsString('throw new Error', $result);

        // 验证重试逻辑
        $this->assertStringContainsString('if (retryCount < maxRetries)', $result);
        $this->assertStringContainsString('retryCount++;', $result);
        $this->assertStringContainsString('setTimeout(triggerCron, retryDelay);', $result);

        // 验证初始延迟执行
        $this->assertStringContainsString('setTimeout(triggerCron, 1000);', $result);
    }

    public function testCustomIntervalFromConstructor(): void
    {
        // 测试通过构造函数传递自定义 interval 值
        $customInterval = 120000; // 120 秒
        $customExtension = new CronJobExtension($this->urlGenerator, $customInterval);
        
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $customExtension->renderCronAutoTrigger();
        
        // 验证使用了自定义的 interval 值
        $this->assertStringContainsString('const interval = 120000;', $result);
    }

    public function testCustomIntervalOverridesConstructorValue(): void
    {
        // 测试 renderCronAutoTrigger 方法的参数会覆盖构造函数的值
        $constructorInterval = 120000;
        $methodInterval = 30000;
        $customExtension = new CronJobExtension($this->urlGenerator, $constructorInterval);
        
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://example.com/cron/trigger');

        $result = $customExtension->renderCronAutoTrigger($methodInterval);
        
        // 验证使用了方法参数的 interval 值，而不是构造函数的值
        $this->assertStringContainsString('const interval = 30000;', $result);
    }

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->extension = new CronJobExtension($this->urlGenerator);
    }
}