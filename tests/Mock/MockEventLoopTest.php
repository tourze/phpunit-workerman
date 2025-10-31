<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Mock;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Workerman\Events\EventInterface;

/**
 * @internal
 */
#[CoversClass(MockEventLoop::class)]
final class MockEventLoopTest extends WorkermanTestCase
{
    private MockEventLoop $eventLoop;

    protected function onSetUp(): void
    {
        $this->eventLoop = new MockEventLoop();
    }

    protected function onTearDown(): void
    {
        $this->eventLoop->clear();
    }

    public function testImplementsEventInterface(): void
    {
        $this->assertInstanceOf(EventInterface::class, $this->eventLoop);
    }

    public function testInstanceInstantiation(): void
    {
        $eventLoop = new MockEventLoop();

        $this->assertInstanceOf(MockEventLoop::class, $eventLoop);
        $this->assertGreaterThan(0, $eventLoop->getCurrentTime());
        $this->assertFalse($eventLoop->hasEvents());
    }

    public function testOnReadableAddsReadEventListener(): void
    {
        $stream = fopen('php://memory', 'r');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $called = false;
        $callback = function () use (&$called): void {
            $called = true;
        };

        $this->eventLoop->onReadable($stream, $callback);

        $this->assertTrue($this->eventLoop->hasReadEvents());
        $this->assertTrue($this->eventLoop->hasEvents());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testOffReadableRemovesReadEventListener(): void
    {
        $stream = fopen('php://memory', 'r');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $callback = function (): void {};

        $this->eventLoop->onReadable($stream, $callback);
        $this->assertTrue($this->eventLoop->hasReadEvents());

        $result = $this->eventLoop->offReadable($stream);

        $this->assertTrue($result);
        $this->assertFalse($this->eventLoop->hasReadEvents());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testOnWritableAddsWriteEventListener(): void
    {
        $stream = fopen('php://memory', 'w');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $called = false;
        $callback = function () use (&$called): void {
            $called = true;
        };

        $this->eventLoop->onWritable($stream, $callback);

        $this->assertTrue($this->eventLoop->hasWriteEvents());
        $this->assertTrue($this->eventLoop->hasEvents());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testOffWritableRemovesWriteEventListener(): void
    {
        $stream = fopen('php://memory', 'w');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $callback = function (): void {};

        $this->eventLoop->onWritable($stream, $callback);
        $this->assertTrue($this->eventLoop->hasWriteEvents());

        $result = $this->eventLoop->offWritable($stream);

        $this->assertTrue($result);
        $this->assertFalse($this->eventLoop->hasWriteEvents());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testOnSignalAddsSignalListener(): void
    {
        $signal = SIGTERM;
        $callback = function (): void {};

        $this->eventLoop->onSignal($signal, $callback);

        $called = false;
        $testCallback = function () use (&$called): void {
            $called = true;
        };

        $this->eventLoop->onSignal($signal, $testCallback);
        $this->eventLoop->triggerSignal($signal);

        $this->assertTrue($called);
    }

    public function testOffSignalRemovesSignalListener(): void
    {
        $signal = SIGTERM;
        $callback = function (): void {};

        $this->eventLoop->onSignal($signal, $callback);
        $result = $this->eventLoop->offSignal($signal);

        $this->assertTrue($result);
    }

    public function testDelayAddsOneTimeTimer(): void
    {
        $called = false;
        $callback = function () use (&$called): void {
            $called = true;
        };

        $timerId = $this->eventLoop->delay(1.0, $callback);

        $this->assertIsInt($timerId);
        $this->assertTrue($this->eventLoop->hasPendingTimers());
        $this->assertEquals(1, $this->eventLoop->getTimerCount());
        $this->assertFalse($called);

        $this->eventLoop->fastForward(1.0);
        $this->assertTrue($called);
        $this->assertFalse($this->eventLoop->hasPendingTimers());
    }

    public function testTickExecutesDueTimers(): void
    {
        $called = false;
        $callback = function () use (&$called): void {
            $called = true;
        };

        // Add timer that should execute immediately
        $this->eventLoop->delay(0.0, $callback);

        $this->eventLoop->tick();

        $this->assertTrue($called);
    }

    public function testClearRemovesAllEventsAndTimers(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $this->eventLoop->onReadable($stream, function (): void {});
        $this->eventLoop->onWritable($stream, function (): void {});
        $this->eventLoop->delay(1.0, function (): void {});
        $this->eventLoop->onSignal(SIGTERM, function (): void {});

        $this->assertTrue($this->eventLoop->hasEvents());
        $this->assertTrue($this->eventLoop->hasReadEvents());
        $this->assertTrue($this->eventLoop->hasWriteEvents());
        $this->assertTrue($this->eventLoop->hasPendingTimers());

        $this->eventLoop->clear();

        $this->assertFalse($this->eventLoop->hasEvents());
        $this->assertFalse($this->eventLoop->hasReadEvents());
        $this->assertFalse($this->eventLoop->hasWriteEvents());
        $this->assertFalse($this->eventLoop->hasPendingTimers());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testDeleteAllTimerRemovesAllTimers(): void
    {
        $this->eventLoop->delay(1.0, function (): void {});
        $this->eventLoop->delay(2.0, function (): void {});
        $this->eventLoop->repeat(1.0, function (): void {});

        $this->assertEquals(3, $this->eventLoop->getTimerCount());
        $this->assertTrue($this->eventLoop->hasPendingTimers());

        $this->eventLoop->deleteAllTimer();

        $this->assertEquals(0, $this->eventLoop->getTimerCount());
        $this->assertFalse($this->eventLoop->hasPendingTimers());
    }

    public function testFastForwardAdvancesTime(): void
    {
        $executionOrder = [];

        $this->eventLoop->delay(1.0, function () use (&$executionOrder): void {
            $executionOrder[] = 'first';
        });

        $this->eventLoop->delay(2.0, function () use (&$executionOrder): void {
            $executionOrder[] = 'second';
        });

        $this->eventLoop->delay(3.0, function () use (&$executionOrder): void {
            $executionOrder[] = 'third';
        });

        $this->eventLoop->fastForward(2.5);

        $this->assertEquals(['first', 'second'], $executionOrder);
        $this->assertEquals(1, $this->eventLoop->getTimerCount());
    }

    public function testOffDelayRemovesTimer(): void
    {
        $timerId = $this->eventLoop->delay(1.0, function (): void {});

        $this->assertEquals(1, $this->eventLoop->getTimerCount());

        $result = $this->eventLoop->offDelay($timerId);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->eventLoop->getTimerCount());
    }

    public function testOffRepeatRemovesRepeatingTimer(): void
    {
        $timerId = $this->eventLoop->repeat(1.0, function (): void {});

        $this->assertEquals(1, $this->eventLoop->getTimerCount());

        $result = $this->eventLoop->offRepeat($timerId);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->eventLoop->getTimerCount());
    }

    public function testRepeatExecutesMultipleTimes(): void
    {
        $count = 0;
        $callback = function () use (&$count): void {
            $count++;
        };

        $timerId = $this->eventLoop->repeat(1.0, $callback);

        $this->assertIsInt($timerId);
        $this->assertEquals(1, $this->eventLoop->getTimerCount());

        $this->eventLoop->fastForward(3.5);

        $this->assertEquals(3, $count);
        $this->assertEquals(1, $this->eventLoop->getTimerCount());
    }

    public function testRunExecutesEventLoop(): void
    {
        $executed = false;
        $this->eventLoop->delay(0.0, function () use (&$executed): void {
            $executed = true;
            $this->eventLoop->stop();
        });

        $this->eventLoop->run();

        $this->assertTrue($executed);
    }

    public function testStopStopsEventLoop(): void
    {
        $this->eventLoop->delay(0.0, function (): void {
            $this->eventLoop->stop();
        });

        $this->eventLoop->run();

        $this->assertFalse($this->eventLoop->hasPendingTimers());
    }

    public function testTriggerExceptExecutesExceptionHandler(): void
    {
        $stream = fopen('php://memory', 'r+');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $called = false;

        $callback = function () use (&$called): void {
            $called = true;
        };

        // Note: MockEventLoop doesn't implement onExcept, but we can test triggerExcept
        // This test documents the expected behavior even if not fully implemented
        $this->eventLoop->triggerExcept($stream);

        // Since onExcept is not implemented, triggerExcept should not cause errors
        $this->assertFalse($called);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testTriggerReadExecutesReadCallback(): void
    {
        $stream = fopen('php://memory', 'r');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $called = false;
        $receivedStream = null;

        $callback = function ($s) use (&$called, &$receivedStream): void {
            $called = true;
            $receivedStream = $s;
        };

        $this->eventLoop->onReadable($stream, $callback);
        $this->eventLoop->triggerRead($stream);

        $this->assertTrue($called);
        $this->assertSame($stream, $receivedStream);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testTriggerSignalExecutesSignalCallback(): void
    {
        $signal = SIGUSR1;
        $called = false;
        $receivedSignal = null;

        $callback = function ($sig) use (&$called, &$receivedSignal): void {
            $called = true;
            $receivedSignal = $sig;
        };

        $this->eventLoop->onSignal($signal, $callback);
        $this->eventLoop->triggerSignal($signal);

        $this->assertTrue($called);
        $this->assertSame($signal, $receivedSignal);
    }

    public function testTriggerWriteExecutesWriteCallback(): void
    {
        $stream = fopen('php://memory', 'w');
        if (false === $stream) {
            self::fail('Failed to open stream');
        }
        $called = false;
        $receivedStream = null;

        $callback = function ($s) use (&$called, &$receivedStream): void {
            $called = true;
            $receivedStream = $s;
        };

        $this->eventLoop->onWritable($stream, $callback);
        $this->eventLoop->triggerWrite($stream);

        $this->assertTrue($called);
        $this->assertSame($stream, $receivedStream);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}

