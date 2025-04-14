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
use Markocupic\ContaoDbBackup\Cron\DatabaseBackupCron;
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
        $container->setParameter($rootKey.'.cron_interval', $config['cron_interval']);

        // Configure the cron interval from configuration
        $this->configureCron($container);
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
        $storageName = 'markocupic_database_backups';

        $backupPath = $config->getContainer()->getParameterBag()->resolveValue('%markocupic_contao_db_backup.backup_dir%');

        $config
            ->mountLocalAdapter($backupPath, $storageName, $storageName)
            ->addVirtualFilesystem($storageName, $storageName)
        ;
    }

    /**
     * Configures the cron job for the database backup service
     * by tagging it with the specified interval.
     *
     * @param ContainerBuilder $container The container builder instance
     */
    public function configureCron(ContainerBuilder $container): void
    {
        // Check if the service definition exists
        if ($container->hasDefinition(DatabaseBackupCron::class)) {
            $definition = $container->getDefinition(DatabaseBackupCron::class);
            $definition->addTag('contao.cronjob', [
                'interval' => $container->getParameter('markocupic_contao_db_backup.cron_interval'),
            ]);
        }
    }
}
