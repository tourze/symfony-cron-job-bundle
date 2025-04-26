<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\Symfony\CronJob\Command\CronRunCommand;
use Tourze\Symfony\CronJob\Command\CronStartCommand;

class CronStartCommandTest extends TestCase
{
    private MockObject|ContainerInterface $container;
    private CronStartCommand $command;
    private Application $application;
    private MockObject|Command $cronRunCommand;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->method('get')
            ->with('services_resetter')
            ->willReturn(new class {
                public function reset(): void
                {
                    // 模拟重置服务
                }
            });

        $this->command = new CronStartCommand($this->container);

        $this->cronRunCommand = $this->createMock(Command::class);
        $this->cronRunCommand->method('getName')
            ->willReturn(CronRunCommand::NAME);

        $this->application = new Application();
        $this->application->add($this->cronRunCommand);
        $this->command->setApplication($this->application);
    }

    public function test_pid_file_constant()
    {
        $this->assertEquals('.cron-pid', CronStartCommand::PID_FILE);
    }

    public function test_configure_sets_correct_options()
    {
        $this->assertEquals('Starts cron scheduler', $this->command->getDescription());
        $this->assertTrue($this->command->getDefinition()->hasOption('blocking'));
        $this->assertEquals('b', $this->command->getDefinition()->getOption('blocking')->getShortcut());
    }

    public function test_blocking_mode_executes_scheduler_directly()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension is required for this test.');
        }

        // 创建一个可以被控制的CronStartCommand
        $commandMock = $this->getMockBuilder(CronStartCommand::class)
            ->setConstructorArgs([$this->container])
            ->onlyMethods(['scheduler'])
            ->getMock();

        // 确保scheduler方法被调用一次，带有适当的参数
        $commandMock->expects($this->once())
            ->method('scheduler')
            ->with(
                $this->isInstanceOf(OutputInterface::class),
                $this->isNull()
            );

        $commandTester = new CommandTester($commandMock);
        $exitCode = $commandTester->execute(['--blocking' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('blocking mode', $commandTester->getDisplay());
    }

    public function test_execute_without_pcntl_throws_exception()
    {
        // 如果pcntl扩展可用，就跳过这个测试
        if (extension_loaded('pcntl')) {
            $this->markTestSkipped('This test is only for environments without pcntl extension.');
            return;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This command needs the pcntl extension to run.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);
    }

    public function test_memory_limit_property_exists()
    {
        $reflection = new \ReflectionClass(CronStartCommand::class);
        $this->assertTrue($reflection->hasProperty('mbLimit'));

        $property = $reflection->getProperty('mbLimit');
        $property->setAccessible(true);

        $instance = new CronStartCommand($this->container);
        $this->assertEquals(1024, $property->getValue($instance));
    }
}
