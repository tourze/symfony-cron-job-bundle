<?php

namespace Tourze\Symfony\CronJob;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AsyncCommandBundle\AsyncCommandBundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class CronJobBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            AsyncCommandBundle::class => ['all' => true],
        ];
    }
}
