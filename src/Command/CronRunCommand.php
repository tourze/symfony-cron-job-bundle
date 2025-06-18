<?php

namespace Tourze\Symfony\CronJob\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tourze\Symfony\CronJob\Service\CronTriggerService;

/**
 * 基于当前的时间，运行一次相关的定时任务
 */
#[AsCommand(name: self::NAME, description: '运行当前分钟的定时任务')]
class CronRunCommand extends Command
{
    public const NAME = 'cron:run';

    public function __construct(
        private readonly CronTriggerService $cronTriggerService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cronTriggerService->triggerScheduledTasks();
        return Command::SUCCESS;
    }
}
