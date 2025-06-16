<?php

namespace Tourze\Symfony\CronJob\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;
use Tourze\Symfony\CronJob\Request\CommandRequest;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

class CronTriggerServiceTest extends TestCase
{
    private MockObject|MessageBusInterface $messageBus;
    private MockObject|LoggerInterface $logger;
    private MockObject|LockService $lockService;
    private MockObject|CacheItemPoolInterface $cache;
    private MockObject|CacheItemInterface $cacheItem;
    private CronTriggerService $service;

    public function test_trigger_with_no_tasks_returns_true()
    {
        $result = $this->service->triggerScheduledTasks();
        $this->assertTrue($result);
    }

    public function test_trigger_with_lock_failure_returns_false()
    {
        // 配置 LockService 抛出异常，模拟获取锁失败
        $this->lockService->expects($this->once())
            ->method('acquireLock')
            ->willThrowException(new \RuntimeException('Cannot acquire lock'));

        $result = $this->service->triggerScheduledTasks();
        $this->assertFalse($result);
    }

    public function test_trigger_with_provider_commands()
    {
        // 创建一个提供命令的模拟提供者
        $commandRequest = new CommandRequest();
        $commandRequest->setCommand('provider:command');
        $commandRequest->setCronExpression('* * * * *');

        $provider = $this->createMock(CronCommandProvider::class);
        $provider->method('getCommands')->willReturn([$commandRequest]);

        // 预期消息总线将被调用
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // 创建带有提供者的服务
        $service = new CronTriggerService(
            [],
            [$provider],
            $this->messageBus,
            $this->logger,
            $this->lockService,
            $this->cache
        );

        $result = $service->triggerScheduledTasks();
        $this->assertTrue($result);
    }

    public function test_trigger_handles_exceptions()
    {
        // 创建一个抛出异常的消息总线
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        // 创建一个会抛出异常的提供者
        $provider = $this->createMock(CronCommandProvider::class);
        $provider->method('getCommands')
            ->willThrowException(new \Exception('Test exception'));

        $service = new CronTriggerService(
            [],
            [$provider],
            $this->messageBus,
            $this->logger,
            $this->lockService,
            $this->cache
        );

        // 预期错误日志
        $this->logger->expects($this->once())
            ->method('error');

        $result = $service->triggerScheduledTasks();
        $this->assertFalse($result);
    }

    public function test_same_minute_lock_prevents_duplicate_execution()
    {
        // 第一次调用成功，第二次调用失败
        $this->lockService->expects($this->exactly(2))
            ->method('acquireLock')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(\Symfony\Component\Lock\LockInterface::class),
                $this->throwException(new \RuntimeException('Cannot acquire lock'))
            );

        // 第一次调用应该成功
        $result1 = $this->service->triggerScheduledTasks();
        $this->assertTrue($result1);

        // 同一分钟内的第二次调用应该被阻止
        $result2 = $this->service->triggerScheduledTasks();
        $this->assertFalse($result2);
    }

    public function test_cache_prevents_duplicate_execution()
    {
        // 创建新的缓存和缓存项 mock
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        // 配置缓存返回已存在
        $cache->method('getItem')->willReturn($cacheItem);
        $cacheItem->method('isHit')->willReturn(true);

        // 创建新的服务实例
        $service = new CronTriggerService(
            [],
            [],
            $this->messageBus,
            $this->logger,
            $this->lockService,
            $cache
        );

        // 不应该调用锁服务
        $this->lockService->expects($this->never())
            ->method('acquireLock');

        $result = $service->triggerScheduledTasks();
        $this->assertFalse($result);
    }
    
    public function test_trigger_sets_cache_after_execution()
    {
        // 配置缓存操作
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with(true);
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(120);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->cacheItem);

        $result = $this->service->triggerScheduledTasks();
        $this->assertTrue($result);
    }
    
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->lockService = $this->createMock(LockService::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheItem = $this->createMock(CacheItemInterface::class);

        // 默认配置缓存行为
        $this->cache->method('getItem')->willReturn($this->cacheItem);
        $this->cacheItem->method('isHit')->willReturn(false);

        $this->service = new CronTriggerService(
            [],  // 空的命令迭代器
            [],  // 空的提供者迭代器
            $this->messageBus,
            $this->logger,
            $this->lockService,
            $this->cache
        );
    }
}