# Portado desde BOVS — mejoras del 25-10-2026

Cambios llevados de BOVS a SOVCA respetando las diferencias propias de SOVCA
(prefijo `SOVCA-`, pies de página y búsquedas por código).

## 1. Gestor de Reportes Parametrizables (`views/dashboard/dashboard.php`)

- **Código BOVS y Lote como valores únicos**: se eliminaron los rangos
  «Desde/Hasta» (`cod_bovs_inicio/final`, `lote_inicio/final`). Ahora hay un
  `#cod_bovs_unico` (oculto, lo llena el buscador) y un `#lote_unico`.
  Al generar se envía el mismo valor en `cod_bovs_inicio`/`cod_bovs_final` y
  `lote_inicio`/`lote_final`.
- **Exclusividad código/lote** en Producción de Leche (`excluirCodigoLote`,
  aviso `#hint_exclusivo`).
- **Buscador interactivo de animales** (`#sel_rep_cod` y `#sel_rep_unico`) con
  `js/servicio-selector.js`: filtra por nombre, código BOVS o N° de arete.
  Se eliminaron el campo de texto manual, los checkboxes Nro Arete/Código y el
  botón Validar. El animal elegido se marca en el pie («Seleccionado: …»).
- **Consecutivo de Peso** admite ahora filtro por animal (`cod_bovs`).
- Se cargan `css/servicio-selector.css` y `js/servicio-selector.js`.
- Backend: nueva acción `listar_animales_reporte` en
  `controllers/manimales_controller.php`.

## 2. Nomenclatura de salida de los reportes

- Nuevo `Reportes/lib/reporte_contexto_helper.php`:
  - `bovs_reporte_contexto()` detecta animal / lote / rango y resuelve nombre y
    arete (`manimales`) y nombre del lote (`mlotes`).
  - `bovs_reporte_titulo()` y `bovs_reporte_nombre_archivo()` arman
    `REPORTE - DETALLE - Generado el dd-mm-aaaa` y
    `Reporte_DETALLE_dd-mm-aaaa.pdf`.
- Aplicado en los wrappers `rpl`, `rsa`, `rca`, `rcp`, `rna`, `rge`, `rla`,
  `rlga`, `rlsa`, `rlpa`, `ral`, `rmov` y `rcat`.
- El contexto se imprime en el encabezado de `pdfprodlechera`,
  `pdfservicio_animal`, `pdfconsecutivo_peso`, `pdfcontrol_animal` y
  `pdfreg_nacimientos` (en los dos últimos sustituye al `subtitulo_reporte`).

## 3. Nacimientos

- «Propósito ?» → **«Entrar en lactancia ?»** con **Sí (2) / No (0)**.
- «P. Prod. Leche» → **«Periodo de lactancia»** (muestra el PPL del fundo).
- «Fecha Secado / Destete» → **«Fecha de secado»** = fecha de parto + PPL.
- Al editar, un registro viejo con `proposito = 1` se muestra como **Sí**.
- **Tabla de terneros**: `table-layout:fixed` con anchos
  5/17/17/13/20/23/5 %, controles de 32 px alineados y error del ID flotante.
- **Observaciones** habilitada desde el inicio (registrar y editar).
- **Ficha pendiente + alta precargada** (`frm_ficha.php`): resuelve el ternero,
  muestra sus datos, calcula la **raza esperada** (servicio → madre → padre) y
  abre `animales/frm_registrar` con sexo, fecha, peso, aptitud, raza, servicio,
  madre y padre precargados.
- En `controllers/manimales_controller.php` se añadió
  `bovs_marcar_nacimiento_fichado()` para no fallar cuando el alta no viene de
  un nacimiento (`id_reg_nac` vacío).

## 4. App sin conexión

- `app_offline/index.html`, `js/app.js` y `js/nacimientos.js`: «Entrar en
  lactancia» (Sí/No) y etiquetas del detalle.
- `versiones.json` → **4.42**.

## Verificado

- `php -l` y `node --check` en todos los archivos tocados.
- Gestor: listado de 100 animales, filtro «KA», selección de `SOVCA-05-018`,
  Generar habilitado y exclusividad código/lote activa.
- Reportes generados:
  - `Reporte_de_Nacimientos_01-01-2020_al_31-12-2026_25-10-2026.pdf` con
    «Del 01-01-2020 al 31-12-2026».
  - `Control_Sanitario_KA_053_25-10-2026.pdf` con «KA 053 · SOVCA-05-018».
  - `Consecutivo_de_Peso_KA_053_25-10-2026.pdf` con «KA 053 · SOVCA-05-018».
