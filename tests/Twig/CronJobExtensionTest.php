<?php

namespace Tourze\Symfony\CronJob\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\Symfony\CronJob\Twig\CronJobExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(CronJobExtension::class)]
#[RunTestsInSeparateProcesses]
final class CronJobExtensionTest extends AbstractIntegrationTestCase
{
    private CronJobExtension $extension;

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('cron_auto_trigger', $functions[0]->getName());
    }

    public function testRenderCronAutoTriggerWithDefaults(): void
    {
        $result = $this->extension->renderCronAutoTrigger();

        $this->assertStringContainsString('<script>', $result);
        $this->assertStringContainsString('http://localhost/cron/trigger', $result);
        $this->assertStringContainsString('const interval = 60000;', $result);
        $this->assertStringContainsString('const debug = false;', $result);
        $this->assertStringContainsString('const maxRetries = 3;', $result);
        $this->assertStringContainsString('const retryDelay = 5000;', $result);
        $this->assertStringContainsString('setInterval(triggerCron, interval);', $result);
    }

    public function testRenderCronAutoTriggerWithCustomInterval(): void
    {
        $result = $this->extension->renderCronAutoTrigger(30000);

        $this->assertStringContainsString('const interval = 30000;', $result);
    }

    public function testRenderCronAutoTriggerWithDebugEnabled(): void
    {
        $result = $this->extension->renderCronAutoTrigger(null, ['debug' => true]);

        $this->assertStringContainsString('const debug = true;', $result);
        $this->assertStringContainsString('console.log(\'[CronJob] Trigger response:\', data);', $result);
        $this->assertStringContainsString('console.error(\'[CronJob] Trigger error:\', error);', $result);
    }

    public function testRenderCronAutoTriggerWithCustomOptions(): void
    {
        $result = $this->extension->renderCronAutoTrigger(null, [
            'maxRetries' => 5,
            'retryDelay' => 10000,
        ]);

        $this->assertStringContainsString('const maxRetries = 5;', $result);
        $this->assertStringContainsString('const retryDelay = 10000;', $result);
    }

    public function testJavaScriptStructure(): void
    {
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

    public function testCustomIntervalFromEnv(): void
    {
        // 测试通过环境变量设置自定义 interval 值
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '120000'; // 120 秒

        // 从容器获取新的扩展实例以读取环境变量
        $customExtension = self::getService(CronJobExtension::class);

        $result = $customExtension->renderCronAutoTrigger();

        // 验证使用了环境变量的 interval 值
        $this->assertStringContainsString('const interval = 120000;', $result);

        // 清理环境变量
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
    }

    public function testCustomIntervalOverridesEnvValue(): void
    {
        // 测试 renderCronAutoTrigger 方法的参数会覆盖环境变量的值
        $_ENV['CRON_AUTO_TRIGGER_INTERVAL'] = '120000';
        $methodInterval = 30000;

        // 从容器获取新的扩展实例以读取环境变量
        $customExtension = self::getService(CronJobExtension::class);

        $result = $customExtension->renderCronAutoTrigger($methodInterval);

        // 验证使用了方法参数的 interval 值，而不是环境变量的值
        $this->assertStringContainsString('const interval = 30000;', $result);

        // 清理环境变量
        unset($_ENV['CRON_AUTO_TRIGGER_INTERVAL']);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务
        $this->extension = self::getService(CronJobExtension::class);
    }
}
