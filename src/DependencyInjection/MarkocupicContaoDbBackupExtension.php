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
        $container->setParameter($rootKey.'.backup_dir', $config['backup_dir']);
        $container->setParameter($rootKey.'.cron_intervals', $config['cron_intervals']);
        $container->setParameter($rootKey.'.ignore_tables', $config['ignore_tables']);
        $container->setParameter($rootKey.'.keep_max', $config['keep_max']);
        $container->setParameter($rootKey.'.keep_intervals', $config['keep_intervals']);

        // Inject configuration to the backup services
        $this->handleBackup($config, $container);

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

    /**
     * Configures the filesystem with a local adapter and adds a virtual filesystem.
     *
     * @param FilesystemConfiguration $config The filesystem configuration instance.
     */
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
     * by tagging it with the specified intervals.
     *
     * @param ContainerBuilder $container The container builder instance
     */
    public function configureCron(ContainerBuilder $container): void
    {
        // Check if the service definition exists
        if ($container->hasDefinition(DatabaseBackupCron::class)) {
            $definition = $container->getDefinition(DatabaseBackupCron::class);
            $intervals = $container->getParameter('markocupic_contao_db_backup.cron_intervals');

            if (!empty($intervals)) {
                foreach ($intervals as $interval) {
                    $definition->addTag('contao.cronjob', [
                        'interval' => $interval,
                    ]);
                }
            }
        }
    }

    private function handleBackup(array $config, ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('markocupic_contao_db_backup.doctrine.backup_manager')) {
            return;
        }

        if (!$container->hasDefinition('markocupic_contao_db_backup.doctrine.backup.retention_policy')) {
            return;
        }

        $retentionPolicy = $container->getDefinition('markocupic_contao_db_backup.doctrine.backup.retention_policy');
        $retentionPolicy->setArgument(0, $config['keep_max']);
        $retentionPolicy->setArgument(1, $config['keep_intervals']);

        $dbDumper = $container->getDefinition('markocupic_contao_db_backup.doctrine.backup_manager');
        $dbDumper->setArgument(3, $config['ignore_tables']);
    }
}
