<?php

declare(strict_types=1);

/*
 * This file is part of Contao Database Backup.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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
            ->defaultValue(30)
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
