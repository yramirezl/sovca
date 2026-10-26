# MODO SOPORTE — acceso temporal de un administrador a un fundo

Documento técnico del **modo soporte** de SOVCA: permite que un administrador del
sistema (rol **1** o **4**) entre temporalmente a un fundo para revisar y
corregir datos, **sin cambiar de cuenta ni de rol**.

- Implementado: 2026-10-18
- Alcance: **solo versión web** (la PWA offline no lo usa)
- Duración máxima: **2 horas** por acceso

---

## 1. Qué resuelve

Antes, para revisar los datos de un fundo, el administrador tenía que:

1. pedir la clave del productor, o
2. crear un usuario ficticio, o
3. cambiar el propietario del fundo.

Las tres opciones son inseguras (suplantan identidad y no dejan rastro). El modo
soporte conserva la **identidad real del administrador**, deja **bitácora** de
cada acceso y **se cierra solo**.

---

## 2. Comportamiento para el usuario

1. El administrador entra a **Fundos** (`views/fundos/`).
2. Elige el modo **Modo soporte**: el listado muestra **solo los fundos con
   propietario activo** (sin licencia vigente un fundo casi nunca necesita
   asistencia), ordenados por propietario y después por nombre del fundo. La
   casilla **Ver también los inactivos** del aviso superior desactiva el filtro;
   en ese caso los activos siguen apareciendo primero.
3. Pulsa **Entrar** en la fila del fundo (o haz clic sobre la fila).
4. Se abre un diálogo que explica el modo y pide un **motivo (opcional)**.
5. Al confirmar:
   - La sesión toma el contexto del fundo elegido (nombre, tipo de ganadería,
     plan, límites de registro).
   - El **menú lateral** muestra un bloque naranja **MODO SOPORTE** con el
     nombre del fundo, la hora de inicio, la hora de cierre automático y el
     botón **Salir del modo soporte**.
   - Debajo aparecen los menús de gestión del fundo (MAESTROS, PARAMETROS,
     GESTION, REPORTES) **y** el bloque **ADMINISTRACIÓN**, para no perder el
     acceso a las pantallas de sistema.
   - La **barra superior** muestra el chip **Modo soporte** con el botón
     **Salir**.
6. Al pulsar **Salir** (menú o barra superior) se cierra el modo, se restaura el
   contexto anterior y se vuelve al inicio.
7. A las **2 horas** el acceso se cierra automáticamente (ver §5).

---

## 3. Piezas del código

| Archivo | Responsabilidad |
|---|---|
| `scripts/migracion_soporte_fundos.sql` | Crea la tabla `soporte_fundos` (bitácora) + índices. Idempotente. |
| `lib/multifundo_helper.php` | `mf_es_admin()`, `mf_modo_supervisor_activo()`, `mf_soporte_estado()`, `mf_soporte_expirado()`, `mf_activar_supervisor()`, `mf_desactivar_supervisor()`. Constante `MF_SOPORTE_DURACION_SEG = 7200`. |
| `controllers/mfundos_controller.php` | Acciones `activar_supervisor` y `salir_supervisor` (JSON). |
| `views/fundos/index.php` | Modo **Modo soporte** del listado: botón **Entrar** por fila (y clic en la fila) + diálogo con motivo. |
| `views/layouts/menu_lateral_izquierdo.php` | Bloque **MODO SOPORTE**, menús de gestión (`render_menu_gestion_fundo()`) y bloque ADMINISTRACIÓN. |
| `views/layouts/barra_navegacion_superior.php` | Chip **Modo soporte** + botón **Salir** + temporizador de cierre. |
| `lib/validar_session.php` | Cierre automático del soporte vencido **en el servidor** (red de seguridad). |

### Variables de sesión

| Variable | Contenido |
|---|---|
| `supervisor_mode` | `true` mientras el modo está activo |
| `supervisor_fundo` | id del fundo en soporte |
| `supervisor_inicio` / `supervisor_expira` | marcas de tiempo (unix) |
| `supervisor_motivo` | motivo declarado (máx. 200) |
| `supervisor_registro` | id de la fila en `soporte_fundos` |
| `supervisor_fundo_original` | fundo que tenía el administrador antes de entrar (0 si no tenía) |

Al entrar se reutilizan las mismas claves de contexto que usa el resto del
sistema (`mfundos_id`, `fundo_nombre`, `tipo_ganaderia`, `mplanes_id`,
`maximo_registros`, `acceso_reg_animales`), por lo que **todas las pantallas
funcionan sin cambios**.

> ⚠️ No se usa `mf_cargar_fundo_en_sesion()` para entrar: esa función exige un
> vínculo en `mfundosxusuarios`, que el administrador no tiene. Tampoco se
> guarda el fundo visitado como "último fundo": el administrador no debe quedar
> "pegado" al fundo revisado.

---

## 4. Bitácora (`soporte_fundos`)

