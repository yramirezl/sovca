# Correo saliente de SOVCA: plantilla, cabeceras y entregabilidad

Documento de trabajo sobre el envío de correos del sistema (verificación de
cuenta demo, recuperación de contraseña y notificaciones).

---

## 1. Qué se cambió en el código

### `lib/mailer_helper.php` (centro de todos los envíos)

| Elemento | Antes | Ahora |
|---|---|---|
| Remitente | `Gestión Ganadera` (heredado) | `SOVCA · Gestión Ovina y Caprina` |
| Reply-To | el mismo del `From` | `SOVCA · Soporte` (`reply_to`) |
| Return-Path (`Sender`) | lo ponía PHPMailer sin control | `sender_email` (mismo dominio que el `From`) |
| HELO | hostname del servidor | `mail.ruted.net` |
| Message-ID | el de la librería, con dominio del servidor | `<aleatorio@ruted.net>` |
| X-Mailer | `PHPMailer 5.1 (phpmailer.sourceforge.net)` | `SOVCA Mailer` |
| Texto alternativo | "To view the message..." | texto real en español |
| Vía de baja | no existía | `List-Unsubscribe` + `List-Unsubscribe-Post` |
| Correo automático | no se declaraba | `Auto-Submitted` + `X-Auto-Response-Suppress` |
| Enlaces | `http://127.0.0.1/sovca/...` cuando no había `base_url` | `public_url` (`https://global.ruted.net/sovca`) o sin enlace |
| DKIM | no se firmaba | se firma si se configuran `dkim_domain` + `dkim_private` |

### `alertas/class.phpmailer.php` (PHPMailer 5.1 incluido en el proyecto)

Se corrigieron dos fallos que afectaban a **todos** los correos:

1. `EncodeQ()` usaba el modificador `/e` de `preg_replace`, eliminado en
   PHP 7. Resultado real: el nombre del remitente salía vacío
   (`From: =?UTF-8?Q??= <global@ruted.net>`) y los asuntos con acentos
   quedaban corruptos. Ahora usa `preg_replace_callback`.
2. `X-Mailer` anunciaba `PHPMailer 5.1 (phpmailer.sourceforge.net)`;
   ahora anuncia `SOVCA Mailer`.

### Otros archivos

- `config/mail.php`: nueva configuración (remitente, `sender_email`,
  `reply_to`, `hostname`, `message_id_domain`, `list_unsubscribe`,
  `public_url`, DKIM).
- `lib/demo_helper.php`: correo de la demo con marca SOVCA, asunto
  `Tu código SOVCA: XXXXXX` y enlace sólo si hay URL pública.
- `alertas/enviar_codigo.php`: reescrito; delega en el helper y conserva el
  contrato `(new Enviar_Correo())->enviar($correo, $codigo)` → `bool`.
- `alertas/enviar.php`, `enviar_inactividad.php`, `enviar_backup.php`,
  `enviar_promo.php`: remitente SOVCA, cabeceras y texto alternativo real.

---

## 2. Resultado verificado (envío real al buzón del sistema)

```
Return-Path: <global@ruted.net>
From: =?UTF-8?Q?SOVCA_=C2=B7_Gesti=C3=B3n_Ovina_y_Caprina?= <global@ruted.net>
Reply-to: =?UTF-8?Q?SOVCA_=C2=B7_Soporte?= <global@ruted.net>
Subject: =?UTF-8?Q?Tu_c=C3=B3digo_SOVCA:_482913?=
Message-ID: <a2d0eb35dad68ffae277d5150f6479b8@ruted.net>
X-Priority: 3
X-Mailer: SOVCA Mailer
Auto-Submitted: auto-generated
X-Auto-Response-Suppress: All
List-Unsubscribe: <mailto:global@ruted.net?subject=Baja%20SOVCA>
Content-Type: multipart/alternative (text/plain + text/html, UTF-8)
```

Falta una pieza: **no aparece `DKIM-Signature`**. Es decir, el servidor que
envía (`mail.ruted.net`, banahosting) **no está firmando** los mensajes
aunque el registro público `default._domainkey.ruted.net` exista.

---

## 3. Estado del DNS del dominio `ruted.net`

