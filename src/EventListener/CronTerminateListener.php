<?php

namespace Tourze\Symfony\CronJob\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Tourze\Symfony\CronJob\Command\CronRunCommand;

/**
 * 监听 kernel.terminate 事件，在请求结束后触发定时任务
 * 适用于 Serverless 环境或需要在 HTTP 请求生命周期内执行定时任务的场景
 */
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onKernelTerminate', priority: -1024)]
class CronTerminateListener
{
    private const TERMINATE_LOCK_KEY = 'cron-terminate-trigger';
    private const TERMINATE_LOCK_TTL = 60; // 1分钟锁
    private const MIN_INTERVAL_SECONDS = 55; // 最小触发间隔，防止过于频繁

    private static ?int $lastExecutionTime = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理 kernel.terminate 事件
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        // 检查是否启用 terminate 触发
        if (!$this->isTerminateTriggerEnabled()) {
            return;
        }

        // 只在主请求时触发，忽略子请求
        if (!$event->isMainRequest()) {
            return;
        }

        // 根据概率决定是否执行
        if (!$this->shouldExecute()) {
            return;
        }

        // 检查时间间隔
        if (!$this->checkTimeInterval()) {
            return;
        }

        // 获取锁，防止并发执行
        $lock = $this->lockFactory->createLock(self::TERMINATE_LOCK_KEY, self::TERMINATE_LOCK_TTL);
        if (!$lock->acquire()) {
            return;
        }

        try {
            // 异步执行，避免阻塞响应
            $this->triggerCronRun();
        } finally {
            $lock->release();
        }
    }

    private function isTerminateTriggerEnabled(): bool
    {
        return (bool) ($_ENV['CRON_TERMINATE_TRIGGER_ENABLED'] ?? false);
    }

    /**
     * 根据配置的概率决定是否执行
     */
    private function shouldExecute(): bool
    {
        $probability = $this->getExecutionProbability();

        // 如果概率设置为 1.0，则每次都执行
        if ($probability >= 1.0) {
            return true;
        }

        // 如果概率设置为 0 或负数，则永不执行
        if ($probability <= 0) {
            return false;
        }

        // 生成随机数判断是否执行
        return (mt_rand() / mt_getrandmax()) < $probability;
    }

    private function getExecutionProbability(): float
    {
        return (float) ($_ENV['CRON_TERMINATE_TRIGGER_PROBABILITY'] ?? 0.01);
    }

    /**
     * 检查时间间隔，避免过于频繁的执行
     */
    private function checkTimeInterval(): bool
    {
        $currentTime = time();
        
        // 第一次执行
        if (self::$lastExecutionTime === null) {
            self::$lastExecutionTime = $currentTime;
            return true;
        }

        // 检查距离上次执行的时间间隔
        if ($currentTime - self::$lastExecutionTime >= self::MIN_INTERVAL_SECONDS) {
            self::$lastExecutionTime = $currentTime;
            return true;
        }

        return false;
    }

    /**
     * 触发 cron:run 命令
     */
    private function triggerCronRun(): void
    {
        try {
            $this->logger->info('Triggering cron tasks via terminate event', [
                'probability' => $this->getExecutionProbability(),
                'last_execution' => self::$lastExecutionTime,
            ]);

            // 使用 pcntl_fork 在子进程中执行，避免阻塞主进程
            if (function_exists('pcntl_fork')) {
                $pid = pcntl_fork();
                
                if ($pid === -1) {
                    // Fork 失败，直接执行
                    $this->executeCronCommand();
                } elseif ($pid === 0) {
                    // 子进程中执行
                    $this->executeCronCommand();
                    exit(0);
                }
                // 父进程继续，不等待子进程
            } else {
                // 没有 pcntl 扩展，直接执行
                $this->executeCronCommand();
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to trigger cron via terminate event', [
                'exception' => $e,
            ]);
        }
    }

    /**
     * 执行 cron:run 命令
     */
    private function executeCronCommand(): void
    {
        try {
            $application = $this->kernel->getContainer()->get('console.command_loader')
                ->get(CronRunCommand::NAME)
                ->getApplication();
            
            if (!$application) {
                throw new \RuntimeException('Console application not available');
            }

            $input = new ArrayInput(['command' => CronRunCommand::NAME]);
            $output = new NullOutput();

            $exitCode = $application->find(CronRunCommand::NAME)->run($input, $output);
            
            $this->logger->info('Cron command executed via terminate event', [
                'exit_code' => $exitCode,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to execute cron:run command', [
                'exception' => $e,
            ]);
        }
    }
}
