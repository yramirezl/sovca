# `/opt/backups/script/sovca/` · tareas programadas de SOVCA

Estos scripts son la copia versionada de lo que corre el cron de
producción en `/opt/backups/script/sovca/`. Se versionan aquí porque en `/opt`
no hay historial: cuando el servidor se reinstaló (24/10/2026) se perdieron
los logs y las tareas dejaron de ejecutarse **sin que nadie se enterara**.

## Qué hace cada uno

| Script | Cron | Función |
|---|---|---|
| `crear_alerta.sh` | `0 6 * * 1,3,5` | Regenera la tabla `malertas` (SECADO, DESTETE, CELO, PALPACIÓN, PARTO, CONTROL SANITARIO). **Debe correr antes del envío.** |
| `enviar_alerta.sh` | `0 8 * * 1,3,5` | Envía por correo las alertas pendientes. Acepta `--dry-run`. |
| `backup.sh` | `0 1 * * *` | `pg_dump` comprimido en `/opt/backups/databases`, más la copia `sovca/respaldo_db_sovca_DD-MM-YYYY.tar.gz` que se envía por correo. Rotación de 14 días. |
| `enviar_backup_db.sh` | `0 2 * * *` | Envía el respaldo del día usando `alertas/controlador_email_backup.php` (el controlador oficial, no una copia). |
| `purgar_sync_log.sh` | `30 3 * * *` | Purga `sync_offline_log` (registro del modo offline que alimenta *Dispositivos → Resumen de sync*). Conserva 180 días. Acepta `--dry-run`. |

## Regla importante

**Los scripts crean `/opt/logs` por sí mismos.** La redirección del crontab
(`>> /opt/logs/alerta.log`) se resuelve **antes** de ejecutar el comando: si el
directorio no existe, el comando no corre y no queda ni rastro. Eso fue lo que
dejó al sistema 5 días sin alertas ni copias de seguridad.

## Instalación

```bash
# 1) copiar los scripts
sudo mkdir -p /opt/backups/script/sovca /opt/logs
sudo cp /var/www/html/sovca/scripts/cron/*.sh   /opt/backups/script/sovca/
sudo chown -R root:root /opt/backups/script/sovca
sudo chmod 750 /opt/backups/script/sovca/*.sh
sudo chmod 755 /opt/logs

# 2) crontab
sudo crontab -e
```

```cron
0 6 * * 1,3,5 /opt/backups/script/sovca/crear_alerta.sh     >> /opt/logs/alerta.log 2>&1
0 8 * * 1,3,5 /opt/backups/script/sovca/enviar_alerta.sh    >> /opt/logs/correo.log 2>&1
0 1 * * *     /opt/backups/script/sovca/backup.sh           >> /opt/logs/backup.log 2>&1
0 2 * * *     /opt/backups/script/sovca/enviar_backup_db.sh >> /opt/logs/enviar_backup_db.log 2>&1
30 3 * * *    /opt/backups/script/sovca/purgar_sync_log.sh  >> /opt/logs/sync_log.log 2>&1
```

### Purga del registro de sincronizaciones offline

`purgar_sync_log.sh` borra las filas de `sync_offline_log` con más de 180 días
(configurable con `SOVCA_SYNC_LOG_RETENCION`) y deja en el log cuántas borró:

```bash
# cuántas filas se borrarían (no toca nada)
/var/www/html/sovca/scripts/cron/purgar_sync_log.sh --dry-run

# forzar otra base o retención
SOVCA_SYNC_LOG_BASE=sovca SOVCA_SYNC_LOG_RETENCION=120 \
  /var/www/html/sovca/scripts/cron/purgar_sync_log.sh

# revisar el resultado
tail -20 /opt/logs/sync_log.log
```

Si la tabla no existe (despliegue sin la migración), el script termina con
salida 1 y lo deja escrito: así el cron manda correo en vez de fallar en
silencio. Requiere haber aplicado `scripts/migracion_sync_offline_log.sql`.

`enviar_backup_db.sh` y `enviar_alerta.sh` usan el correo del sistema
(`config/mail.php`, sobreescribible por variables de entorno `SOVCA_SMTP_*` y
`SOVCA_MAIL_*`). Destinatario del respaldo opcional:

```cron
SOVCA_BACKUP_EMAIL=respaldo@ruted.net
```

## Envío de correo: SMTP configurable

Todos los envíos usan `bovs_mail_enviar()` / `bovs_mail_enviar_codigo()` de
`lib/mailer_helper.php`, que leen `config/mail.php`. El servidor SMTP, el
puerto, el cifrado y la clave se pueden fijar por entorno (en el pool de
PHP-FPM, no en el crontab):

| Variable | Ejemplo |
|---|---|
| `SOVCA_SMTP_HOST` | `mail.ruted.net` |
| `SOVCA_SMTP_PORT` | `465` |
| `SOVCA_SMTP_SECURE` | `ssl` o `tls` |
| `SOVCA_SMTP_USER` | `global@ruted.net` |
| `SOVCA_SMTP_PASS` | la clave del buzón |
| `SOVCA_SMTP_HELO` | `app.ruted.net` (debe coincidir con el PTR) |
| `SOVCA_MAIL_FROM` / `SOVCA_MAIL_SENDER` | remitente y Return-Path |

Para diagnosticar un envío:

```bash
sudo -u www-data php scripts/diagnostico_correo.php --to=tu@correo.com
```

## Verificación rápida

```bash
ls -l /opt/logs/
tail -20 /opt/logs/alerta.log
tail -20 /opt/logs/correo.log
tail -5  /opt/logs/backup.log
tail -5  /opt/logs/sync_log.log
ls -lh /opt/backups/databases | tail -5
```

## Prueba sin enviar

```bash
/var/www/html/sovca/scripts/cron/enviar_alerta.sh --dry-run
php /var/www/html/sovca/alertas/controlador_email_backup.php --dry-run
```
