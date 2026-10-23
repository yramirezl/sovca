<?php

/**
 * =====================================================================
 * HELPERS DE TEXTO PARA LA INTERFAZ (DISPLAY)
 * ---------------------------------------------------------------------
 * Los nombres vienen de la base de datos y de la sesión, y en registros
 * antiguos quedaron con capitalización inconsistente porque se usó
 * strtoupper() (solo ASCII): "FERNANDO PéREZ", "JOSé CRESPO", "GARCíA".
 *
 * Estas funciones NO modifican la base de datos: solo preparan el texto
 * para mostrarlo en la interfaz.
 *
 * Lo usan:
 *   - views/layouts/menu_perfil_usuario.php  (navbar: trigger + dropdown)
 *   - views/layouts/datos_usuario.php        (panel del menú lateral)
 *   - views/layouts/barra_navegacion_superior.php (nombre del fundo)
 * =====================================================================
 */

if (!function_exists('bovs_texto_mayusculas')) {

    /**
     * Mayúsculas seguras con UTF-8 (equivalentes para acentos y ñ).
     * Si mbstring no está disponible, cae a strtoupper().
     *
     * @param string $valor
     * @return string
     */
    function bovs_texto_mayusculas($valor)
    {
        $valor = (string) $valor;
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($valor, 'UTF-8')
            : strtoupper($valor);
    }
}

if (!function_exists('bovs_texto_limpiar_espacios')) {

    /**
     * Quita espacios al inicio/fin y colapsa espacios repetidos.
     * Respeta los acentos (trabaja en UTF-8).
     *
     * @param string $valor
     * @return string
     */
    function bovs_texto_limpiar_espacios($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        return preg_replace('/\s+/u', ' ', $valor);
    }
}

if (!function_exists('bovs_nombre_mostrar')) {

    /**
     * Deja un nombre listo para mostrarse en la interfaz.
     *
     * - Colapsa espacios múltiples.
     * - Si el nombre está pensado en MAYÚSCULAS pero algún acento quedó
     *   en minúscula por datos antiguos ("FERNANDO PéREZ"), lo normaliza
     *   completo: "FERNANDO PÉREZ".
     * - Si ya tiene capitalización normal ("Diego Fernando Vegas"), se
     *   respeta tal cual para no alterar nombres propios.
     *
     * @param string $nombre
     * @return string
     */
    function bovs_nombre_mostrar($nombre)
    {
        $nombre = bovs_texto_limpiar_espacios($nombre);
        if ($nombre === '') {
            return '';
        }

        /* ¿Tiene minúsculas ASCII? Entonces la capitalización es
           intencional (Título o mixta) y no se toca. Las minúsculas
           acentuadas (é, í, ó, ú, ñ...) no cuentan: son justamente el
           síntoma del dato viejo guardado con strtoupper(). */
        if (preg_match('/[a-z]/', $nombre)) {
            return $nombre;
        }

        return bovs_texto_mayusculas($nombre);
    }
}
