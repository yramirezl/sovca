# Adecuación de SOVCA (ovina y caprina)

Estado de la migración del código de **BOVS** a **SOVCA**, base `sovca_qas`.

## 1. Punto de partida

El proyecto era una copia del código actualizado de BOVS con muy pocos cambios:

| Elemento | Estado inicial |
| --- | --- |
| `config/conexion.php` | Ya apuntaba a `sovca_qas`. |
| `views/login.php` | Adaptado visualmente (logo y textos SOVCA). |
| Catálogos de la base | `mtipoganaderias` ya tenía OVINA / CAPRINA / TODAS y 38 razas ovinas y caprinas. |
| Resto del código | Sin adaptar: marca BOVS, terminología bovina/bufalina, prefijo de códigos `BOVS-`. |
| Esquema de `sovca_qas` | 87 objetos; le faltaban 34 tablas y 15 vistas del código nuevo. |

Además existía una incoherencia funcional: `config/conexion.php` leía las constantes
`SOVCA_DEMO_*`, pero `config/demo.php` sólo definía `BOVS_DEMO_*`; el modo demo nunca
llegaba a activarse.

## 2. Base de datos

Se generó y aplicó `scripts/migracion_sovca_esquema_bovs.sql`, que lleva `sovca_qas`
al esquema que espera el código migrado. El script:

- crea la extensión `pgcrypto` (necesaria para `gen_random_uuid()`);
- crea las funciones de rangos offline (`reservar_rango_animal`, de servicios y de crías)
  y las de sincronización (`trg_*_marca_sync`);
- crea 34 tablas nuevas: planes, suscripciones, potreros, movimientos de rebaño,
  soporte, validación de correos, conexiones, dispositivos offline, contadores de
  códigos, tablas temporales de sincronización, etc.;
- agrega 62 columnas nuevas a tablas existentes (`uuid_local`, `version`,
  `origen_dispositivo`, `mplanes_id`, columnas de reset de contraseña, ciclo
  reproductivo, etc.);
- crea sus secuencias, llaves, índices y triggers;
- crea 15 vistas nuevas y recrea las 5 vistas cuyo contrato cambió
  (`vusuarios`, `vregservicios`, `vmregnacimientos`, `vdesincorporaciones`,
  `vconsecutivopesos`);
- siembra `mplanes`, `msuscripciones` y `mestatusanimales`;
- asigna el plan DIAMANTE a los fundos y productores que ya existían, para que
  `maximo_registros` no quede en 0 y no se bloquee el registro de animales.

El script es **idempotente**: puede ejecutarse más de una vez. No elimina tablas,
columnas ni datos. Se probó dos veces seguidas sobre un clon de `sovca_qas` antes de
aplicarlo.

Respaldo previo generado en
`/var/www/html/Respaldos/sovca_qas_pre_adecuacion_20261022_2306.backup`.

### Diferencias que se conservaron a propósito

- `mperiodos.codper_sovca` (SOVCA) se mantiene, y se agregó `codper_bov` por
  compatibilidad con las consultas heredadas.
- `mregnacimientos.mrazas_id` y `mregnacimientos.lactancia` se conservan, y la vista
  `vmregnacimientos` devuelve además las columnas nuevas del código migrado
  (`produccion_leche`, `proposito`, `mestatus_id`).
- `maptitudes.mtipoganaderias_id` se conserva (permite aptitudes por especie).

## 3. Código: cambios aplicados

### Configuración

- `config/demo.php` ahora define las constantes canónicas `SOVCA_*` y mantiene
  alias `BOVS_*` para los helpers heredados.
- Base demo: `sovca_demo`; base origen y oficial: `sovca_qas`.
- El login usa `SOVCA_DEMO_ACTIVO` y `SOVCA_DEMO_DIAS`.

### Marca y navegación

- `index.php` redirige a `/sovca/views/login`.
- Título del sistema: **SOVCA | Gestión Ovina y Caprina**.
- Pies de página: OVINA / CAPRINA / OVINA Y CAPRINA según el fundo activo.
- App offline: nombre, manifiesto, título y versión de caché (`sovca-shell-v1`).
- README del proyecto.

### Terminología

- `BOVINA` → `OVINA`, `BUFALINA` → `CAPRINA` en controladores, datos JSON, formularios
  de fundos, importación de demo y reportes.
- Se contempla el tipo 3 (`TODAS`) como “OVINA Y CAPRINA”, que antes caía por error en
  “BUFALINA”.

### Códigos de animales

Todos los puntos que generan códigos ahora usan el prefijo **`SOVCA-`**, que es el que
ya tienen los 216 animales existentes:

- controladores de animales, pesos, control sanitario, desincorporación, nacimientos,
  producción de leche, servicios y migración entre fundos;
- importador de Excel;
- tableros JSON;
- API de sincronización y app offline;
- formularios (autocompletado de códigos);
- reportes PDF.

La lectura acepta tanto `SOVCA-` como `BOVS-` para no romper datos antiguos.

## 4. Verificaciones realizadas

- Migración ejecutada en un clon y después en `sovca_qas`: 136 objetos, 216 animales
  intactos.
- Segunda ejecución del script sin errores (idempotencia).
- `php -l` sobre los 50 archivos modificados: sin errores.
- `node --check` sobre los JS de la app offline modificados: sin errores.
- `GET /sovca/views/login`: HTTP 200, sin warnings ni errores, título correcto.
- Vistas principales consultables: `vusuarios`, `vmregnacimientos`, `vregservicios`,
  `vdesincorporaciones`, `vconsecutivopesos`, `vmfichaxanimales`, `vlistarusuarios`.
- Productores con plan y `maximo_registros = 5000`.

