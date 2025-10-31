<?php

namespace Tourze\Symfony\CronJob\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class CronJobExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
