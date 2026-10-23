<?php
/**
 * lib/password_policy.php
 * ---------------------------------------------------------------------------
 * Reglas de contraseña de BOVS en un solo lugar.
 *
 * Las mismas reglas que ya aplica "Cambiar contraseña"
 * (controllers/musuarios_controller.php, case 'changepassword'):
 *
 *   · mínimo 8 caracteres
 *   · al menos 1 letra MAYÚSCULA
 *   · al menos 1 letra minúscula
 *   · al menos 1 número
 *   · al menos 1 carácter especial de: @ # $ %
 *   · solo se admiten letras (a-z, A-Z, ñ, Ñ), números y @ # $ %
 *
 * Se usa en la recuperación de contraseña por correo
 * (case 'aplicar_reset'). Si mañana cambian las reglas, se cambian aquí
 * y no hay que buscarlas por el código.
 */

/** Carácter de "sal" con el que se firma la contraseña (md5(sal + clave)). */
if (!defined('BOVS_PASSWORD_SALT')) {
    define('BOVS_PASSWORD_SALT', '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/');
}

if (!function_exists('bovs_password_errores')) {
    /**
     * Valida una contraseña nueva.
     *
     * @param string $password
     * @return array Lista de mensajes de error (vacía = contraseña válida)
     */
    function bovs_password_errores($password)
    {
        $password = (string) $password;
        $errores  = array();

        if (strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password)) {
            $errores[] = 'Debe contener al menos una letra mayúscula.';
        }

        if (!preg_match('/[a-záéíóúüñ]/u', $password)) {
            $errores[] = 'Debe contener al menos una letra minúscula.';
        }

        if (!preg_match('/\d/', $password)) {
            $errores[] = 'Debe contener al menos un número.';
        }

        if (!preg_match('/[@#$%]/', $password)) {
            $errores[] = 'Debe contener al menos uno de estos caracteres especiales: @ # $ %';
        }

        // Solo se admiten letras (con acentos y ñ), números y @ # $ %
        if ($password !== '' && !preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9@#$%]+$/u', $password)) {
            $errores[] = 'Solo se permiten letras, números y los caracteres @ # $ % (sin espacios ni símbolos).';
        }

        return $errores;
    }
}

if (!function_exists('bovs_password_hash')) {
    /**
     * Firma la contraseña igual que el inicio de sesión y "Cambiar contraseña".
     *
     * @param string $password
     * @return string md5(sal + contraseña)
     */
    function bovs_password_hash($password)
    {
        return md5(BOVS_PASSWORD_SALT . (string) $password);
    }
}
