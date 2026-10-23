# Recuperación de contraseña ("¿Olvidaste tu contraseña?")

Permite que un usuario que olvidó su clave **reciba un código de 6 dígitos por
correo** y con él cree una nueva, sin intervención del administrador.

- Implementado: 2026-10-18 (adaptado del sistema `sitemlla`, que usa esta misma
  modalidad de código de 6 dígitos)
- Alcance: **solo versión web** (el modo offline usa el login del API, que no cambia)

---

## 1. Flujo completo

```
views/login  →  [¿Olvidaste tu contraseña?]
                        │
                        ▼
        views/credencial/recuperar.php            (paso 1)
        · correo + captcha
        · POST action=solicitar_reset
                        │
        controllers/musuarios_controller.php
        · valida captcha, busca la cuenta activa
        · genera CÓDIGO de 6 dígitos y guarda su HASH sha256
        · envía el correo con el código (15 min de vigencia)
                        │
                        ▼
        views/credencial/reset_password.php       (paso 2)
        · 6 casillas para el código + cuenta atrás + "Reenviar"
        · contraseña nueva + confirmación + requisitos en vivo
        · POST action=aplicar_reset
                        │
        controllers/musuarios_controller.php
        · valida el código (vigencia + máximo de intentos)
        · guarda md5(sal + clave), act_clave = 1
        · borra el código (un solo uso)
                        │
                        ▼
        views/login?stat=reset_ok   ("Contraseña actualizada")
```

No hay enlace con token: **el código se escribe en la pantalla**, así que el
correo no contiene ninguna URL que dé acceso directo a la cuenta.

---

## 2. Piezas del código

| Archivo | Responsabilidad |
|---|---|
| `scripts/migracion_reset_password.sql` | Columnas `password_reset_codigo`, `password_reset_expires`, `password_reset_intentos`, `password_reset_solicitado` (+ COMMENTs). Elimina la antigua `password_reset_token`. Idempotente. |
| `config/mail.php` | SMTP (mismo buzón del módulo de alertas), remitente, `base_url`, `reset_vigencia_min` (15) y `reset_max_intentos` (5), modo de prueba. |
| `lib/mailer_helper.php` | `bovs_mail_enviar()`, `bovs_mail_enviar_codigo()`, plantilla HTML con la identidad BOVS, `bovs_mail_codigo()` (6 dígitos con `random_int`), `bovs_mail_codigo_hash()` (sha256), `bovs_url_base()`. |
| `lib/password_policy.php` | `bovs_password_errores()` y `bovs_password_hash()` — **las mismas reglas de "Cambiar contraseña"**, en un solo lugar. |
| `lib/captcha_helper.php` | Se agregó `bovs_captcha_validar($token, $respuesta)` (un solo uso, 15 min). El captcha es el mismo del login. |
| `controllers/musuarios_controller.php` | Acciones nuevas `solicitar_reset`, `reenviar_codigo` y `aplicar_reset`. |
| `views/credencial/recuperar.php` | Pantalla 1 (correo + captcha). Al enviar, pasa al paso 2. |
| `views/credencial/reset_password.php` | Pantalla 2 (6 casillas del código + temporizador + reenviar + contraseña nueva). |
| `views/credencial/index.php` | Redirige a `recuperar` (evita que se liste el directorio). |
| `views/login.php` | Enlace **¿Olvidaste tu contraseña?** y aviso `stat=reset_ok`. |
| `lib/sanitizar.php`, `config/conexion.php` | Las acciones de recuperación se atienden **aunque la suscripción esté vencida** (si no, el usuario no tendría forma de volver a entrar). |

### Comportamiento de la pantalla del código

- 6 casillas independientes: solo dígitos, avance automático, `Backspace` y
  flechas para moverse, y al pegar un código completo se reparte solo.
- Las casillas son **fluidas** (`flex:1 1 0; max-width:46px`): 46×54 px en
  escritorio, 40×48 px por debajo de 480 px y 44 px de alto bajo 340 px, así que
  las 6 siempre caben sin desbordar la tarjeta en teléfonos angostos.
