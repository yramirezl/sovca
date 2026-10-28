<?php
/**
 * config/mail.php
 * ---------------------------------------------------------------------------
 * Configuración del correo saliente de SOVCA (verificación de correo,
 * recuperación de contraseña, notificaciones, etc.).
 *
 * Usa el mismo buzón que el módulo de alertas (alertas/enviar.php):
 *   mail.ruted.net · SSL · puerto 465 · global@ruted.net
 *
 * ⚠️ LA CONTRASEÑA NO SE GUARDA EN EL REPOSITORIO.
 *    Al desplegar, se define de una de estas dos formas:
 *
 *      1) Variable de entorno del servidor:
 *             SOVCA_SMTP_PASS="la-clave-del-buzon"
 *
 *      2) Escribiéndola en el valor de 'password' de este archivo.
 *
 *    Sin contraseña el envío falla con un mensaje claro en el log de PHP
 *    ("SMTP sin contraseña configurada") y el usuario ve el aviso genérico
 *    de "no se pudo enviar el correo".
 */
return array(

    /* ---------------- Servidor SMTP ---------------- */
    'host'     => 'mail.ruted.net',
    'port'     => 465,
    'secure'   => 'ssl',            // 'ssl' (465) o 'tls' (587)
    'username' => 'global@ruted.net',
    // La variable de entorno SOVCA_SMTP_PASS tiene prioridad sobre el valor
    // escrito aquí.
    'password' => (getenv('SOVCA_SMTP_PASS') !== false && getenv('SOVCA_SMTP_PASS') !== ''
                    ? getenv('SOVCA_SMTP_PASS')
                    : 'Gl0b@l*2023#'),

    /* ---------------- Remitente ----------------
       IMPORTANTE PARA NO CAER EN SPAM:
       · from_email debe pertenecer a un dominio con SPF y DKIM válidos
         (hoy: ruted.net).
       · sender_email es el "envelope from" (Return-Path); debe ser del
         mismo dominio que from_email para que SPF alinee.
       · hostname se usa en el saludo HELO y en el Message-ID; debe ser un
         nombre del dominio remitente, no el hostname del servidor.
       · reply_to puede ser un buzón real de soporte. */
    'from_email'        => 'global@ruted.net',
    'from_name'         => 'SOVCA · Gestión Ovina y Caprina',
    'sender_email'      => 'global@ruted.net',
    'reply_to'          => 'global@ruted.net',
    'reply_to_name'     => 'SOVCA · Soporte',
    'hostname'          => 'mail.ruted.net',
    'message_id_domain' => 'ruted.net',

    /* ---------------- List-Unsubscribe ----------------
       Ayuda a que los filtros traten el correo como transaccional.
       Con List-Unsubscribe-Post se habilita la baja en un clic. */
    'list_unsubscribe' => 'mailto:global@ruted.net?subject=Baja%20SOVCA',

    /* ---------------- DKIM (opcional, muy recomendado) ----------------
       Si el servidor de correo no firma los mensajes, aquí se puede firmar
       desde PHP. Hay que usar la clave PRIVADA del selector publicado en
       DNS (hoy: default._domainkey.ruted.net).

         'dkim_domain'   => 'ruted.net',
         'dkim_private'  => '/ruta/fuera/del/repositorio/ruted.default.private',

       La clave privada NUNCA debe quedar en el repositorio ni dentro de
       public_html. Si el hosting ya firma DKIM (cPanel → Email
       Deliverability), dejar estos valores vacíos. */
    'dkim_domain'   => '',
    'dkim_selector' => 'default',
    'dkim_private'  => '',

    /* ---------------- Enlaces de los correos ----------------
       URL pública que se escribe DENTRO de los correos. Si queda vacía se
       usa la del navegador, pero un enlace a localhost/127.0.0.1 es una
       señal fuerte de spam: en producción fijar aquí el dominio real. */
    'public_url' => 'https://global.ruted.net/sovca',

    /* base_url se conserva para compatibilidad; public_url tiene prioridad. */
    'base_url' => '',

    /* ---------------- Comportamiento ---------------- */
    // true = escribe cada correo en consola (SMTPDebug). Solo para depurar.
    'debug' => false,

    /* Modo de prueba local (QA sin buzón real):
       true  → el correo NO se envía: se guarda como .html en 'archivo_dir'
               (o en file/correo/ y, si no se puede escribir, en la carpeta
               temporal del sistema).
       false → envío real por SMTP (producción).
       Nunca dejar en true en producción: los usuarios no recibirían el enlace. */
    'guardar_en_archivo' => false,

    // Carpeta para los correos del modo de prueba (vacía = automática).
    // Ojo: si Apache corre con PrivateTmp, la carpeta temporal del sistema no
    // es visible desde la consola; conviene fijar aquí una carpeta del proyecto
    // con permiso de escritura para el usuario de Apache.
    'archivo_dir' => '',

    // Minutos de vigencia del código de recuperación
    'reset_vigencia_min' => 15,

    // Intentos fallidos permitidos con un mismo código (después se anula)
    'reset_max_intentos' => 5,
);
