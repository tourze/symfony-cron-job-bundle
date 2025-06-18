<?php

namespace Tourze\Symfony\CronJob\Twig;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig 扩展，提供定时任务自动触发功能
 */
class CronJobExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $interval = 60000, // 默认 60 秒触发一次
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cron_auto_trigger', [$this, 'renderCronAutoTrigger'], [
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * 渲染自动触发定时任务的 JavaScript 代码
     *
     * @param int|null $interval 触发间隔（毫秒），默认 60000（60秒）
     * @param array<string, mixed> $options 选项配置
     *  - bool debug: 是否开启调试日志
     *  - int maxRetries: 最大重试次数
     *  - int retryDelay: 重试延迟（毫秒）
     */
    public function renderCronAutoTrigger(?int $interval = null, array $options = []): string
    {
        $interval = $interval ?? $this->interval;
        $triggerUrl = $this->urlGenerator->generate('cron_trigger', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $debug = $options['debug'] ?? false;
        $maxRetries = $options['maxRetries'] ?? 3;
        $retryDelay = $options['retryDelay'] ?? 5000;

        $debugJs = $debug ? 'true' : 'false';

        return <<<HTML
<script>
(function() {
    const cronTriggerUrl = '{$triggerUrl}';
    const interval = {$interval};
    const debug = {$debugJs};
    const maxRetries = {$maxRetries};
    const retryDelay = {$retryDelay};
    
    let retryCount = 0;

    async function triggerCron() {
        try {
            const response = await fetch(cronTriggerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    source: 'auto-trigger',
                    timestamp: Date.now()
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: \${response.status}`);
            }

            const data = await response.json();
            
            if (debug) {
                console.log('[CronJob] Trigger response:', data);
            }
            
            // 重置重试计数
            retryCount = 0;
            
        } catch (error) {
            if (debug) {
                console.error('[CronJob] Trigger error:', error);
            }
            
            // 实现重试逻辑
            if (retryCount < maxRetries) {
                retryCount++;
                if (debug) {
                    console.log(`[CronJob] Retrying in \${retryDelay}ms... (attempt \${retryCount}/\${maxRetries})`);
                }
                setTimeout(triggerCron, retryDelay);
            }
        }
    }

    // 初始延迟执行
    setTimeout(triggerCron, 1000);
    
    // 定时执行
    setInterval(triggerCron, interval);
    
    if (debug) {
        console.log(`[CronJob] Auto-trigger initialized with interval: \${interval}ms`);
    }
})();
</script>
HTML;
    }
}