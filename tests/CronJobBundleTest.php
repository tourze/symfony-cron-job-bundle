<?php

declare(strict_types=1);

namespace Tourze\Symfony\CronJob\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\Symfony\CronJob\CronJobBundle;

/**
 * @internal
 */
#[CoversClass(CronJobBundle::class)]
#[RunTestsInSeparateProcesses]
final class CronJobBundleTest extends AbstractBundleTestCase
{
}
