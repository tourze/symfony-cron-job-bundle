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

#[AsCommand(name: 'cron:start', description: '跑一个进程定时检查定时任务')]
class CronStartCommand extends Command
{
    final public const PID_FILE = '.cron-pid';

    private int $mbLimit = 1024;

    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Starts cron scheduler')
            ->addOption('blocking', 'b', InputOption::VALUE_NONE, 'Run in blocking mode.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('blocking')) {
            $output->writeln(sprintf('<info>%s</info>', 'Starting cron scheduler in blocking mode.'));
            $this->scheduler($output->isVerbose() ? $output : new NullOutput(), null);

            return 0;
        }

        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('This command needs the pcntl extension to run.');
        }

        $pidFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::PID_FILE;

        if (-1 === $pid = pcntl_fork()) {
            throw new \RuntimeException('Unable to start the cron process.');
        } elseif (0 !== $pid) {
            if (false === file_put_contents($pidFile, $pid)) {
                throw new \RuntimeException('Unable to create process file.');
            }

            $output->writeln(sprintf('<info>%s</info>', 'Cron scheduler started in non-blocking mode...'));

            return 0;
        }

        if (-1 === posix_setsid()) {
            throw new \RuntimeException('Unable to set the child process as session leader.');
        }

        $this->scheduler(new NullOutput(), $pidFile);

        return Command::SUCCESS;
    }

    private function scheduler(OutputInterface $output, ?string $pidFile): void
    {
        $input = new ArrayInput([]);

        $console = $this->getApplication();
        $command = $console->find(CronRunCommand::NAME);

        while (true) {
            $now = microtime(true);
            $intNow = (int) $now;
            $delaySeconds = 60 - ($intNow % 60) + $intNow - $now;
            $microseconds = $delaySeconds * 1e6;
            $output->writeln('等待下一个分钟周期');
            usleep((int) $microseconds);

            if (null !== $pidFile && !file_exists($pidFile)) {
                $output->writeln("进程未启动：{$pidFile}");
                break;
            }

            $output->writeln('开始执行Cron任务');
            $command->run($input, $output);

            $this->container->get('services_resetter')->reset();

            $memoryUsage = memory_get_usage(true) / (1024 * 1024); // 转换为MB单位
            $output->writeln("当前内存使用量{$memoryUsage}MB");
            if ($memoryUsage > $this->mbLimit) {
                exit('MEMORY OUT');
            }
        }
    }
}
