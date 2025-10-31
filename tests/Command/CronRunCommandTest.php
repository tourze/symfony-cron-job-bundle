<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\Symfony\CronJob\Command\CronRunCommand;
use Tourze\Symfony\CronJob\Exception\CronJobException;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

/**
 * @internal
 */
#[CoversClass(CronRunCommand::class)]
#[RunTestsInSeparateProcesses]
final class CronRunCommandTest extends AbstractCommandTestCase
{
    private ?CronTriggerService $cronTriggerService = null;

    private ?CronRunCommand $command = null;

    protected function onSetUp(): void
    {
        // 空实现，因为不需要额外的设置
    }

    protected function getCommandTester(): CommandTester
    {
        $this->initializeServices();
        if (null === $this->command) {
            throw new CronJobException('Command not initialized');
        }

        return new CommandTester($this->command);
    }

    private function initializeServices(): void
    {
        if (null !== $this->command) {
            return;
        }

        $command = self::getService(CronRunCommand::class);
        self::assertInstanceOf(CronRunCommand::class, $command);
        $this->command = $command;

        $cronTriggerService = self::getService(CronTriggerService::class);
        self::assertInstanceOf(CronTriggerService::class, $cronTriggerService);
        $this->cronTriggerService = $cronTriggerService;
    }

    public function testCommandNameConstant(): void
    {
        $this->initializeServices();
        $this->assertEquals('cron:run', CronRunCommand::NAME);
    }

    public function testExecuteReturnsSuccess(): void
    {
        $this->initializeServices();
        if (null === $this->command) {
            throw new CronJobException('Command not initialized');
        }
        // 集成测试：验证命令执行成功
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);

        // 验证服务存在并可以被正确注入
        $this->assertInstanceOf(CronTriggerService::class, $this->cronTriggerService);
    }
}
