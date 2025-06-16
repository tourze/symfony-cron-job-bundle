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
use Tourze\AsyncCommandBundle\Message\RunCommandMessage;
use Tourze\DoctrineHelper\ReflectionHelper;
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

                $tagData = $item->tags[0][AsCronTask::TAG_NAME];
                if (!$this->checkCronExpression($tagData['expression'], $now)) {
                    continue;
                }

                $lockTtl = $tagData['lockTtl'] ?? null;

                $this->createMessage($command->getName(), [], $now, $lockTtl);
            }
        }

        foreach ($this->providers as $provider) {
            /** @var CronCommandProvider $provider */
            foreach ($provider->getCommands() as $command) {
                /** @var CommandRequest $command */
                if (!$this->checkCronExpression($command->getCronExpression(), $now)) {
                    continue;
                }

                $this->createMessage(
                    $command->getCommand(), 
                    $command->getOptions(), 
                    $now,
                    $command->getLockTtl()
                );
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

    private function createMessage(
        string $command, 
        array $options, 
        DateTimeImmutable $time,
        ?int $lockTtl = null
    ): void {
        $key = str_replace(':', '-', $command) . md5(serialize($options)) . '-cron-' . $time->format('YmdHi00');

        // 使用自定义的 TTL 或默认的 1 小时
        $ttl = $lockTtl ?? 3600;

        // 这个锁，拿了就不释放，到期后自动释放
        $lock = $this->lockFactory->createLock($key, ttl: $ttl, autoRelease: false);
        if (!$lock->acquire()) {
            // 拿不到锁，直接返回，说明有别人拿了
            $this->logger->warning('无法获取锁，跳过', [
                'key' => $key,
                'ttl' => $ttl,
                'command' => $command,
            ]);
            return;
        }

        $this->dispatchMessage($command, $options);
    }

    private function dispatchMessage(string $command, array $options): void
    {
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
