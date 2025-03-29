<?php

namespace Tourze\Symfony\CronJob\Command;

use Cron\CronExpression;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DoctrineHelper\ReflectionHelper;
use Tourze\Symfony\Async\Message\RunCommandMessage;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;
use Tourze\Symfony\CronJob\Request\CommandRequest;

/**
 * 基于当前的时间，运行一次相关的定时任务
 */
#[AsCommand(name: CronRunCommand::NAME, description: '运行当前分钟的定时任务')]
class CronRunCommand extends Command
{
    const NAME = 'cron:run';

    public function __construct(
        #[TaggedIterator(AsCronTask::TAG_NAME)] private readonly iterable $commands,
        #[TaggedIterator(CronCommandProvider::TAG_NAME)] private readonly iterable $providers,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new DateTimeImmutable();

        foreach ($this->commands as $command) {
            /** @var Command $command */
            if ($command->getName() === $this->getName()) {
                continue;
            }

            foreach (ReflectionHelper::getClassReflection($command)->getAttributes(AsCronTask::class) as $item) {
                $item = $item->newInstance();
                /* @var AsCronTask $item */

                if (!$this->checkCronExpression($item->tags[0][AsCronTask::TAG_NAME]['expression'], $now)) {
                    continue;
                }

                $this->createMessage($command->getName(), [], $now);
            }
        }

        foreach ($this->providers as $provider) {
            /** @var CronCommandProvider $provider */
            foreach ($provider->getCommands() as $command) {
                /** @var CommandRequest $command */
                if (!$this->checkCronExpression($command->getCronExpression(), $now)) {
                    continue;
                }

                $this->createMessage($command->getCommand(), $command->getOptions(), $now);
            }
        }

        return Command::SUCCESS;
    }

    private function checkCronExpression(string $expression, DateTimeImmutable $time): bool
    {
        try {
            $cron = new CronExpression($expression);
            if (!$cron->isDue($time)) {
                return false;
            }
        } catch (\Throwable $exception) {
            $this->logger->error('定时任务检查时间失败', [
                'exception' => $exception,
            ]);
            return false;
        }
        return true;
    }

    private function createMessage(string $command, array $options, DateTimeImmutable $time): void
    {
        $key = str_replace(':', '-', $command) . md5(serialize($options)) . '-cron-' . $time->format('YmdHi00');

        // 这个锁，拿了就不释放，一小时后自动释放
        $lock = $this->lockFactory->createLock($key, ttl: 60 * 60, autoRelease: false);
        if (!$lock->acquire()) {
            // 拿不到锁，直接返回，说明有别人拿了
            $this->logger->warning('无法获取锁，跳过', [
                'key' => $key,
            ]);
            return;
        }

        $this->logger->info('生成定时任务异步任务', [
            'command' => $command,
        ]);
        try {
            $message = new RunCommandMessage();
            $message->setCommand($command);
            $message->setOptions($options);
            $this->messageBus->dispatch($message);
        } catch (\Throwable $exception) {
            $this->logger->error('生成定时任务异步任务失败', [
                'command' => $command,
                'exception' => $exception,
            ]);
        }
    }
}
