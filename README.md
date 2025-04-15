![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Daily database backup for Contao CMS

This Contao extension creates by default a daily database backup via a cron job and saves it as a gz-compressed SQL dump in `files/contao-db-backup`.

## Configuration

```
# In your config/config.yaml
markocupic_contao_db_backup:
    backup_dir: '%kernel.project_dir%/../contao_database_backups' # default %kernel.project_dir%/files
    ignore_tables: [ 'tl_crawl_queue', 'tl_log', 'tl_search', 'tl_search_index', 'tl_search_term' ] # default
    keep_max: 40 # Keep 40 backup files (default 5)
    # https://docs.contao.org/manual/en/cli/db-backups/#configuration
    # time elements (H, M und S) must be prefixed with "T"
    keep_intervals: [ 'T15M','T30M','T45M','T2H','T4H','T6H','T12H','1D','2D','3D','4D','5D','6D','7D','1M','2M','3M','4M','5M','6M' ]
    cron_intervals:
        - '*/15 * * * *'
        #- '0 4 * * *' # multiple intervals possible
```

## Command
To execute the database backup on the command line, you can run `php vendor/bin/contao-console markocupic:backup:create`
