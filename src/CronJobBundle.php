<?php

namespace Tourze\Symfony\CronJob;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\Symfony\Async\AsyncBundle;

class CronJobBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            AsyncBundle::class => ['all' => true],
        ];
    }
}
