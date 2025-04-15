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

namespace Markocupic\ContaoDbBackup\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_KEY = 'markocupic_contao_db_backup';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('store_backup_files')
                    ->info('Specify how many days the backups should be stored on the server.')
                    ->defaultValue(30)
                ->end()
                ->scalarNode('backup_dir')
                    ->cannotBeEmpty()
                    ->info('Enter the file path where the backups are to be stored on the web server.')
                    ->defaultValue('%kernel.project_dir%/%contao.upload_path%/contao-db-backup')
                ->end()
                ->arrayNode('cron_intervals')
                    ->scalarPrototype()
                    ->info('Use one or more cron formats to execute the backup at a specific time. You can also use the text representation of the cron format "yearly", "monthly", "weekly", "daily", "hourly", "minutely".')
                    ->defaultValue(['0 0 * * *'])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
