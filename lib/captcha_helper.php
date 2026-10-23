<?php

/**
 * =====================================================================
 * CAPTCHA DE INICIO DE SESIÓN (suma / resta)
 * ---------------------------------------------------------------------
 * Genera la operación, su resultado y el token anti-manipulación, y
 * guarda el captcha en la sesión ($_SESSION['captcha']).
 *
 * Lo usan:
 *   - views/login.php                     (al pintar el formulario)
 *   - views/demo/index.php                (alta de cuenta demo)
 *   - controllers/mcaptcha_controller.php (al pedir otro captcha sin
 *                                          recargar el formulario)
 *
 * Cada captcha vive en su propio espacio de sesión ($slot): así el del
 * formulario de acceso y el del modal de instalación offline pueden
 * convivir sin invalidarse entre sí.
 * =====================================================================
 */

if (!function_exists('bovs_captcha_generar')) {

    /**
     * Genera un captcha nuevo.
     *
     * Si la sesión está activa, lo deja guardado en $_SESSION[$slot].
     *
     * @param string $slot Espacio de sesión ('captcha' por defecto).
     * @return array a, b, signo, resultado, token, captcha (datos de sesión)
     */
    function bovs_captcha_generar($slot = 'captcha')
    {
        $a  = random_int(2, 9);
        $b  = random_int(2, 9);
        $op = random_int(0, 1); // 0 = suma, 1 = resta

        /* La resta nunca debe dar negativo */
        if ($op === 1 && $b > $a) {
            $tmp = $a;
            $a   = $b;
            $b   = $tmp;
        }

        $resultado = ($op === 0) ? ($a + $b) : ($a - $b);
        $signo     = ($op === 0) ? '+' : '−';
        $token     = bin2hex(random_bytes(16));

        $captcha = array(
            'resultado' => (int) $resultado,
            'token'     => $token,
            'ts'        => time(),
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$slot] = $captcha;
        }

        return array(
            'a'         => $a,
            'b'         => $b,
            'signo'     => $signo,
            'resultado' => (int) $resultado,
            'token'     => $token,
            'captcha'   => $captcha,
        );
    }
}

if (!function_exists('bovs_captcha_validar')) {

    /**
     * Valida la respuesta del captcha contra la sesión.
     *
     * Reglas (las mismas del inicio de sesión):
     *   · el token debe coincidir con el de la sesión
     *   · no deben haber pasado más de 15 minutos
     *   · la respuesta debe ser el resultado exacto
     * El captcha es de UN SOLO USO: se borra de la sesión al validarlo.
     *
     * @param string $token      Valor de $_POST['captcha_token']
     * @param string $respuesta  Valor de $_POST['captcha_respuesta']
     * @param string $slot       Espacio de sesión ('captcha' por defecto)
     * @return bool
     */
    function bovs_captcha_validar($token, $respuesta, $slot = 'captcha')
    {
        $guardado = isset($_SESSION[$slot]) ? $_SESSION[$slot] : null;

        // Un solo uso: se consume siempre, acierte o no
        unset($_SESSION[$slot]);

        if (
            !is_array($guardado) ||
            !isset($guardado['token'], $guardado['resultado'], $guardado['ts'])
        ) {
            return false;
        }

        if (!hash_equals((string) $guardado['token'], (string) $token)) {
            return false;
        }

        if ((time() - (int) $guardado['ts']) > 900) {
            return false;
        }

        if (!preg_match('/^-?\d+$/', (string) $respuesta)) {
            return false;
        }

        return (int) $respuesta === (int) $guardado['resultado'];
    }
}
