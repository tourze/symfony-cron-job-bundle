<?php

namespace Tourze\Symfony\CronJob\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\Symfony\CronJob\Controller\CronTriggerController;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

class CronTriggerControllerTest extends TestCase
{
    private MockObject|CronTriggerService $cronTriggerService;
    private MockObject|LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->cronTriggerService = $this->createMock(CronTriggerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_trigger_success()
    {
        $controller = new CronTriggerController(
            $this->cronTriggerService,
            $this->logger
        );

        $this->cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info');

        $request = new Request();
        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cron tasks triggered successfully', $data['message']);
    }

    public function test_trigger_no_tasks_triggered()
    {
        $controller = new CronTriggerController(
            $this->cronTriggerService,
            $this->logger
        );

        $this->cronTriggerService->expects($this->once())
            ->method('triggerScheduledTasks')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info');

        $request = new Request();
        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('No tasks triggered or already running', $data['message']);
    }

}