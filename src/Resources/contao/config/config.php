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

// Keep backup files for 30 days on the server
$GLOBALS['TL_CONFIG']['contaoDbBackupKeepBackupFiles'] = 30;

// TL_CRON
$GLOBALS['TL_CRON']['daily']['doContaoDbBackup'] = array('ContaoDbBackup\ContaoDbBackup', 'doDbBackup');

