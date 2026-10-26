# Alerta por inactividad de fundos (aviso al productor)

Notificación enviada al **productor dueño del fundo** cuando **ningún usuario del fundo**
(productor y ayudantes) registra conexiones durante **7 días** o más.

---

## 1. Regla de negocio

Si NINGÚN usuario activo del fundo (productor y ayudantes) registra conexión en los últimos
**N días** (por defecto **7**), se envía **un correo al productor** expresando preocupación por
la inactividad del fundo y ofreciéndole ayuda (capacitación, migración de datos, revisión de
reportes, soporte técnico).

### Condiciones que debe cumplir el fundo

| Condición | Verificación |
|---|---|
| Fundo activo | `mfundos.status = 1` |
| Productor dueño **activo** | `mfundos.user_propietario` → usuario con `mroles_id = 2` y `status = 1` |
| Correo del productor válido | campo `email` no vacío |
| Fundo con al menos un usuario activo vinculado | `mfundosxusuarios.status = 1` y `musuarios.status = 1` |
| Sin conexiones recientes | la conexión más reciente de cualquiera de esos usuarios es anterior a `CURRENT_DATE - N días` (o no existen registros) |

### Destinatario

El productor dueño del fundo (`mfundos.user_propietario`, el mismo criterio que la vista
`v_email_alertas`). Los ayudantes **no** reciben el aviso.

---

## 2. Fuente de la actividad

Tabla `mrastreosessiones` (la misma información que muestra el listado
`views/conexiones/index.php`, a través de la vista `Vmdataconexiones`): una fila por usuario que
se actualiza en cada inicio de sesión.

La actividad del fundo es la **conexión más reciente de cualquiera de sus usuarios activos**
(la del productor o la de cualquiera de sus ayudantes). Si alguien del fundo ingresa, el fundo
deja de considerarse inactivo.

---

## 3. Archivos

| Archivo | Función |
|---|---|
| `scripts/migracion_alerta_inactividad.sql` | Crea la tabla de seguimiento `malertas_inactividad_estado` |
| `alertas/enviar_inactividad.php` | Clase `Enviar_Correo`: `construir_cuerpo()`, `enviar_inactividad()`, `enviar_resumen()` |
| `alertas/controlador_email_inactividad.php` | Proceso (cron): detecta, **agrupa por productor**, envía y registra |
| `alertas/enviar.php` | Se le agregaron `construir_cuerpo_agrupado()` y `enviar_agrupado()` para las alertas de eventos |
| `alertas/controlador_email.php` | Alertas de eventos: ahora **agrupa el envío por productor** + modo `--dry-run` |

Sigue el mismo patrón que el resto de las notificaciones de `alertas/`
(`controlador_email.php` + `enviar.php`): proceso CLI ejecutado por cron, clase `Enviar_Correo`
con su propio bloque SMTP y registro del envío para no repetirlo.

---

## 4. Instalación

```bash
# 1) Crear la tabla de seguimiento (idempotente)
psql -h 127.0.0.1 -U postgres -d <base_de_datos> -f scripts/migracion_alerta_inactividad.sql
```

```cron
# 2) Programar la ejecución (todos los días a las 6:00 a.m.)
0 6 * * * cd /var/www/html/sovca/alertas && php controlador_email_inactividad.php >> /var/log/sovca_inactividad.log 2>&1
```

> **Nota (credenciales SMTP):** igual que el resto de los enviadores de `alertas/`, la
> contraseña del correo se coloca en el despliegue (`$mail->Password`), no se versiona.

---

## 5. Uso del proceso

```bash
# Revisión SIN enviar correos (informe de fundos inactivos)
php controlador_email_inactividad.php --dry-run

# Revisión + ver el correo que se enviaría al primer candidato
php controlador_email_inactividad.php --dry-run --html

# Envío real
php controlador_email_inactividad.php

# Cambiar el umbral de días (por ejemplo 10)
php controlador_email_inactividad.php --dias=10

# Primera puesta en marcha por lotes (máximo 20 avisos por corrida)
php controlador_email_inactividad.php --limite=20

# Volver a avisar aunque ya se haya avisado dentro del período
php controlador_email_inactividad.php --forzar

# Enviar el resumen interno de la corrida a un correo del equipo
php controlador_email_inactividad.php --resumen=soporte@ruted.net
```

| Parámetro | Descripción |
|---|---|
| `--dias=N` | Días sin conexión para considerar inactivo el fundo (por defecto `7`) |
| `--limite=N` | Máximo de **correos** por corrida (`0` = sin límite), ya agrupados por productor |
| `--dry-run` | Solo informa los candidatos; **no envía** ni registra nada |
| `--html` | Junto a `--dry-run`, imprime el HTML del correo del primer candidato |
| `--forzar` | Ignora el control de avisos repetidos |
| `--resumen=correo` | Correo interno que recibe el resumen de la corrida |

