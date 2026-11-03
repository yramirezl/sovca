# Envío de alertas desde el equipo local (Plan B)

## Por qué existe

El servidor de producción **no puede enviar correo**: `mail.ruted.net` rechaza su
IP pública (`550 Message rejected` en el saludo de los puertos 25 y 587, y corte
del handshake TLS en el 465). El buzón y la contraseña son correctos — desde
otra IP el mismo envío funciona.

Mientras el proveedor autoriza la IP `57.129.187.133`, este script hace el envío
desde el equipo local, que sí está aceptado.

## Cómo funciona

```
tu equipo                                   servidor (57.129.187.133)
──────────                                  ─────────────────────────
enviar_alertas.sh
  │
  ├─ túnel SSH  localhost:15432 ──────────► PostgreSQL 127.0.0.1:5432
  │
  ├─ php alertas/controlador_email.php
  │     · lee las alertas pendientes  ◄──── base de PRODUCCIÓN
  │     · envía por SMTP desde tu IP  ────► mail.ruted.net (acepta tu IP)
  │     · marca "enviado"             ────► base de PRODUCCIÓN
  │
  └─ cierra el túnel
```

No hay código duplicado: se ejecuta **el mismo controlador** que usa el cron.
Lo único que cambia es de donde lee la base. Y como el registro de "ya enviado"
se escribe en la base de producción, cuando el servidor recupere el envío no
repetirá estas alertas.

## Requisitos

1. Acceso SSH sin contraseña al servidor:
   ```bash
   ssh-copy-id root@57.129.187.133
   ```
2. Credenciales de la base de producción en `config/db.local.php` (no se
   versiona, permisos 600):
   ```php
   <?php
   define('SOVCA_DB_PORT', 6432);          // puerto LIBRE en tu equipo
   define('SOVCA_DB_USER', 'sovca_app');
   define('SOVCA_DB_PASS', 'la-clave-de-produccion');
   ```
   O por entorno: `SOVCA_DB_USER=... SOVCA_DB_PASS=... ./enviar_alertas.sh`

   **El puerto lo manda este archivo.** El script abre el túnel SSH en ese
   mismo puerto local, así no hay que pelearse con la precedencia de
   constantes. Debe estar libre en tu equipo; si lo ocupa tu PostgreSQL o
   PgBouncer local, cambia a otro (por ejemplo 15432). No definas
   `SOVCA_DB_HOST` ni `SOVCA_DB_NAME`: se toman del servidor.
3. La clave del buzón en `config/mail.php` o en `SOVCA_SMTP_PASS`.

> Nunca pongas estas credenciales en `config/db.php`: ese archivo **sí** se
> versiona y terminaría en GitHub. Todo lo local va en `db.local.php`.

## Uso

```bash
# 1) ver qué se enviaría (no envía ni registra nada)
./scripts/envio_local/enviar_alertas.sh --dry-run

# 2) prueba real: todo va a una sola dirección y NO se marca como enviado
./scripts/envio_local/enviar_alertas.sh --a=backupglobalsovcabovs@gmail.com

# 3) envío real a los productores
./scripts/envio_local/enviar_alertas.sh
```

Opciones:

| Opción | Efecto |
|---|---|
| `--dry-run` | Lista los correos y sus alertas. No envía ni registra. |
| `--a=correo@x` | Manda todo a esa dirección y no registra el estado. Para probar. |
| `--sin-tunel` | Usa la base de `config/db.php` / `db.local.php` sin abrir SSH. |

Variables: `SOVCA_REMOTE` (por defecto `root@57.129.187.133`),
`SOVCA_TUNEL_PUERTO` (15432), `SOVCA_REMOTE_DB` (`bovs`).

## Qué revisar después de un envío

```bash
# ¿quedaron registradas en producción?
ssh root@57.129.187.133 "sudo -u postgres psql -d sovca -Atc \
  \"select max(ultimo_envio) from malertas_email_estado\""
```

El comando debe terminar sin errores (salida 0). Si algún correo no salió, el
controlador termina con salida 1 y **no** marca esas alertas, así que se
reintentan en la siguiente ejecución.

## Cuándo dejar de usarlo

Usa la prueba de envío sin mensaje para enterarte, sin molestar a nadie:

```bash
# ¿el servidor ya puede enviar? (no entrega ningún correo)
./scripts/envio_local/monitorear_envio_servidor.sh

# vigilancia automática: reintenta cada 15 min hasta que funcione
./scripts/envio_local/monitorear_envio_servidor.sh --esperar=15
```

La prueba habla SMTP hasta `RCPT TO` y corta con `RSET`: nunca llega a `DATA`,
así que el buzón de control no recibe nada. Si devuelve `0`, el bloqueo por IP
desapareció.

Ese es el momento de dejar de usar este directorio. Si ambos envían a la vez no
se duplican las alertas (el estado vive en la misma base), pero es mejor no
tener dos orígenes de correo: el cron ya se encarga.

Pendiente de infraestructura para ese momento:

1. `ip4:57.129.187.133` añadido al SPF de `ruted.net`.
2. PTR de la IP apuntando a `mail.ruted.net` (panel de OVH).
3. IP autorizada por el proveedor de correo (ticket).
