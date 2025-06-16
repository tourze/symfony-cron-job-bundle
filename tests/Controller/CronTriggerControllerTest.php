<?php

namespace Tourze\Symfony\CronJob\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Tourze\Symfony\CronJob\Controller\CronTriggerController;

class CronTriggerControllerTest extends TestCase
{
    private MockObject|KernelInterface $kernel;
    private MockObject|LockFactory $lockFactory;
    private MockObject|LoggerInterface $logger;
    private MockObject|LockInterface $lock;

    public function test_trigger_disabled_returns_forbidden()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $request = new Request();
        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('HTTP trigger is disabled', $data['message']);
    }

    public function test_trigger_without_auth_returns_unauthorized()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_HTTP_TRIGGER_SECRET'] = 'secret-key';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $request = new Request();
        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Unauthorized', $data['message']);
    }

    public function test_trigger_with_bearer_auth_success()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_HTTP_TRIGGER_SECRET'] = 'secret-key';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        // 模拟 kernel 容器返回空，以避免实际执行命令
        $this->kernel->method('getContainer')
            ->willThrowException(new \RuntimeException('Console application not available'));

        $this->logger->expects($this->exactly(2))
            ->method($this->logicalOr('error', 'info'));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer secret-key');

        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function test_trigger_with_custom_header_auth()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_HTTP_TRIGGER_SECRET'] = 'secret-key';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        // 模拟 kernel 容器返回空，以避免实际执行命令
        $this->kernel->method('getContainer')
            ->willThrowException(new \RuntimeException('Console application not available'));

        $this->logger->expects($this->exactly(2))
            ->method($this->logicalOr('error', 'info'));

        $request = new Request();
        $request->headers->set('X-Cron-Secret', 'secret-key');

        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function test_trigger_lock_already_acquired()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';
        $_ENV['CRON_HTTP_TRIGGER_SECRET'] = 'secret-key';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer secret-key');

        $response = $controller->trigger($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Another trigger is already running', $data['message']);
    }

    public function test_health_endpoint()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $response = $controller->health();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertTrue($data['enabled']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_trigger_without_secret_configured()
    {
        $_ENV['CRON_HTTP_TRIGGER_ENABLED'] = '1';
        // No secret configured

        $controller = new CronTriggerController(
            $this->kernel,
            $this->lockFactory,
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Cron trigger secret not configured');

        $request = new Request();
        $response = $controller->trigger($request);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->lock = $this->createMock(LockInterface::class);

        $this->lockFactory->method('createLock')->willReturn($this->lock);
    }

    protected function tearDown(): void
    {
        // 清理环境变量
        unset($_ENV['CRON_HTTP_TRIGGER_ENABLED'], $_ENV['CRON_HTTP_TRIGGER_SECRET']);
    }
}