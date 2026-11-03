# Modo offline: inicio, servicios, nacimientos y recuperación de registros

Versión **1.00063** (`sovca-shell-v110`). Este documento resume los fallos
reportados desde el campo y cómo quedaron resueltos.

## 1. Recuperar registros atascados en el equipo

### Qué pasaba

Un registro que el servidor rechazaba se reintentaba cada 2 minutos para
siempre. En el panel de administración se veía el mismo rechazo durante
días (*"Movimiento de rebaño · SOVCA-02-PEND-1247B0"*), y en el teléfono no
había forma de saber qué hacer con él ni de rescatar los datos cargados en
el campo.

### Estrategia de recuperación (en capas)

| Capa | Qué hace |
| ---- | -------- |
| **1. No se genera el problema** | Antes de enviar, la app verifica que los animales referenciados ya existan en el servidor; si no, el registro espera y se envía solo cuando el alta sube. |
| **2. No se insiste para siempre** | Retroceso progresivo (1, 2, 4, 8, 16 y 30 minutos). Tras 6 rechazos el registro queda **bloqueado** y deja de reintentarse solo. |
| **3. Se ve y se entiende** | El ícono de sincronización muestra **"N para revisar"** en rojo. La burbuja lista cada registro con el motivo real que devolvió el servidor. |
| **4. Se puede reintentar** | Botón **Reintentar ahora**: limpia contadores y vuelve a enviar (tras corregir el dato en su pantalla). |
| **5. Se puede rescatar el trabajo** | Botón **Descargar para soporte**: genera `bovs-registros-atascados-f<fundo>-<fecha>.json` con los datos completos de cada registro, el motivo del rechazo, el fundo, el usuario y el dispositivo. Soporte puede revisarlo y volver a cargarlo sin pedirle al productor que lo tipee de nuevo. |
| **6. Nunca se guarda a medias** | El movimiento de rebaño se aplica completo o se rechaza completo, nombrando los animales que impiden guardarlo. |

### Qué hacer en el campo con un registro bloqueado

1. Tocar el estado de sincronización (arriba, junto al usuario).
2. Leer el motivo de cada registro bloqueado.
3. Corregir el dato en su pestaña (por ejemplo, volver a cargar la lista de
   animales para que aparezcan los que ya subieron).
4. **Reintentar ahora**.
5. Si no sube, **Descargar para soporte** y enviar el archivo.
6. Si el registro ya no corresponde, borrarlo/revertirlo desde su tarjeta
   (en movimientos, revertir devuelve los animales a su potrero de origen).

## 2. Servicios: fecha probable de parto automática

**Antes:** al editar un servicio e indicar que la hembra está *gestante*, el
campo "Fecha probable de parto" quedaba vacío y solo se podía completar
eligiendo una fecha en el calendario.

