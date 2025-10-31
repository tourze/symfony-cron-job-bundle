<?php

namespace Tourze\Symfony\CronJob\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\Symfony\CronJob\EventListener\CronTerminateListener;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

/**
 * @internal
 */
#[CoversClass(CronTerminateListener::class)]
#[RunTestsInSeparateProcesses]
final class CronTerminateListenerTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 空实现，因为不需要额外的设置
    }

    public function testMainRequestTriggersService(): void
    {
        $container = self::getContainer();

        /*
         * 使用 CronTriggerService 具体类的 Mock，理由：
         * 1. 该服务包含复杂的业务逻辑和外部依赖（锁、缓存、消息队列），测试时需要隔离
         * 2. 测试重点是验证当前组件的行为，而非 CronTriggerService 的内部实现
         * 3. Mock 可以精确控制服务的返回值，测试各种场景包括成功和失败情况
         */
        $cronTriggerService = $this->createMock(CronTriggerService::class);
        $container->set(CronTriggerService::class, $cronTriggerService);

        $listener = $container->get(CronTerminateListener::class);
        self::assertInstanceOf(CronTerminateListener::class, $listener);

        $cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(true)
        ;

        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent(
            $httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function testMainRequestNoTasksTriggered(): void
    {
        $container = self::getContainer();

        $cronTriggerService = $this->createMock(CronTriggerService::class);
        $container->set(CronTriggerService::class, $cronTriggerService);

        $listener = $container->get(CronTerminateListener::class);
        self::assertInstanceOf(CronTerminateListener::class, $listener);

        $cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(false)
        ;

        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent(
            $httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function testOnKernelTerminate(): void
    {
        $container = self::getContainer();

        $cronTriggerService = $this->createMock(CronTriggerService::class);
        $container->set(CronTriggerService::class, $cronTriggerService);

        $listener = $container->get(CronTerminateListener::class);
        self::assertInstanceOf(CronTerminateListener::class, $listener);

        $cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(true)
        ;

        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent(
            $httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function testEventListenerAttribute(): void
    {
        $reflection = new \ReflectionClass(CronTerminateListener::class);
        $attributes = $reflection->getAttributes();

        $this->assertNotEmpty($attributes);

        $found = false;
        foreach ($attributes as $attribute) {
            if ('Symfony\Component\EventDispatcher\Attribute\AsEventListener' === $attribute->getName()) {
                $found = true;
                $args = $attribute->getArguments();
                $this->assertEquals('kernel.terminate', $args['event']);
                $this->assertEquals('onKernelTerminate', $args['method']);
                $this->assertEquals(-1024, $args['priority']);
            }
        }

        $this->assertTrue($found, 'AsEventListener attribute not found');
    }
}
