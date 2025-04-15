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

/**
 * Manages the creation and handling of database backups within the application.
 *
 * This class provides functionality to:
 * - Initiate and configure database backups.
 * - Maintain backup files by removing old backups.
 * - Store backups in defined storage locations.
 * - Dispatch events upon backup completion.
 * - Log relevant information for monitoring and debugging purposes.
 */
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
     * Executes the process of creating and managing a database backup.
     *
     * The method performs the following steps:
     * - Deletes old backup files to maintain the backup storage.
     * - Creates a backup configuration based on the provided `CreateConfig` or generates a new one.
     * - Initiates the database backup creation using the backup manager.
     * - Transfers the backup file from the source storage to the destination.
     * - Removes the original backup file from the source storage after transferring it.
     * - Retrieves the final backup file from the destination storage.
     * - Dispatches an event to signal that the database backup has been completed.
     * - Logs and returns the outcome of the backup process.
     *
     * @param CreateConfig|null $config Optional backup configuration. If null, a new configuration will be generated.
     *
     * @return bool True if the database backup operation completes successfully, false otherwise.
     */
    public function run(CreateConfig|null $config = null): bool
    {
        // Delete old backup files
        $this->deleteOldBackupFiles();

        // Create the backup configuration
        if (null === $config) {
            $backup = $this->createNewBackup(new \DateTime('now'), self::DATETIME_FORMAT);
            $config = (new CreateConfig($backup))->withTablesToIgnore([]);
        } else {
            $backup = $config->getBackup();
        }

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

    /**
     * Creates a new backup instance with a unique filename based on the provided date and time.
     *
     * This method uses the given date, time, and format to generate a backup filename.
     * The filename follows the pattern defined by combining the file prefix and the formatted date/time.
     *
     * @param \DateTime $dateTime       The date and time used to generate the backup filename.
     * @param string    $dateTimeFormat The format string to apply when formatting the date and time.
     *
     * @return Backup A new backup instance with the generated filename.
     */
    protected function createNewBackup(\DateTime $dateTime, string $dateTimeFormat): Backup
    {
        // Use the server time zone
        // $dateTimeFormat->setTimezone(new \DateTimeZone('UTC'));

        $filename = sprintf(self::FILE_PREFIX.'%s.sql.gz', $dateTime->format($dateTimeFormat));

        return new Backup($filename);
    }

    /**
     * Deletes old database backup files based on a configured retention period.
     *
     * This method iterates through all backup files in the storage. If a backup file's
     * creation timestamp indicates that it is older than the allowed retention period,
     * the file is deleted from the storage system. Additionally, a log entry is created
     * for each deleted file.
     *
     * The retention period is calculated based on the value of `storeBackupFiles`,
     * which specifies the number of days to retain backups. Old files that fall outside
     * of this period are considered eligible for deletion.
     *
     * Logging is done using the Contao GeneralLogger, with contextual information to
     * inform about the backup deletion process.
     */
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

    /**
     * Retrieves the creation timestamp of a backup file.
     *
     * This method extracts the timestamp information embedded in the backup file's name
     * by removing predefined prefixes and file extensions. The remaining string is parsed
     * as a date using a specific datetime format defined by `DATETIME_FORMAT`. If the
     * parsing succeeds, the timestamp is returned with the time set to midnight.
     *
     * If the file name does not conform to the expected format or parsing fails, `null` is returned.
     *
     * @param FilesystemItem $backupFile The backup file for which the creation timestamp is determined.
     *
     * @return int|null The timestamp at midnight on the file's creation date, or null if extraction fails.
     */
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
