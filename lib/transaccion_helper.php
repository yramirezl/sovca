<?php

/**
 * lib/transaccion_helper.php
 *
 * Transacciones anidadas sobre php-activerecord usando SAVEPOINT.
 *
 * ¿Por qué?
 *   PostgreSQL deja la transacción en estado ABORTADO en cuanto una
 *   sentencia falla. A partir de ese momento **cualquier** sentencia
 *   responde "SQLSTATE[25P02] current transaction is aborted, commands
 *   ignored until end of transaction block", ocultando el error real y
 *   arruinando el resto del flujo.
 *
 *   Con un SAVEPOINT antes de cada paso delicado se puede hacer
 *   ROLLBACK TO SAVEPOINT: la transacción vuelve a estar utilizable,
 *   los cambios de ese paso se descartan y el error original se puede
 *   mostrar al operador.
 *
 * Uso:
 *
 *   $token = bovs_tx_iniciar($conexion);          // BEGIN o SAVEPOINT
 *   try {
 *       ...trabajo...
 *       bovs_tx_confirmar($conexion, $token);     // COMMIT o RELEASE
 *   } catch (Throwable $e) {
 *       bovs_tx_revertir($conexion, $token);      // ROLLBACK / ROLLBACK TO
 *       ...
 *   }
 *
 * El primer nivel hace BEGIN/COMMIT/ROLLBACK; los siguientes usan
 * SAVEPOINT/RELEASE/ROLLBACK TO SAVEPOINT (los nombres los genera este
 * archivo, nunca llegan desde la petición).
 */

if (!function_exists('bovs_tx_pilas')) {
    /**
     * Contador de niveles por conexión (una entrada por objeto Connection).
     *
     * @return array<int,int>
     */
    function &bovs_tx_pilas()
    {
        static $pilas = array();
        return $pilas;
    }
}

if (!function_exists('bovs_tx_nivel')) {
    /**
     * Nivel de anidamiento actual de una conexión (0 = sin transacción).
     *
     * @param object $conexion Connection de php-activerecord.
     * @return int
     */
    function bovs_tx_nivel($conexion)
    {
        $pilas = &bovs_tx_pilas();
        $clave = spl_object_id($conexion);
        return isset($pilas[$clave]) ? (int) $pilas[$clave] : 0;
    }
}

if (!function_exists('bovs_tx_iniciar')) {
    /**
     * Abre una transacción o un SAVEPOINT anidado.
     *
     * @param object $conexion Connection de php-activerecord.
     * @return string Token que se debe devolver a confirmar/revertir.
     */
    function bovs_tx_iniciar($conexion)
    {
        $pilas = &bovs_tx_pilas();
        $clave = spl_object_id($conexion);
        $nivel = isset($pilas[$clave]) ? (int) $pilas[$clave] : 0;

        if ($nivel === 0) {
            $conexion->transaction();
            $token = 'raiz';
        } else {
            $token = 'bovs_sp_' . $nivel;
            $conexion->query('SAVEPOINT ' . $token);
        }

        $pilas[$clave] = $nivel + 1;

        return $token;
    }
}

if (!function_exists('bovs_tx_confirmar')) {
    /**
     * Confirma el nivel abierto con bovs_tx_iniciar().
     *
     * @param object $conexion Connection de php-activerecord.
     * @param string $token    Token devuelto por bovs_tx_iniciar().
     * @return void
     */
    function bovs_tx_confirmar($conexion, $token)
    {
        $pilas = &bovs_tx_pilas();
        $clave = spl_object_id($conexion);
        $nivel = isset($pilas[$clave]) ? (int) $pilas[$clave] : 0;

        if ($token === 'raiz' || $nivel <= 1) {
            $pilas[$clave] = 0;
            $conexion->commit();
            return;
        }

        $conexion->query('RELEASE SAVEPOINT ' . $token);
        $pilas[$clave] = $nivel - 1;
    }
}

if (!function_exists('bovs_tx_revertir')) {
    /**
     * Deshace el nivel abierto con bovs_tx_iniciar().
     *
     * Si la sentencia que falló dejó la transacción abortada, el
     * ROLLBACK (o ROLLBACK TO SAVEPOINT) la deja utilizable otra vez,
     * que es justo lo que evita el error 25P02 en cascada.
     *
     * @param object $conexion Connection de php-activerecord.
     * @param string $token    Token devuelto por bovs_tx_iniciar().
     * @return void
     */
    function bovs_tx_revertir($conexion, $token)
    {
        $pilas = &bovs_tx_pilas();
        $clave = spl_object_id($conexion);
        $nivel = isset($pilas[$clave]) ? (int) $pilas[$clave] : 0;

        if ($token === 'raiz' || $nivel <= 1) {
            $pilas[$clave] = 0;
            if (method_exists($conexion->connection, 'inTransaction')
                && $conexion->connection->inTransaction()) {
                $conexion->rollback();
            }
            return;
        }

        $conexion->query('ROLLBACK TO SAVEPOINT ' . $token);
        $pilas[$clave] = $nivel - 1;
    }
}

if (!function_exists('bovs_tx_error_abortado')) {
    /**
     * ¿El error es el "current transaction is aborted" de PostgreSQL?
     *
     * Con ese estado la conexión ya no acepta más trabajo dentro de la
     * transacción, así que el flujo se puede reintentar una vez desde
     * cero (la pasada anterior quedó revertida por completo).
     *
     * @param Throwable|string $error
     * @return bool
     */
    function bovs_tx_error_abortado($error)
    {
        $mensaje = ($error instanceof Throwable) ? $error->getMessage() : (string) $error;

        return stripos($mensaje, '25P02') !== false
            || stripos($mensaje, 'current transaction is aborted') !== false;
    }
}