- Contador con el tiempo restante (`15:00`) que avisa "El código caducó: pide uno
  nuevo" al llegar a cero.
- Botón **Reenviar** que pide otro código sin volver al paso 1 (respeta el mismo
  límite de 3 minutos por cuenta).
- El botón **Guardar** permanece deshabilitado hasta que el código tiene 6 dígitos,
  el correo es válido, la contraseña cumple la política y ambas coinciden.
- Enlace "Usar otro correo / pedir un código nuevo" para volver a `recuperar`.

---

## 3. Seguridad aplicada

| Medida | Detalle |
|---|---|
| **Código no adivinable en la práctica** | 6 dígitos con `random_int` → 1 000 000 de combinaciones, pero **máximo 5 intentos** por código. |
| **Se guarda el HASH** | En `musuarios` va `sha256(código)`. Si alguien lee la tabla, no puede usar el código. |
| **Un solo uso** | Al aplicar el cambio el código se borra (junto con caducidad e intentos). |
| **Caducidad corta** | 15 minutos (`config/mail.php` → `reset_vigencia_min`). Vencido, se limpia y se pide otro. |
| **Límite de intentos** | 5 (`reset_max_intentos`). Al agotarlos el código se anula: hay que pedir uno nuevo. Cada fallo informa cuántos quedan. |
| **No revela cuentas** | La respuesta de la solicitud es idéntica exista o no el correo ("Si el correo está registrado…"). |
| **Anti abuso del correo** | Captcha obligatorio + **1 envío cada 3 minutos** por cuenta (el reenvío inmediato responde igual pero no regenera el código). |
| **Reglas de contraseña** | Las mismas del sistema: 8+ caracteres, mayúscula, minúscula, número y uno de `@ # $ %`; sin espacios ni otros símbolos. |
| **Auditoría** | Se registra `user_modify` y `updated` en la cuenta modificada. |
| **Sin sesión previa necesaria** | El flujo funciona con la sesión cerrada; solo se pide el correo. |

Detalles de validación en el servidor (no se confía en el navegador):

- El código se normaliza a solo dígitos y se exige de **exactamente 6**.
- Una contraseña que no cumple la política **no gasta intentos** (solo los gasta un
  código incorrecto).
- Correspondencia de errores: código incorrecto → cuenta el intento; caducado o
  agotado → anula el código y pide uno nuevo.

---

## 4. Configuración del correo (importante al desplegar)

`config/mail.php` usa el **mismo buzón del módulo de alertas**:

```
host: mail.ruted.net · puerto 465 · SSL · usuario global@ruted.net
```

⚠️ **La contraseña del buzón NO está en el repositorio.** Se define de una de dos formas:

1. Variable de entorno del servidor:
   ```bash
   BOVS_SMTP_PASS="la-clave-del-buzon"
   ```
2. Escribiéndola en `'password' => '…'` dentro de `config/mail.php`.

Sin contraseña el envío falla con un mensaje claro en el log de PHP
(`[BOVS mail] SMTP sin contraseña configurada`) y el usuario ve
"No se pudo enviar el correo…". **Si el correo no sale, el código se borra**: así
no queda un código válido que nadie recibió.

`base_url` está vacía a propósito: el enlace del correo se arma con el dominio
desde el que se hizo la solicitud (funciona igual en QAS y en producción). Si el
sistema está detrás de un proxy, se fija a mano:
`'base_url' => 'https://global.ruted.net/bovs'`.

### Modo de prueba (QA sin buzón real)

`config/mail.php` → `'guardar_en_archivo' => true` escribe cada correo como
`.html` en lugar de enviarlo (carpeta `archivo_dir`; si está vacía, `file/correo/`
y luego la carpeta temporal del sistema). Así se puede leer el código de 6 dígitos
sin buzón. **Nunca dejarlo en `true` en producción.** También se puede activar con
`BOVS_MAIL_ARCHIVO=1`.

