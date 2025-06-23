<?php

namespace Tourze\Symfony\CronJob\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

/**
 * HTTP 层触发定时任务的控制器
 * 用于在无法部署传统 cron 任务的环境（如 Serverless）中执行定时任务
 */
class CronTriggerController
{
    public function __construct(
        private readonly CronTriggerService $cronTriggerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * HTTP 轮询触发定时任务
     */
    #[Route('/cron/trigger', name: 'cron_job_http_trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        // 使用统一的触发服务
        $success = $this->cronTriggerService->triggerScheduledTasks();
        $message = $success ? 'Cron tasks triggered successfully' : 'No tasks triggered or already running';

        $this->logger->info('HTTP cron trigger executed', [
            'success' => $success,
            'ip' => $request->getClientIp(),
        ]);

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'timestamp' => time(),
        ], Response::HTTP_OK);
    }
}
