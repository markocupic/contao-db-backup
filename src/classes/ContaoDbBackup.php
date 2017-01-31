<?php

namespace ContaoDbBackup;

require_once(TL_ROOT . '/vendor/pclzip/pclzip/pclzip.lib.php');

use PclZip;


/**
 * ContaoDbBackup
 *
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * @package contao-db-backup
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */
class ContaoDbBackup extends \System
{

    public function doDbBackup()
    {

        $host = $GLOBALS['TL_CONFIG']['dbHost'];
        $user = $GLOBALS['TL_CONFIG']['dbUser'];
        $pw = $GLOBALS['TL_CONFIG']['dbPass'];
        $db = $GLOBALS['TL_CONFIG']['dbDatabase'];
        $keepBackupFiles = intval($GLOBALS['TL_CONFIG']['ContaoDbBackup']['keepBackupFiles']) > 0 ? intval($GLOBALS['TL_CONFIG']['ContaoDbBackup']['keepBackupFiles']) * 60 * 90 * 24 : 60 * 90 * 24 * 60; //default 60 days

        $filename = 'contao_db_backup' . date("Y_m_d") . '.sql';
        $backupDir = $GLOBALS['TL_CONFIG']['uploadPath'] . '/contao_db_backup';
        $src_temp = $backupDir . '/' . $filename;
        $src_zip = $backupDir . '/' . $filename . '.zip';

        new \Folder($backupDir);

        //Wenn Backup schon existiert, dann weiter
        if (!file_exists(TL_ROOT . '/' . $src_zip))
        {
            // Delete old files
            $arrFiles = scan(TL_ROOT . '/' . $backupDir);
            foreach ($arrFiles as $strFile)
            {
                if (strncmp('.', $strFile, 1) !== 0 && is_file(TL_ROOT . '/' . $backupDir . '/' . $strFile))
                {
                    $objFile = new \File($backupDir . '/' . $strFile);
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
                $sqlcommand = 'mysqldump -h ' . $host . ' -u ' . $user . ' -p' . $pw . ' ' . $db . ' > ' . TL_ROOT . '/' . $src_temp;
                exec($sqlcommand);
                if (file_exists(TL_ROOT . '/' . $src_temp))
                {
                    $archive = new PclZip(TL_ROOT . '/' . $src_zip);
                    $v_list = $archive->create(TL_ROOT . '/' . $src_temp);
                    if ($v_list == 0)
                    {
                        die("Error : " . $archive->errorInfo(true));
                    }
                    \Dbafs::addResource($src_zip, true);

                    // Delete temp file
                    $objTempFile = new \File($src_temp);
                    $objTempFile->delete();
                }
            }
        }
    }
}
