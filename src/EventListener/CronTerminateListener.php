<?php

namespace Tourze\Symfony\CronJob\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

/**
 * 监听 kernel.terminate 事件，在请求结束后触发定时任务
 * 适用于 Serverless 环境或需要在 HTTP 请求生命周期内执行定时任务的场景
 */
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onKernelTerminate', priority: -1024)]
class CronTerminateListener
{
    public function __construct(
        private readonly CronTriggerService $cronTriggerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理 kernel.terminate 事件
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        // 只在主请求时触发，忽略子请求
        if (!$event->isMainRequest()) {
            return;
        }

        // 直接使用统一的触发服务
        $triggered = $this->cronTriggerService->triggerScheduledTasks();
        
        if ($triggered) {
            $this->logger->info('Cron tasks triggered via terminate event');
        }
    }
}
