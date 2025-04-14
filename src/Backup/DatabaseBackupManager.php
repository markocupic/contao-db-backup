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

namespace Markocupic\ContaoDbBackup\Backup;

use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Markocupic\ContaoDbBackup\Event\DatabaseBackupEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;

class DatabaseBackupManager
{
    public const string FILE_PREFIX = 'contao_db_backup__';
    public const string DATETIME_FORMAT = 'YmdHis';

    public function __construct(
        #[Autowire(service: 'contao.doctrine.backup_manager')]
        private readonly BackupManager $backupManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly VirtualFilesystemInterface $backupsStorage,
        private readonly VirtualFilesystemInterface $markocupicDatabaseBackupsStorage,
        #[Autowire('%markocupic_contao_db_backup.backup_dir%')]
        private readonly string $backupDir,
        #[Autowire('%markocupic_contao_db_backup.store_backup_files%')]
        private readonly int $storeBackupFiles,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function run(): bool
    {
        // Delete old backup files
        $this->deleteOldBackupFiles();

        // Create the backup configuration
        $backup = $this->createNewBackup(new \DateTime('now'), self::DATETIME_FORMAT);
        $config = new CreateConfig($backup);
        $config = $config->withTablesToIgnore([]);

        // Start backup
        $this->backupManager->create($config);

        // Read the file from the source and write to the destination
        $stream = $this->backupsStorage->readStream($backup->getFilename());
        $this->markocupicDatabaseBackupsStorage->writeStream($backup->getFilename(), $stream);

        if (\is_resource($stream)) {
            fclose($stream);
        }

        // Delete the original file
        if ($this->backupsStorage->has($backup->getFilename())) {
            $this->backupsStorage->delete($backup->getFilename());
        }

        // Get the backup file from virtual filesystem
        $backupFile = $this->markocupicDatabaseBackupsStorage->get($backup->getFilename());

        // Dispatch the database backup event
        $event = new DatabaseBackupEvent($this->markocupicDatabaseBackupsStorage, $backupFile);
        $this->eventDispatcher->dispatch($event);

        if (null === $backupFile) {
            $logText = sprintf(
                'Database backup failed for "%s".',
                Path::join($this->backupDir, $backup->getFilename()),
            );

            $this->contaoErrorLogger?->error($logText);

            return false;
        }

        $logText = sprintf(
            'Finished contao database backup and stored the database dump in ("%s").',
            Path::join($this->backupDir, $backupFile->getPath()),
        );

        $this->contaoGeneralLogger?->info($logText, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);

        return true;
    }

    protected function createNewBackup(\DateTime $dateTime, string $dateTimeFormat): Backup
    {
        // Use the server time zone
        // $dateTimeFormat->setTimezone(new \DateTimeZone('UTC'));

        $filename = sprintf(self::FILE_PREFIX.'%s.sql.gz', $dateTime->format($dateTimeFormat));

        return new Backup($filename);
    }

    protected function deleteOldBackupFiles(): void
    {
        foreach ($this->markocupicDatabaseBackupsStorage->listContents('', false, VirtualFilesystemInterface::BYPASS_DBAFS)->files() as $backupFile) {
            $fileCreationTimestamp = $this->getFileCreationTimestamp($backupFile);

            if (null === $fileCreationTimestamp) {
                continue;
            }

            if (strtotime('midnight') - $fileCreationTimestamp >= $this->storeBackupFiles * 24 * 3600) {
                $logText = sprintf(
                    'Deleted old database backup file "%s".',
                    Path::join($this->backupDir, $backupFile->getPath()),
                );

                $this->contaoGeneralLogger?->info($logText, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);

                // Delete old backup file
                $this->markocupicDatabaseBackupsStorage->delete($backupFile->getPath());
            }
        }
    }

    protected function getFileCreationTimestamp(FilesystemItem $backupFile): int|null
    {
        $filenameWithoutExtensions = rtrim(rtrim($backupFile->getName(), '.sql.gz'), '.sql');
        $dateStr = ltrim($filenameWithoutExtensions, self::FILE_PREFIX);

        $dateTime = \DateTime::createFromFormat(self::DATETIME_FORMAT, $dateStr);

        if (false === $dateTime) {
            return null;
        }

        return strtotime('midnight', $dateTime->getTimestamp());
    }
}
