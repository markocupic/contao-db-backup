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

namespace Markocupic\ContaoDbBackup\Backup;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\System;
use Doctrine\ORM\EntityManagerInterface;
use Markocupic\ZipBundle\Zip\Zip;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;

class DatabaseBackupManager
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
        private readonly int $storeBackupFiles,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $this->framework->initialize();

        $filename = 'contao_db_backup'.date('Y_m_d').'.sql';
        $backupDir = Path::join(System::getContainer()->getParameter('contao.upload_path'), 'contao_db_backup');

        $tempSrc = $backupDir.'/'.$filename;
        $zipSrc = $backupDir.'/'.$filename.'.zip';

        // Create backup folder if not exists
        new Folder($backupDir);

        // Skip routine if backup already exists
        if (file_exists(Path::makeAbsolute($zipSrc, $this->projectDir))) {
            return;
        }

        // Delete old archives
        $this->deleteOldBackupArchives($backupDir);

        $hostname = $this->getDbHost();
        $user = $this->getDbUser();
        $password = $this->getDbPassword();
        $dbname = $this->getDbName();

        // Run db dump
        if (false === $this->dump($tempSrc, $hostname, $user, $password, $dbname)) {
            // Add an entry to the Contao system log.
            $this->contaoErrorLogger?->error('Could not proceed contao database backup due to an error.');

            return;
        }

        // Wait to be sure, the file is readable.
        sleep(2);

        if (file_exists(Path::makeAbsolute($tempSrc, $this->projectDir))) {
            (new Zip())
                ->stripSourcePath(\dirname(Path::makeAbsolute($tempSrc, $this->projectDir)))
                ->addFile(Path::makeAbsolute($tempSrc, $this->projectDir))
                ->run(Path::makeAbsolute($zipSrc, $this->projectDir))
             ;

            Dbafs::addResource($zipSrc);

            // Delete temp file
            $objTempFile = new File($tempSrc);
            $objTempFile->delete();

            $log = "Finished contao database backup and stored the database dump in ('".$zipSrc."').";

            $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
        }
    }

    /**
     * @throws \Exception
     */
    protected function deleteOldBackupArchives(string $backupDir): void
    {
        // Delete database backup files
        $arrFiles = Folder::scan($this->projectDir.'/'.$backupDir);

        foreach ($arrFiles as $strFile) {
            if (0 !== strncmp('.', $strFile, 1) && is_file(Path::join($this->projectDir, $backupDir, $strFile))) {
                $objFile = new File(path::join($backupDir, $strFile));

                if ($objFile->mtime > 0) {
                    if (time() - $objFile->mtime > $this->storeBackupFiles * 24 * 3600) {
                        $log = sprintf('Delete old database backup file "%s".', $objFile->path);
                        $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
                        $objFile->delete();
                    }
                }
            }
        }
    }

    protected function dump($tempSrc, string $host, string $user, string $password, string $dbname): string|false
    {
        try {
            if (empty($host) || empty($user) || empty($password) || empty($dbname)) {
                throw new \Exception('Could not load database params (host, user, password or dbname)');
            }

            $sqlCommand = '/usr/bin/mysqldump -h '.$host.' -u '.$user.' -p"'.$password.'" '.$dbname.' > '.Path::join($this->projectDir, $tempSrc);
            $result = exec($sqlCommand);
        } catch (\Exception $e) {
            $log = 'Could not proceed contao database backup due to an error: '.$e->getMessage();
            $this->contaoErrorLogger?->error($log, ['contao' => new ContaoContext(__METHOD__, 'CONTAO_DB_BACKUP')]);
        }

        return $result ?? false;
    }

    protected function getDbConnectionParams(): array
    {
        return $this->entityManager->getConnection()->getParams();
    }

    protected function getDbHost(): string
    {
        $configuration = $this->getDbConnectionParams();

        return $configuration['host'] ?? '';
    }

    protected function getDbUser(): string
    {
        $configuration = $this->getDbConnectionParams();

        return $configuration['user'] ?? '';
    }

    protected function getDbPassword(): string
    {
        $configuration = $this->getDbConnectionParams();

        return $configuration['password'] ?? '';
    }

    protected function getDbName(): string
    {
        $configuration = $this->getDbConnectionParams();

        return $configuration['dbname'] ?? '';
    }
}
