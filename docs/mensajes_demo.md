# Mensajes por correo a usuarios demo

Módulo del administrador para **recuperar visitantes que probaron la
demostración y no compraron**: muestra los correos de quienes crearon una
cuenta demo (vigentes y caducadas) y les envía un mensaje con la identidad de
SOVCA que los invita a pasar a la cuenta oficial.

Se llega desde el menú **Mensajería → Mensajes Demo**
(`views/mensajesdemo/`).

---

## 1. Piezas

| Pieza | Archivo | Para qué |
|---|---|---|
| Pantalla | `views/mensajesdemo/index.php` | Listado con estado, selección, redacción, vista previa y historial. |
| Endpoint | `controllers/mensajes_demo_controller.php` | `listar`, `vista_previa`, `enviar`, `historial`. Solo roles 1 y 4. |
| Lógica | `lib/mensajes_demo_helper.php` | Consulta de correos, plantilla del correo, envío y bitácora. |
| Secuencia | `lib/demo_correos_auto_helper.php` | Etapas, textos adaptados, control de envíos y detección de datos. |
| Cron | `scripts/demo_correos_auto.php` + `scripts/cron/demo_correos_auto.sh` | Envío diario de la secuencia (ver §5). |
| Menú | `views/layouts/menu_lateral_izquierdo.php` | Entrada **Mensajes Demo** en las dos variantes de administrador. |

La información de los usuarios demo vive en la base `sovca_demo`; la base
oficial no se toca. La bitácora de envíos se guarda en `demo_mensajes` dentro
de la propia base demo (se crea sola la primera vez).

---

## 2. Qué correos aparecen

Se parte de **`demo_registros`** (la bitácora que se escribe al crear la
cuenta demo) y no de `musuarios`: así el correo se conserva aunque la cuenta se
haya **desactivado o purgado** al vencer. Después:

| Caso | ¿Aparece? | Por qué |
|---|---|---|
| Cuenta demo **vigente** | Sí, marcada **VIGENTE** | Todavía puede comprar y conservar lo cargado. |
| Cuenta demo **caducada** | Sí, marcada **CADUCADA** | Es el caso que el módulo busca recuperar. |
| Cuenta **migrada** a la oficial (`migrado_a_usuario > 0`) | No | Ya es cliente. |
| Cuenta **cerrada** por el administrador sin comprar | No | El administrador decidió no continuar y su correo queda bloqueado para nuevas demos. |
| Correo inválido o vacío | No | No se puede escribir. |
| Varias filas con el mismo correo | Una sola | Un correo = una demostración; si alguna sigue vigente, se muestra vigente. |

El estado se calcula con la fecha de vencimiento del plan
(`mplanesxfundo.fecha_caduca_suscripcion`, con respaldo en
`demo_registros.fecha_caduca`) y con el `status` del usuario demo.

---

## 3. Cómo se envía

1. Se marcan correos (o se filtra por **Vigentes** / **Caducadas** y se marca
   todo con la casilla del encabezado).
2. **Redactar mensaje**: se ajustan asunto y texto. El asunto y el cuerpo
   admiten variables:

   | Variable | Se reemplaza por |
   |---|---|
   | `{nombre}` | Nombre del usuario demo |
   | `{usuario}` | Usuario demo |
   | `{fundo}` | Nombre del fundo de prueba |
   | `{vence}` | Fecha de vencimiento (dd/mm/aaaa) |
   | `{dias}` | Días que faltan o que pasaron, sin signo |
   | `{correo}` | Correo del destinatario |
   | `{estado}` | `vigente` o `caducada` |

3. **Vista previa** arma el correo real con el primer destinatario
   seleccionado (o con un productor de ejemplo si aún no hay selección).
4. **Enviar mensaje** pide confirmación y envía **en lotes de 10** con barra de
   progreso, para que una lista grande no caiga por tiempo de espera.

Servidor: los envíos usan `bovs_mail_enviar()` de `lib/mailer_helper.php`
(mismo SMTP de `config/mail.php`, con cabeceras de entregabilidad y
`List-Unsubscribe`). Con `SOVCA_MAIL_ARCHIVO=1` los correos se guardan en disco
en lugar de enviarse, útil para pruebas.

### Seguridad del envío

- Solo roles **1 (ADMINISTRADOR)** y **4 (SUPER USUARIO)**.
- El servidor **no confía en la lista del navegador**: recalcula los correos
  permitidos con `bovs_msj_demo_listar()` y descarta cualquier otro destino.
- Máximo **25 correos por petición** (lotes de 10 desde la pantalla).
- Asunto ≤ 180 caracteres, mensaje ≤ 6000 caracteres.

---

## 4. Plantilla del correo

`bovs_msj_demo_html()` arma un correo HTML con:

- cabecera verde con logo y “SOVCA · Gestión Ovina y Caprina · Ruted”,
- saludo personal (**Hola, {nombre}**),
- recuadro con el **estado de la demo**: vigente (“sigue activa hasta el …,
  quedan N días”) o caducada (“ya venció el …; los datos siguen guardados y los
  pasamos a tu cuenta oficial”),
