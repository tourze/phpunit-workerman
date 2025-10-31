# Workerman PHPUnit 测试方案对比

本文档详细说明了我们提供的两种Workerman测试方案的区别、适用场景和使用方法。

## 🎯 核心区别

### Mock测试方案 (原方案)
- **基类**: `WorkermanTestCase`
- **特点**: 通过Mock对象模拟Workerman的行为
- **适用场景**: 测试Workerman框架本身的功能、简单的协议测试

### 真实业务测试方案 (新方案)
- **基类**: `RealWorkerTestCase` 
- **特点**: 使用真实的Worker和Connection对象，测试真实的业务逻辑
- **适用场景**: 测试实际的Workerman应用、复杂的业务逻辑、自定义Worker类

## 📋 详细对比

| 方面 | Mock测试方案 | 真实业务测试方案 |
|------|-------------|------------------|
| **Worker对象** | Mock Worker | 真实 Worker 实例 |
| **Connection对象** | Mock Connection | 真实 TcpConnection |
| **回调执行** | 可能被Mock影响 | 真实执行用户代码 |
| **业务逻辑测试** | 受限 | 完全支持 |
| **协议处理** | 模拟 | 真实协议处理 |
| **数据传输** | Mock行为 | 真实数据传输 |
| **自定义Worker类** | 不适合 | 完全支持 |
| **复杂状态管理** | 困难 | 自然支持 |

## 🚀 使用示例

### Mock测试方案使用示例

```php
<?php
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;

class SimpleProtocolTest extends WorkermanTestCase
{
    public function testBasicEcho(): void
    {
        $worker = $this->createWorker();
        
        $worker->onMessage = function ($connection, $data) {
            $connection->send('echo: ' . $data);
        };
        
        $this->startWorker($worker);
        $this->sendDataToWorker($worker, 'hello');
        
        // 这里测试的是框架行为，不是真实业务逻辑
    }
}
```

### 真实业务测试方案使用示例

```php
<?php
use Tourze\PHPUnitWorkerman\Core\RealWorkerTestCase;

class ChatServerTest extends RealWorkerTestCase
{
    public function testRealChatApplication(): void
    {
        // 创建真实的聊天服务器
        $chatServer = $this->createRealWorker('websocket://127.0.0.1:8080');
        
        // 真实的业务逻辑
        $onlineUsers = [];
        $chatRooms = [];
        
        $chatServer->onConnect = function ($connection) use (&$onlineUsers) {
            $connection->user_id = uniqid('user_');
            $onlineUsers[$connection->user_id] = $connection;
            
            // 真实的欢迎消息逻辑
            $connection->send(json_encode([
                'type' => 'welcome',
                'user_id' => $connection->user_id,
                'server_time' => time()
            ]));
        };
        
        $chatServer->onMessage = function ($connection, $data) use (&$chatRooms) {
            $message = json_decode($data, true);
            
            // 真实的消息路由逻辑
            switch ($message['type']) {
                case 'join_room':
                    $roomId = $message['room_id'];
                    if (!isset($chatRooms[$roomId])) {
                        $chatRooms[$roomId] = [];
                    }
                    $chatRooms[$roomId][$connection->user_id] = $connection;
                    
                    // 真实的房间加入逻辑
                    foreach ($chatRooms[$roomId] as $user) {
                        $user->send(json_encode([
                            'type' => 'user_joined',
                            'user_id' => $connection->user_id,
                            'room_id' => $roomId
                        ]));
                    }
                    break;
                    
                case 'chat_message':
                    // 真实的消息广播逻辑
                    $roomId = $connection->current_room ?? 'general';
                    if (isset($chatRooms[$roomId])) {
                        foreach ($chatRooms[$roomId] as $user) {
                            $user->send(json_encode([
                                'type' => 'new_message',
                                'from' => $connection->user_id,
                                'message' => $message['content'],
                                'timestamp' => microtime(true)
                            ]));
                        }
                    }
                    break;
            }
        };
        
        // 启动真实服务器
        $this->startRealWorker($chatServer);
        
        // 测试真实的用户交互
        $user1 = $this->createRealConnection($chatServer, '192.168.1.100:8001');
        $user2 = $this->createRealConnection($chatServer, '192.168.1.101:8002');
        
        // 验证欢迎消息（真实业务逻辑的结果）
        $welcome1 = implode('', $this->getSentData($user1));
        $this->assertStringContainsString('welcome', $welcome1);
        $this->assertStringContainsString('user_id', $welcome1);
        
        // 测试加入房间功能
        $this->sendRealData($chatServer, $user1, json_encode([
            'type' => 'join_room',
            'room_id' => 'tech_discussion'
        ]));
        
        $this->sendRealData($chatServer, $user2, json_encode([
            'type' => 'join_room',
            'room_id' => 'tech_discussion'
        ]));
        
        // 验证真实的业务逻辑结果
        $this->assertCount(2, $onlineUsers);
        $this->assertArrayHasKey('tech_discussion', $chatRooms);
        $this->assertCount(2, $chatRooms['tech_discussion']);
        
        // 测试聊天功能
        $this->sendRealData($chatServer, $user1, json_encode([
            'type' => 'chat_message',
            'content' => 'Hello everyone!'
        ]));
        
        // 验证消息广播到两个用户
        $user1Messages = implode('', $this->getSentData($user1));
        $user2Messages = implode('', $this->getSentData($user2));
        
        $this->assertStringContainsString('Hello everyone!', $user1Messages);
        $this->assertStringContainsString('Hello everyone!', $user2Messages);
        $this->assertStringContainsString('new_message', $user2Messages);
    }
}
```