- Ficha pendiente del nacimiento 24: raza esperada **KATAHDIN** y alta abierta
  con `#raza_animal = 5`, sexo, fecha, peso, aptitud y código del ternero.

## Nota

La nomenclatura usa `basedatosAdoIbase` (la conexión propia de los reportes) y
`animal_codigo_helper`, que en SOVCA ya contempla los prefijos `SOVCA-` y
`BOVS-`. No requiere migración de base de datos.

## 5. Gestor de Reportes: catálogo filtrado por reporte (2026-10-25)

Portado desde BOVS con el mismo parche, comparado bloque a bloque:

- `controllers/manimales_controller.php` → `listar_animales_reporte` acepta
  `reporte`, `fecha_inicio` y `fecha_final` y devuelve solo los animales con
  datos de ese reporte (`mprolecheras`, `mconsecutivopesos`, `mcontrolsanitarios`,
  `mregnacimientos`, `mregservicios`), con el conteo en `etiqueta`.
- `views/dashboard/dashboard.php` → `params` del selector, `recargarSelectorReporte()`,
  franja `#rep_animal_sel` con el animal confirmado y botón *Cambiar*, y textos
  de vacío por reporte.

Probado contra `sovca_qas`: fundo 5 → 31 controles, 56 nacimientos, 96 servicios,
1 pesaje, 0 producciones (el fundo no registra leche); fundo 2 → 1 producción y
1 pesaje. `php -l` y `node --check` OK. Sin migración de base de datos.

## 6. Nacimientos: máximo 2 terneros por parto (2026-10-25)

Portado desde BOVS con el mismo parche:

- `views/nacimientos/frm_registrar.php` → `MAX_TERNEROS = 2`, bandera
  `ternerosBloqueados`, `actualizarBotonAgregarTernero()` y aviso
  `#nac_terneros_tope`.
- `controllers/mnacimientos_controller.php` (acción `add`) → rechaza más de 2
  terneros.
- `app_offline/js/app.js` → `MAX_TERNEROS_PARTO = 2`.
- `app_offline/versiones.json` → **4.43**.

`php -l` y `node --check` OK en los tres archivos. Sin migración de base de datos.

> **PENDIENTE · SOVCA debe permitir hasta 5 crías por parto** (BOVS se queda en 2).
> Cambiar el tope a 5 en: `views/nacimientos/frm_registrar.php`
> (`MAX_TERNEROS = 5`), `controllers/mnacimientos_controller.php`
> (`count($terneros) > 5`) y `app_offline/js/app.js`
> (`MAX_TERNEROS_PARTO = 5`), más la nota en `versiones.json`. Los textos de
> ayuda y el aviso usan la variable, así que se ajustan solos.

## 7. Nacimientos con mellizos: una ficha por cría (2026-10-25)

Portado desde BOVS con los mismos parches:

- `controllers/mnacimientos_controller.php` → acción
  `listar_terneros_nacimiento` y sincronización de crías en `edit`
  (actualiza, inserta y desactiva con `status = 0`; los campos planos quedan
  con la primera cría).
- `views/nacimientos/frm_ficha.php` → tarjeta «Crías del parto» con una fila
  por ternero, estado de ficha y botón **Crear ficha** propio (o **Ver ficha**).
- `views/nacimientos/frm_editar.php` → bloque «Crías del parto» en tarjetas,
  con *Agregar cría*, *Quitar* y *Fichar crías*; el ID de una cría fichada es
  de solo lectura.

Probado contra la base `sovca`: nacimiento **89** (fundo 5) con crías `360`
(hembra, 30 kg) y `365` (macho, 28 kg) → `listar_terneros_nacimiento` devuelve
`total: 2` con `id_cria` 1 y 2, sexo, peso y aptitud de cada una.

`php -l` y `node --check` OK. Sin migración de base de datos.

> El soporte de mellizos es independiente del tope de crías por parto: en SOVCA
> ese tope debe ser **5** (pendiente), en BOVS es 2.

## 8. Período de lactancia y fichas por cría en el listado (2026-10-25)

- `views/nacimientos/frm_registrar.php` y `frm_editar.php` → la caja «Periodo de
  lactancia» se llena con `periodo_lactancia` (PBL) y la fecha de secado se
  calcula con `periodoLactanciaDias` (antes usaba PPL).
- `controllers/mnacimientos_controller.php` → `calcularFechaSecadoNacimiento()`
  calcula con PBL.
- `data_json/data_mnacimientos.php` → con varias crías el estado es
  «X de Y fichadas».
