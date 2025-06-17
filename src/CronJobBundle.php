<?php

namespace Tourze\Symfony\CronJob;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AsyncCommandBundle\AsyncCommandBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\LockServiceBundle\LockServiceBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

class CronJobBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            AsyncCommandBundle::class => ['all' => true],
            LockServiceBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
        ];
    }
}
