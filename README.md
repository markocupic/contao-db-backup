![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Daily database backup for Contao CMS

This Contao extension creates a daily database backup via a cron job and saves it as a gz-compressed SQL dump in “files/contao-db-backup”.
The storage location, the cron interval and the number of days that backups can be stored are configurable.


## Configuration

```
# In your config/config.yaml
markocupic_contao_db_backup:
  store_backup_files: 60 # Store backup files for 60 days
  backup_dir: '%kernel.project_dir%/my_secret_db_backup_dir' # Default %kernel.project_dir%/files/contao-db-backup
  cron_interval: '* */1 * * *' # Run the cron hourly (default: daily)
```

## Command
To execute the database backup on the command line, you can run `php vendor/bin/contao-console markocupic:database-backup`
