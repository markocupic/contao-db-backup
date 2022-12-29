<?php

declare(strict_types=1);

/*
 * This file is part of Contao Database Backup.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-db-backup
 */

namespace Markocupic\ContaoDbBackup\Cron;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Psr\Log\LoggerInterface;

#[AsCronJob('daily')]
class DatabaseBackup
{
    private string $projectDir;
    private int $storeBackupFiles;
    private LoggerInterface|null $contaoGeneralLogger;
    private LoggerInterface|null $contaoErrorLogger;

    public function __construct(string $projectDir, int $storeBackupFiles, LoggerInterface|null $contaoGeneralLogger, LoggerInterface|null $contaoErrorLogger)
    {
        $this->projectDir = $projectDir;
        $this->storeBackupFiles = $storeBackupFiles * 24 * 60 * 60;
        $this->contaoGeneralLogger = $contaoGeneralLogger;
        $this->contaoErrorLogger = $contaoErrorLogger;
    }

    /**
     * @throws \Exception
     */
    public function onDaily(): void
    {
        $filename = 'contao_db_backup'.date('Y_m_d').'.sql';
        $backupDir = Config::get('uploadPath').'/contao_db_backup';
        $tempSrc = $backupDir.'/'.$filename;
        $zipSrc = $backupDir.'/'.$filename.'.zip';

        // Create backup folder if not exists
        new Folder($backupDir);

        // Skip routine if backup already exists
        if (file_exists($this->projectDir.'/'.$zipSrc)) {
            return;
        }

        // Delete old archives
        $this->deleteOldBackupArchives($backupDir);

        // Run db dump
        if (false === $this->dump($tempSrc)) {
            // Add an entry to the Contao system log.
            $this->contaoErrorLogger?->error('Could not proceed contao database backup due to an error.');

            return;
        }

        // Wait to be sure, the file is readable.
        sleep(2);

        if (file_exists($this->projectDir.'/'.$tempSrc)) {
            $archive = new \PclZip($this->projectDir.'/'.$zipSrc);
            $vList = $archive->create($this->projectDir.'/'.$tempSrc);

            if (0 === $vList) {
                // Add an entry to the Contao system log.
                $this->contaoErrorLogger?->error('Could not proceed contao database backup due to an error: '.$archive->errorInfo(true));

                return;
            }

            Dbafs::addResource($zipSrc);

            // Delete temp file
            $objTempFile = new File($tempSrc);
            $objTempFile->delete();

            // Add an entry to the Contao system log.
            $text = "Finished contao database backup and stored the database dump in ('".$zipSrc."').";

            $this->contaoGeneralLogger?->info($text, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
        }
    }

    private function dump($tempSrc): string|false
    {
        $host = Config::get('dbHost');
        $user = Config::get('dbUser');
        $pw = Config::get('dbPass');
        $db = Config::get('dbDatabase');

        try {
            $sql_command = '/usr/bin/mysqldump -h '.$host.' -u '.$user.' -p"'.$pw.'" '.$db.' > '.$this->projectDir.'/'.$tempSrc;
            $result = exec($sql_command);
        } catch (\Exception $e) {
            echo 'Database Backup Error! - ', $e->getMessage();
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    private function deleteOldBackupArchives(string $backupDir): void
    {
        // Delete database backup files
        $arrFiles = Folder::scan($this->projectDir.'/'.$backupDir);

        foreach ($arrFiles as $strFile) {
            if (0 !== strncmp('.', $strFile, 1) && is_file($this->projectDir.'/'.$backupDir.'/'.$strFile)) {
                $objFile = new File($backupDir.'/'.$strFile);

                if ($objFile->mtime > 0) {
                    if (time() - $objFile->mtime > $this->storeBackupFiles) {
                        $text = sprintf('Delete old database backup file "%s".', $objFile->path);
                        $this->contaoGeneralLogger?->info($text, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
                        $objFile->delete();
                    }
                }
            }
        }
    }
}
