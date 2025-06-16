<?php

namespace Tourze\Symfony\CronJob\Request;

use Tourze\AsyncCommandBundle\Message\RunCommandMessage;

class CommandRequest extends RunCommandMessage
{
    private string $cronExpression;
    private ?int $lockTtl = null;

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
