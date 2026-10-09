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
                // Removed in version 2: the backup manager of the Contao core uses the
                // "contao.backup.keep_max" and "contao.backup.keep_intervals" options.
                // The option is still accepted, so that existing configurations do not
                // break the container.
                ->integerNode('store_backup_files')
                    ->setDeprecated('markocupic/contao-db-backup', '2.0', 'The "%node%" option is no longer used. Configure "contao.backup.keep_max" and "contao.backup.keep_intervals" instead.')
                ->end()
                ->arrayNode('cron_intervals')
                    ->prototype('scalar')->end()
                    ->info('Use one or more cron formats to execute the backup at a specific time. You can also use the text representation of the cron format "yearly", "monthly", "weekly", "daily", "hourly", "minutely".')
                    ->defaultValue(['0 0 * * *'])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
