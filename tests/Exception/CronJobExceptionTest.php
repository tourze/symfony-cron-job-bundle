<?php

namespace Tourze\Symfony\CronJob\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\Symfony\CronJob\Exception\CronJobException;

/**
 * @internal
 */
#[CoversClass(CronJobException::class)]
final class CronJobExceptionTest extends AbstractExceptionTestCase
{
    public function testIsRuntimeException(): void
    {
        $exception = new CronJobException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testCanBeCreatedWithMessageAndCode(): void
    {
        $exception = new CronJobException('Test message', 123);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
    }

    public function testCanBeCreatedWithPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new CronJobException('Test message', 0, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
