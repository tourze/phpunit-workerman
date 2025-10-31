# Workerman PHPUnit 测试框架

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/phpunit-workerman.svg?style=flat-square)](https://packagist.org/packages/tourze/phpunit-workerman)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg?style=flat-square)](#)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg?style=flat-square)](#)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/phpunit-workerman.svg?style=flat-square)](https://packagist.org/packages/tourze/phpunit-workerman)

专为 Workerman 应用设计的 PHPUnit 测试框架，解决异步、事件驱动应用的测试难题。

## 目录

- [核心特性](#核心特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [核心 API](#核心-api)
- [最佳实践](#最佳实践)

## 核心特性

### 主要功能
- **事件循环模拟** - 提供完全可控的事件循环和时间控制
- **异步操作测试** - 支持定时器、延迟操作和回调测试
- **连接模拟** - 完整的 TCP 连接生命周期模拟
- **Worker 管理** - 创建、启动和管理 Worker 实例
- **专用断言** - 针对 Workerman 特性的断言方法

### 解决的问题
- 异步操作测试
- 事件循环时间控制
- 网络连接模拟
- 多进程 Worker 测试
- 定时器功能验证

## 安装

### 系统要求

- PHP 8.1 或更高版本
- ext-sockets 扩展

### 依赖项

- [workerman/workerman](https://packagist.org/packages/workerman/workerman) ^5.1
- [phpunit/phpunit](https://packagist.org/packages/phpunit/phpunit) ^10.0 (开发依赖)

### 安装命令

```bash
composer require --dev tourze/phpunit-workerman
```

## 快速开始

### 基础 Worker 测试

```php
<?php

use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Workerman\Worker;

class MyWorkerTest extends WorkermanTestCase
{
    public function testBasicWorker(): void
    {
        // 创建测试 Worker
        $worker = $this->createWorker('tcp://127.0.0.1:8080');
        
        $messageReceived = false;
        $worker->onMessage = function ($connection, $data) use (&$messageReceived) {
            $messageReceived = true;
            $connection->send('echo: ' . $data);
        };
        
        // 启动 Worker
        $this->startWorker($worker);
        
        // 模拟发送数据
        $this->sendDataToWorker($worker, 'hello');
        
        // 验证结果
        $this->assertTrue($messageReceived);
    }
}
```

### 定时器测试

```php
<?php

use Tourze\PHPUnitWorkerman\Core\AsyncTestCase;
use Workerman\Timer;

class TimerTest extends AsyncTestCase
{
    public function testTimer(): void
    {
        $executed = false;
        
        // 创建定时器
        Timer::add(0.5, function () use (&$executed) {
            $executed = true;
        }, [], false);
        
        // 快进时间
        $this->advanceTime(0.6);
        
        // 验证定时器执行
        $this->assertTrue($executed);
    }
}
```

### 连接测试

```php
<?php

use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Workerman\Connection\TcpConnection;

class ConnectionTest extends WorkermanTestCase
{
    public function testConnection(): void
    {
        $worker = $this->createWorker();
        
        $events = [];
        $worker->onConnect = function ($connection) use (&$events) {
            $events[] = 'connected';
        };
        
        $worker->onMessage = function ($connection, $data) use (&$events) {
            $events[] = "message: $data";
        };
        
        $this->startWorker($worker);
        
        // 模拟连接
        $connection = $this->createMockConnection($worker);
        $worker->onConnect($connection);
        
        // 模拟消息
        $this->sendDataToWorker($worker, 'test');
        
        $this->assertEquals(['connected', 'message: test'], $events);
    }
}
```

## 核心 API

### 测试基类

#### WorkermanTestCase
用于一般的 Workerman 应用测试，提供 Worker 管理和连接模拟。

```php
abstract class WorkermanTestCase extends TestCase
{
    // 创建测试 Worker
    protected function createWorker(string $socketName = ''): Worker;
    
    // 启动 Worker
    protected function startWorker(Worker $worker): void;
    
    // 模拟发送数据到 Worker
    protected function sendDataToWorker(Worker $worker, string $data): void;
    
    // 时间快进
    protected function fastForward(float $seconds): void;
    
    // 等待条件满足
    protected function waitFor(callable $condition, float $timeout = 5.0): void;
}
```

#### AsyncTestCase
专门用于异步操作测试

```php
abstract class AsyncTestCase extends TestCase
{
    // 时间推进
    protected function advanceTime(float $seconds): void;
    
    // 等待直到条件满足
    protected function waitUntil(callable $condition, float $timeout = 5.0): void;
    
    // 运行异步操作
    protected function runAsync(callable $asyncOperation, float $timeout = 5.0);
    
    // 并行运行多个异步操作
    protected function runAsyncParallel(array $operations, float $timeout = 5.0): array;
}
```

### 专用断言方法

```php
// 断言连接状态
$this->assertConnectionStatus($connection, TcpConnection::STATUS_ESTABLISHED);

// 断言连接缓冲区为空
$this->assertConnectionBufferEmpty($connection);

// 断言回调被调用
$this->assertCallbackCalled($callback, $trigger);

// 断言回调被触发
$this->assertCallbackTriggered($callback, $trigger);
```

## 高级用法

### 复杂异步流程测试

```php
public function testComplexAsyncFlow(): void
{
    $results = [];
    
    // 模拟复杂异步操作链
    $this->runAsync(function ($resolve, $reject) use (&$results) {
        Timer::add(0.1, function () use (&$results, $resolve) {
            $results[] = 'step1';
            
            Timer::add(0.2, function () use (&$results, $resolve) {
                $results[] = 'step2';
                
                Timer::add(0.3, function () use (&$results, $resolve) {
                    $results[] = 'step3';
                    $resolve($results);
                }, [], false);
            }, [], false);
        }, [], false);
    });
    
    $this->assertEquals(['step1', 'step2', 'step3'], $results);
}
```

### 高并发连接测试

```php
public function testHighConcurrency(): void
{
    $worker = $this->createWorker();
    $connectionCount = 0;
    
    $worker->onConnect = function () use (&$connectionCount) {
        $connectionCount++;
    };
    
    // 创建1000个并发连接
    $connections = $this->createMassConnections($worker, 1000);
    
    $this->assertEquals(1000, $connectionCount);
    $this->assertCount(1000, $connections);
}
```

## 最佳实践

### 1. 测试初始化
正确设置和清理测试环境

```php
class MyTest extends WorkermanTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // 必须调用父类方法
    }
    
    protected function tearDown(): void
    {
        parent::tearDown(); // 必须调用父类方法
    }
}
```

### 2. 时间控制
使用时间快进而不是真实等待

```php
// 推荐：瞬间完成
$this->fastForward(2.0);

// 不推荐：真实等待
sleep(2);
```

### 3. 异步操作超时
为异步操作设置合理的超时时间

```php
$this->waitFor(fn() => $condition, 5.0); // 5秒超时
$this->runAsync($operation, 10.0); // 10秒超时
```

## 常见问题

### 调试相关

**Q: 定时器测试失败怎么办？**
A: 使用 `fastForward()` 方法而不是真实等待

**Q: 连接状态异常？**
A: 确保使用 `mockConnectionClose()` 等方法正确管理连接生命周期

**Q: 测试运行超时？**
A: 检查异步操作是否正确完成，确保没有无限循环

**Q: 测试环境初始化失败？**
A: 确保测试类正确继承并调用 `parent::setUp()` 和 `parent::tearDown()`

### 性能优化

如果测试较慢，可以尝试：

```php
// 减少 Worker 数量
$this->createWorker(); // 而不是多个

// 调试定时器状态
$timerCount = $this->getPendingTimerCount();
$timers = $this->eventLoop->getTimers();
```

## 贡献指南

欢迎提交 Issue 和 Pull Request 来改进这个测试框架。

### 开发环境
```bash
git clone <repository>
cd phpunit-workerman
composer install
vendor/bin/phpunit
```

### 代码规范
遵循 PSR-12 编码标准

## 许可证

MIT 许可证

## 相关资源

- [Workerman 官方文档](https://www.workerman.net/)
- [PHPUnit 文档](https://phpunit.de/)
- [Workerman GitHub](https://github.com/walkor/Workerman)

---

这个测试框架让您能够轻松测试 Workerman 应用中的异步操作、事件处理和网络通信功能。