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

namespace Markocupic\ContaoDbBackup\Cron;

use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Util\ProcessUtil;
use GuzzleHttp\Promise\PromiseInterface;
use Markocupic\ContaoDbBackup\Event\DatabaseBackupEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Creates a database backup with the backup manager of the Contao core. The
 * backup is created in a separate "contao:backup:create" console process.
 */
readonly class BackupCron
{
    public const LOG_ACTION = 'CONTAO_DB_BACKUP';

    public function __construct(
        #[Autowire(service: 'contao.doctrine.backup_manager')]
        private BackupManager $backupManager,
        private EventDispatcherInterface $eventDispatcher,
        private ProcessUtil $processUtil,
        #[Autowire(service: 'contao.filesystem.virtual.backups')]
        private VirtualFilesystemInterface $backupsStorage,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private LoggerInterface|null $contaoGeneralLogger = null,
        private LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(string $scope): PromiseInterface
    {
        try {
            $backup = $this->backupManager->createCreateConfig()->getBackup();

            $process = $this->processUtil->createSymfonyConsoleProcess('contao:backup:create', $backup->getFilename());
            $process->setTimeout(null);
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('The database backup could not be performed successfully. Error: %s', $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, self::LOG_ACTION)]);

            throw $e;
        }

        return $this->processUtil->createPromise($process)->then(
            fn () => $this->onBackupSuccess($backup),
            fn (mixed $reason) => $this->onBackupError($backup, $reason),
        );
    }

    private function onBackupSuccess(Backup $backup): void
    {
        $fileItem = $this->backupsStorage->get($backup->getFilename());

        $this->eventDispatcher->dispatch(new DatabaseBackupEvent($this->backupsStorage, true, $fileItem));

        $logText = \sprintf(
            'Successfully performed the database backup and stored the database dump under ("%s").',
            Path::join($this->projectDir, 'var/backups', $backup->getFilename()),
        );

        $this->contaoGeneralLogger?->info($logText, ['contao' => new ContaoContext(__METHOD__, self::LOG_ACTION)]);
    }

    private function onBackupError(Backup $backup, mixed $reason): void
    {
        $this->eventDispatcher->dispatch(new DatabaseBackupEvent($this->backupsStorage, false, null));

        $error = $reason instanceof \Throwable ? $reason->getMessage() : 'unknown error';

        $this->contaoErrorLogger?->error(\sprintf('The database backup "%s" could not be created. Error: %s', $backup->getFilename(), $error), ['contao' => new ContaoContext(__METHOD__, self::LOG_ACTION)]);
    }
}
