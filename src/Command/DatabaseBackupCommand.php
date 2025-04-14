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

use Markocupic\ContaoDbBackup\Backup\DatabaseBackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'markocupic:database-backup',
    description: 'Runs a database backup on the command line.',
)]
class DatabaseBackupCommand extends Command
{
    public function __construct(
        private readonly DatabaseBackupManager $databaseBackupManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->getFormatter()->setStyle('success', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));

        if ($this->databaseBackupManager->run()) {
            $output->writeln('<success>Database backup was successful.</success>');
        } else {
            $output->writeln('<error>Database backup failed.</error>');
        }

        return 0;
    }
}
