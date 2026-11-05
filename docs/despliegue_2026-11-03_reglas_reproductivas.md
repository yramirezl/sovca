# Despliegue en producción · reglas reproductivas (2026-11-03)

Esta entrega corrige el comportamiento reproductivo de ovinos y caprinos y dos
flujos que quedaban bloqueados: la **unificación de nacimientos duplicados** y
el botón **Incluir** del control sanitario.

| Dato | Valor |
|---|---|
| Rama | `main` |
| Tag de la entrega | `v2026.11.03` |
| Commit anterior (rollback) | `bba11a8` |
| Ruta en producción | `/var/www/html/sovca` |
| Base principal | `sovca` |
| Base de demostración | `sovca_demo` |
| Usuario de la aplicación | `sovca_app` |
| Migraciones SQL | `sudo -u postgres psql` directo al 5432 |

> No ejecutar `scripts/desplegar.sh` en el equipo de desarrollo: está pensado
> para el servidor de producción (usa `systemctl`, permisos de `www-data` y la
> base real).

---

## 0. Antes de empezar

1. Avisar una ventana corta de mantenimiento.
2. Respaldar la base principal:

   ```bash
   cd /var/www/html/sovca
   RESPALDO="/tmp/sovca_$(date +%F_%H%M).dump"
   sudo -u postgres pg_dump -Fc -d sovca -f "$RESPALDO"
   sudo mv "$RESPALDO" /root/
   ```

3. Confirmar que `config/db.local.php` existe y apunta a la base real
   (`SOVCA_DB_NAME`, `SOVCA_DB_USER`). Ese archivo **no se versiona** y el
   despliegue no lo toca.

---

## 1. Subir el código

El repositorio de producción es un espejo de `origin/main`:

```bash
cd /var/www/html/sovca
sudo ./scripts/desplegar.sh
```

El script:

- hace `git fetch` y `git reset --hard origin/main`;
- respalda commits o archivos sueltos que encuentre;
- ajusta permisos y comprueba que `www-data` pueda leer el código;
- valida la sintaxis PHP de los archivos cambiados;
- recarga PHP-FPM;
- verifica la conexión a la base y sincroniza el esquema de `sovca_demo`.

Al terminar debe mostrar el commit de esta entrega:

```bash
git log --oneline -1
# Debe ser el commit de main con el tag v2026.11.03
```

---

## 2. Aplicar las migraciones SQL

Son idempotentes y solo ajustan datos de catálogo (`mperiodos`,
`mestatusanimales`). Se ejecutan como `postgres` por el puerto 5432, igual que
las demás migraciones del manual.

```bash
cd /var/www/html/sovca

DB="$(php -r 'require "config/db.php"; echo SOVCA_DB_NAME;' 2>/dev/null)"
DEMO="$(php -r 'require "config/db.php"; echo SOVCA_DB_DEMO_NAME;' 2>/dev/null)"

for base in "$DB" "$DEMO"; do
    if ! sudo -u postgres psql -Atc \
         "select 1 from pg_database where datname = '$base'" postgres | grep -qx 1; then
        echo "· $base no existe: se omite"
        continue
    fi

    echo "== $base =="
    for sql in \
        scripts/migracion_secado_caprino_120.sql \
        scripts/migracion_periodos_codper_bov.sql \
        scripts/migracion_estatus_animal.sql \
        scripts/migracion_edad_minima_hembra.sql
    do
        echo "-- $sql"
        # El archivo lo lee el shell (root) y se pasa por stdin. Con -f,
        # psql corre como postgres y no puede abrir el código 640 root:www-data.
        sudo -u postgres psql -d "$base" -P pager=off \
             -v ON_ERROR_STOP=1 < "$sql" || exit 1
    done
done
```

Orden importante:

1. `migracion_secado_caprino_120.sql`: crea `PSD` (210 ovino / 120 caprino) y
   `PBL` (120) y ajusta el `PPL` global caprino a 120.
2. `migracion_periodos_codper_bov.sql`: completa `codper_bov` en los períodos
   que hayan quedado con `0` o vacío.
3. `migracion_estatus_animal.sql`: deja los estatus 4 y 5 como femeninos y los
   2 y 3 como masculinos.
4. `migracion_edad_minima_hembra.sql`: crea el período `EHM` con 210 días.

### Verificación SQL

```bash
sudo -u postgres psql -d "$DB" -P pager=off -c "
SELECT codper_bov, mtipoganaderias_id, dias_gestacion, mfundos_id
  FROM mperiodos
 WHERE codper_bov IN ('PSD','PBL','PPL','EHM')
 ORDER BY 1,2,4;"

sudo -u postgres psql -d "$DB" -P pager=off -c "
SELECT id, nombre, sexo FROM mestatusanimales ORDER BY id;"
```