## 5. Reportes PDF

### Inventario comparado con SOVCA v1

Se compararon `sovca/Reportes/reportes/bovs` y `sovca_v1/Reportes/reportes/sovca`.
**No hay ningún reporte exclusivo de v1 que se haya quedado fuera**: los 18
reportes de v1 tienen homónimo en la versión actual.

| Reporte v1 | Equivalente actual |
| --- | --- |
| `pdfcontrol_animal.php` | sí |
| `pdfgruposetarios.php` | sí |
| `pdfprodlechera.php` | sí |
| `pdfrlisanimales_activos.php` | sí |
| `pdfrlisanimales_activos_x_lotes.php` | sí |
| `pdfrlisanimales_gestantes_act.php` | sí |
| `pdfrlisanimales_prodleche_act.php` | sí |
| `pdfrlisanimales_servidas_act.php` | sí |
| `pdfservicio_animal.php` | sí |
| `ral.php` | sí |
| `rca.php` | sí |
| `rge.php` | sí |
| `rla.php` | sí |
| `rlga.php` | sí |
| `rlpa.php` | sí |
| `rlsa.php` | sí |
| `rpl.php` | sí |
| `rsa.php` | sí |

La versión actual incorpora además: `pdfconsecutivo_peso`, `pdfmovimiento`,
`pdfreg_nacimientos`, `pdfresumen_nacimientos`, `rcat`, `rcp`, `rmov`, `rna`,
`rrn`, y las descargas Excel `data/animales.php` y `data/servicios.php`.

### Corrección aplicada (importante)

Los reportes usan ADOdb y **no** pasan por `config/conexion.php`. La conexión estaba
fijada a `sovca_qas` en `Reportes/lib/bd/basedatosAdoIbase.php`, por lo que los PDF
mostraban animales, fundos y categorías de BOVS. Se cambió a **`sovca_qas`**.

Comprobado generando el listado de animales activos: ahora muestra
`USUARIO DEMO SOVCA`, códigos `SOVCA-02-…`, categorías `CABRITA`/`CABRA` y razas
`TOGGENGURG`/`ALPINA`.

### Ajustes de terminología

- Pie de página: “Sistema BOVS-Web” → “Sistema SOVCA” en los 16 reportes que lo usaban.
- Columna “Código BOVS” → “Código SOVCA” (control sanitario, consecutivo de peso,
  nacimientos y resumen de nacimientos).
- “LISTADO DE TERNEROS (Sin Fichar)” y “Nro Total de Terneros” → “LISTADO DE CRÍAS”
  y “Nro Total de Crías”.

Reportes verificados con sesión real: `rla`, `rlga`, `rlsa`, `rlpa` y `rge`
(PDF válido o aviso “No existen datos” cuando el fundo no tiene registros).
`php -l` sin errores en todos los reportes.

### Renombrado de la carpeta (hecho)

`Reportes/reportes/bovs` → **`Reportes/reportes/sovca`**. Se actualizaron los
apuntadores en `data_json` y en las vistas (`dashboard`, `dashboards`, `animales`,
`lotes`, `categorías`, `movreb`). No quedan referencias a `reportes/bovs` en el
código y el dashboard genera sus 11 enlaces a la ruta nueva.

### Pendientes de reportes

1. Si se habilita el modo demostración, los reportes siempre leen `sovca_qas`; habría
   que hacer que `basedatosAdoIbase.php` respete `$_SESSION['demo']`.
2. `Reportes/lib/general/fpdfpg.php` conserva una conexión antigua a `dbname=curso`,
   pero ningún reporte lo incluye (código muerto).

## 6. Pendientes (siguientes fases)

1. **Renombrar clases y archivos con prefijo `bovs-`** (`css/tema-bovs.css`,
   `bovs-tooltip.css`, clases `bovs-*` en vistas y JS). No afecta la operación, pero
   conviene hacerlo de forma coordinada (HTML + CSS + JS).
2. **Textos de correos**: quedan menciones “Sistema BOVS-WEB”, “Bovinos” y enlaces a
   `ruted.net/producto/...bovinos` en `alertas/` (los reportes PDF ya están adaptados).
3. **Terminología zootécnica fina**: revisar etiquetas “ternero”, “becerro”, “vaca”,
   “toro” en formularios y ayudas, y adaptarlas a cordero/cordero, cabrito, oveja,
   carnero, etc. Las columnas de base (`codigo_ternero`, `mternerosxnacimientos`) se
   mantienen por compatibilidad.
4. **Suscripciones**: `mplanesxfundo` está vacía; hay que sembrar la vigencia de las
   cuentas existentes si se va a usar el módulo de renovación.
5. **Modo demostración**: crear `sovca_demo` con `php scripts/demo_instalar.php` y
   probar el alta de una cuenta demo.
6. **Menú y catálogos**: revisar que los módulos de bovinos que no aplican (por
   ejemplo, si hay alguno específico de leche bovina) queden ocultos o renombrados.
7. **Commit inicial del repositorio**: el repo está vacío y todo aparece sin seguimiento.

## 7. Cómo replicar en otro entorno

```bash
# 1. Respaldo
pg_dump -h 127.0.0.1 -U postgres -Fc -f respaldo_sovca_qas.backup sovca_qas

# 2. Migración (idempotente)
psql -h 127.0.0.1 -U postgres -d sovca_qas -v ON_ERROR_STOP=1 \
  -f scripts/migracion_sovca_esquema_bovs.sql

# 3. Verificación rápida
psql -h 127.0.0.1 -U postgres -d sovca_qas -Atc \
  "select count(*) from information_schema.tables where table_schema='public'"
```
