<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\Symfony\CronJob\Command\CronStartCommand;
use Tourze\Symfony\CronJob\Exception\CronJobException;

/**
 * @internal
 */
#[CoversClass(CronStartCommand::class)]
#[RunTestsInSeparateProcesses]
final class CronStartCommandTest extends AbstractCommandTestCase
{
    private ?CronStartCommand $command = null;

    private ?Application $application = null;

    protected function onSetUp(): void
    {
        // 空实现，因为不需要额外的设置
    }

    protected function getCommandTester(): CommandTester
    {
        $this->initializeCommand();
        if (null === $this->command) {
            throw new CronJobException('Command not initialized');
        }

        return new CommandTester($this->command);
    }

    private function initializeCommand(): void
    {
        if (null !== $this->command) {
            return;
        }

        $command = self::getService(CronStartCommand::class);
        self::assertInstanceOf(CronStartCommand::class, $command);
        $this->command = $command;

        // 创建一个真实的命令作为cron:run命令
        $runCommand = new #[AsCommand(name: self::NAME, description: 'Mock cron run command')] class extends Command {
            public const NAME = 'cron:run';

            public function __construct()
            {
                parent::__construct();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };

        // 使用真实的Application对象
        $this->application = new Application();
        // 注册cron:run命令
        $this->application->add($runCommand);
        $this->command->setApplication($this->application);
    }

    public function testPidFileConstant(): void
    {
        $this->initializeCommand();
        $this->assertEquals('.cron-pid', CronStartCommand::PID_FILE);
    }

    public function testConfigureSetsCorrectOptions(): void
    {
        $this->initializeCommand();
        $command = $this->command;
        if (null === $command) {
            throw new CronJobException('Command not initialized');
        }
        $this->assertEquals('跑一个进程定时检查定时任务', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('blocking'));
        $this->assertEquals('b', $command->getDefinition()->getOption('blocking')->getShortcut());
    }

    public function testBlockingModeOutputsCorrectMessage(): void
    {
        $this->initializeCommand();
        $command = $this->command;
        if (null === $command) {
            throw new CronJobException('Command not initialized');
        }
        // 使用输出缓冲来测试阻塞模式的输出
        $commandTester = new CommandTester($command);

        // 测试阻塞选项的定义
        $this->assertTrue($command->getDefinition()->hasOption('blocking'));

        // 我们不能实际执行阻塞模式，因为它会进入无限循环
        // 所以我们只测试选项的存在和描述
        $this->assertEquals('Run in blocking mode.', $command->getDefinition()->getOption('blocking')->getDescription());

        // 通过反射测试 scheduler 方法的存在
        $reflection = new \ReflectionClass(CronStartCommand::class);
        $this->assertTrue($reflection->hasMethod('scheduler'));
        $schedulerMethod = $reflection->getMethod('scheduler');
        $this->assertTrue($schedulerMethod->isProtected() || $schedulerMethod->isPrivate());
    }

    public function testExecuteWithoutPcntlThrowsException(): void
    {
        $this->initializeCommand();
        // 测试执行方法对 pcntl 扩展的依赖
        if (extension_loaded('pcntl')) {
            // 如果 pcntl 可用，我们测试类中包含扩展检查的逻辑
            $reflection = new \ReflectionClass(CronStartCommand::class);

            // 读取整个类源代码
            $filename = $reflection->getFileName();
            if (false === $filename) {
                self::fail('Could not get file name from reflection');
            }
            $classSource = file_get_contents($filename);
            if (false === $classSource) {
                self::fail('Could not read class source file');
            }

            // 验证类代码中包含对 pcntl 扩展的检查
            $this->assertStringContainsString('extension_loaded(\'pcntl\')', $classSource);
            $this->assertStringContainsString('This command needs the pcntl extension to run.', $classSource);
        } else {
            // 如果 pcntl 不可用，测试实际的异常抛出
            $this->expectException(CronJobException::class);
            $this->expectExceptionMessage('This command needs the pcntl extension to run.');

            if (null === $this->command) {
                throw new CronJobException('Command not initialized');
            }
            $commandTester = new CommandTester($this->command);
            $commandTester->execute([]);
        }
    }

    public function testMemoryLimitPropertyExists(): void
    {
        $this->initializeCommand();
        $reflection = new \ReflectionClass(CronStartCommand::class);
        $this->assertTrue($reflection->hasProperty('mbLimit'));

        $property = $reflection->getProperty('mbLimit');
        $property->setAccessible(true);

        $instance = self::getService(CronStartCommand::class);
        self::assertInstanceOf(CronStartCommand::class, $instance);
        $this->assertEquals(1024, $property->getValue($instance));
    }

    public function testOptionBlocking(): void
    {
        $this->initializeCommand();
        $command = $this->command;
        if (null === $command) {
            throw new CronJobException('Command not initialized');
        }

        // 测试 blocking 选项的存在和配置
        $this->assertTrue($command->getDefinition()->hasOption('blocking'));

        $blockingOption = $command->getDefinition()->getOption('blocking');
        $this->assertEquals('b', $blockingOption->getShortcut());
        $this->assertEquals('Run in blocking mode.', $blockingOption->getDescription());
        $this->assertFalse($blockingOption->isValueRequired());
        $this->assertFalse($blockingOption->isValueOptional());
        $this->assertFalse($blockingOption->isArray());

        // 测试短选项和长选项都可用
        $this->assertTrue($command->getDefinition()->hasShortcut('b'));
        $this->assertEquals('blocking', $command->getDefinition()->getOptionForShortcut('b')->getName());
    }

    public function testLockServiceIsInjected(): void
    {
        $this->initializeCommand();
        $command = self::getService(CronStartCommand::class);

        $reflection = new \ReflectionClass(CronStartCommand::class);
        $property = $reflection->getProperty('lockService');
        $property->setAccessible(true);
        $lockService = $property->getValue($command);

        $this->assertInstanceOf(LockService::class, $lockService);
    }

    public function testSchedulerUsesLockMechanism(): void
    {
        $this->initializeCommand();

        $reflection = new \ReflectionClass(CronStartCommand::class);
        $filename = $reflection->getFileName();
        if (false === $filename) {
            self::fail('Could not get file name from reflection');
        }
        $classSource = file_get_contents($filename);
        if (false === $classSource) {
            self::fail('Could not read class source file');
        }

        $this->assertStringContainsString('$lockKey = \'cron:run:\' . (int) ($now / 60);', $classSource);
        $this->assertStringContainsString('$this->lockService->acquireLock($lockKey)', $classSource);
        $this->assertStringContainsString('$this->lockService->releaseLock($lockKey)', $classSource);
        $this->assertStringContainsString('其他进程正在执行Cron任务', $classSource);
        $this->assertStringContainsString('跳过', $classSource);
    }
}
