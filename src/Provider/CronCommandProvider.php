<?php

namespace Tourze\Symfony\CronJob\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\Symfony\CronJob\Request\CommandRequest;

#[AutoconfigureTag(name: self::TAG_NAME)]
interface CronCommandProvider
{
    public const TAG_NAME = 'cron-command-provider';

    /**
     * 获取要执行的命令
     *
     * @return iterable<CommandRequest>
     */
    public function getCommands(): iterable;
}
