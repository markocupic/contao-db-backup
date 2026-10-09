<?php

declare(strict_types=1);

/*
 * This file is part of Contao Database Backup.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-db-backup
 */

namespace Markocupic\ContaoDbBackup\Tests\Cron;

use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Util\ProcessUtil;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Markocupic\ContaoDbBackup\Cron\BackupCron;
use Markocupic\ContaoDbBackup\Event\DatabaseBackupEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Process;

class BackupCronTest extends TestCase
{
    private const FILENAME = 'backup__20261009120000.sql.gz';

    public function testCreatesTheBackupInAConsoleProcessAndDispatchesTheSuccessEvent(): void
    {
        $process = $this->createMock(Process::class);
        $process
            ->expects($this->once())
            ->method('setTimeout')
            ->with(null)
        ;

        $processUtil = $this->createMock(ProcessUtil::class);
        $processUtil
            ->expects($this->once())
            ->method('createSymfonyConsoleProcess')
            ->with('contao:backup:create', self::FILENAME)
            ->willReturn($process)
        ;

        $processUtil
            ->method('createPromise')
            ->willReturn(new FulfilledPromise(null))
        ;

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (DatabaseBackupEvent $event): bool => $event->isSuccess()))
            ->willReturnArgument(0)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('var/backups/'.self::FILENAME))
        ;

        $cron = new BackupCron($this->mockBackupManager(), $eventDispatcher, $processUtil, $this->createMock(VirtualFilesystemInterface::class), '/project', $logger);
        $cron('cli')->wait();
    }

    public function testDispatchesTheErrorEventAndLogsTheErrorIfTheProcessFails(): void
    {
        $processUtil = $this->createMock(ProcessUtil::class);
        $processUtil
            ->method('createSymfonyConsoleProcess')
            ->willReturn($this->createMock(Process::class))
        ;

        $processUtil
            ->method('createPromise')
            ->willReturn(new RejectedPromise(new \RuntimeException('mysql is gone')))
        ;

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (DatabaseBackupEvent $event): bool => !$event->isSuccess() && null === $event->getBackupFile()))
            ->willReturnArgument(0)
        ;

        $errorLogger = $this->createMock(LoggerInterface::class);
        $errorLogger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('mysql is gone'))
        ;

        $cron = new BackupCron($this->mockBackupManager(), $eventDispatcher, $processUtil, $this->createMock(VirtualFilesystemInterface::class), '/project', null, $errorLogger);
        $cron('cli')->wait();
    }

    public function testLogsAndRethrowsExceptionsBeforeTheProcessIsStarted(): void
    {
        $backupManager = $this->createMock(BackupManager::class);
        $backupManager
            ->method('createCreateConfig')
            ->willThrowException(new \RuntimeException('no database connection'))
        ;

        $errorLogger = $this->createMock(LoggerInterface::class);
        $errorLogger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('no database connection'))
        ;

        $cron = new BackupCron($backupManager, $this->createMock(EventDispatcherInterface::class), $this->createMock(ProcessUtil::class), $this->createMock(VirtualFilesystemInterface::class), '/project', null, $errorLogger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no database connection');

        $cron('cli');
    }

    private function mockBackupManager(): BackupManager
    {
        $backupManager = $this->createMock(BackupManager::class);
        $backupManager
            ->method('createCreateConfig')
            ->willReturn(new CreateConfig(new Backup(self::FILENAME)))
        ;

        return $backupManager;
    }
}
