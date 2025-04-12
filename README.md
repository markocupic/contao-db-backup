![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Daily database backup for Contao CMS

This Contao extension creates a daily database backup via a cron job and saves it as an SQL dump in the file system.
For the plugin to work, the execution of the PHP function “exec()” must be enabled on the hosting.

## Configuration

By default, the backup files remain on the server for 30 d and are then automatically deleted.
However, the time before the deletion process can be configured.

```
# In your config/config.yaml
markocupic_contao_db_backup:
  store_backup_files: 60 # Store backup files for 60 days
```
