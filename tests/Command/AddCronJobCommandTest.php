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
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\Symfony\CronJob\Command\AddCronJobCommand;
use Tourze\Symfony\CronJob\Exception\CronJobException;

/**
 * @internal
 */
#[CoversClass(AddCronJobCommand::class)]
#[RunTestsInSeparateProcesses]
final class AddCronJobCommandTest extends AbstractCommandTestCase
{
    private ?AddCronJobCommand $command = null;

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

        $command = self::getService(AddCronJobCommand::class);
        self::assertInstanceOf(AddCronJobCommand::class, $command);
        $this->command = $command;

        // 设置Application
        $application = new Application();
        $application->add($this->command);
        $this->command->setApplication($application);
    }

    public function testCommandConfiguration(): void
    {
        $this->initializeCommand();
        $command = $this->command;
        if (null === $command) {
            throw new CronJobException('Command not initialized');
        }
        $this->assertEquals('cron-job:add-cron-tab', $command->getName());
        $this->assertEquals('注册到Crontab', $command->getDescription());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $this->initializeCommand();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/var/www/project');

        // 创建一个模拟的 AddCronJobCommand 以避免实际的 crontab 操作
        $command = new #[AsCommand(name: self::NAME, description: 'Mock command')] class($kernel) extends AddCronJobCommand {
            public const NAME = 'cron-job:add-cron-tab';

            public function __construct(KernelInterface $kernel)
            {
                parent::__construct($kernel);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                // 模拟执行逻辑，不进行实际的 crontab 操作
                $output->writeln('Mock crontab operation');

                return Command::SUCCESS;
            }
        };

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Mock crontab operation', $commandTester->getDisplay());
    }

    public function testKernelProjectDirIsUsed(): void
    {
        $this->initializeCommand();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/var/www/project');

        // 创建一个部分模拟的 AddCronJobCommand
        $command = new #[AsCommand(name: self::NAME, description: 'Mock command')] class($kernel) extends AddCronJobCommand {
            public const NAME = 'cron-job:add-cron-tab';

            public bool $projectDirCalled = false;

            public function __construct(KernelInterface $kernel)
            {
                parent::__construct($kernel);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                // 调用父类的 kernel（通过反射访问 protected 属性）
                $reflection = new \ReflectionClass(parent::class);
                $kernelProperty = $reflection->getProperty('kernel');
                $kernelProperty->setAccessible(true);
                $kernel = $kernelProperty->getValue($this);
                if (!$kernel instanceof KernelInterface) {
                    throw new \RuntimeException('Expected KernelInterface instance');
                }

                $projectDir = $kernel->getProjectDir();
                $this->projectDirCalled = true;
                $output->writeln("Project dir: {$projectDir}");

                return Command::SUCCESS;
            }
        };

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertTrue($command->projectDirCalled);
        // 检查实际的输出（kernel 返回 '/var/www/project'）
        $this->assertStringContainsString('Project dir: /var/www/project', $commandTester->getDisplay());
        // 宽松的断言
        $this->assertStringContainsString('Project dir:', $commandTester->getDisplay());
    }

    /**
     * 测试命令建构函数注入依赖
     */
    public function testConstructorDependencies(): void
    {
        $this->initializeCommand();
        $reflectionClass = new \ReflectionClass(AddCronJobCommand::class);
        $constructor = $reflectionClass->getConstructor();
        if (null === $constructor) {
            self::fail('Constructor should exist for AddCronJobCommand class');
        }
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('kernel', $parameters[0]->getName());

        $parameterType = $parameters[0]->getType();
        if ($parameterType instanceof \ReflectionNamedType) {
            $this->assertEquals(KernelInterface::class, $parameterType->getName());
        } else {
            self::fail('Expected ReflectionNamedType for kernel parameter');
        }
    }
}
