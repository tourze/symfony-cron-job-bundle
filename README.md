# Symfony Cron Job Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![License](https://img.shields.io/packagist/l/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://scrutinizer-ci.com/g/tourze/symfony-cron-job-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/symfony-cron-job-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/symfony-cron-job-bundle)

A Symfony bundle for managing and running cron jobs with flexible scheduling, registration, 
and async execution support. Features anti-duplicate execution, HTTP triggers, and Twig 
integration for serverless environments.

## Table of Contents

- [Quick Start](#quick-start)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [1. Register a Cron Job](#1-register-a-cron-job)
  - [2. Enable the Bundle](#2-enable-the-bundle)
  - [3. Add Cron Entry](#3-add-cron-entry)
  - [4. Start the Scheduler](#4-start-the-scheduler)
  - [5. Manual Execution](#5-manual-execution)
- [Features](#features)
- [Advanced Usage](#advanced-usage)
  - [Custom Lock TTL](#custom-lock-ttl)
  - [Dynamic Job Registration](#dynamic-job-registration)
- [Serverless Integration](#serverless-integration)
  - [HTTP Triggers for Serverless](#http-triggers-for-serverless)
  - [Twig Auto-trigger](#twig-auto-trigger)
- [Environment Configuration](#environment-configuration)
- [Available Commands](#available-commands)
- [API Reference](#api-reference)
  - [AsCronTask Attribute](#ascrontask-attribute)
- [Interfaces](#interfaces)
  - [CronCommandProvider Interface](#croncommandprovider-interface)
  - [CommandRequest Class](#commandrequest-class)
  - [CronTriggerService](#crontriggerservice)
- [Configuration](#configuration)
  - [Bundle Dependencies](#bundle-dependencies)
  - [Cache Configuration](#cache-configuration)
  - [Messenger Configuration](#messenger-configuration)
- [Contributing](#contributing)
- [License](#license)
- [Changelog](#changelog)

## Quick Start

### Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Extensions: `posix`, `pcntl`

### Installation

Install via Composer:

```bash
composer require tourze/symfony-cron-job-bundle
```

### 1. Register a Cron Job

Use the `AsCronTask` attribute on your Symfony command:

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask('0 * * * *')] // runs every hour
class MyHourlyCommand extends Command { ... }
```

Or implement the `CronCommandProvider` interface to provide jobs dynamically.

### 2. Enable the Bundle

Add the bundle to your `bundles.php` file:

```php
return [
    // ... other bundles
    Tourze\Symfony\CronJob\CronJobBundle::class => ['all' => true],
];
```

### 3. Add Cron Entry

```bash
php bin/console cron-job:add-cron-tab
```

This registers the main cron entry in your system crontab.

### 4. Start the Scheduler

```bash
php bin/console cron:start
```

This starts a process to check and run due cron jobs every minute.

### 5. Manual Execution

```bash
php bin/console cron:run
```

Manually trigger and run all cron jobs that are due at the current time.

## Features

- **Multiple Registration Methods**: Register cron jobs via PHP attribute or provider interface
- **Auto-generate Crontab**: Auto-generate crontab entries and manage them programmatically
- **Asynchronous Execution**: Execute scheduled commands asynchronously via Symfony Messenger
- **Anti-duplicate Execution**: Built-in locking mechanism prevents duplicate task execution
- **HTTP Triggers**: Support HTTP triggers for serverless environments (AWS Lambda, Function Compute, etc.)
- **Twig Integration**: Auto-trigger via JavaScript in templates
- **Flexible Scheduling**: Support for custom cron expressions with per-task lock TTL
- **Symfony Integration**: Integrates with Symfony Messenger, Lock, and Cache components

## Advanced Usage

### Custom Lock TTL

Configure custom lock timeout for specific tasks:

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask(
    expression: '*/5 * * * *',  // Every 5 minutes
    lockTtl: 300               // Lock for 5 minutes (300 seconds)
)]
class MyCustomCommand extends Command
{
    // ... command implementation
}
```

### Dynamic Job Registration

Implement the `CronCommandProvider` interface for dynamic job registration:

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
        $request->setLockTtl(600);  // 10 minutes
        $request->setOptions(['--env' => 'prod']);
        
        yield $request;
    }
}
```

## Serverless Integration

### HTTP Triggers for Serverless

For serverless environments, use HTTP triggers:

```bash
# Direct HTTP request
curl -X POST https://your-app.com/cron/trigger
```

### Twig Auto-trigger

Add auto-trigger functionality to your templates:

```html
{# Basic usage: trigger every 60 seconds #}
{{ cron_auto_trigger() }}

{# Custom interval: trigger every 30 seconds #}
{{ cron_auto_trigger(30000) }}

{# With debug and retry options #}
{{ cron_auto_trigger(60000, {
    debug: true,
    maxRetries: 5,
    retryDelay: 10000
}) }}
```

## Environment Configuration

Configure auto-trigger interval via environment variables:

```bash
# .env
CRON_AUTO_TRIGGER_INTERVAL=30000  # 30 seconds
```

## Available Commands

- `cron-job:add-cron-tab` - Add the main cron entry to system crontab
- `cron:start` - Start the cron scheduler daemon process
- `cron:run` - Run all cron jobs due at the current time

## API Reference

### AsCronTask Attribute

Configure cron jobs directly on command classes:

```php
#[AsCronTask(
    expression: '0 */6 * * *',  // Every 6 hours
    lockTtl: 21600             // Lock for 6 hours (21600 seconds)
)]
```

**Parameters:**
- `expression`: Cron expression (default: `'* * * * *'`)
- `lockTtl`: Lock timeout in seconds (default: `null` = 3600 seconds)

## Interfaces

### CronCommandProvider Interface

Implement this interface to provide dynamic cron jobs:

```php
interface CronCommandProvider
{
    public function getCommands(): iterable;
}
```

### CommandRequest Class

Configure dynamic cron job requests:

```php
$request = new CommandRequest();
$request->setCommand('app:example');
$request->setCronExpression('0 2 * * *');
$request->setLockTtl(7200);
$request->setOptions(['--batch-size' => 100]);
```

**Methods:**
- `setCommand(string $command)`: Set the command name
- `setCronExpression(string $expression)`: Set the cron expression
- `setLockTtl(?int $ttl)`: Set the lock timeout
- `setOptions(array $options)`: Set command options

### CronTriggerService

Main service for triggering cron jobs:

```php
public function triggerScheduledTasks(): bool
```

Returns `true` if tasks were triggered, `false` if already triggered this minute.

## Configuration

### Bundle Dependencies

This bundle depends on the following packages:
- `tourze/async-command-bundle` - For asynchronous command execution
- `tourze/lock-service-bundle` - For task locking mechanism
- `tourze/symfony-routing-auto-loader-bundle` - For auto-routing

### Cache Configuration

The bundle uses Symfony's cache system for anti-duplicate execution.
Configure your cache adapter in `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        app: cache.adapter.redis  # Or your preferred adapter
```

### Messenger Configuration

For asynchronous execution, configure Symfony Messenger:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: "%env(MESSENGER_TRANSPORT_DSN)%"
        routing:
            'Tourze\AsyncCommandBundle\Message\RunCommandMessage': async
```

## Contributing

- Open issues or pull requests on GitHub
- Follow PSR coding standards
- Write and run tests before submitting PRs

## License

MIT License. See [LICENSE](LICENSE) for details.

## Changelog

See Git history for latest changes and releases.
