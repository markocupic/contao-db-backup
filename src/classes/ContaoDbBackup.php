<?php

/**
 * Contao Db Backup
 *
 * Copyright (C) 2018 Marko Cupic
 *
 * @package contao-db-backup
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace ContaoDbBackup;

use Contao\Config;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\System;
use PclZip;

// Contao <= 4.0
if (is_file(TL_ROOT . '/vendor/pclzip/pclzip/pclzip.lib.php'))
{
    require_once(TL_ROOT . '/vendor/pclzip/pclzip/pclzip.lib.php');
}
// Contao < 4
elseif (is_file(TL_ROOT . '/composer/vendor/pclzip/pclzip/pclzip.lib.php'))
{
    require_once(TL_ROOT . '/composer/vendor/pclzip/pclzip/pclzip.lib.php');
}
else
{
    return;
}


/**
 * Class ContaoDbBackup
 * @package ContaoDbBackup
 */
class ContaoDbBackup
{
    /**
     * @throws \Exception
     */
    public function doDbBackup()
    {

        $host = Config::get('dbHost');
        $user = Config::get('dbUser');
        $pw = Config::get('dbPass');
        $db = Config::get('dbDatabase');
        $keepBackupFiles = intval(Config::get('contaoDbBackupKeepBackupFiles')) > 0 ? intval(Config::get('contaoDbBackupKeepBackupFiles')) * 60 * 90 * 24 : 60 * 90 * 24 * 60; //default 60 days
        $filename = 'contao_db_backup' . date("Y_m_d") . '.sql';
        $backupDir = Config::get('uploadPath') . '/contao_db_backup';
        $src_temp = $backupDir . '/' . $filename;
        $src_zip = $backupDir . '/' . $filename . '.zip';
        new Folder($backupDir);

        // Leave routine if backup already exists
        if (file_exists(TL_ROOT . '/' . $src_zip))
        {
            return;
        }


        // Delete old files
        $arrFiles = scan(TL_ROOT . '/' . $backupDir);
        foreach ($arrFiles as $strFile)
        {
            if (strncmp('.', $strFile, 1) !== 0 && is_file(TL_ROOT . '/' . $backupDir . '/' . $strFile))
            {
                $objFile = new File($backupDir . '/' . $strFile);
                if ($objFile->mtime > 0)
                {
                    if (time() - $objFile->mtime > $keepBackupFiles)
                    {
                        $objFile->delete();
                    }
                }
            }
        }

        //SQL-Dump
        if (strlen($pw))
        {
            // Run db dump
            $sqlcommand = '/usr/bin/mysqldump -h ' . $host . ' -u ' . $user . ' -p"' . $pw . '" ' . $db . ' > ' . TL_ROOT . '/' . $src_temp;
            exec($sqlcommand);

            if (file_exists(TL_ROOT . '/' . $src_temp))
            {
                $archive = new PclZip(TL_ROOT . '/' . $src_zip);
                $v_list = $archive->create(TL_ROOT . '/' . $src_temp);
                if ($v_list == 0)
                {
                    die("Error : " . $archive->errorInfo(true));
                }
                Dbafs::addResource($src_zip, true);

                // Delete temp file
                $objTempFile = new File($src_temp);
                $objTempFile->delete();

                // Log
                System::log("Finished daily contao database backup ('" . $src_zip . "').", __METHOD__, TL_GENERAL);
            }
        }

    }
}
