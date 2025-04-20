# Symfony Cron Job Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/symfony-cron-job-bundle)
[![Build Status](https://img.shields.io/travis/tourze/symfony-cron-job-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/symfony-cron-job-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/symfony-cron-job-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/symfony-cron-job-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/symfony-cron-job-bundle)

A Symfony bundle for managing and running cron jobs with flexible scheduling, registration, and async execution support.

## Features

- Register cron jobs via PHP attribute or provider interface
- Auto-generate crontab entries and manage them programmatically
- Asynchronous execution of scheduled commands
- Support for custom cron expressions
- Built-in commands for running and scheduling jobs
- Integrates with Symfony Messenger and Lock

## Installation

### Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Extensions: `posix`, `pcntl`

### Install via Composer

```bash
composer require tourze/symfony-cron-job-bundle
```

## Quick Start

### 1. Register a Cron Job

Use the `AsCronTask` attribute on your Symfony command:

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask('0 * * * *')] // runs every hour
class MyHourlyCommand extends Command { ... }
```

Or implement the `CronCommandProvider` interface to provide jobs dynamically.

### 2. Add Cron Entry

```bash
php bin/console cron-job:add-cron-tab
```

This registers the main cron entry in your system crontab.

### 3. Start the Scheduler

```bash
php bin/console cron:start
```

This starts a process to check and run due cron jobs every minute.

## Documentation

- Cron job registration via attribute or provider
- Custom cron expressions
- Async execution with Messenger
- Advanced configuration: see source code and comments

## Contributing

- Open issues or pull requests on GitHub
- Follow PSR coding standards
- Write and run tests before submitting PRs

## License

MIT License. See [LICENSE](LICENSE) for details.

## Changelog

See Git history for latest changes and releases.
