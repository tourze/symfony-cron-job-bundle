<?php

namespace Tourze\Symfony\CronJob\Tests\Request;

use PHPUnit\Framework\TestCase;
use Tourze\Symfony\Async\Message\RunCommandMessage;
use Tourze\Symfony\CronJob\Request\CommandRequest;

class CommandRequestTest extends TestCase
{
    public function test_extends_run_command_message()
    {
        $request = new CommandRequest();
        $this->assertInstanceOf(RunCommandMessage::class, $request);
    }

    public function test_get_set_cron_expression()
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

    public function test_inherits_command_and_options_from_parent()
    {
        $request = new CommandRequest();
        $command = 'app:test-command';
        $options = ['option1' => 'value1'];

        $request->setCommand($command);
        $request->setOptions($options);

        $this->assertEquals($command, $request->getCommand());
        $this->assertEquals($options, $request->getOptions());
    }
}
