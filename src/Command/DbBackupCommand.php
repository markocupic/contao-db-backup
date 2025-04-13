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

namespace Markocupic\ContaoDbBackup\Command;

use Contao\CoreBundle\Cron\Cron;
use Markocupic\ContaoDbBackup\Backup\DatabaseBackupManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbBackupCommand extends Command
{
    protected static $defaultName = 'contao:db-backup';
    protected static $defaultDescription = 'Runs a db backup on the command line.';

    protected Cron $cron;

    public function __construct(
        private readonly DatabaseBackupManager $dbBackupManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dbBackupManager->run();

        return 0;
    }
}
