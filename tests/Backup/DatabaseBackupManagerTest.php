<?php

namespace Markocupic\ContaoDbBackup\Tests\Backup;

use Contao\CoreBundle\Filesystem\FilesystemItem;
use Markocupic\ContaoDbBackup\Backup\DatabaseBackupManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DatabaseBackupManagerTest extends TestCase
{
    /**
     * Tests that the method correctly extracts the timestamp from a valid backup file name.
     */
    public function testGetFileCreationTimestampReturnsCorrectTimestampForValidFilename(): void
    {
        $backupFile = $this->createMock(FilesystemItem::class);
        $backupFile->method('getName')->willReturn('contao_db_backup__20231015000000.sql.gz');

        $backupManager = $this->createPartialMock(DatabaseBackupManager::class, []);

        $method = new ReflectionMethod(DatabaseBackupManager::class, 'getFileCreationTimestamp');
        $method->setAccessible(true);

        $expectedTimestamp = strtotime('midnight', strtotime('2023-10-15'));
        $timestamp = $method->invokeArgs($backupManager, [$backupFile]);

        $this->assertEquals($expectedTimestamp, $timestamp);
    }

    /**
     * Tests that the method correctly extracts the timestamp from a valid backup file name without prefix.
     */
    public function testGetFileCreationTimestampReturnsCorrectTimestampForValidFilenameWithoutPrefix(): void
    {
        $backupFile = $this->createMock(FilesystemItem::class);
        $backupFile->method('getName')->willReturn('20231015000000.sql.gz');

        $backupManager = $this->createPartialMock(DatabaseBackupManager::class, []);

        $method = new ReflectionMethod(DatabaseBackupManager::class, 'getFileCreationTimestamp');
        $method->setAccessible(true);

        $expectedTimestamp = strtotime('midnight', strtotime('2023-10-15'));
        $timestamp = $method->invokeArgs($backupManager, [$backupFile]);

        $this->assertEquals($expectedTimestamp, $timestamp);
    }

    /**
     * Tests that null is returned when the backup file name does not contain a valid timestamp.
     */
    public function testGetFileCreationTimestampReturnsNullForInvalidFilename(): void
    {
        $backupFile = $this->createMock(FilesystemItem::class);
        $backupFile->method('getName')->willReturn('contao_db_backup__invalid.sql.gz');

        $backupManager = $this->createPartialMock(DatabaseBackupManager::class, []);

        $method = new ReflectionMethod(DatabaseBackupManager::class, 'getFileCreationTimestamp');
        $method->setAccessible(true);

        $timestamp = $method->invokeArgs($backupManager, [$backupFile]);

        $this->assertNull($timestamp);
    }

    /**
     * Tests that null is returned when the backup file name has an unrecognized format.
     */
    public function testGetFileCreationTimestampReturnsNullForUnrecognizedFormat(): void
    {
        $backupFile = $this->createMock(FilesystemItem::class);
        $backupFile->method('getName')->willReturn('random_file_name.sql.gz');

        $backupManager = $this->createPartialMock(DatabaseBackupManager::class, []);

        $method = new ReflectionMethod(DatabaseBackupManager::class, 'getFileCreationTimestamp');
        $method->setAccessible(true);

        $timestamp = $method->invokeArgs($backupManager, [$backupFile]);

        $this->assertNull($timestamp);
    }

    /**
     * Tests that null is returned for a file with a valid prefix but missing the timestamp section.
     */
    public function testGetFileCreationTimestampReturnsNullWhenTimestampIsMissing(): void
    {
        $backupFile = $this->createMock(FilesystemItem::class);
        $backupFile->method('getName')->willReturn('contao_db_backup__.sql.gz');

        $backupManager = $this->createPartialMock(DatabaseBackupManager::class, []);

        $method = new ReflectionMethod(DatabaseBackupManager::class, 'getFileCreationTimestamp');
        $method->setAccessible(true);

        $timestamp = $method->invokeArgs($backupManager, [$backupFile]);

        $this->assertNull($timestamp);
    }

}
