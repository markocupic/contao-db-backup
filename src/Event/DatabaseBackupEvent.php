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

namespace Markocupic\ContaoDbBackup\Event;

use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Symfony\Contracts\EventDispatcher\Event;

class DatabaseBackupEvent extends Event
{
    public function __construct(
        private readonly VirtualFilesystemInterface $markocupicDatabaseBackupsStorage,
        private readonly bool $success,
        private readonly FilesystemItem|null $backupFile,
    ) {
    }

    public function getDatabaseBackupsStorage(): VirtualFilesystemInterface
    {
        return $this->markocupicDatabaseBackupsStorage;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getBackupFile(): FilesystemItem|null
    {
        return $this->backupFile;
    }
}
