<?php

declare(strict_types=1);

namespace Tourze\Symfony\CronJob\Request;

use Tourze\AsyncCommandBundle\Message\RunCommandMessage;
use Tourze\AsyncContracts\AsyncMessageInterface;

/**
 * Cron 任务命令请求类
 *
 * 使用组合模式包装 RunCommandMessage，避免继承具体类
 */
class CommandRequest implements AsyncMessageInterface
{
    private RunCommandMessage $message;

    private string $cronExpression;

    private ?int $lockTtl = null;

    public function __construct()
    {
        $this->message = new RunCommandMessage();
    }

    public function getCommand(): string
    {
        return $this->message->getCommand();
    }

    public function setCommand(string $command): void
    {
        $this->message->setCommand($command);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->message->getOptions();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->message->setOptions($options);
    }

    public function getRunCommandMessage(): RunCommandMessage
    {
        return $this->message;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(string $cronExpression): void
    {
        $this->cronExpression = $cronExpression;
    }

    public function getLockTtl(): ?int
    {
        return $this->lockTtl;
    }

    public function setLockTtl(?int $lockTtl): void
    {
        $this->lockTtl = $lockTtl;
    }
}
