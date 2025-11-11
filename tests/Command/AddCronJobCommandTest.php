<?php

declare(strict_types=1);

namespace Tourze\Symfony\CronJob\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Tourze\Symfony\CronJob\Command\AddCronJobCommand;
use TiBeN\CrontabManager\CrontabRepository;

/**
 * Add Cron Job Command 测试
 */
#[CoversClass(AddCronJobCommand::class)]
class AddCronJobCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // 初始化命令测试器
        $this->initializeCommand();
    }

    private function initializeCommand(?string $projectDir = null): void
    {
        // 如果未指定项目目录，创建临时目录
        if ($projectDir === null) {
            $projectDir = sys_get_temp_dir() . '/test_project_' . uniqid();
            mkdir($projectDir, 0777, true);
            mkdir($projectDir . '/bin', 0777, true);
            // 创建一个空的 console 文件
            touch($projectDir . '/bin/console');
        }

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        // 创建模拟的 CrontabRepository 工厂
        $crontabRepositoryFactory = function (): CrontabRepository {
            $mockRepository = $this->createMock(CrontabRepository::class);
            $mockRepository->expects($this->once())
                ->method('addJob')
                ->willReturnSelf();
            $mockRepository->expects($this->once())
                ->method('persist')
                ->willReturnSelf();
            return $mockRepository;
        };

        $command = new AddCronJobCommand($kernel, $crontabRepositoryFactory);
        $this->commandTester = new CommandTester($command);
    }

    public function testCommandSignature(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $command = new AddCronJobCommand($kernel);
        $this->assertEquals('cron-job:add-cron-tab', $command->getName());
        $this->assertEquals('注册到Crontab', $command->getDescription());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cron 任务已成功添加到 crontab', $this->commandTester->getDisplay());
    }

    public function testKernelProjectDirIsUsed(): void
    {
        // 创建自定义的 kernel mock 以验证调用
        // 创建临时目录和必需的文件
        $projectDir = sys_get_temp_dir() . '/test_project_' . uniqid();
        mkdir($projectDir, 0777, true);
        mkdir($projectDir . '/bin', 0777, true);
        touch($projectDir . '/bin/console');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getProjectDir')
            ->willReturn($projectDir);

        $crontabRepositoryFactory = function (): CrontabRepository {
            $mockRepository = $this->createMock(CrontabRepository::class);
            $mockRepository->expects($this->once())
                ->method('addJob')
                ->willReturnSelf();
            $mockRepository->expects($this->once())
                ->method('persist')
                ->willReturnSelf();
            return $mockRepository;
        };

        $command = new AddCronJobCommand($kernel, $crontabRepositoryFactory);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cron 任务已成功添加到 crontab', $commandTester->getDisplay());
    }

    /**
     * 测试命令建构函数注入依赖
     */
    public function testConstructorDependencies(): void
    {
        $reflectionClass = new \ReflectionClass(AddCronJobCommand::class);

        // 验证构造函数参数
        $constructor = $reflectionClass->getConstructor();
        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();
        $this->assertCount(2, $parameters);

        // 验证第一个参数是 KernelInterface
        $kernelParam = $parameters[0];
        $this->assertEquals('kernel', $kernelParam->getName());
        $paramType = $kernelParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(KernelInterface::class, $paramType->getName());

        // 验证第二个参数是可选的 callable
        $factoryParam = $parameters[1];
        $this->assertEquals('crontabRepositoryFactory', $factoryParam->getName());
        $this->assertTrue($factoryParam->isOptional());
    }
}