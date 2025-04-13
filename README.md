![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Daily database backup for Contao CMS

This Contao extension creates a daily database backup via a cron job and saves it as gz compressed SQL dump in "files/contao-db-backup".

## Configuration

By default, the backup files remain on the server for 30 d and are then automatically deleted.
However, the backup folder and the time before the deletion process can be configured.

```
# In your config/config.yaml
markocupic_contao_db_backup:
  store_backup_files: 60 # Store backup files for 60 days
  backup_dir: '%kernel.project_dir%/my_secret_db_backup_dir' # Default %kernel.project_dir%/files/contao-db-backup
```

## Command
To execute the database backup on the command line, you can run `php vendor/bin/contao-console contao:markocupic-database-backup`
