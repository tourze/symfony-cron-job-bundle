<?php

namespace Tourze\Symfony\CronJob;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\Symfony\AsyncMessage\AsyncMessageBundle;

class CronJobBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            AsyncMessageBundle::class => ['all' => true],
        ];
    }
}
