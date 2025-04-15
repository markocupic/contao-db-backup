<?php

namespace Markocupic\ContaoDbBackup\Tests\Backup;

use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\DumperInterface;
use Contao\CoreBundle\Doctrine\Backup\RetentionPolicy;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use Markocupic\ContaoDbBackup\Backup\BackupManagerFactory;
use PHPUnit\Framework\TestCase;

class BackupManagerFactoryTest extends TestCase
{
    private Connection $connection;
    private DumperInterface $dumper;
    private VirtualFilesystemInterface $virtualFilesystem;
    private array $ignoreTables;
    private array $keepIntervals;
    private int $keepMax;
    private BackupManagerFactory $backupManagerFactory;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->dumper = $this->createMock(DumperInterface::class);
        $this->virtualFilesystem = $this->createMock(VirtualFilesystemInterface::class);
        $this->ignoreTables = ['table1', 'table2'];
        $this->keepIntervals = ['7D', '30D', '90D'];
        $this->keepMax = 100;

        $this->backupManagerFactory = new BackupManagerFactory(
            $this->connection,
            $this->dumper,
            $this->virtualFilesystem,
            $this->ignoreTables,
            $this->keepIntervals,
            $this->keepMax
        );
    }

    public function testCreateReturnsBackupManagerInstance(): void
    {
        $result = $this->backupManagerFactory->create();

        $this->assertInstanceOf(BackupManager::class, $result);
    }

    public function testCreateConfiguresBackupManagerWithCorrectParameters(): void
    {
        $backupManager = $this->backupManagerFactory->create();

        $reflectionClass = new \ReflectionClass($backupManager);

        $connectionProperty = $reflectionClass->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $this->assertSame($this->connection, $connectionProperty->getValue($backupManager));

        $dumperProperty = $reflectionClass->getProperty('dumper');
        $dumperProperty->setAccessible(true);
        $this->assertSame($this->dumper, $dumperProperty->getValue($backupManager));

        $storageProperty = $reflectionClass->getProperty('backupsStorage');
        $storageProperty->setAccessible(true);
        $this->assertSame($this->virtualFilesystem, $storageProperty->getValue($backupManager));

        $ignoreTablesProperty = $reflectionClass->getProperty('tablesToIgnore');
        $ignoreTablesProperty->setAccessible(true);
        $this->assertSame($this->ignoreTables, $ignoreTablesProperty->getValue($backupManager));

        $retentionPolicyProperty = $reflectionClass->getProperty('retentionPolicy');
        $retentionPolicyProperty->setAccessible(true);
        $retentionPolicy = $retentionPolicyProperty->getValue($backupManager);

        $this->assertInstanceOf(RetentionPolicy::class, $retentionPolicy);

        $retentionPolicyReflection = new \ReflectionClass($retentionPolicy);

        $maxProperty = $retentionPolicyReflection->getProperty('keepMax');
        $maxProperty->setAccessible(true);
        $this->assertSame($this->keepMax, $maxProperty->getValue($retentionPolicy));

        $intervalsProperty = $retentionPolicyReflection->getProperty('keepIntervals');
        $intervalsProperty->setAccessible(true);
        $this->assertSame($this->keepIntervals, $intervalsProperty->getValue($retentionPolicy));
    }
}
