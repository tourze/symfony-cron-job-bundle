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

- 支持 Attribute 与 Provider 两种注册方式
- 灵活 Cron 表达式
- Messenger 异步执行
- 更多高级配置请参考源码与注释

## 贡献指南

- 在 GitHub 提交 Issue 或 PR
- 遵循 PSR 代码规范
- 提交 PR 前请编写并通过测试

## 版权和许可

MIT 协议，详见 [LICENSE](LICENSE)

## 更新日志

变更记录请参见 Git 历史和版本发布说明。
