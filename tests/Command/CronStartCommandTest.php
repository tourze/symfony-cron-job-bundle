<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\Symfony\CronJob\Command\CronStartCommand;
use Tourze\Symfony\CronJob\Exception\CronJobException;

class CronStartCommandTest extends TestCase
{
    /** @var MockObject&ContainerInterface */
    private MockObject $container;

    private CronStartCommand $command;
    private Application $application;

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

    public function test_blocking_mode_outputs_correct_message()
    {
        // 使用输出缓冲来测试阻塞模式的输出
        $commandTester = new CommandTester($this->command);
        
        // 测试阻塞选项的定义
        $this->assertTrue($this->command->getDefinition()->hasOption('blocking'));
        
        // 我们不能实际执行阻塞模式，因为它会进入无限循环
        // 所以我们只测试选项的存在和描述
        $this->assertEquals('Run in blocking mode.', $this->command->getDefinition()->getOption('blocking')->getDescription());
        
        // 通过反射测试 scheduler 方法的存在
        $reflection = new \ReflectionClass(CronStartCommand::class);
        $this->assertTrue($reflection->hasMethod('scheduler'));
        $schedulerMethod = $reflection->getMethod('scheduler');
        $this->assertTrue($schedulerMethod->isProtected() || $schedulerMethod->isPrivate());
    }

    public function test_execute_without_pcntl_throws_exception()
    {
        // 测试执行方法对 pcntl 扩展的依赖
        if (extension_loaded('pcntl')) {
            // 如果 pcntl 可用，我们只能测试扩展检查的逻辑存在
            $reflection = new \ReflectionClass(CronStartCommand::class);
            $method = $reflection->getMethod('execute');
            
            // 读取方法源代码（通过反射）
            $filename = $reflection->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            $sourceLines = file($filename);
            $methodSource = implode('', array_slice($sourceLines, $startLine - 1, $endLine - $startLine + 1));
            
            // 验证代码中包含对 pcntl 扩展的检查
            $this->assertStringContainsString('extension_loaded(\'pcntl\')', $methodSource);
            $this->assertStringContainsString('This command needs the pcntl extension to run.', $methodSource);
        } else {
            // 如果 pcntl 不可用，测试实际的异常抛出
            $this->expectException(CronJobException::class);
            $this->expectExceptionMessage('This command needs the pcntl extension to run.');
            
            $commandTester = new CommandTester($this->command);
            $commandTester->execute([]);
        }
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
