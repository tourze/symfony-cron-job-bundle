<?php

namespace Tourze\Symfony\CronJob\Tests\EventListener;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Tourze\Symfony\CronJob\EventListener\CronTerminateListener;

class CronTerminateListenerTest extends TestCase
{
    private MockObject|KernelInterface $kernel;
    private MockObject|LockFactory $lockFactory;
    private MockObject|LoggerInterface $logger;
    private MockObject|LockInterface $lock;
    private MockObject|HttpKernelInterface $httpKernel;

    public function test_disabled_listener_does_nothing()
    {
        $_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] = '';

        $listener = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->never())->method('acquire');

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function test_sub_request_ignored()
    {
        // 跳过此测试，因为我们无法模拟 final 类 TerminateEvent
        // 在实际环境中，子请求会被 isMainRequest() 检查过滤
        $this->markTestSkipped('Cannot test sub-request filtering due to final TerminateEvent class');
    }

    public function test_probability_zero_never_executes()
    {
        $_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY'] = '0.0';

        $listener = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->never())->method('acquire');

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function test_probability_one_always_executes()
    {
        $_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY'] = '1.0';

        // 重置静态变量以确保时间检查通过
        $reflection = new \ReflectionClass(CronTerminateListener::class);
        $property = $reflection->getProperty('lastExecutionTime');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $listener = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        // 模拟执行失败以避免实际命令执行
        $this->kernel->method('getContainer')
            ->willThrowException(new \RuntimeException('Test exception'));

        $this->logger->expects($this->atLeastOnce())
            ->method($this->logicalOr('info', 'error'));

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function test_lock_not_acquired_skips_execution()
    {
        $_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY'] = '1.0';

        // 重置静态变量以确保时间检查通过
        $reflection = new \ReflectionClass(CronTerminateListener::class);
        $property = $reflection->getProperty('lastExecutionTime');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $listener = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $this->logger->expects($this->never())
            ->method('info');

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function test_time_interval_check()
    {
        $_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY'] = '1.0';

        // 重置静态变量
        $reflection = new \ReflectionClass(CronTerminateListener::class);
        $property = $reflection->getProperty('lastExecutionTime');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $listener = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        // 第一次执行应该成功
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);

        // 立即再次执行应该被时间间隔检查阻止
        $listener2 = new CronTerminateListener(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->never())
            ->method('acquire');

        $listener2->onKernelTerminate($event);
    }

    public function test_event_listener_attribute()
    {
        $reflection = new \ReflectionClass(CronTerminateListener::class);
        $attributes = $reflection->getAttributes();

        $this->assertNotEmpty($attributes);

        $found = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Symfony\Component\EventDispatcher\Attribute\AsEventListener') {
                $found = true;
                $args = $attribute->getArguments();
                $this->assertEquals('kernel.terminate', $args['event']);
                $this->assertEquals('onKernelTerminate', $args['method']);
                $this->assertEquals(-1024, $args['priority']);
            }
        }

        $this->assertTrue($found, 'AsEventListener attribute not found');
    }

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->lock = $this->createMock(LockInterface::class);
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);

        $this->lockFactory->method('createLock')->willReturn($this->lock);

        // 清理环境变量
        unset($_ENV['CRON_TERMINATE_TRIGGER_ENABLED'], $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY']);
    }

    protected function tearDown(): void
    {
        // 清理环境变量
        unset($_ENV['CRON_TERMINATE_TRIGGER_ENABLED'], $_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY']);
    }
}