<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\Symfony\CronJob\Command\CronRunCommand;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

class CronRunCommandTest extends TestCase
{
    private MockObject|CronTriggerService $cronTriggerService;
    private CronRunCommand $command;

    protected function setUp(): void
    {
        $this->cronTriggerService = $this->createMock(CronTriggerService::class);

        $this->command = new CronRunCommand(
            $this->cronTriggerService
        );
    }

    public function test_command_name_constant()
    {
        $this->assertEquals('cron:run', CronRunCommand::NAME);
    }

    public function test_execute_returns_success()
    {
        $this->cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks');

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

}
