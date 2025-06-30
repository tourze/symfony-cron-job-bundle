<?php

namespace Tourze\Symfony\CronJob\Service;

use Cron\CronExpression;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\AsyncCommandBundle\Message\RunCommandMessage;
use Tourze\DoctrineHelper\ReflectionHelper;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;

/**
 * 统一的定时任务触发服务
 * 用于处理所有类型的定时任务触发（命令行、HTTP、事件）
 */
class CronTriggerService
{
    private const TRIGGER_LOCK_KEY = 'cron-trigger';

    public function __construct(
        #[TaggedIterator(tag: AsCronTask::TAG_NAME)] private readonly iterable $commands,
        #[TaggedIterator(tag: CronCommandProvider::TAG_NAME)] private readonly iterable $providers,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly LockService $lockService,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * 触发当前分钟应该执行的定时任务
     */
    public function triggerScheduledTasks(): bool
    {
        $now = new DateTimeImmutable();
        $minute = $now->format('YmdHi');
        $cacheKey = self::TRIGGER_LOCK_KEY . '-' . $minute;
        
        // 先检查缓存，如果该分钟已执行过则直接返回
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $this->logger->debug('Cron tasks already triggered for this minute (cache hit)', [
                'minute' => $minute,
                'cache_key' => $cacheKey,
            ]);
            return false;
        }

        // 尝试获取锁
        try {
            $lock = $this->lockService->acquireLock($cacheKey);
        } catch (\RuntimeException $e) {
            $this->logger->debug('Cron trigger lock already acquired for this minute', [
                'minute' => $minute,
                'lock_key' => $cacheKey,
            ]);
            return false;
        }

        // 双重检查：获取锁后再次检查缓存
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $this->logger->debug('Cron tasks already triggered for this minute (double check)', [
                'minute' => $minute,
                'cache_key' => $cacheKey,
            ]);
            return false;
        }

        $this->logger->info('Starting cron trigger execution', [
            'minute' => $minute,
            'timestamp' => $now->format('c'),
        ]);

        try {
            $tasksTriggered = $this->processTasks($now);

            // 成功执行后设置缓存，有效期2分钟
            $cacheItem->set(true);
            $cacheItem->expiresAfter(120);
            $this->cache->save($cacheItem);

            $this->logger->info('Cron trigger execution completed', [
                'tasks_triggered' => $tasksTriggered,
                'minute' => $minute,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to trigger cron tasks', [
                'exception' => $e,
                'minute' => $minute,
            ]);
            return false;
        }
    }

    /**
     * 处理所有定时任务
     */
    private function processTasks(DateTimeImmutable $now): int
    {
        $tasksTriggered = 0;

        // 处理基于属性的定时任务
        $tasksTriggered += $this->processAttributeTasks($now);

        // 处理基于提供者的定时任务
        $tasksTriggered += $this->processProviderTasks($now);

        return $tasksTriggered;
    }

    /**
     * 处理基于属性的定时任务
     */
    private function processAttributeTasks(DateTimeImmutable $now): int
    {
        $tasksTriggered = 0;

        foreach ($this->commands as $command) {
            if ($command->getName() === 'cron:run') {
                continue; // 跳过自己
            }

            $attributes = ReflectionHelper::getClassReflection($command)->getAttributes(AsCronTask::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $tagData = $instance->tags[0][AsCronTask::TAG_NAME];
                
                if (!$this->shouldExecuteTask($tagData['expression'], $now)) {
                    continue;
                }

                if ($this->triggerTask($command->getName(), [], $now, $tagData['lockTtl'] ?? null)) {
                    $tasksTriggered++;
                }
            }
        }

        return $tasksTriggered;
    }

    /**
     * 检查任务是否应该执行
     */
    private function shouldExecuteTask(string $expression, DateTimeImmutable $time): bool
    {
        try {
            $cron = new CronExpression($expression);
            return $cron->isDue($time);
        } catch (\Throwable $exception) {
            $this->logger->error('定时任务检查时间失败', [
                'expression' => $expression,
                'exception' => $exception,
            ]);
            return false;
        }
    }

    /**
     * 触发单个任务
     */
    private function triggerTask(
        string $command,
        array $options,
        DateTimeImmutable $time,
        ?int $lockTtl = null
    ): bool {
        $lockKey = $this->generateTaskLockKey($command, $options, $time);
        $cacheKey = 'task-' . $lockKey;

        // 使用自定义的 TTL 或默认的 1 小时
        $ttl = $lockTtl ?? 3600;

        // 先检查缓存，如果任务已触发则跳过
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $this->logger->debug('任务已触发，跳过 (cache hit)', [
                'command' => $command,
                'cache_key' => $cacheKey,
            ]);
            return false;
        }

        try {
            // 尝试获取任务级别的锁
            $this->lockService->acquireLock($lockKey);
        } catch (\RuntimeException $e) {
            // 拿不到锁，说明任务正在执行
            $this->logger->warning('无法获取任务锁，跳过', [
                'key' => $lockKey,
                'command' => $command,
            ]);
            return false;
        }

        // 双重检查缓存
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $this->logger->debug('任务已触发，跳过 (double check)', [
                'command' => $command,
                'cache_key' => $cacheKey,
            ]);
            return false;
        }

        // 分发消息
        $this->dispatchMessage($command, $options);

        // 设置缓存，防止重复触发
        $cacheItem->set(true);
        $cacheItem->expiresAfter($ttl);
        $this->cache->save($cacheItem);

        return true;
    }

    /**
     * 生成任务锁键名
     */
    private function generateTaskLockKey(string $command, array $options, DateTimeImmutable $time): string
    {
        return str_replace(':', '-', $command) . md5(serialize($options)) . '-cron-' . $time->format('YmdHi00');
    }

    /**
     * 分发消息到消息队列
     */
    private function dispatchMessage(string $command, array $options): void
    {
        $this->logger->info('生成定时任务异步任务', [
            'command' => $command,
        ]);

        try {
            $message = new RunCommandMessage();
            $message->setCommand($command);
            $message->setOptions($options);
            $this->messageBus->dispatch($message);
        } catch (\Throwable $exception) {
            $this->logger->error('生成定时任务异步任务失败', [
                'command' => $command,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * 处理基于提供者的定时任务
     */
    private function processProviderTasks(DateTimeImmutable $now): int
    {
        $tasksTriggered = 0;

        foreach ($this->providers as $provider) {
            foreach ($provider->getCommands() as $commandRequest) {
                if (!$this->shouldExecuteTask($commandRequest->getCronExpression(), $now)) {
                    continue;
                }

                if ($this->triggerTask(
                    $commandRequest->getCommand(),
                    $commandRequest->getOptions(),
                    $now,
                    $commandRequest->getLockTtl()
                )) {
                    $tasksTriggered++;
                }
            }
        }

        return $tasksTriggered;
    }
}
