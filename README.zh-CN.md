# Symfony Cron Job Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![构建状态](https://img.shields.io/travis/tourze/symfony-cron-job-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/symfony-cron-job-bundle)
[![质量评分](https://img.shields.io/scrutinizer/g/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/symfony-cron-job-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/symfony-cron-job-bundle)

一个用于管理和运行定时任务的 Symfony Bundle，支持灵活的任务注册、调度与异步执行。

## 功能特性

- 通过 PHP Attribute 或 Provider 接口注册定时任务
- 自动生成并管理系统 Crontab 条目
- 支持异步执行定时命令
- 灵活自定义 Cron 表达式
- 内置命令行工具，便于任务调度与运行
- 集成 Symfony Messenger 与 Lock 组件
- **内置防重复执行机制，确保同一任务在同一时间点只会触发一次**
- **支持自定义锁 TTL 和可选的防重复执行配置**
- **支持 HTTP 触发和 Terminate 事件触发，适用于 Serverless 环境**

## 安装说明

### 环境要求

- PHP >= 8.1
- Symfony >= 6.4
- 扩展：`posix`、`pcntl`

### Composer 安装

```bash
composer require tourze/symfony-cron-job-bundle
```

## 快速开始

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

### 2. 添加 Crontab 条目

```bash
php bin/console cron-job:add-cron-tab
```

注册主调度入口到系统 Crontab。

### 3. 启动调度进程

```bash
php bin/console cron:start
```

启动进程，每分钟检查并执行到期任务。

## 详细文档

### 防重复执行机制

Bundle 内置了强大的防重复执行机制：

1. **锁机制**：使用 Symfony Lock 组件，所有任务在执行前都会获取锁
2. **锁键生成**：基于命令名、参数和执行时间生成唯一键值
3. **自定义 TTL**：可为每个任务设置不同的锁定时长（默认 3600 秒）
4. **分布式支持**：通过配置不同的锁存储（Redis、数据库等）支持分布式部署

### HTTP 触发支持（适用于 Serverless 环境）

在无法部署传统 cron 任务的环境（如函数计算 FC、AWS Lambda 等），可以使用 HTTP 触发功能：

#### HTTP 轮询触发

```bash
# 直接 HTTP 请求触发
curl -X POST https://your-app.com/cron/trigger
```

#### Terminate 事件触发

系统会在普通 HTTP 请求结束后按概率触发定时任务检查。这种方式：

- 不会阻塞正常请求响应
- 按概率执行，避免过度消耗资源
- 适合流量较大的应用作为兜底方案

#### 安全建议

1. **限制访问**：在 Web 服务器或云服务商层面限制访问来源
2. **监控触发频率**：避免过于频繁的触发导致资源浪费
3. **使用 HTTPS**：确保请求传输安全
4. **防火墙保护**：配置防火墙规则限制外部访问

### Provider 接口使用

```php
use Tourze\Symfony\CronJob\Provider\CronCommandProvider;
use Tourze\Symfony\CronJob\Request\CommandRequest;

class MyCustomProvider implements CronCommandProvider
{
    public function getCommands(): iterable
    {
        $request = new CommandRequest();
        $request->setCommand('app:process-queue');
        $request->setCronExpression('*/10 * * * *');
        $request->setLockTtl(600);  // 10 分钟锁定
        
        yield $request;
    }
}
```

### Twig 模板集成

Bundle 提供了 `cron_auto_trigger` Twig 函数，可以在模板中自动注入 JavaScript 代码来定时触发定时任务：

```twig
{# 基础用法：每 60 秒触发一次 #}
{{ cron_auto_trigger() }}

{# 自定义触发间隔：每 30 秒触发一次 #}
{{ cron_auto_trigger(30000) }}

{# 开启调试模式 #}
{{ cron_auto_trigger(null, { debug: true }) }}

{# 完整配置示例 #}
{{ cron_auto_trigger(120000, {
    debug: true,           {# 开启控制台日志 #}
    maxRetries: 5,        {# 最大重试次数 #}
    retryDelay: 10000     {# 重试延迟（毫秒） #}
}) }}
```

#### 配置说明

- **interval**：触发间隔时间（毫秒），默认 60000（60秒）
- **debug**：是否开启调试日志，默认 false
- **maxRetries**：请求失败时的最大重试次数，默认 3
- **retryDelay**：重试延迟时间（毫秒），默认 5000

#### 环境变量配置

可以通过环境变量设置默认触发间隔：

```env
# .env
CRON_AUTO_TRIGGER_INTERVAL=30000  # 30 秒
```

#### 使用场景

此功能特别适用于：

- **共享主机环境**：无法配置系统 crontab
- **Serverless 应用**：需要定期触发任务但无后台进程
- **开发测试**：快速测试定时任务功能
- **用户活跃时触发**：只在有用户访问时执行任务

### 更多特性

- 支持 Attribute 与 Provider 两种注册方式
- 灵活 Cron 表达式
- Messenger 异步执行
- 分布式部署支持（通过配置 Redis/数据库锁存储）
- Twig 模板集成，支持前端自动触发
- 更多高级配置请参考源码与注释

## 贡献指南

- 在 GitHub 提交 Issue 或 PR
- 遵循 PSR 代码规范
- 提交 PR 前请编写并通过测试

## 版权和许可

MIT 协议，详见 [LICENSE](LICENSE)

## 更新日志

变更记录请参见 Git 历史和版本发布说明。
