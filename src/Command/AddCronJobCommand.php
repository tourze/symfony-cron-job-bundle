<?php

declare(strict_types=1);

namespace Tourze\Symfony\CronJob\Command;

use Composer\CaBundle\CaBundle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabRepository;

/**
 * 注册到Crontab
 */
#[AsCommand(name: self::NAME, description: '注册到Crontab')]
final class AddCronJobCommand extends Command
{
    public const NAME = 'cron-job:add-cron-tab';

    /**
     * @var callable|null Factory for creating CrontabRepository (主要用于测试)
     */
    private $crontabRepositoryFactory;

    public function __construct(
        private readonly KernelInterface $kernel,
        ?callable $crontabRepositoryFactory = null,
    ) {
        parent::__construct();
        $this->crontabRepositoryFactory = $crontabRepositoryFactory;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new PhpExecutableFinder();
        $phpExecutable = $finder->find();

        if ($phpExecutable === false) {
            $output->writeln('<error>找不到 PHP 可执行文件</error>');
            return Command::FAILURE;
        }

        $projectDir = $this->kernel->getProjectDir();
        $consolePath = $projectDir . '/bin/console';

        if (!file_exists($consolePath)) {
            $output->writeln('<error>找不到 console 文件</error>');
            return Command::FAILURE;
        }

        // 创建 Crontab 任务
        $job = new CrontabJob();
        $job->setMinutes('*');
        $job->setHours('*');
        $job->setDayOfMonth('*');
        $job->setMonths('*');
        $job->setDayOfWeek('*');
        $job->setTaskCommandLine(sprintf(
            'cd %s && %s cron-job:run',
            escapeshellarg($projectDir),
            $phpExecutable
        ));

        // 使用工厂方法创建 CrontabRepository（主要用于测试）
        if ($this->crontabRepositoryFactory !== null) {
            $crontabRepository = ($this->crontabRepositoryFactory)();
        } else {
            $crontabRepository = new CrontabRepository(new CrontabAdapter());
        }

        try {
            assert($crontabRepository instanceof CrontabRepository);
            $crontabRepository->addJob($job);
            $crontabRepository->persist();

            $output->writeln('<info>Cron 任务已成功添加到 crontab</info>');
            $output->writeln(sprintf('任务命令: <comment>%s</comment>', $job->getTaskCommandLine()));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>添加 Cron 任务失败: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}