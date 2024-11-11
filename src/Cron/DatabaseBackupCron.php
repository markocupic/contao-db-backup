<?php

declare(strict_types=1);

/*
 * This file is part of Contao Database Backup.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-db-backup
 */

namespace Markocupic\ContaoDbBackup\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Markocupic\ContaoDbBackup\Backup\DatabaseBackupManager;

#[AsCronJob('daily')]
class DatabaseBackupCron
{
    public function __construct(
        private readonly DatabaseBackupManager $databaseBackupManager,
    ) {
    }

    public function onDaily(): void
    {
        $this->databaseBackupManager->run();
    }
}
