<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'ContaoDbBackup',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Src
	'ContaoDbBackup\ContaoDbBackup' => 'system/modules/contao-db-backup/src/classes/ContaoDbBackup.php',
));
