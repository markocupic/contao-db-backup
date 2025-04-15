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

use Contao\CoreBundle\Doctrine\Backup\RetentionPolicy;
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
                ->scalarNode('backup_dir')
                    ->cannotBeEmpty()
                    ->info('Enter the file path where the backups are to be stored on the web server.')
                    ->defaultValue('%kernel.project_dir%/%contao.upload_path%/contao-db-backup')
                ->end()
                ->arrayNode('cron_intervals')
                    ->prototype('scalar')->end()
                    ->info('Use one or more cron formats to execute the backup at a specific time. You can also use the text representation of the cron format "yearly", "monthly", "weekly", "daily", "hourly", "minutely".')
                    ->defaultValue(['0 0 * * *'])
                ->end()
                ->arrayNode('ignore_tables')
                    ->prototype('scalar')->end()
                    ->info('Add one or more tables which will be ignored during the backup process.')
                    ->defaultValue([])
                ->end()
                ->integerNode('keep_max')
                    ->info('The maximum number of backups to keep. Use 0 to keep all the backups forever.')
                    ->defaultValue(5)
                ->end()
                ->arrayNode('keep_intervals')
                    ->info('The latest backup plus the oldest of every configured interval will be kept. Intervals have to be specified as documented in https://www.php.net/manual/en/dateinterval.construct.php without the P prefix.')
                    ->defaultValue(['1D', '7D', '14D', '1M'])
                    ->validate()
                        ->ifTrue(
                            static function (array $intervals) {
                                try {
                                    RetentionPolicy::validateAndSortIntervals($intervals);
                                } catch (\Exception) {
                                    return true;
                                }

                                return false;
                            },
                        )
                    ->thenInvalid('%s')
                    ->end()
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