Esperado:

- `PSD`: 210 días para ovinos (tipo 1) y 120 para caprinos (tipo 2).
- `PBL`: 120 días.
- `PPL` caprino global: 120 días.
- `EHM`: 210 días.
- Estatus: `1 ACTIVO 0`, `2 DONADOR ACTIVO 2`, `3 DONADOR 2`,
  `4 DONADORA ACTIVO 1`, `5 DONADORA 1`.

---

## 3. Fechas históricas de secado (opcional recomendado)

El código nuevo calcula bien las fechas **nuevas y editadas**, pero las fechas
históricas se generaron con reglas viejas. El ejecutor recalcula
`mregnacimientos.f_secado` y `mregservicios.f_secado` con la regla única.

Primero simular (no escribe nada):

```bash
cd /var/www/html/sovca
php scripts/migracion_fecha_secado.php --modulo=todos
```

Revisar los contadores. Si el resultado es razonable, aplicar:

```bash
php scripts/migracion_fecha_secado.php --modulo=todos --aplicar
```

Guardar el **código de lote** que imprime. El script actualiza `updated` y
`version` para que la PWA reciba el cambio en la siguiente sincronización.

> El ejecutor crea/usa la tabla `migracion_fecha_secado_log`. Si esa tabla no
existe y falla por permisos, ejecutarlo con las variables de administración del
manual (§8): `SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_PORT=5432 SOVCA_DB_USER=postgres
SOVCA_DB_PASS=...`.

> Si solo se quieren completar fechas vacías sin tocar las existentes, usar
> `--solo-vacios`. Para revertir, restaurar el respaldo del paso 0; los archivos
> `*_revert.sql` no están incluidos en esta entrega.

---

## 4. Verificación web

1. Abrir:

   ```text
   https://<dominio>/sovca/verificar_despliegue.php?k=sovca-verif-2026&reset=1
   ```

   No debe quedar ninguna fila en rojo. La página comprueba, entre otros, los
   marcadores nuevos de nacimientos, control sanitario, PWA y unificación.

2. Iniciar sesión y comprobar:
   - **Animales → ficha**: el PDF no muestra `?` en campos vacíos.
   - **Animales → estatus**: una hembra no ve `DONADOR` / `DONADOR ACTIVO`.
   - **Servicios**: una hembra de 7 meses ya puede registrarse; el control
     muestra período de palpación, período de gestación y fechas probables.
   - **Nacimientos**: la búsqueda de servicio gestante usa 4–7 meses; la fecha
     probable de destete se calcula con el período de lactancia.
   - **Nacimientos → Revisar duplicados**: un grupo con números de parto
     distintos o servicios distintos se puede unificar.
   - **Control sanitario → Agregar producto → Incluir**: guarda el producto y,
     si falta un dato, muestra el motivo.
   - **Caprinos**: el secado se calcula con 120 días.

3. Revisar el log de PHP-FPM por errores nuevos:

   ```bash
   sudo tail -n 80 /var/log/php8.4-fpm.log
   ```

---

## 5. PWA / equipos offline

Esta entrega publica:

- `app_offline/versiones.json` → **1.00047**;
- `app_offline/service-worker.js` → caché **sovca-shell-v5**.

Los equipos con la app abierta verán el aviso de versión nueva y el botón
**Actualizar ahora**. No hay que borrar datos ni reinstalar la app.

---

## 6. Rollback

### Código

```bash
cd /var/www/html/sovca
sudo ./scripts/desplegar.sh --rollback bba11a8
```

### Base de datos

Las migraciones de esta entrega son **aditivas** (INSERT/UPDATE de catálogos),
así que no requieren rollback para que el código anterior siga funcionando. Si
fuera necesario deshacer todo:

```bash
sudo -u postgres pg_restore --clean --no-owner --no-privileges \
     -d sovca < /root/sovca_<fecha>.dump
```

Si se aplicó la migración histórica de secado y se quiere revertir, restaurar el
respaldo del paso 0 (o el lote indicado por el script, si se conserva el log).

---

## 7. Checklist final

- [ ] `git log --oneline -1` en el servidor muestra el commit de la entrega.
- [ ] `config/db.local.php` sigue apuntando a `sovca` / `sovca_app`.
- [ ] Las cuatro migraciones se ejecutaron sin `ON_ERROR_STOP`.
- [ ] `PSD`, `PBL`, `PPL` y `EHM` tienen los días esperados.
- [ ] Los estatus 2/3 son masculinos y 4/5 femeninos.
- [ ] `verificar_despliegue.php?k=sovca-verif-2026` sin rojos.
- [ ] Login, servicios, nacimientos, control sanitario y PDF probados.
- [ ] La PWA muestra la versión 1.00047 y actualiza el shell.
- [ ] Respaldo de base guardado en `/root/`.

