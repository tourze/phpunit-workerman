<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Mock;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Tourze\PHPUnitWorkerman\Mock\TestableTcpConnection;
use Workerman\Connection\TcpConnection;

/**
 * @internal
 */
#[CoversClass(TestableTcpConnection::class)]
final class TestableTcpConnectionTest extends WorkermanTestCase
{
    private TestableTcpConnection $connection;

    protected function onSetUp(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            throw new SocketException('Failed to create stream');
        }
        $this->connection = new TestableTcpConnection(new MockEventLoop(), $stream);
    }

    protected function onTearDown(): void
    {
        $this->connection->_sentData = [];
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
        $connection = new TestableTcpConnection(new MockEventLoop(), $stream);

        $this->assertInstanceOf(TestableTcpConnection::class, $connection);
        $this->assertIsArray($connection->getSentData());
        $this->assertEmpty($connection->getSentData());
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

    public function testSendAlwaysReturnsTrue(): void
    {
        $results = [];

        $results[] = $this->connection->send('Message 1');
        $results[] = $this->connection->send('Message 2');
        $results[] = $this->connection->send('');
        $results[] = $this->connection->send(null);
        $results[] = $this->connection->send(0);

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
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
        $connection = new TestableTcpConnection(new MockEventLoop(), $stream);

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
        $booleanData = true;
        $floatData = 3.14;

        $this->connection->send($stringData);
        $this->connection->send($numericData);
        $this->connection->send($arrayData);
        $this->connection->send($objectData);
        $this->connection->send($booleanData);
        $this->connection->send($floatData);

        $sentData = $this->connection->getSentData();

        $this->assertEquals($stringData, $sentData[0]);
        $this->assertEquals($numericData, $sentData[1]);
        $this->assertEquals($arrayData, $sentData[2]);
        $this->assertEquals($objectData, $sentData[3]);
        $this->assertEquals($booleanData, $sentData[4]);
        $this->assertEquals($floatData, $sentData[5]);
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

    public function testSendWithFalse(): void
    {
        $result = $this->connection->send(false);

        $this->assertTrue($result);
        $this->assertEquals([false], $this->connection->getSentData());
    }

    public function testSendDataIntegrity(): void
    {
        $complexData = [
            'type' => 'message',
            'payload' => [
                'id' => 123,
                'content' => 'Complex message',
                'metadata' => [
                    'timestamp' => time(),
                    'user_id' => 456,
                    'nested' => [
                        'level' => 3,
                        'values' => ['a', 'b', 'c'],
                    ],
                ],
            ],
        ];

        $this->connection->send($complexData);

        $sentData = $this->connection->getSentData();
        $this->assertEquals([$complexData], $sentData);
        $this->assertEquals($complexData, $sentData[0]);
    }

    public function testConsecutiveSendsWithSameData(): void
    {
        $data = 'Repeated message';

        $this->connection->send($data);
        $this->connection->send($data);
        $this->connection->send($data);

        $sentData = $this->connection->getSentData();
        $this->assertCount(3, $sentData);
        $this->assertEquals([$data, $data, $data], $sentData);
    }

    public function testLargeDataSend(): void
    {
        $largeData = str_repeat('Large data chunk ', 1000);

        $result = $this->connection->send($largeData);

        $this->assertTrue($result);
        $this->assertEquals([$largeData], $this->connection->getSentData());
        $this->assertEquals($largeData, $this->connection->getSentData()[0]);
    }

    public function testRawFlagDoesNotAffectDataStorage(): void
    {
        $data1 = 'Normal send';
        $data2 = 'Raw send';

        $this->connection->send($data1, false);
        $this->connection->send($data2, true);

        $sentData = $this->connection->getSentData();
        $this->assertEquals([$data1, $data2], $sentData);
    }

    public function testMultipleSendsWithDifferentRawFlags(): void
    {
        $this->connection->send('msg1', false);
        $this->connection->send('msg2', true);
        $this->connection->send('msg3');
        $this->connection->send('msg4', false);
        $this->connection->send('msg5', true);

        $expected = ['msg1', 'msg2', 'msg3', 'msg4', 'msg5'];
        $this->assertEquals($expected, $this->connection->getSentData());
    }

    public function testSerializableObjectSend(): void
    {
        $object = new \stdClass();
        $object->id = 123;
        $object->name = 'Test Object';
        $object->data = ['key1' => 'value1', 'key2' => 'value2'];

        $this->connection->send($object);

        $sentData = $this->connection->getSentData();
        $this->assertCount(1, $sentData);
        $this->assertEquals($object, $sentData[0]);

        // Add type assertion for PHPStan Level 9 compliance
        $sentObject = $sentData[0];
        $this->assertInstanceOf(\stdClass::class, $sentObject);
        /** @var \stdClass $sentObject */

        $this->assertEquals($object->id, $sentObject->id);
        $this->assertEquals($object->name, $sentObject->name);
        $this->assertEquals($object->data, $sentObject->data);
    }

    public function testConcurrentDataTypeMixing(): void
    {
        $mixedData = [
            'string',
            123,
            ['array' => 'value'],
            true,
            false,
            null,
            3.14159,
            new \stdClass(),
        ];

        foreach ($mixedData as $data) {
            $result = $this->connection->send($data);
            $this->assertTrue($result);
        }

        $this->assertEquals($mixedData, $this->connection->getSentData());
    }

    public function testConnectionState(): void
    {
        $this->assertInstanceOf(TestableTcpConnection::class, $this->connection);

        $this->connection->send('Test message');

        $this->assertInstanceOf(TestableTcpConnection::class, $this->connection);
        $this->assertEquals(['Test message'], $this->connection->getSentData());
    }
}
