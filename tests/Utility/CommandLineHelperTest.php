<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Utility\CommandLineHelper;

/**
 * @internal
 */
#[CoversClass(CommandLineHelper::class)]
#[RunTestsInSeparateProcesses] final class CommandLineHelperTest extends WorkermanTestCase
{
    protected function onTearDown(): void
    {
        CommandLineHelper::restoreArgv();
    }

    public function testGetArgvReturnsCurrentArguments(): void
    {
        $argv = CommandLineHelper::getArgv();
        $this->assertNotEmpty($argv);
        $this->assertIsString($argv[0]);
    }

    public function testSetArgvChangesArguments(): void
    {
        $testArgv = ['test', '--option', 'value'];
        CommandLineHelper::setArgv($testArgv);

        $this->assertEquals($testArgv, CommandLineHelper::getArgv());
    }

    public function testSetArgvStoresOriginalArguments(): void
    {
        $testArgv = ['test', '--option', 'value'];
        CommandLineHelper::setArgv($testArgv);

        $this->assertTrue(CommandLineHelper::hasStoredArgv());
    }

    public function testRestoreArgvRestoresOriginalArguments(): void
    {
        $originalArgv = CommandLineHelper::getArgv();
        $testArgv = ['test', '--option', 'value'];

        CommandLineHelper::setArgv($testArgv);
        $this->assertEquals($testArgv, CommandLineHelper::getArgv());

        CommandLineHelper::restoreArgv();
        $this->assertEquals($originalArgv, CommandLineHelper::getArgv());
    }

    public function testRestoreArgvClearsStoredState(): void
    {
        CommandLineHelper::setArgv(['test']);
        $this->assertTrue(CommandLineHelper::hasStoredArgv());

        CommandLineHelper::restoreArgv();
        $this->assertFalse(CommandLineHelper::hasStoredArgv());
    }

    public function testHasStoredArgvReturnsFalseInitially(): void
    {
        $this->assertFalse(CommandLineHelper::hasStoredArgv());
    }

    public function testMultipleSetArgvCallsOnlyStoreOriginalOnce(): void
    {
        $originalArgv = CommandLineHelper::getArgv();

        CommandLineHelper::setArgv(['first']);
        CommandLineHelper::setArgv(['second']);

        CommandLineHelper::restoreArgv();
        $this->assertEquals($originalArgv, CommandLineHelper::getArgv());
    }
}