---

## 8. Correcciones posteriores (2026-11-05)

### Fecha de secado calculada

En el **control del servicio**, la fecha de secado ya no se elige en el
calendario: el campo es de solo lectura y el servidor la calcula al guardar.

- Intervalo último parto → servicio **menor a 6 meses**:
  `fecha de servicio + 120 días` (4 meses). En caprinos siempre 120; en ovinos
  se respeta `PSD` si está entre 1 y 180 días (si no, 210).
- Intervalo de 6 meses o más: se mantiene `fecha de parto + PPL`.

Detalle en `docs/servicios_reglas_reproductivas.md` §4.

### Error 25P02 en unificación de nacimientos y control sanitario

`SQLSTATE[25P02] current transaction is aborted` aparecía cuando una
sentencia fallaba dentro de la transacción: PostgreSQL rechaza todo lo que
venga después, así que el error real quedaba oculto y la operación moría.

- Nuevo `lib/transaccion_helper.php`: transacciones con **SAVEPOINT** y
  `ROLLBACK TO SAVEPOINT`, de modo que un fallo interno no envenena la
  transacción.
- Si aun así llega un 25P02, ambos flujos **revierte**n y **reintentan una
  vez** desde cero (la pasada anterior no deja cambios).
- **Unificación de nacimientos**: se corrigieron dos choques con
  restricciones UNIQUE reales:
  - `codigo_ternero` es UNIQUE: el duplicado libera su código (queda como
    `…-U<id>`) antes de que lo tome el registro principal.
  - `mternerosxnacimientos (mregnacimientos_id, nro_ternero)` es UNIQUE e
    incluye los detalles archivados: al crear o renumerar detalles se apartan
    primero los archivados a valores negativos.
- **Control sanitario**: se valida que el control exista en el fundo antes de
  insertar el producto y se informa el motivo real si algo falla.

### Nacimientos: tope de crías por parto según la especie

| Especie del fundo (`mfondos.mtipoganaderias_id`) | Crías por parto |
|---|---|
| Caprina (2) | **3** |
| Ovina (1) | **4** |

La regla vive en `lib/especie_helper.php` (`bovs_max_crias_parto()` /
`bovs_mensaje_max_crias_parto()`) y se aplica en el alta
(`mnacimientos_controller` · `case 'add'`), en la edición (antes de guardar),
en el formulario web (`frm_registrar` / `frm_editar`), en la app sin conexión
(`app_offline/js/app.js`) y en la sincronización (`api/sync_controller.php`).
Antes el tope era fijo en 5 crías.

### Alta de nacimiento: fin del HTTP 500 sin mensaje

`POST mnacimientos_controller action=add` respondía **500 con el cuerpo
vacío** cuando PHP moría por un error fatal no controlado (y el navegador solo
podía mostrar «Error de comunicación con el servidor (HTTP 500)»).

- Un `register_shutdown_function` + un `try/catch` alrededor de todo el
  `switch` devuelven siempre JSON con el detalle del error (que además queda
  en el log como `[NAC] error fatal: …`).
- Los `save()` de las crías, del vínculo con el fundo y del cierre del
  servicio gestante se ejecutan dentro de `try/catch`: `Model::save()` lanza
  `DatabaseException` en vez de devolver `false`, y esa excepción rompía la
  respuesta.
- `observaciones` (NOT NULL) se normaliza a cadena vacía si el campo no llega,
  y `$dias` (período `PPS`) se inicializa: los fondos sin período PPS
  provocaban un *Undefined variable*.

### Botones Editar / Guardar / Regresar arriba del formulario

`js/crud-form-acciones.js` (cargado desde `views/layouts/pie.php`) mueve los
botones de la barra inferior (`.crud-form__actions`) a la cabecera del
formulario (`.crud-form__header-actions`), a la izquierda de «Regresar». Son
los mismos nodos, así que los manejadores de cada vista siguen intactos. En
móvil los tres botones comparten una sola fila. Aplica a todos los
formularios de proceso y de gestión sin tocar cada vista.

No requiere migraciones SQL.

---

## 9. Despliegue de la entrega del 2026-11-05

**No requiere migraciones SQL.** Es solo código, estilos y documentos.

### 9.1 Un solo paso

El despliegue se hace con el script de siempre, ya actualizado con los
controles de esta entrega:

```bash
cd /var/www/html/sovca
sudo ./scripts/desplegar.sh --dry-run    # opcional: ver qué haría
sudo ./scripts/desplegar.sh
```

El script:

1. hace `git fetch` y `git reset --hard origin/main` (producción es espejo
   del repositorio);
