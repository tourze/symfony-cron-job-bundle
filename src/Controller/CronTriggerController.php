<?php

namespace Tourze\Symfony\CronJob\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\Symfony\CronJob\Command\CronRunCommand;

/**
 * HTTP 层触发定时任务的控制器
 * 用于在无法部署传统 cron 任务的环境（如 Serverless）中执行定时任务
 */
class CronTriggerController
{
    private const TRIGGER_LOCK_KEY = 'cron-http-trigger';
    private const TRIGGER_LOCK_TTL = 120; // 2分钟锁，防止并发触发

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * HTTP 轮询触发定时任务
     */
    #[Route('/cron/trigger', name: 'cron_trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        // 检查是否启用 HTTP 触发
        if (!$this->isHttpTriggerEnabled()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'HTTP trigger is disabled',
            ], Response::HTTP_FORBIDDEN);
        }

        // 验证请求授权
        if (!$this->validateRequest($request)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 获取分布式锁，防止并发触发
        $lock = $this->lockFactory->createLock(self::TRIGGER_LOCK_KEY, self::TRIGGER_LOCK_TTL);
        if (!$lock->acquire()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Another trigger is already running',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            // 执行 cron:run 命令
            $exitCode = $this->executeCronRun();

            $success = $exitCode === 0;
            $message = $success ? 'Cron tasks triggered successfully' : 'Failed to trigger cron tasks';

            $this->logger->info('HTTP cron trigger executed', [
                'success' => $success,
                'exit_code' => $exitCode,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'success' => $success,
                'message' => $message,
                'timestamp' => time(),
            ], $success ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            $lock->release();
        }
    }

    private function isHttpTriggerEnabled(): bool
    {
        return (bool) ($_ENV['CRON_HTTP_TRIGGER_ENABLED'] ?? false);
    }

    /**
     * 验证请求是否合法
     */
    private function validateRequest(Request $request): bool
    {
        // 如果没有配置密钥，拒绝所有请求
        $triggerSecret = $this->getTriggerSecret();
        if (empty($triggerSecret)) {
            $this->logger->warning('Cron trigger secret not configured');
            return false;
        }

        // 验证 Authorization header
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader) {
            // 支持 Bearer token 格式
            if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
                return hash_equals($triggerSecret, $matches[1]);
            }
        }

        // 支持自定义 X-Cron-Secret header
        $customSecret = $request->headers->get('X-Cron-Secret');
        if ($customSecret) {
            return hash_equals($triggerSecret, $customSecret);
        }

        return false;
    }

    private function getTriggerSecret(): ?string
    {
        return $_ENV['CRON_HTTP_TRIGGER_SECRET'] ?? null;
    }

    /**
     * 执行 cron:run 命令
     */
    private function executeCronRun(): int
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

            return $application->find(CronRunCommand::NAME)->run($input, $output);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to execute cron:run command', [
                'exception' => $e,
            ]);
            return 1;
        }
    }

    /**
     * 健康检查端点
     */
    #[Route('/cron/health', name: 'cron_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'enabled' => $this->isHttpTriggerEnabled(),
            'timestamp' => time(),
        ]);
    }
}