El proceso solo se ejecuta por **línea de comandos**: si se abre por URL responde
`403 Forbidden`.

---

## 6. Control de avisos repetidos

Tabla `malertas_inactividad_estado` (una fila por fundo):

| Columna | Descripción |
|---|---|
| `mfundos_id` | Fundo avisado (PK) |
| `ultimo_envio` | Fecha/hora del último aviso enviado |
| `ultima_conexion` | Última conexión que tenía el fundo al momento del aviso |
| `dias_inactividad` | Días sin conexión detectados |
| `correo_enviado` | Correo al que se envió (auditoría) |
| `updated` | Última actualización de la fila |

**Política:** un fundo recibe como máximo **un aviso cada N días** (7 por defecto). Si sigue
inactivo, vuelve a recibir el correo la semana siguiente. El registro solo se escribe cuando el
envío fue **exitoso**, de modo que un fallo de SMTP se reintenta en la corrida siguiente.

El control es **por fundo** aunque el correo se envíe agrupado: si un productor tiene 3 fundos
inactivos y solo 2 cumplieron los 7 días, el correo se envía solo con esos 2 y el tercero entrará
en un correo posterior cuando cumpla su período.

Si la tabla no existe, el proceso se detiene con un mensaje explícito (evita repetir avisos a
diario por falta de control).

---

## 7. Contenido del correo

- Saludo dirigido al productor por su nombre.
- Mención del fundo (y RIF/cédula si está registrado).
- Días sin actividad y fecha de la última conexión registrada ("Sin registros de conexión" si
  el fundo nunca registró actividad).
- Mensaje de empatía y ofrecimiento de ayuda: capacitación y acompañamiento, carga/migración de
  datos, revisión de reportes y configuración, soporte técnico (incluidos los equipos que
  trabajan sin señal).
- Tabla con los usuarios activos del fundo, su rol y su última conexión.
- Botón `Ingrese Aquí` hacia `https://global.ruted.net/sovca/`.
- Firma "Ruted, Tecnología y Desarrollo Rural."

---

## 8. Comportamientos a tener presentes

- **Productores multifundo:** el registro de conexiones es **por usuario**, no por fundo. Si un
  productor con varios fundos ingresa al sistema, cuenta como actividad en **todos** sus fundos
  (sus ayudantes, en cambio, solo dan actividad al fundo al que pertenecen).
- **Fundos sin usuarios activos** no generan aviso (no hay a quién considerar inactivo).
- **Fundos sin conexiones registradas nunca** sí generan aviso: se informa el umbral configurado
  y "Sin registros de conexión" como última conexión.
- El aviso es informativo y **no modifica** fundos, usuarios ni vínculos.
- La primera corrida real puede detectar muchos fundos históricamente inactivos: se recomienda
  arrancar con `--limite` (por ejemplo `--limite=20`) y luego dejar la corrida diaria sin límite.

---

## 9. Verificación realizada (QA)

- `--dry-run`: 21 fundos activos con productor activo y 7 días sin conexiones detectados.
- **Agrupación multifundo:** esos 21 fundos se consolidan en **19 correos** (dos productores con
  2 fundos cada uno: 65+61 y 71+70).
- Control de repetidos: con un aviso previo simulado, el fundo se reporta como `OMITIDO` y el
  total baja de 21 a 20; con `--forzar` vuelve a aparecer.
- `--limite=N`: exactamente N correos por corrida (probado con `--limite=1` y `--limite=3`).
- Camino de error (SMTP inexistente): el/los fundo(s) se reportan con `ERROR` y **no** se escribe
  en el registro de seguimiento (se reintenta en la próxima corrida). Mismo comportamiento
  verificado para las alertas de eventos (`malertas_email_estado` quedó en 0 filas).
- Render del correo agrupado: 2 fundos en un solo mensaje (inactividad) y 3 fundos en un solo
  mensaje (alertas de eventos), con acentos y codificación UTF-8 correctos.
- `controlador_email.php --dry-run`: 4 productores / 18 alertas detectadas sin enviar ni
  registrar nada.

---

## 10. Corrección previa necesaria (PHP 8)

La clase `alertas/conexion` usaba el **constructor al estilo PHP 4** (método con el mismo nombre
de la clase), que en PHP 8 ya no se ejecuta como constructor: `host`, `login`, `password` y
`port` quedaban sin inicializar y la conexión fallaba, afectando a **todos** los procesos de
`alertas/`. Se agregó `__construct()` que invoca al método original (compatible con PHP 5/6/7 y
PHP 8).

---

## 11. Agrupación por productor (usuarios MULTIFUNDO)

### El problema

