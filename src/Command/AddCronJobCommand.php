<?php

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

#[AsCommand(name: self::NAME, description: '注册到Crontab')]
class AddCronJobCommand extends Command
{
    public const NAME = 'cron-job:add-cron-tab';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        $rootDir = $this->kernel->getProjectDir();
        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();

        $cmd = "{$phpExecutable} -d openssl.cafile={$caPathOrFile} {$rootDir}/bin/console " . CronRunCommand::NAME;
        $output->writeln('cmd: ' . $cmd);

        $hasJob = false;
        $crontabJob = null;
        $crontabRepository = new CrontabRepository(new CrontabAdapter());
        foreach ($crontabRepository->getJobs() as $job) {
            /** @var CrontabJob $job */
            if ($job->getTaskCommandLine() === $cmd) {
                $hasJob = true;
                $crontabJob = $job;
                break;
            }
        }
        $output->writeln('find cronjob:');
        dump($crontabJob);

        if (!$hasJob) {
            $crontabJob = new CrontabJob();
            $crontabJob
                ->setMinutes('*')
                ->setHours('*')
                ->setDayOfMonth('*')
                ->setMonths('*')
                ->setDayOfWeek('*')
                ->setTaskCommandLine($cmd)
            ;
        }

        if (null !== $crontabJob) {
            $crontabJob->setEnabled(true);
            $crontabRepository->addJob($crontabJob);
        }
        $crontabRepository->persist();

        return Command::SUCCESS;
    }
}
