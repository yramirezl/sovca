<?php
/**
 * lib/firma.local.example.php
 * ---------------------------------------------------------------------------
 * Plantilla de configuración local de la firma digital.
 *
 * En cada servidor hay que copiarla como `lib/firma.local.php` (ese archivo
 * NO se versiona) y poner los valores reales:
 *
 *     cp lib/firma.local.example.php lib/firma.local.php
 *     chown root:www-data lib/firma.local.php
 *     chmod 640 lib/firma.local.php
 *
 * ¿Por qué? `lib/firma.php` SÍ está en el repositorio, así que cada
 * `git reset --hard` lo deja tal cual viene. La clave HMAC de producción no
 * debe vivir ahí: si cambia, todos los QR ya impresos dejan de validar.
 *
 * Orden de prioridad que aplica lib/firma.php:
 *   1. este archivo (constantes ya definidas)
 *   2. variables de entorno SOVCA_FIRMA_SECRET / SOVCA_URL_PUBLICA
 *   3. valor por defecto del repositorio
 */

// Clave HMAC con la que se firman los tokens de la ficha pública.
// NO cambiarla en un servidor en producción: invalida todos los QR emitidos.
// Para una instalación nueva: php -r "echo bin2hex(random_bytes(32));"
define('SOVCA_FIRMA_SECRET', 'cambiar-por-la-clave-real-del-servidor');

// URL pública con la que se arman los enlaces/QR (sin barra final).
define('SOVCA_URL_PUBLICA', 'https://global.ruted.net/sovca');