El registro de conexiones y de alertas es **por fundo**, y los procesos originales recorrían los
fundos enviando **un correo por fundo**. Un productor con 5 fundos inactivos recibía 5 correos
(el mismo día, con el mismo mensaje) y, si además tenía alertas de eventos, otros 5 más.

### La solución aplicada

Ambos procesos **agrupan por destinatario** (el correo del productor) y envían **un solo
mensaje** con una sección numerada por fundo:

| Proceso | Antes | Ahora |
|---|---|---|
| `controlador_email_inactividad.php` | 1 correo por fundo inactivo | 1 correo por productor con todos sus fundos inactivos |
| `controlador_email.php` (alertas de eventos) | 1 correo por fundo con alertas | 1 correo por productor con todos sus fundos con alertas |

Ejemplo real (QA, 21 fundos inactivos): **19 productores → 19 correos** en lugar de 21, porque
dos productores eran multifundo (`enelay29@gmail.com` con los fundos 65 y 61;
`alfonsomogollon@gmail.com` con los fundos 71 y 70).

Con 5 fundos y ambos tipos de aviso, el productor recibe como máximo **2 correos** (uno de
inactividad semanal y uno de alertas de eventos cuando existan), en lugar de hasta 10.

### Detalles de implementación

- En el asunto y en la introducción se refleja si el aviso cubre uno o varios fundos
  (`¿Cómo podemos ayudarle con el fundo X?` vs. `Detectamos N fundos sin actividad en su cuenta SOVCA`).
- Cada fundo se rotula con su nombre y RIF, y muestra sus propios días de inactividad y su tabla
  de usuarios (el aviso de inactividad) o su lista de animales/actividades (alertas de eventos).
- El **seguimiento anti-duplicado sigue siendo por fundo** en ambos casos: se registra cada
  alerta/fundo incluido en el correo y **solo si el envío fue exitoso**.
- Los envíos individuales anteriores (`Enviar_Correo::enviar()`) se conservan por compatibilidad,
  pero el proceso ya no los usa (usa `enviar_agrupado()`).

### ¿Por qué no un único correo con TODO (inactividad + eventos)?

Serían los dos procesos (dos cron, dos reglas y dos tablas de control) fusionados en uno solo.
Ventaja: un solo mensaje por productor. Costos: duplicar la lógica de detección de alertas de
eventos (que hoy vive en `controlador_email.php`) y cambiar el despliegue (desactivar el cron
antiguo). Queda como fase 2 si se decide unificar; mientras tanto, el máximo son 2 correos por
productor y **ninguno se repite por fundo**.

---

## 12. Alertas de eventos agrupadas (`controlador_email.php`)

Se mantiene el mismo cron y la misma lógica de detección y de seguimiento (tabla
`malertas_email_estado`), con dos cambios:

1. **Agrupación:** las alertas se acumulan por correo de destino y se envía un solo mensaje por
   productor con una sección por fundo (`Enviar_Correo::enviar_agrupado()` y
   `construir_cuerpo_agrupado()` en `alertas/enviar.php`, que además fija `CharSet = 'UTF-8'`).
2. **Modo revisión:** si se ejecuta por línea de comandos con `--dry-run` informa los correos que
   se enviarían sin enviar ni registrar nada; con `--dry-run --html` muestra el HTML del primer
   correo. Por URL se comporta exactamente como antes (no se le agregó ninguna restricción para
   no romper un cron basado en URL).

```bash
php controlador_email.php --dry-run
php controlador_email.php --dry-run --html
```

---

## 13. Compatibilidad PHP 7.4 y 8.3

Todo el código de esta funcionalidad (y de los archivos que se modificaron alrededor) usa
construcciones válidas en **PHP 7.4 y 8.3**:

- Arreglos cortos `[]` y `array()`, `foreach`, `isset()`/ternarios (sin `??`, sin `?->`).
- Sin `match`, sin `str_contains()`/`str_starts_with()`, sin atributos `#[...]`, sin `readonly`,
  sin tipos de unión, sin argumentos con nombre.
- Compatibilidad del constructor: `alertas/conexion.php` define `__construct()` (tiene prioridad
  tanto en PHP 7.4 como en 8.3) y conserva el método original.
- `getopt()` con valores opcionales: se valida que el valor no venga vacío para no interpretarlo
  como `0` (comportamiento distinto entre versiones).
- La codificación del correo se fija explícitamente (`CharSet = 'UTF-8'`) porque el PHPMailer
  incluido usa `iso-8859-1` por defecto y los nombres de fundos pueden tener acentos.

Comprobación realizada: `php -l` de todos los archivos modificados (PHP 8.3) y revisión estática
para descartar sintaxis exclusiva de PHP 8. El servidor local solo tiene PHP 8.3 instalado, por
lo que la validación con 7.4 es por revisión de sintaxis (no hay binario 7.4 disponible).
