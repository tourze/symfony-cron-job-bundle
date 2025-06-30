<?php

namespace Tourze\Symfony\CronJob\Tests\EventListener;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\Symfony\CronJob\EventListener\CronTerminateListener;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

class CronTerminateListenerTest extends TestCase
{
    private MockObject|CronTriggerService $cronTriggerService;
    private MockObject|LoggerInterface $logger;
    private MockObject|HttpKernelInterface $httpKernel;

    protected function setUp(): void
    {
        $this->cronTriggerService = $this->createMock(CronTriggerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
    }


    public function test_main_request_triggers_service()
    {
        $listener = new CronTerminateListener(
            $this->cronTriggerService,
            $this->logger
        );

        $this->cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Cron tasks triggered via terminate event');

        $event = new TerminateEvent(
            $this->httpKernel,
            new Request(),
            new Response()
        );

        $listener->onKernelTerminate($event);
    }

    public function test_main_request_no_tasks_triggered()
    {
        $listener = new CronTerminateListener(
            $this->cronTriggerService,
            $this->logger
        );

        $this->cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
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
}