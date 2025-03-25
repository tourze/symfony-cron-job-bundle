<?php

namespace Tourze\Symfony\CronJob\Request;

use Tourze\Symfony\AsyncMessage\Message\RunCommandMessage;

class CommandRequest extends RunCommandMessage
{
    private string $cronExpression;

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(string $cronExpression): void
    {
        $this->cronExpression = $cronExpression;
    }
}