2. **protege la configuración del servidor** (ver 9.2);
3. ajusta permisos y comprueba que el usuario del pool de PHP-FPM lea el
   código;
4. valida la sintaxis PHP de los archivos cambiados;
5. **comprueba los archivos de esta entrega**:
   - `lib/transaccion_helper.php`
   - `js/crud-form-acciones.js`
   - que los controladores de nacimientos y de control sanitario los usen.
   Si falta alguno, revierte el despliegue;
6. recarga PHP-FPM;
7. verifica la conexión a la base (`SOVCA_DB_NAME`) y sincroniza `sovca_demo`;
8. informa la versión del frontend offline y la caché del shell.

### 9.2 Configuración del servidor que NO se reemplaza

| Archivo | Contenido | Trato en el despliegue |
|---|---|---|
| `config/db.local.php` | Credenciales de PostgreSQL | Fuera de git. Se respalda y se conserva |
| `lib/firma.local.php` | Clave HMAC de los QR y URL pública | Fuera de git. Se respalda y se conserva |
| `config/mail.php` | SMTP, remitente y HELO | Si el servidor lo tiene modificado, se respalda y **se restaura la versión del servidor** |

Los dos `*.local.php` no los toca `git reset` (no están versionados); el
script los copia a `/root/sovca-respaldo-<fecha>/` antes del reset por si
acaso. `config/mail.php` sí está versionado: si en el servidor se ajustó
(host, puerto, remitente), el script lo detecta, lo respalda y lo devuelve
tras el reset, avisando en pantalla. La contraseña SMTP se recomienda
pasarla por la variable de entorno `SOVCA_SMTP_PASS` del pool de PHP-FPM.

### 9.2.1 Datos que genera el servidor (no se versionan)

| Carpeta | Contenido | Trato |
|---|---|---|
| `dist/img/fotos_animales/` | Fotos de los animales (`.webp`) | **Ignorada** por su propio `.gitignore`. El despliegue no la toca |
| `file/correo/` | Copias de correos del modo de prueba | Ignorada. Puede contener códigos de recuperación |

Las fotos se generan al registrar o editar un animal (y al sincronizar desde
la app sin conexión) y viven **solo** en el servidor:

- `git reset --hard` no borra archivos ignorados, así que se conservan entre
  despliegues; el script informa «N foto(s) de animales en el servidor (no se
  tocan)».
- Si alguna foto llegara a quedar **versionada por error** (un `git add -A`
  en el servidor), el script la respalda y **devuelve la versión del
  servidor** después del reset, con el aviso «foto versionada por error».
- No hace falta subirlas al repositorio. Para respaldarlas, copiarlas aparte:

  ```bash
  sudo tar czf /root/sovca-fotos-$(date +%F).tar.gz -C /var/www/html/sovca \
       dist/img/fotos_animales --exclude=index.php --exclude=.gitignore
  ```

### 9.3 Caché de navegador

Ya viene resuelto en el código:

- `views/layouts/constantes.php` → `tema-bovs.css?v=14`
- `views/layouts/pie.php` → `crud-form-acciones.js?v=3`

Si algún equipo sigue viendo los botones al final del formulario, recarga
forzada (Ctrl+F5 en escritorio; mantener «recargar» en el móvil).

### 9.4 App sin conexión (PWA)

- `app_offline/service-worker.js` sube a `sovca-shell-v6`: las PWA
  instaladas descargan el shell nuevo (tope de crías 3/4).
- `app_offline/versiones.json` queda en **1.00048** (se ve en el badge).

En los equipos de campo: abrir la app **con internet una vez** y comprobar
en *Nacimientos → Nueva cría* que «Agregar cría» se detiene en 3 (caprinos)
o 4 (ovinos).

### 9.5 Verificación funcional después del despliegue

- [ ] Login y cambio de fundo.
- [ ] Ficha animal / servicios / control sanitario: botones
      **Editar · Guardar · Regresar** en la cabecera, también en móvil.
- [ ] Control de servicio: la fecha de secado no se puede escribir y se
      calcula sola.
- [ ] Registrar nacimiento: guarda; si falta un dato muestra el motivo
      (ya no aparece un 500 vacío).
- [ ] 4.º ternero en un fundo caprino → *«Un parto de caprinos admite como
      máximo 3 crías»*.
- [ ] Revisar duplicados → unificar: termina sin 25P02.
- [ ] Control sanitario → Incluir producto: guarda sin 25P02.

### 9.6 Reversión

```bash
cd /var/www/html/sovca
sudo ./scripts/desplegar.sh --rollback a3dd83d    # commit previo a esta entrega
```

No hay cambios de base de datos que revertir. Los respaldos de la corrida
anterior quedan en `/root/sovca-respaldo-<fecha>/`.
