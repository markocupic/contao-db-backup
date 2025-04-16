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
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Markocupic\ContaoDbBackup\Event\DatabaseBackupEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Manages the process of creating a backup for the Contao database.
 *
 * This class leverages the Contao Core backup manager and virtual filesystem to create and store database backups.
 * It also handles the dispatch of events during the backup lifecycle and provides logging mechanisms for success or failure cases.
 */
class DatabaseBackupManager
{
    public const string FILE_PREFIX = 'contao_db_backup__';

    public function __construct(
        #[Autowire(service: 'markocupic_contao_db_backup.doctrine.backup_manager')]
        private readonly BackupManager $backupManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly VirtualFilesystemInterface $markocupicDatabaseBackupsStorage,
        #[Autowire('%markocupic_contao_db_backup.backup_dir%')]
        private readonly string $backupDir,
        #[Autowire('%markocupic_contao_db_backup.ignore_tables%')]
        private readonly array $ignoreTables,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Executes the database backup process.
     *
     * This method creates a backup configuration if none is provided. It interacts with the Contao Core backup manager
     * and a virtual filesystem to perform the backup operation. The backup file is retrieved and a database backup
     * event is dispatched upon success.
     *
     * Logging is performed to track both successful and failed backup attempts.
     *
     * @param CreateConfig|null $config The backup configuration. If null, a new configuration is generated.
     *
     * @return bool Returns true if the backup process is successful, false otherwise.
     */
    public function run(CreateConfig|null $config = null): bool
    {
        // Create the backup configuration
        if (null === $config) {
            $backup = $this->createNewBackup(new \DateTime('now'));
            $config = (new CreateConfig($backup))->withTablesToIgnore($this->ignoreTables);
        } else {
            $backup = $config->getBackup();
        }

        // Start backup
        $this->backupManager->create($config);

        // Get the backup file from virtual filesystem
        $backupFile = $this->markocupicDatabaseBackupsStorage->get($backup->getFilename());

        // Dispatch the database backup event
        $event = new DatabaseBackupEvent($this->markocupicDatabaseBackupsStorage, $backupFile);
        $this->eventDispatcher->dispatch($event);

        if (null === $backupFile) {
            $logText = sprintf(
                'The database backup for "%s" could not be performed successfully.',
                Path::join($this->backupDir, $backup->getFilename()),
            );

            $this->contaoErrorLogger?->error($logText);

            return false;
        }

        $logText = sprintf(
            'Successfully performed the database backup and stored the database dump under ("%s").',
            Path::join($this->backupDir, $backupFile->getPath()),
        );

        $this->contaoGeneralLogger?->info($logText, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);

        return true;
    }

    protected function createNewBackup(\DateTime|null $dateTime = null): Backup
    {
        $now = $dateTime ?? new \DateTime('now');
        $now->setTimezone(new \DateTimeZone('UTC'));

        $filename = sprintf(self::FILE_PREFIX.'%s.sql.gz', $now->format(Backup::DATETIME_FORMAT));

        return new Backup($filename);
    }
}