## 🎯 选择指南

### 使用Mock测试方案的情况

✅ **适合**:
- 测试简单的协议处理
- 测试Workerman框架功能
- 快速原型验证
- 单元测试级别的测试

❌ **不适合**:
- 复杂的业务逻辑测试
- 状态管理测试
- 自定义Worker类测试
- 真实应用场景测试

### 使用真实业务测试方案的情况

✅ **适合**:
- 测试真实的Workerman应用
- 复杂的业务逻辑验证
- 自定义Worker类测试
- 状态管理和数据流测试
- 集成测试级别的测试
- 用户交互流程测试

❌ **不适合**:
- 简单的单元测试
- 框架本身的功能测试

## 🛠️ 具体使用场景示例

### 1. 测试自定义Worker类 (使用真实测试方案)

```php
class MyCustomWorker extends Worker
{
    private array $userSessions = [];
    private int $messageCount = 0;
    
    public function __construct()
    {
        parent::__construct('tcp://0.0.0.0:8080');
        $this->onMessage = [$this, 'handleMessage'];
    }
    
    public function handleMessage($connection, $data): void
    {
        $this->messageCount++;
        // 复杂的业务逻辑...
    }
    
    public function getMessageCount(): int
    {
        return $this->messageCount;
    }
}

// 测试
class MyCustomWorkerTest extends RealWorkerTestCase
{
    public function testCustomWorkerLogic(): void
    {
        $worker = new MyCustomWorker();
        $this->workers[] = $worker; // 添加到管理
        
        $this->startRealWorker($worker);
        
        $client = $this->createRealConnection($worker);
        $this->sendRealData($worker, $client, 'test message');
        
        // 测试真实的业务状态
        $this->assertEquals(1, $worker->getMessageCount());
    }
}
```

### 2. 测试HTTP API服务器 (使用真实测试方案)

```php
class ApiServerTest extends RealWorkerTestCase
{
    public function testRESTfulAPI(): void
    {
        $apiServer = $this->createRealWorker('http://127.0.0.1:8080');
        
        $apiServer->onMessage = function ($connection, $request) {
            // 真实的HTTP路由处理
            if (strpos($request, 'GET /api/users') !== false) {
                $response = "HTTP/1.1 200 OK\r\n\r\n" . 
                           json_encode(['users' => [['id' => 1, 'name' => 'John']]]);
                $connection->send($response);
            }
        };
        
        $this->startRealWorker($apiServer);
        
        $client = $this->createRealConnection($apiServer);
        $this->sendRealData($apiServer, $client, "GET /api/users HTTP/1.1\r\nHost: localhost\r\n\r\n");
        
        $response = implode('', $this->getSentData($client));
        $this->assertStringContainsString('200 OK', $response);
        $this->assertStringContainsString('John', $response);
    }
}
```

### 3. 测试简单协议 (使用Mock测试方案)

```php
class SimpleProtocolTest extends WorkermanTestCase
{
    public function testEchoProtocol(): void
    {
        $worker = $this->createWorker();
        
        $worker->onMessage = function ($connection, $data) {
            $connection->send('ECHO:' . strtoupper($data));
        };
        
        $this->startWorker($worker);
        $this->sendDataToWorker($worker, 'hello');
        
        // 简单的协议测试，Mock方案就足够了
    }
}
```

## 🔧 最佳实践

### 1. 项目结构建议

```
tests/
├── Unit/              # 使用Mock测试方案
│   ├── ProtocolTest.php
│   └── UtilityTest.php
├── Integration/       # 使用真实测试方案
│   ├── ChatServerTest.php
│   ├── ApiServerTest.php
│   └── CustomWorkerTest.php
└── Examples/
    ├── MockExamples/
    └── RealExamples/
```

### 2. 测试策略

1. **单元测试**: 使用Mock方案测试独立的函数和简单协议
2. **集成测试**: 使用真实方案测试完整的业务流程
3. **端到端测试**: 使用真实方案测试用户完整的交互流程

### 3. 性能考量

- **Mock方案**: 执行速度更快，适合大量快速测试
- **真实方案**: 更接近实际运行环境，但执行相对较慢

## 📚 总结

两种测试方案各有优势，建议根据实际需求选择：

- **开发初期，简单协议测试**: 使用Mock方案快速验证
- **业务逻辑复杂，需要状态管理**: 使用真实方案全面测试
- **混合使用**: 在同一个项目中同时使用两种方案，覆盖不同的测试需求

关键是理解你要测试的是什么：
- 如果测试的是**Workerman框架的使用方式**，用Mock方案
- 如果测试的是**你的业务逻辑和应用行为**，用真实方案

这样可以确保测试既高效又全面，真正帮助你构建可靠的Workerman应用。