| Registro | Estado | Valor actual |
|---|---|---|
| SPF | ✅ existe | `v=spf1 +a +mx +ip4:50.31.188.162 include:relay.mailchannels.net ~all` |
| DKIM (`default`) | ✅ publicado | clave RSA de 2048 bits |
| DMARC | ❌ **no existe** | — |
| PTR de la IP de envío | ⚠️ no coincide | `single-4720.banahosting.com` |

### Por qué esto importa

Gmail y Outlook, ante un correo con un código de verificación (patrón muy
usado por estafadores), aplican:

1. **DMARC**: sin registro `_dmarc`, el dominio no declara política; Gmail lo
   trata con más dureza cuando además **no hay firma DKIM válida**.
2. **DKIM**: sin firma, se pierde la prueba criptográfica de autenticidad;
   sólo queda SPF, que se rompe en cuanto el mensaje se reenvía o se
   procesa en algún salto.
3. **Alineación**: si el `From` es `@ruted.net` pero no hay DKIM ni DMARC,
   la alineación depende únicamente de SPF.

---

## 4. Acciones que hay que hacer FUERA del código

Estas no se pueden resolver desde PHP; las hace quien administra el DNS y el
buzón (cPanel de banahosting):

1. **Publicar DMARC** (empezar suave, sin romper nada):

   ```
   _dmarc.ruted.net.  TXT  "v=DMARC1; p=none; rua=mailto:global@ruted.net; adkim=s; aspf=s; pct=100"
   ```

   Cuando todo esté estable, subir a `p=quarantine`.

2. **Activar la firma DKIM en el servidor de correo.** En cPanel:
   `Email Deliverability` → dominio `ruted.net` → `DKIM` → *Install the
   suggested record* / *Enable*. Así todos los correos que salgan de ese
   buzón irán firmados, sin tocar el código.

3. **Alternativa si el hosting no firma**: firmar desde PHP.
   Descargar la clave privada del selector `default` y dejarla fuera del
   repositorio, por ejemplo `/var/www/llaves/ruted.default.private`, luego en
   `config/mail.php`:

   ```php
   'dkim_domain'  => 'ruted.net',
   'dkim_selector'=> 'default',
   'dkim_private' => '/var/www/llaves/ruted.default.private',
   ```

   El helper la usa automáticamente y el correo saldrá con
   `DKIM-Signature`.

4. **PTR / reverse DNS**: pedir al hosting que el PTR de la IP de envío sea
   `mail.ruted.net`. Hoy es `single-4720.banahosting.com`, que no coincide con
   el HELO (`mail.ruted.net`) ni con el `From`.

5. **Reputación del dominio**: un dominio que empieza a enviar correos con
   código de verificación tiene mala fama por defecto. Ayuda:
   - enviar siempre desde el mismo buzón (`global@ruted.net`),
   - pedir a los usuarios marcar "No es spam" en los primeros envíos,
   - no cambiar el asunto de forma drástica entre envíos,
   - incluir la dirección física/de contacto en el pie.

---

## 5. Cómo comprobar si un correo salió bien

Script de apoyo (no forma parte del sistema), envía al buzón propio y lee las
cabeceras por POP3:

```bash
php /tmp/sovca_mail_send.php     # envía usando bovs_mail_enviar_codigo()
php /tmp/sovca_pop_check.php     # muestra las cabeceras del último mensaje
```

Buscar en la salida: `DKIM-Signature`, `Return-Path`, `Reply-to`,
`List-Unsubscribe`, `X-Mailer: SOVCA Mailer`.

Prueba externa recomendada: enviar a una cuenta de Gmail y revisar
*Mostrar original* → `Authentication-Results` (spf=, dkim=, dmarc=).

---

## 6. Pendientes conocidos

- `alertas/enviar.php` todavía tiene el botón "Ingrese Aquí" apuntando a
  `https://global.ruted.net/sovca/` (tres bloques de HTML). Debe pasar a
  `/sovca/`.
- `alertas/enviar_promo.php` ya apunta a `https://global.ruted.net/sovca/`;
  si se publica una página de producto SOVCA en `ruted.net`, actualizar la URL.
- El `PHP 5.1` de PHPMailer no soporta `DKIM_passphrase`; si la clave privada
  se protege con contraseña habría que migrar a PHPMailer 6.
