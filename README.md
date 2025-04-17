![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Daily database backup for Contao CMS

This extension for [Contao CMS](https://contao.org) uses the Contao Core Backup mBnager and creates by default a daily database backup via a cron job.
The backup files are stored under `var/backups`.

## Configuration

```
# In your config/config.yaml
markocupic_contao_db_backup:
    cron_intervals:
        - '0 4 * * *' # Create a backup every day at 4.00 AM (multiple cron jobs possible)

contao:
    backup:
        ignore_tables: [ 'tl_crawl_queue', 'tl_log', 'tl_search', 'tl_search_index', 'tl_search_term' ] # default
        keep_max: 40 # Keep 40 backup files (default 5)
        # https://docs.contao.org/manual/en/cli/db-backups/#configuration
        # time elements (H, M und S) must be prefixed with "T"
        keep_intervals: [ 'T15M','T30M','T45M','T2H','T4H','T6H','T12H','1D','2D','3D','4D','5D','6D','7D','1M','2M','3M','4M','5M','6M' ]
```

## Command

To execute the database backup on the command line, you can run `php vendor/bin/contao-console contao:backup:create`

To show the existing backups you can run `php vendor/bin/contao-console contao:backup:list` on the command line.

To show restore the database you can run `php vendor/bin/contao-console contao:backup:restore backup__20220126153243.sql.gz` on the command line.

## Learn more

https://docs.contao.org/manual/de/cli/datenbank-backups/
