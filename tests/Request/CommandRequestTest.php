<?php

namespace Tourze\Symfony\CronJob\Tests\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tourze\AsyncCommandBundle\Message\RunCommandMessage;
use Tourze\AsyncContracts\AsyncMessageInterface;
use Tourze\Symfony\CronJob\Request\CommandRequest;

/**
 * @internal
 */
#[CoversClass(CommandRequest::class)]
final class CommandRequestTest extends TestCase
{
    public function testImplementsAsyncMessageInterface(): void
    {
        $request = new CommandRequest();
        $this->assertInstanceOf(AsyncMessageInterface::class, $request);
    }

    public function testWrapsRunCommandMessage(): void
    {
        $request = new CommandRequest();
        $this->assertInstanceOf(RunCommandMessage::class, $request->getRunCommandMessage());
    }

    public function testGetSetCronExpression(): void
    {
        $request = new CommandRequest();

        // 不直接访问未初始化的属性，先设置再获取
        $expression = '*/5 * * * *';
        $request->setCronExpression($expression);
        $this->assertEquals($expression, $request->getCronExpression());

        // 更新表达式
        $newExpression = '0 0 * * *';
        $request->setCronExpression($newExpression);
        $this->assertEquals($newExpression, $request->getCronExpression());
    }

    public function testDelegatesCommandAndOptionsToWrappedMessage(): void
    {
        $request = new CommandRequest();
        $command = 'app:test-command';
        $options = ['option1' => 'value1'];

        $request->setCommand($command);
        $request->setOptions($options);

        $this->assertEquals($command, $request->getCommand());
        $this->assertEquals($options, $request->getOptions());

        // Verify the wrapped message also has the same values
        $wrappedMessage = $request->getRunCommandMessage();
        $this->assertEquals($command, $wrappedMessage->getCommand());
        $this->assertEquals($options, $wrappedMessage->getOptions());
    }

    public function testGetSetLockTtl(): void
    {
        $request = new CommandRequest();

        // 默认值为 null
        $this->assertNull($request->getLockTtl());

        // 设置锁 TTL
        $ttl = 600;
        $request->setLockTtl($ttl);
        $this->assertEquals($ttl, $request->getLockTtl());

        // 设置为 null
        $request->setLockTtl(null);
        $this->assertNull($request->getLockTtl());
    }
}
