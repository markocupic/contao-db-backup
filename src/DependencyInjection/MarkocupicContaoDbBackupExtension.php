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

use Contao\CoreBundle\DependencyInjection\Filesystem\ConfigureFilesystemInterface;
use Contao\CoreBundle\DependencyInjection\Filesystem\FilesystemConfiguration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MarkocupicContaoDbBackupExtension extends Extension implements ConfigureFilesystemInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('services.yaml');

        $rootKey = $this->getAlias();
        $container->setParameter($rootKey.'.store_backup_files', $config['store_backup_files']);
        $container->setParameter($rootKey.'.backup_dir', $config['backup_dir']);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return Configuration::ROOT_KEY;
    }

    public function configureFilesystem(FilesystemConfiguration $config): void
    {
        $storageName = 'markocupic_db_backups';

        $backupPath = $config->getContainer()->getParameterBag()->resolveValue('%markocupic_contao_db_backup.backup_dir%');

        $config
            ->mountLocalAdapter($backupPath, $storageName, $storageName)
            ->addVirtualFilesystem($storageName, $storageName)
        ;
    }
}
