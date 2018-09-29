<?php

/**
 * DbBackup
 *
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * @package my_db_backup
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

// Keep Backup Files 30 d on the server
$GLOBALS['TL_CONFIG']['ContaoDbBackup']['keepBackupFiles'] = 30;

// Cronjob
$GLOBALS['TL_CRON']['daily']['doDbBackup'] = array('ContaoDbBackup\ContaoDbBackup', 'doDbBackup');

