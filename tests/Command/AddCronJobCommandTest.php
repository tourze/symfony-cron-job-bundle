<?php

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
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
        // 跳过实际执行，因为需要系统crontab访问
        $this->markTestSkipped('需要系统访问权限，跳过实际执行');
    }

    public function test_kernel_project_dir_is_used()
    {
        // 验证内核被调用以获取项目目录
        $this->kernel->expects($this->once())
            ->method('getProjectDir');

        // 跳过实际执行，因为需要系统crontab访问
        $this->markTestSkipped('需要系统访问权限，跳过实际执行');
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
