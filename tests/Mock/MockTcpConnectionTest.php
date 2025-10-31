<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Mock;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Tourze\PHPUnitWorkerman\Mock\MockTcpConnection;
use Workerman\Connection\TcpConnection;

/**
 * @internal
 */
#[CoversClass(MockTcpConnection::class)]
final class MockTcpConnectionTest extends WorkermanTestCase
{
    private MockTcpConnection $connection;

    protected function onSetUp(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            throw new SocketException('Failed to create stream');
        }
        $this->connection = new MockTcpConnection(new MockEventLoop(), $stream);
    }

    protected function onTearDown(): void
    {
        $this->connection->_sentData = [];
        $this->connection->sendCallback = null;
    }

    public function testExtendsTcpConnection(): void
    {
        $this->assertInstanceOf(TcpConnection::class, $this->connection);
    }

    public function testInstanceInstantiation(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            throw new SocketException('Failed to create stream');
        }
        $connection = new MockTcpConnection(new MockEventLoop(), $stream);

        $this->assertInstanceOf(MockTcpConnection::class, $connection);
        $this->assertIsArray($connection->getSentData());
        $this->assertEmpty($connection->getSentData());
        $this->assertNull($connection->sendCallback);
    }

    public function testSendStoresDataInSentDataArray(): void
    {
        $testData = 'Hello World';

        $result = $this->connection->send($testData);

        $this->assertTrue($result);
        $this->assertEquals([$testData], $this->connection->getSentData());
    }

    public function testSendWithMultipleMessages(): void
    {
        $message1 = 'First message';
        $message2 = 'Second message';
        $message3 = 'Third message';

        $this->connection->send($message1);
        $this->connection->send($message2);
        $this->connection->send($message3);

        $sentData = $this->connection->getSentData();
        $this->assertCount(3, $sentData);
        $this->assertEquals($message1, $sentData[0]);
        $this->assertEquals($message2, $sentData[1]);
        $this->assertEquals($message3, $sentData[2]);
    }

    public function testSendWithRawFlag(): void
    {
        $testData = 'Raw data';

        $result = $this->connection->send($testData, true);

        $this->assertTrue($result);
        $this->assertEquals([$testData], $this->connection->getSentData());
    }

    public function testSendCallsCallbackWhenSet(): void
    {
        $callbackData = null;
        $this->connection->sendCallback = function ($data) use (&$callbackData): void {
            $callbackData = $data;
        };

        $testData = 'Callback test';
        $this->connection->send($testData);

        $this->assertEquals($testData, $callbackData);
        $this->assertEquals([$testData], $this->connection->getSentData());
    }

    public function testSendWithoutCallbackDoesNotThrow(): void
    {
        $this->connection->sendCallback = null;

        $result = $this->connection->send('Test data');

        $this->assertTrue($result);
        $this->assertEquals(['Test data'], $this->connection->getSentData());
    }

    public function testGetSentDataReturnsArrayOfSentMessages(): void
    {
        $messages = ['msg1', 'msg2', 'msg3'];

        foreach ($messages as $message) {
            $this->connection->send($message);
        }

        $sentData = $this->connection->getSentData();
        $this->assertIsArray($sentData);
        $this->assertEquals($messages, $sentData);
    }

    public function testGetSentDataReturnsEmptyArrayInitially(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            throw new SocketException('Failed to create stream');
        }
        $connection = new MockTcpConnection(new MockEventLoop(), $stream);

        $this->assertIsArray($connection->getSentData());
        $this->assertEmpty($connection->getSentData());
    }

    public function testSendDataAccumulation(): void
    {
        $this->connection->send('First');
        $this->assertCount(1, $this->connection->getSentData());

        $this->connection->send('Second');
        $this->assertCount(2, $this->connection->getSentData());

        $this->connection->send('Third');
        $this->assertCount(3, $this->connection->getSentData());

        $this->assertEquals(['First', 'Second', 'Third'], $this->connection->getSentData());
    }

    public function testSendWithVariousDataTypes(): void
    {
        $stringData = 'String data';
        $numericData = 12345;
        $arrayData = ['key' => 'value'];
        $objectData = new \stdClass();
        $objectData->property = 'value';

        $this->connection->send($stringData);
        $this->connection->send($numericData);
        $this->connection->send($arrayData);
        $this->connection->send($objectData);

        $sentData = $this->connection->getSentData();

        $this->assertEquals($stringData, $sentData[0]);
        $this->assertEquals($numericData, $sentData[1]);
        $this->assertEquals($arrayData, $sentData[2]);
        $this->assertEquals($objectData, $sentData[3]);
    }

    public function testCallbackReceivesExactDataSent(): void
    {
        $receivedData = [];
        $this->connection->sendCallback = function ($data) use (&$receivedData): void {
            $receivedData[] = $data;
        };

        $testMessages = ['Hello', 'World', 'Test'];

        foreach ($testMessages as $message) {
            $this->connection->send($message);
        }

        $this->assertEquals($testMessages, $receivedData);
        $this->assertEquals($testMessages, $this->connection->getSentData());
    }

    public function testMultipleCallbacksOverride(): void
    {
        $firstCallbackExecuted = false;
        $secondCallbackExecuted = false;

        $this->connection->sendCallback = function () use (&$firstCallbackExecuted): void {
            $firstCallbackExecuted = true;
        };

        $this->connection->sendCallback = function () use (&$secondCallbackExecuted): void {
            $secondCallbackExecuted = true;
        };

        $this->connection->send('Test');

        $this->assertFalse($firstCallbackExecuted);
        $this->assertTrue($secondCallbackExecuted);
    }

    public function testCallbackExceptionDoesNotAffectSendResult(): void
    {
        $this->connection->sendCallback = function (): void {
            throw new SocketException('Callback exception');
        };

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Callback exception');

        $this->connection->send('Test data');
    }

    public function testSendReturnsNullableBoolean(): void
    {
        $result = $this->connection->send('Test');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testSentDataPropertyIsPublic(): void
    {
        $this->connection->send('Test');

        $this->assertIsArray($this->connection->_sentData);
        $this->assertEquals(['Test'], $this->connection->_sentData);

        $this->connection->_sentData[] = 'Manual addition';
        $this->assertEquals(['Test', 'Manual addition'], $this->connection->getSentData());
    }

    public function testSendCallbackPropertyIsPublic(): void
    {
        $callback = function (): void {};

        $this->connection->sendCallback = $callback;

        $this->assertSame($callback, $this->connection->sendCallback);
    }

    public function testResetSentData(): void
    {
        $this->connection->send('First message');
        $this->connection->send('Second message');

        $this->assertCount(2, $this->connection->getSentData());

        $this->connection->_sentData = [];

        $this->assertEmpty($this->connection->getSentData());
    }

    public function testSendWithEmptyString(): void
    {
        $result = $this->connection->send('');

        $this->assertTrue($result);
        $this->assertEquals([''], $this->connection->getSentData());
    }

    public function testSendWithNull(): void
    {
        $result = $this->connection->send(null);

        $this->assertTrue($result);
        $this->assertEquals([null], $this->connection->getSentData());
    }

    public function testSendWithZero(): void
    {
        $result = $this->connection->send(0);

        $this->assertTrue($result);
        $this->assertEquals([0], $this->connection->getSentData());
    }

    public function testCallbackWithComplexData(): void
    {
        $complexData = [
            'type' => 'message',
            'payload' => [
                'id' => 123,
                'content' => 'Complex message',
                'metadata' => [
                    'timestamp' => time(),
                    'user_id' => 456,
                ],
            ],
        ];

        $receivedData = null;
        $this->connection->sendCallback = function ($data) use (&$receivedData): void {
            $receivedData = $data;
        };

        $this->connection->send($complexData);

        $this->assertEquals($complexData, $receivedData);
        $this->assertEquals([$complexData], $this->connection->getSentData());
    }
}