> ⚠️ Si Apache corre con `PrivateTmp`, la carpeta temporal del sistema **no se ve
> desde la consola** (es otro `/tmp`). Para revisar los correos de prueba, fijar
> `archivo_dir` a una carpeta del proyecto con permiso de escritura para `www-data`.

---

## 5. Instalación en otro entorno (PRD)

```bash
psql -h 127.0.0.1 -U postgres -d bovs_prd -f scripts/migracion_reset_password.sql
```

Luego, en el servidor:

1. Definir `BOVS_SMTP_PASS` (o la clave en `config/mail.php`).
2. Verificar que `guardar_en_archivo` esté en `false`.
3. Probar el envío real con una cuenta de prueba.

La migración es idempotente y no toca datos existentes. Si el entorno venía de la
versión anterior (enlace con token), la propia migración elimina
`password_reset_token`.

---

## 6. Pruebas realizadas (QAS)

**Por consola — 33 comprobaciones, todas OK.** Captcha incorrecto rechazado;
correo inexistente → respuesta neutra que habla del código de 6 dígitos; solicitud
válida guarda hash de 64 hex, `intentos = 0`, caducidad y fecha de solicitud, y el
correo generado contiene un código de 6 dígitos que coincide con el hash;
reenvío inmediato no regenera; código incorrecto cuenta el intento e informa
"quedan 4"; código de 3 dígitos rechazado; contraseña débil rechazada sin gastar
intentos; a los 5 fallos el código se anula; reenvío pasados 3 minutos genera
código nuevo y el cambio correcto guarda `md5(sal+clave)`, `act_clave = 1`,
`user_modify`, y borra código/caducidad/intentos; el código no se reutiliza;
código caducado rechazado y limpiado; la clave nueva valida el login.

**Por HTTP (contra Apache) — 20 comprobaciones, todas OK.** Ambas pantallas
responden 200 sin avisos de PHP; captcha incorrecto devuelve `captcha_error`;
solicitud válida responde el mensaje neutro con `paso = 2`; la pantalla del código
carga con el correo precargado; código incorrecto cuenta intento y responde
"quedan 4"; el código leído del correo real (modo archivo) coincide con el hash y
al aplicarlo responde "Contraseña actualizada correctamente" con la fila
verificada en base de datos; el login por HTTP entra con la clave nueva.

**En navegador:** el enlace aparece bajo el campo de contraseña del login; la
pantalla de recuperación muestra correo + captcha (con botón *Cambiar* que
regenera sin recargar); al enviar pasa al paso 2 mostrando el mensaje neutro; las
6 casillas se ven completas a 208 y 360 px de ancho (40×48 px, sin desbordar),
teclear mueve el foco a la siguiente, pegar `654321` llena las seis y las marca
en verde, la cuenta atrás arranca en `15:00`, *Reenviar* responde el mensaje
neutro y limpia las casillas deshabilitando el botón, y el botón solo se habilita
cuando el código tiene 6 dígitos, la contraseña cumple la política y ambas
coinciden. Al guardar con un código inválido se ve
"El código no es válido o ya caducó…"; con uno correcto redirige a
`login?stat=reset_ok` con el aviso "Contraseña actualizada".

---

## 7. Recomendaciones / pendientes opcionales

- **Cambiar el hash de contraseñas**: hoy es `md5(sal + clave)` por compatibilidad
  con el resto del sistema. Migrar a `password_hash()` (bcrypt) requeriría
  re-hashear en el próximo login exitoso; ambos flujos (login y reset) ya están
  centralizados en `lib/password_policy.php` para facilitarlo.
- Reutilizar `bovs_password_errores()` en `case 'changepassword'` para no tener
  las reglas duplicadas.
- Avisar por correo al usuario cuando la contraseña cambie ("si no fuiste tú…").
- Registrar las solicitudes en una tabla de bitácora (`password_resets_log`) si se
  quiere auditar intentos por IP.
