![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Tägliches Datenbank-Backup für Contao CMS

Diese Contao Extension erzeugt via Cron Job täglich ein Datenbank-Backup und speichert dieses als SQL-Dump im Dateisystem ab.
Damit das Plugin funktioniert, muss die Ausführung der PHP Funktion "exec()" auf dem Hosting freigeschaltet sein.

## Konfiguration
Standardmässig bleiben die Backup-Dateien für 30 d auf dem Server und werden dann automatisch gelöscht.
Die Zeit vor dem Löschvorgang kann jedoch konfiguriert werden.

```
# In your config/config.yaml
markocupic_contao_db_backup:
  store_backup_files: 60 # Store backup files for 60 days
```
