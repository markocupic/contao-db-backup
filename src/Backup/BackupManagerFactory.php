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

use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\DumperInterface;
use Contao\CoreBundle\Doctrine\Backup\RetentionPolicy;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BackupManagerFactory
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DumperInterface $dumper,
        private readonly VirtualFilesystemInterface $markocupicDatabaseBackupsStorage,
        #[Autowire('%markocupic_contao_db_backup.ignore_tables%')]
        private readonly array $ignoreTables,
        #[Autowire('%markocupic_contao_db_backup.keep_intervals%')]
        private readonly array $keepIntervals,
        #[Autowire('%markocupic_contao_db_backup.keep_max%')]
        private readonly int $keepMax,
    ) {
    }

    public function create(): BackupManager
    {
        $retentionPolicy = new RetentionPolicy($this->keepMax, $this->keepIntervals);

        return new BackupManager($this->connection, $this->dumper, $this->markocupicDatabaseBackupsStorage, $this->ignoreTables, $retentionPolicy);
    }
}