- `views/nacimientos/index.php` → resalta en rojo mientras falte alguna cría por
  fichar.

Verificado: el listado devuelve `MA-05-085 | 2 crías: 360, 365 | 0 de 2 fichadas`.
`php -l` y `node --check` OK.

> Nota: en la base local `sovca` no hay períodos PBL/PPL configurados, así que
> `get_periodos_lactancia` devuelve `0 Dias`. Hay que cargarlos en
> Parámetros → Períodos para que la caja y el secado funcionen en este entorno.

## 9. Modo mantenimiento, conexiones y paridad con BOVS (2026-10-25)

SOVCA quedó al mismo nivel de avance que BOVS. Piezas nuevas:

### Modo mantenimiento (completo)

| Archivo | Contenido |
|---|---|
| `lib/mantenimiento_helper.php` | Interruptor por entorno (`SOVCA_MANTENIMIENTO` / `BOVS_MANTENIMIENTO`), `file/mantenimiento/estado.json` o `config/mantenimiento.php`; cierre automático, bitácora y roles permitidos (1 y 4). |
| `controllers/mmantenimiento_controller.php` | `estado`, `activar`, `desactivar`, `bitacora`. Solo roles 1/4. |
| `views/mantenimiento/index.php` | Panel: motivo, duración, mensaje público, estado, bitácora y accesos a Modo soporte. |
| `file/mantenimiento/` | `estado.json` (se crea al activar), `bitacora.log`, `index.php` e `.gitignore`. Carpeta `777` y archivos `666`. |

Integraciones (cinco capas, igual que BOVS):

- `config/conexion.php` → red de seguridad global (403 JSON).
- `lib/validar_session.php` → cierra la sesión del productor/ayudante y redirige al login.
- `lib/sanitizar.php` → 403 `{codigo:"mantenimiento"}` en acciones AJAX.
- `Reportes/lib/bd/basedatosAdoIbase.php` → aviso 403 en los PDF.
- `controllers/musuarios_controller.php` → login bloqueado (`?stat=mantenimiento`).
- `controllers/demo_controller.php` → no se crean/verifican cuentas demo.
- `views/login.php`, `views/layouts/barra_navegacion_superior.php`,
  `views/layouts/menu_lateral_izquierdo.php`, `views/inicio/blank.php` → avisos,
  chip «Mantenimiento → Gestionar» y tarjeta con accesos.
- `api/lib/ApiAuth.php` y `api/auth_controller.php` → 403 `codigo: mantenimiento`.
- `app_offline/js/api.js`, `app.js`, `sync.js` → aviso en la PWA y registros
  pendientes conservados. `versiones.json` → **4.44**.

**Verificado por HTTP** (servidor local, sesiones de prueba):
administrador ve el panel y `estado` (200); productor recibe 403 `no_autorizado`
al intentar gestionar; con el modo activo el productor es redirigido a
`views/login?stat=mantenimiento`, el AJAX responde **403**
`{"codigo":"mantenimiento"}` y `data_json/*` también **403**; el administrador
navega y usa el AJAX sin problema. Activar y desactivar funcionan.

### Duración de sesiones (módulo Conexiones)

- `lib/conexiones_helper.php` (latido y cierre) y `lib/geoip_helper.php`
  actualizado al de BOVS.
- `scripts/migracion_conexiones_duracion.sql` → añade a `conexiones_historial`
  las columnas `session_id_hash`, `ultima_actividad`, `ultima_ruta`, `fecha_fin`,
  `duracion_seg`, `cerrada` y `cerrada_por`, más dos índices. **Ya aplicada en la
  base local `sovca`**; hay que ejecutarla en producción.
- `lib/validar_session.php` y `lib/sanitizar.php` → `bovs_cx_latido()`;
  `views/logout.php` → `bovs_cx_cerrar_sesion()`.

### Listado agrupado de controles sanitarios

- `js/controlsanitario-agrupado.js` y `css/agrupado-lista.css` (copiados de BOVS)
  e integrados en `views/controlsanitario/index.php` (`window.CS_CRUD`,
  `window.CS_PUEDE_EDITAR` y el `<script>` final).

### Tope de crías por parto

- SOVCA admite **5 crías** por parto (partos múltiples de ovejas y cabras):
  `MAX_TERNEROS = 5` en `frm_registrar.php`, `MAX_CRIAS_PARTO = 5` en
  `frm_editar.php`, `MAX_TERNEROS_PARTO = 5` en la app y validación
  `> 5` en `mnacimientos_controller.php` (acciones `add` y `edit`).