- el texto que escribió el administrador,
- botón **Quiero mi cuenta oficial** → WhatsApp con el usuario, el fundo y el
  correo precargados (`bovs_demo_whatsapp_url()`),
- enlace secundario a la página de planes de Ruted,
- pie con el motivo del mensaje.

La versión en texto plano (`bovs_msj_demo_texto()`) va como `AltBody`.

El texto sugerido (`bovs_msj_demo_cuerpo_sugerido()`) menciona los beneficios
concretos de la cuenta oficial (producción por animal, reportes de leche,
rendimiento, modo offline, migración de los datos ya cargados). Se puede
editar por completo antes de enviar.

---

## 5. Secuencia automática de correos

Además del envío manual, el sistema acompaña solo a cada usuario demo. Toda
la lógica vive en `lib/demo_correos_auto_helper.php` y la ejecuta
`scripts/demo_correos_auto.php` desde el cron (wrapper:
`scripts/cron/demo_correos_auto.sh`).

| Etapa | Día | Ventana | Qué dice |
|---|---|---|---|
| `bienvenida` | 0 | 0–1 | Bienvenida con todas las bondades y cómo empezar. Se envía **al verificar el correo** (después de responder la petición) y el cron la reintenta si falló. |
| `dia7` | 7 | 7–10 | **Con datos:** pregunta cómo va la experiencia y empuja la compra. **Sin datos:** ofrece ayuda y los primeros pasos para cargar registros. |
| `dia14` | 14 | 14–15 | Avisa que queda 1 día de prueba, con el texto adaptado a si cargó datos o no. |
| `dia22` | 22 | 22–25 | Ya caducada: rescate 1. **Con datos:** “tus datos siguen guardados”. **Sin datos:** ofrece una demostración guiada. |
| `dia30` | 30 | 30–33 | Último recordatorio, también adaptado a si cargó datos o no. |

### Reglas

- **Cada etapa se envía una sola vez por cuenta**: la tabla `demo_correos_auto`
  tiene índice único `(musuarios_id, etapa)` y la fila se aparta antes de
  enviar (`ON CONFLICT DO NOTHING`), así el cron y la bienvenida del alta no
  se pisan. Si un envío queda en `error`, la siguiente corrida lo reintenta.
- **La ventana es corta** para que un cron caído un día no desordene la
  secuencia y una cuenta vieja nunca reciba los cinco correos de golpe.
  Pasada la ventana, la etapa se da por perdida.
- **“Cargó datos”** se calcula con las tablas operativas del fundo
  (`manimales`, `mregservicios`, `mregnacimientos`, `mprolecheras`,
  `mconsecutivopesos`, `mcontrolsanitarios`, `mdesincorporacionanimales`,
  `mmovimientos_rebano`). Un lote o una raza del catálogo no cuentan.
- **Quien ya es cliente oficial no recibe nada**: se descartan las cuentas
  migradas (`migrado_a_usuario > 0`) y también las que ya tienen una cuenta en
  la base oficial con el mismo correo o usuario (aunque hayan comprado sin
  pasar por *Importar cuenta demo*).
- Los días se calculan con `current_date - created::date` en la base demo, así
  no dependen de la zona horaria del proceso que consulta.

### Ejecución

```bash
# revisión (no envía ni registra nada)
php scripts/demo_correos_auto.php --dry-run --verbose

# envío real del día
php scripts/demo_correos_auto.php

# pruebas puntuales
php scripts/demo_correos_auto.php --usuario=LASCOLINAS
php scripts/demo_correos_auto.php --etapa=dia7 --limite=5
```

```cron
0 9 * * * /opt/backups/script/sovca/demo_correos_auto.sh >> /opt/logs/demo_correos.log 2>&1
```

`--limite` (40 por defecto) evita ráfagas largas contra el SMTP. En pruebas
locales, `SOVCA_MAIL_ARCHIVO=1` guarda los correos en `file/correo` en lugar de
enviarlos.

La pantalla del módulo muestra la columna **Secuencia automática** con las
etapas ya enviadas de cada correo, para saber en qué punto va cada usuario.

---

## 6. Bitácora

Cada envío se anota en `demo_mensajes` (`email`, `asunto`, `estado`
`ok`/`error`, `error`, `admin_id`, `created`).

- La pantalla muestra **Últimos envíos** y, en el listado, la columna
  **Último envío manual** por correo (los automáticos se ven en la columna
  **Secuencia automática**, que se guarda en `demo_correos_auto`).
- Si el usuario de la base no puede crear la tabla, el módulo **sigue
  enviando**; solo se pierde el historial (queda anotado en el log de PHP).

---

## 7. Notas de mantenimiento

- Si se agrega un estado nuevo a las cuentas demo, revisar
  `bovs_msj_demo_listar()`: hoy solo distingue `vigente` / `caducada`.
- El módulo no envía si `SOVCA_DEMO_ACTIVO` es `false` o si la base demo no
  responde; la pantalla lo informa.
- Para pruebas locales: sembrar filas en `demo_registros` + `musuarios` +
  `mfundos` + `mfundosxusuarios` + `mplanesxfundo` de `sovca_demo` y ejecutar con
  `SOVCA_MAIL_ARCHIVO=1` para no usar el SMTP real.
