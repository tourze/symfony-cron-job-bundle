<?php

namespace Tourze\Symfony\CronJob\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\Symfony\CronJob\Exception\CronJobException;

class CronJobExceptionTest extends TestCase
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