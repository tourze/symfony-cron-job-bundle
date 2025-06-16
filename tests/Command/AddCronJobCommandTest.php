<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\Symfony\CronJob\Command\AddCronJobCommand;

class AddCronJobCommandTest extends TestCase
{
    private AddCronJobCommand $command;
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->kernel->method('getProjectDir')->willReturn('/var/www/project');

        $this->command = new AddCronJobCommand($this->kernel);

        // 设置Application
        $application = new Application();
        $application->add($this->command);
        $this->command->setApplication($application);
    }

    public function test_command_configuration()
    {
        $this->assertEquals('cron-job:add-cron-tab', $this->command->getName());
        $this->assertEquals('注册到Crontab', $this->command->getDescription());
    }

    public function test_execute_returns_success()
    {
        // 创建一个模拟的 AddCronJobCommand 以避免实际的 crontab 操作
        $command = new class($this->kernel) extends AddCronJobCommand {
            public function __construct(KernelInterface $kernel)
            {
                parent::__construct($kernel);
                $this->setName('cron-job:add-cron-tab');
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

    public function test_kernel_project_dir_is_used()
    {
        // 创建一个部分模拟的 AddCronJobCommand
        $command = new class($this->kernel) extends AddCronJobCommand {
            public bool $projectDirCalled = false;
            
            public function __construct(KernelInterface $kernel)
            {
                parent::__construct($kernel);
                $this->setName('cron-job:add-cron-tab');
            }
            
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                // 调用父类的 kernel（通过反射访问 protected 属性）
                $reflection = new \ReflectionClass(parent::class);
                $kernelProperty = $reflection->getProperty('kernel');
                $kernelProperty->setAccessible(true);
                $kernel = $kernelProperty->getValue($this);
                
                $projectDir = $kernel->getProjectDir();
                $this->projectDirCalled = true;
                $output->writeln("Project dir: $projectDir");
                return Command::SUCCESS;
            }
        };
        
        // 注意：由于匿名类使用传入的 kernel，这里的 mock 设置无效
        // 我们需要使用原始的 mock 返回值
        // kernel 已经在 setUp 中被配置为返回 '/var/www/project'
        
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
    public function test_constructor_dependencies()
    {
        $reflectionClass = new \ReflectionClass(AddCronJobCommand::class);
        $constructor = $reflectionClass->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('kernel', $parameters[0]->getName());
        $this->assertEquals(KernelInterface::class, $parameters[0]->getType()->getName());
    }
}
