<?php

namespace Tourze\Symfony\CronJob\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tourze\LockServiceBundle\Exception\LockAcquisitionException;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\Symfony\CronJob\Exception\CronJobException;

#[AsCommand(name: self::NAME, description: '跑一个进程定时检查定时任务')]
final class CronStartCommand extends Command
{
    public const NAME = 'cron:start';
    final public const PID_FILE = '.cron-pid';

    private int $mbLimit = 1024;

    public function __construct(
        #[Autowire(service: 'service_container')] private readonly ContainerInterface $container,
        private readonly LockService $lockService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Starts cron scheduler')
            ->addOption('blocking', 'b', InputOption::VALUE_NONE, 'Run in blocking mode.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((bool) $input->getOption('blocking')) {
            return $this->executeBlockingMode($output);
        }

        return $this->executeNonBlockingMode($output);
    }

    private function executeBlockingMode(OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>%s</info>', 'Starting cron scheduler in blocking mode.'));
        $this->scheduler($output->isVerbose() ? $output : new NullOutput(), null);

        return 0;
    }

    private function executeNonBlockingMode(OutputInterface $output): int
    {
        $output->writeln('<info>启动 Cron 调度器（非阻塞模式）...</info>');

        $this->validatePcntlExtension();
        $output->writeln('  - PCNTL 扩展检查通过');

        $pidFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::PID_FILE;
        $output->writeln(sprintf('  - PID 文件路径: <comment>%s</comment>', $pidFile));

        $output->writeln('  - 正在创建子进程...');
        $pid = $this->forkProcess();

        if (0 !== $pid) {
            return $this->handleParentProcess($pid, $pidFile, $output);
        }

        return $this->handleChildProcess($pidFile);
    }

    private function validatePcntlExtension(): void
    {
        if (!extension_loaded('pcntl')) {
            throw new CronJobException('This command needs the pcntl extension to run.');
        }
    }

    private function forkProcess(): int
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new CronJobException('Unable to start the cron process.');
        }

        return $pid;
    }

    private function handleParentProcess(int $pid, string $pidFile, OutputInterface $output): int
    {
        $output->writeln(sprintf('  - 子进程 PID: <comment>%d</comment>', $pid));

        if (false === file_put_contents($pidFile, $pid)) {
            throw new CronJobException('Unable to create process file.');
        }

        $output->writeln(sprintf('  - PID 文件已创建: <comment>%s</comment>', $pidFile));
        $output->writeln(sprintf('<info>✓ Cron scheduler started in non-blocking mode...</info>'));

        return 0;
    }

    private function handleChildProcess(string $pidFile): int
    {
        if (-1 === posix_setsid()) {
            throw new CronJobException('Unable to set the child process as session leader.');
        }

        $this->scheduler(new NullOutput(), $pidFile);

        return Command::SUCCESS;
    }

    private function scheduler(OutputInterface $output, ?string $pidFile): void
    {
        $input = new ArrayInput([]);
        $command = $this->getCronRunCommand();

        while (true) {
            $this->waitForNextMinute($output);

            if ($this->shouldStop($pidFile, $output)) {
                break;
            }

            $this->executeCronTaskWithLock($command, $input, $output);
            $this->performMaintenanceTasks($output);
        }
    }

    private function getCronRunCommand(): Command
    {
        $console = $this->getApplication();
        if (null === $console) {
            throw new CronJobException('Unable to get console application instance.');
        }

        return $console->find(CronRunCommand::NAME);
    }

    private function waitForNextMinute(OutputInterface $output): void
    {
        $now = microtime(true);
        $intNow = (int) $now;
        $delaySeconds = 60 - ($intNow % 60) + $intNow - $now;
        $microseconds = $delaySeconds * 1e6;
        $nextMinute = date('Y-m-d H:i:s', (int) ($now + $delaySeconds));
        $output->writeln(sprintf('等待下一个分钟周期 (下次执行: %s, 等待 %.2f 秒)', $nextMinute, $delaySeconds));
        usleep((int) $microseconds);
    }

    private function shouldStop(?string $pidFile, OutputInterface $output): bool
    {
        if (null !== $pidFile && !file_exists($pidFile)) {
            $output->writeln("进程未启动：{$pidFile}");

            return true;
        }

        return false;
    }

    private function executeCronTaskWithLock(Command $command, ArrayInput $input, OutputInterface $output): void
    {
        $now = microtime(true);
        $lockKey = 'cron:run:' . (int) ($now / 60);
        $timestamp = date('Y-m-d H:i:s', (int) $now);

        $output->writeln('');
        $output->writeln(sprintf('<info>[%s] 准备执行定时任务...</info>', $timestamp));

        try {
            $lock = $this->lockService->acquireLock($lockKey);
            $output->writeln(sprintf('  - 获取分布式锁成功: <comment>%s</comment>', $lockKey));
        } catch (LockAcquisitionException $e) {
            $output->writeln(sprintf('<comment>  - 其他进程正在执行Cron任务 (锁: %s)，跳过</comment>', $lockKey));

            return;
        }

        try {
            $output->writeln('  - 开始执行任务...');
            $command->run($input, $output);
            $output->writeln('  - 任务执行完成');
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>  - 执行Cron任务失败: %s</error>', $e->getMessage()));
        } finally {
            try {
                $this->lockService->releaseLock($lockKey);
                $output->writeln(sprintf('  - 释放分布式锁: <comment>%s</comment>', $lockKey));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>  - 释放锁失败: %s</error>', $e->getMessage()));
            }
        }
    }

    private function performMaintenanceTasks(OutputInterface $output): void
    {
        $servicesResetter = $this->container->get('services_resetter');
        if (is_object($servicesResetter) && method_exists($servicesResetter, 'reset')) {
            $output->writeln('  - 重置服务容器');
            $servicesResetter->reset();
        }

        $memoryUsage = memory_get_usage(true) / (1024 * 1024);
        $output->writeln(sprintf("  - 当前内存使用量: <comment>%.2f MB</comment> / %d MB", $memoryUsage, $this->mbLimit));

        if ($memoryUsage > $this->mbLimit) {
            throw new CronJobException("Memory limit exceeded: {$memoryUsage}MB > {$this->mbLimit}MB");
        }
    }
}
