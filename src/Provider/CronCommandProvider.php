<?php

namespace Tourze\Symfony\CronJob\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(CronCommandProvider::TAG_NAME)]
interface CronCommandProvider
{
    const TAG_NAME = 'cron-command-provider';

    /**
     * 获取要执行的命令
     */
    public function getCommands(): iterable;
}
