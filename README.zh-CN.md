# Symfony Cron Job Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![PHP 版本](https://img.shields.io/packagist/php-v/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![许可证](https://img.shields.io/packagist/l/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/php-monorepo/actions)
[![代码覆盖率](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)
[![质量评分](https://img.shields.io/scrutinizer/g/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://scrutinizer-ci.com/g/tourze/symfony-cron-job-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)

一个用于管理和运行定时任务的 Symfony Bundle，支持灵活的任务注册、调度与异步执行。具备防重复执行、HTTP 触发和 Twig 集成等特性，适用于 Serverless 环境。

## 目录

- [快速开始](#快速开始)
  - [环境要求](#环境要求)
  - [安装说明](#安装说明)
  - [1. 注册定时任务](#1-注册定时任务)
  - [2. 启用 Bundle](#2-启用-bundle)
  - [3. 添加 Crontab 条目](#3-添加-crontab-条目)
  - [4. 启动调度进程](#4-启动调度进程)
  - [5. 手动执行](#5-手动执行)
- [可用命令](#可用命令)
- [功能特性](#功能特性)
- [高级用法](#高级用法)
  - [自定义锁定时长](#自定义锁定时长)
  - [动态任务注册](#动态任务注册)
- [Serverless 集成](#serverless-集成)
  - [HTTP 触发（Serverless 支持）](#http-触发serverless-支持)
  - [Twig 自动触发](#twig-自动触发)
- [环境变量配置](#环境变量配置)
- [API 接口参考](#api-接口参考)
  - [AsCronTask Attribute](#ascrontask-attribute)
- [接口说明](#接口说明)
  - [CronCommandProvider 接口](#croncommandprovider-接口)
  - [CommandRequest 类](#commandrequest-类)
  - [CronTriggerService 服务](#crontriggerservice-服务)
- [配置说明](#配置说明)
  - [Bundle 依赖](#bundle-依赖)
  - [缓存配置](#缓存配置)
  - [消息配置](#消息配置)
- [贡献指南](#贡献指南)
- [版权和许可](#版权和许可)
- [更新日志](#更新日志)

## 快速开始

### 环境要求

- PHP >= 8.1
- Symfony >= 6.4
- 扩展：`posix`、`pcntl`

### 安装说明

使用 Composer 安装：

```bash
composer require tourze/symfony-cron-job-bundle
```

### 1. 注册定时任务

为你的 Symfony 命令类添加 `AsCronTask` Attribute：

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask('0 * * * *')] // 每小时执行一次
class MyHourlyCommand extends Command { ... }

// 高级用法：自定义锁配置
#[AsCronTask(
    expression: '*/5 * * * *',     // 每 5 分钟执行
    lockTtl: 300                   // 锁定 5 分钟（300秒），默认 3600 秒
)]
class MyCustomCommand extends Command { ... }
```

或实现 `CronCommandProvider` 接口，动态提供任务。

### 2. 启用 Bundle

在 `bundles.php` 文件中添加 bundle：

```php
return [
    // ... 其他 bundle
    Tourze\Symfony\CronJob\CronJobBundle::class => ['all' => true],
];
```

### 3. 添加 Crontab 条目

```bash
php bin/console cron-job:add-cron-tab
```

注册主调度入口到系统 Crontab。

### 4. 启动调度进程

```bash
php bin/console cron:start
```

启动进程，每分钟检查并执行到期任务。

### 5. 手动执行

```bash
php bin/console cron:run
```

手动触发并运行当前时间到期的所有定时任务。

## 可用命令

- `cron-job:add-cron-tab` - 将主要的定时任务条目添加到系统 crontab
- `cron:start` - 启动定时任务调度守护进程
- `cron:run` - 运行当前时间到期的所有定时任务

## 功能特性

- **多种注册方式**：通过 PHP Attribute 或 Provider 接口注册定时任务
- **自动 Crontab 管理**：自动生成并管理系统 Crontab 条目
- **异步执行**：通过 Symfony Messenger 异步执行定时命令
- **防重复执行**：内置锁机制，确保同一任务不会重复执行
- **HTTP 触发**：支持 HTTP 触发，适用于 Serverless 环境（AWS Lambda、函数计算等）
- **Twig 集成**：在模板中通过 JavaScript 自动触发定时任务
- **灵活调度**：支持自定义 Cron 表达式和每任务锁定时长配置
- **Symfony 集成**：与 Symfony Messenger、Lock 和 Cache 组件深度集成

## 高级用法

### 自定义锁定时长

为特定任务配置自定义锁定超时：

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask(
    expression: '*/5 * * * *',     // 每 5 分钟执行
    lockTtl: 300                   // 锁定 5 分钟（300秒）
)]
class MyCustomCommand extends Command
{
    // ... 命令实现
}
```

### 动态任务注册

实现 `CronCommandProvider` 接口进行动态任务注册：

```php
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;
use Tourze\Symfony\CronJob\Request\CommandRequest;

class MyJobProvider implements CronCommandProvider
{
    public function getCommands(): iterable
    {
        $request = new CommandRequest();
        $request->setCommand('app:process-queue');
        $request->setCronExpression('*/10 * * * *');
        $request->setLockTtl(600);  // 10 分钟
        $request->setOptions(['--env' => 'prod']);
        
        yield $request;
    }
}
```

## Serverless 集成

### HTTP 触发（Serverless 支持）

在 Serverless 环境中使用 HTTP 触发：

```bash
# 直接 HTTP 请求触发
curl -X POST https://your-app.com/cron/trigger
```

### Twig 自动触发

在模板中添加自动触发功能：

```html
{# 基础用法：每 60 秒触发一次 #}
{{ cron_auto_trigger() }}

{# 自定义触发间隔：每 30 秒触发一次 #}
{{ cron_auto_trigger(30000) }}

{# 开启调试和重试选项 #}
{{ cron_auto_trigger(60000, {
    debug: true,
    maxRetries: 5,
    retryDelay: 10000
}) }}
```

## 环境变量配置

通过环境变量配置自动触发间隔：

```bash
# .env
CRON_AUTO_TRIGGER_INTERVAL=30000  # 30 秒
```

## API 接口参考

### AsCronTask Attribute

直接在命令类上配置定时任务：

```php
#[AsCronTask(
    expression: '0 */6 * * *',  // 每 6 小时执行一次
    lockTtl: 21600             // 锁定 6 小时（21600 秒）
)]
```

**参数说明：**
- `expression`: Cron 表达式（默认：`'* * * * *'`）
- `lockTtl`: 锁定超时时间（秒）（默认：`null` = 3600 秒）

## 接口说明

### CronCommandProvider 接口

实现此接口来提供动态定时任务：

```php
interface CronCommandProvider
{
    public function getCommands(): iterable;
}
```

### CommandRequest 类

配置动态定时任务请求：

```php
$request = new CommandRequest();
$request->setCommand('app:example');
$request->setCronExpression('0 2 * * *');
$request->setLockTtl(7200);
$request->setOptions(['--batch-size' => 100]);
```

**方法说明：**
- `setCommand(string $command)`: 设置命令名称
- `setCronExpression(string $expression)`: 设置 Cron 表达式
- `setLockTtl(?int $ttl)`: 设置锁定超时时间
- `setOptions(array $options)`: 设置命令选项

### CronTriggerService 服务

触发定时任务的主要服务：

```php
public function triggerScheduledTasks(): bool
```

如果任务被触发返回 `true`，如果本分钟已经触发过则返回 `false`。

## 配置说明

### Bundle 依赖

此 Bundle 依赖以下包：
- `tourze/async-command-bundle` - 异步命令执行
- `tourze/lock-service-bundle` - 任务锁定机制
- `tourze/symfony-routing-auto-loader-bundle` - 自动路由

### 缓存配置

Bundle 使用 Symfony 缓存系统防止重复执行。
在 `config/packages/cache.yaml` 中配置缓存适配器：

```yaml
framework:
    cache:
        app: cache.adapter.redis  # 或其他首选适配器
```

### 消息配置

要使用异步执行，需要配置 Symfony Messenger：

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: "%env(MESSENGER_TRANSPORT_DSN)%"
        routing:
            'Tourze\AsyncCommandBundle\Message\RunCommandMessage': async
```

## 贡献指南

- 在 GitHub 提交 Issue 或 PR
- 遵循 PSR 代码规范
- 提交 PR 前请编写并通过测试

## 版权和许可

MIT 协议，详见 [LICENSE](LICENSE)

## 更新日志

变更记录请参见 Git 历史和版本发布说明。
