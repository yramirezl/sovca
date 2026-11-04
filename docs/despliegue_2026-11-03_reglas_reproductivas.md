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
