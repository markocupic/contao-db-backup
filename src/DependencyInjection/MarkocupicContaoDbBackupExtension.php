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

use Markocupic\ContaoDbBackup\Cron\BackupCron;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MarkocupicContaoDbBackupExtension extends Extension
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
        $container->setParameter($rootKey.'.cron_intervals', $config['cron_intervals']);

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
     * Configures the cron job for the database backup service
     * by tagging it with the specified intervals.
     *
     * @param ContainerBuilder $container The container builder instance
     */
    public function configureCron(ContainerBuilder $container): void
    {
        // Check if the service definition exists
        if ($container->hasDefinition(BackupCron::class)) {
            $definition = $container->getDefinition(BackupCron::class);
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
}