| Columna | Tipo | Nota |
|---|---|---|
| `id` | serial | PK |
| `musuarios_id` | int | administrador que entró |
| `mfundos_id` | int | fundo revisado |
| `fecha_inicio` | timestamp | por defecto `now()` |
| `fecha_fin` | timestamp | se llena al salir |
| `duracion_seg` | int | segundos dentro del fundo |
| `motivo` | varchar(200) | texto libre declarado por el administrador |
| `ip` | varchar(45) | `REMOTE_ADDR` |
| `user_agent` | varchar(255) | navegador |
| `status` | smallint | `1` = abierto, `0` = cerrado |

Índices: `soporte_fundos_usuario_idx (musuarios_id, fecha_inicio DESC)` y
`soporte_fundos_fundo_idx (mfundos_id, fecha_inicio DESC)`.

Si la tabla no existe, el helper falla **en silencio** y el modo soporte sigue
funcionando (solo se pierde la bitácora).

Consulta rápida:

```sql
SELECT s.id, u.username, f.nombre AS fundo, s.motivo,
       s.fecha_inicio, s.fecha_fin, s.duracion_seg, s.ip
  FROM soporte_fundos s
  LEFT JOIN musuarios u ON u.id = s.musuarios_id
  LEFT JOIN mfundos  f ON f.id = s.mfundos_id
 ORDER BY s.fecha_inicio DESC
 LIMIT 50;
```

---

## 5. Cierre automático (2 horas)

Hay **tres** mecanismos, en este orden:

1. **Temporizador del navegador** (`barra_navegacion_superior.php`): al cumplirse
   `supervisor_expira` llama a `salir_supervisor` y vuelve al inicio.
2. **Servidor** (`lib/validar_session.php`): en cada carga de página, si
   `time() >= supervisor_expira` cierra el modo, restaura la sesión, deja el
   aviso `soporte_aviso = 'expirado'` y redirige a `inicio/?stat=soporte_finalizado`.
   El menú lateral muestra la alerta "El modo soporte finalizó…".
   Esto cubre el caso de una pestaña olvidada o cerrada sin pulsar **Salir**.
3. **Salida manual** con el botón **Salir** (menú o barra superior).

> Nota técnica: `validar_session.php` no tiene ActiveRecord cargado; antes de
> llamar a `mf_desactivar_supervisor()` lo incluye **cambiando el CWD a
> `config/`**, porque `config/conexion.php` usa rutas relativas
> (`require_once '../php-activerecord/ActiveRecord.php'`). Sin ese cambio la
> página devolvía **HTTP 500**.

---

## 6. Reglas y restricciones

- Solo **rol 1 (ADMINISTRADOR)** y **rol 4 (SUPER USUARIO)** pueden activar el
  modo. La acción responde **403** a cualquier otro rol; el botón y el menú no
  se pintan para ellos.
- No hay cambio de usuario ni de rol: `idusuariox`, `rolx` y los permisos se
  mantienen.
- El acceso es **solo web**: el API offline exige productor con `acceso_offline`,
  así que un administrador no puede sincronizar datos del fundo desde la PWA.
- El modo soporte **no** otorga nada nuevo: el administrador ya puede gestionar
  cualquier fundo; lo que aporta es contexto, claridad y trazabilidad.
- Al salir, si el administrador no tenía fundo propio, la sesión vuelve a
  `mfundos_id = 0` (administrador sin fundo), nunca al fundo visitado.

---

## 7. Pruebas realizadas (QAS, 2026-10-18)

Automatizadas (script temporal, 29 comprobaciones, todas OK):

- rol 2 no puede activar; no deja la sesión en modo soporte;
- rol 4 activa: sesión con fundo, `maximo_registros > 0`, expiración futura,
  registro de bitácora creado con usuario/fundo/motivo correctos;
- `mf_soporte_expirado()` falso antes del límite y verdadero al vencer;
- salida: `supervisor_*` limpio, `mfundos_id = 0`, bitácora cerrada
  (`status = 0`, `fecha_fin` y `duracion_seg`);
- reingreso a otro fundo cierra el registro anterior y abre uno nuevo.

En navegador (sesión sembrada temporalmente, ya eliminada):

- Fundos → modo **Modo soporte** → **Entrar** en la fila → diálogo con motivo → menú con **MODO SOPORTE**
  (nombre, "Desde…", "Cierra solo…"), menús de gestión y ADMINISTRACIÓN;
- barra superior con chip **Modo soporte** + **Salir**;
- **Salir** → vuelve al inicio limpio (menú de administrador, sin chip ni bloque);
- bitácora: `usuario 1 / fundo 16 / motivo / status 0 / duración 202 s / 127.0.0.1`;
- expiración forzada → `302` a `inicio/?stat=soporte_finalizado`, aviso visible
  y registro cerrado;
- rol 2: `403` al intentar la acción, sin botón ni bloque en las pantallas;
- a 400 px de ancho el card del fundo se oculta (clase
  `navbar-active-fundo--soporte`) y el botón **Salir** queda clicable.

---

## 8. Instalación en otro entorno (PRD)

```bash
psql -h 127.0.0.1 -U postgres -d sovca_prd -f scripts/migracion_soporte_fundos.sql
```

El script es idempotente (`CREATE TABLE IF NOT EXISTS`, `CREATE INDEX IF NOT
EXISTS`). No requiere cambios en `versiones.json` ni en el service worker: el
modo soporte es exclusivamente web.