**Ahora:** la app calcula `fecha de servicio + días de gestación del fundo` y
la escribe en el campo, con un texto de ayuda que muestra el cálculo
(*"Calculada con la fecha de servicio (02-10-2026) más 283 días de
gestación"*). El campo sigue siendo editable por si el parto se adelantó o
se atrasó.

- Los días salen de **Parámetros → Períodos**, código `PGB`
  (`mperiodos.dias_gestacion`), primero del fundo y si no hay, del valor
  general de la ganadería. El API ahora envía `periodo_gestacion_dias` en
  `catalogos_controller.php` (acción `listar`).
- Se recalcula al cambiar la fecha de servicio y al marcar el estado
  fisiológico *Gestante*. Al abrir un registro que ya tiene fecha, no se
  pisa el valor guardado.

## 3. Nacimientos

### a) El formulario no se podía recorrer ni cerrar

El overlay del modal centraba el formulario con `flex`. Como el formulario
del parto es alto, se salía por arriba y por abajo: no se veía el título ni
los botones, y había que salir con el botón *Atrás* del teléfono.

Corregido en `css/estilos.css`:
- el overlay ahora hace scroll (`overflow-y: auto`, `align-items: flex-start`);
- la cabecera (título + **Cancelar**) queda **pegada arriba** (`position: sticky`);
- las acciones del formulario (Cancelar / Guardar) quedan pegadas abajo;
- al abrir, el modal siempre arranca desde arriba.

### b) Solo hembras con servicio gestante de 7 a 12 meses

La búsqueda **por servicio previo** ofrece únicamente hembras cuyo servicio
cumple la **misma regla que valida el servidor**
(`validar_servicio_apto_para_nacimiento`):

| Condición | Motivo que muestra la app si no se cumple |
| --------- | ----------------------------------------- |
| `status = 1` | El servicio está anulado. |
| `estatus_ciclo = 1` y `estado_servicio <> 2` | El ciclo ya está cerrado. |
| `medofisiologicos_id = 1` | En ese servicio la hembra no quedó gestante. |
| `parto = 0` | Ya tiene un parto registrado. |
| gestación **entre 7 y 12 meses** | "La gestación es de N mes(es) y debe estar entre 7 y 12." |
| madre activa y hembra | La madre no figura activa / no es hembra. |

Antes la app solo ordenaba por la ventana de meses y dejaba elegir una
hembra con **0 meses**; el nacimiento subía y el servidor lo rechazaba
siempre (*"El servicio debe tener entre siete y doce meses de gestación"*),
generando otro registro atascado. Ahora no se puede ni elegir.

La búsqueda **por madre** sigue disponible tal cual: es la vía para
registrar el parto cuando la hembra llegó preñada de otro fundo o el servicio
no se cargó en el equipo (se pide el tipo de servicio).

### c) Fecha de secado automática

Si en "Entrar en lactancia" se elige **Sí**, la app calcula
`fecha de parto + período del fundo` y la escribe en el campo:

- propósito **2** (entrar en lactancia) → período **PPL** (producción de leche);
- propósito **1** (registros viejos de "Lactancia") → período **PBL** (lactancia/destete).

Es el mismo cálculo que hace el servidor con `bovs_calcular_secado_parto()`,
así que el valor que ve el productor es el que va a quedar guardado. El
campo sigue editable por si hace falta corregirlo, y al abrir un registro
que ya tiene fecha no se pisa.

Los días llegan en el catálogo: `catalogos_controller.php` ahora envía
`periodo_gestacion_dias` (PGB), `periodo_lactancia_dias` (PBL) y
`periodo_produccion_dias` (PPL).

## 4. Botones que parecían etiquetas

`.boton-secundario` era un rectángulo del color del fondo, sin borde ni
relieve. Se rediseñó: fondo blanco, borde verde, sombra y un `:active`
claro. Además, las acciones de agregar llevan la clase `.boton-agregar`
(borde verde + signo **+** en un círculo):

- **Control Sanitario → Agregar producto** (individual y por lote)
- **Nacimientos → Agregar ternero/a**
- y todos los botones secundarios del aplicativo (Cancelar, instalar,
  respaldo, etc.), por el cambio global del estilo.

## 5. Panel de Inicio

La app abre ahora en una pestaña **Inicio** (primera de la tira):

- **Pendientes y avisos**, calculado en el propio equipo (sin internet):
  - registros rechazados que necesitan revisión;
  - registros esperando subir;
  - **palpaciones próximas o vencidas** (15 días de anticipación);
  - **partos probables próximos o vencidos** (30 días de anticipación).
  Cada aviso trae un botón **Ir** que lleva al módulo correspondiente.
- **Módulos**: botones grandes de acceso directo a Animales, Nacimientos,
  Servicios, Control Sanitario, Producción de Leche, Pesos, Movimiento,
  Desincorporaciones y Reportes, con un contador de registros pendientes
  por módulo.

Los íconos de la grilla **no** son los de la tira de pestañas: esos tienen
proporciones distintas (40×32, 48×32) y al llevarlos a la grilla salían
 aplastados y no se entendía el módulo. Se definió un juego propio en
`ICONOS_INICIO` (todos 24×24, mismo grosor de trazo):

| Módulo | Ícono |
| ------ | ----- |
| Animales | cabeza de vacuno con orejas y hocico |
| Nacimientos | madre con la cría al lado |
| Servicios | corazón con latido |
| Control Sanitario | frasco de medicamento con cruz |
| Producción de Leche | tarro de leche |
| Pesos | balanza de platillos |
| Movimiento | dos flechas opuestas |
| Desincorporaciones | ficha con una X |
| Reportes | barras |

Sobre las notificaciones: la app **no usa notificaciones del sistema**
(no depende de permisos ni del servidor). Lo que se agregó es este tablero
local, que funciona sin conexión y se recalcula al abrir la pestaña, al
guardar un registro y al terminar una sincronización.

## 6. Verificación realizada

- `node --check` de `app_offline/js/app.js`, `sync.js`, `config.js`,
  `service-worker.js`; `php -l` de `api/catalogos_controller.php`.
- Pruebas del motor de sincronización:
  `node app_offline/test/verificar_espera_y_reintentos.js` → 20/20.
- Pruebas del servidor:
  `php api/test/validar_alta_offline_flujo.php` → 9/9;
  `php api/test/validar_movimiento_atomico.php` → 6/6.
- Catálogo real (fundo 2): `periodo_palpacion_dias = 80`,
  `periodo_gestacion_dias = 283`.
- En navegador (Chrome headless, 390×640):
  - la tira de pestañas arranca en **Inicio** y el panel muestra 9 accesos;
  - el overlay del nacimiento hace scroll y la cabecera queda en
    `top: 0` al desplazarse 500 px;
  - `Agregar producto` se renderiza como botón (borde 2 px + badge `+`);
  - fecha probable de parto calculada: 02-10-2026 + 283 días → 12-07-2027;
  - estado *Vacía* no calcula la fecha y esconde la ayuda.
  - servicio de 0 meses → *"La gestación es de 0 mes(es) y para registrar
    el parto debe estar entre 7 y 12 meses"*; 9 meses gestante → elegible;
    13 meses → rechazado; *Sin palpar* → rechazado;
  - fecha de secado con propósito 2 y PPL de 60 días: 01-11-2026 →
    31-12-2026;
  - los 9 íconos del inicio son 24×24 y miden 26×26 px.

## 7. Archivos tocados

| Archivo | Cambio |
| ------- | ------ |
| `app_offline/index.html` | pestaña y panel de Inicio; clases de botones; ayuda de fecha probable de parto |
| `app_offline/js/app.js` | panel de inicio y tareas; fecha probable de parto; elegibilidad de servicios en nacimientos; reintento y descarga de atascados |
| `app_offline/js/sync.js` | guarda `periodo_gestacion_dias` del catálogo |
| `app_offline/css/estilos.css` | modal con scroll y cabecera fija, botones visibles, estilos del inicio |
| `api/catalogos_controller.php` | envía `periodo_gestacion_dias` (PGB), `periodo_lactancia_dias` (PBL) y `periodo_produccion_dias` (PPL) |
| `app_offline/versiones.json` | 1.00063 |
| `app_offline/service-worker.js` | `sovca-shell-v110` |

No requiere migración de base de datos.
