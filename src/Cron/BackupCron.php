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

readonly class BackupCron
{
    public function __construct(
        #[Autowire(service: 'contao.doctrine.backup_manager')]
        private BackupManager $backupManager,
        private EventDispatcherInterface $eventDispatcher,
        private ProcessUtil $processUtil,
        private VirtualFilesystemInterface $backupsStorage,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private LoggerInterface|null $contaoGeneralLogger = null,
        private LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function __invoke(string $scope): PromiseInterface
    {
        try {
            $config = $this->backupManager->createCreateConfig();
            $backup = $config->getBackup();

            $process = $this->processUtil->createSymfonyConsoleProcess('contao:backup:create', $backup->getFilename());
            $process->setTimeout(null);
            $promise = $this->processUtil->createPromise($process);

            return $promise->then(
                function ($value) use ($backup): void {
                    $this->onBackupSuccess($backup);
                },
                function ($value) use ($backup): void {
                    $this->onBackupError($backup);
                },
            );
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(sprintf('The database backup could not be performed successfully. Error: %s', $e->getMessage()));
        }

        throw new $e();
    }

    private function onBackupSuccess(Backup $backup): void
    {
        $fileItem = $this->backupsStorage->get($backup->getFilename());

        // Dispatch the database backup event
        $event = new DatabaseBackupEvent($this->backupsStorage, true, $fileItem);
        $this->dispatchEvent($event);

        $logText = sprintf(
            'Successfully performed the database backup and stored the database dump under ("%s").',
            Path::join($this->projectDir, 'var/backups', $backup->getFilename()),
        );

        $this->contaoGeneralLogger?->info($logText, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
    }

    private function onBackupError(Backup $backup): void
    {
        // Dispatch the database backup event
        $event = new DatabaseBackupEvent($this->backupsStorage, false, null);
        $this->dispatchEvent($event);
    }

    private function dispatchEvent(DatabaseBackupEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
