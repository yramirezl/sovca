<?php

/**
 * Regla ÚNICA del permiso de acceso al MODO SIN CONEXIÓN (offline).
 *
 * ─────────────────────────────────────────────────────────────────────
 *  El permiso es del PRODUCTOR (rol 2), dueño del fundo:
 *
 *   * Rol 2 (PRODUCTOR):        usa su propio permiso
 *                               (musuarios.acceso_offline).
 *   * Rol 3 (ENCARGADO):        lo HEREDA de los productores dueños de
 *                               sus fundos activos. Se exige que TODOS
 *                               ellos lo tengan habilitado y que exista
 *                               al menos uno (si no hay productor
 *                               vinculado, no hay permiso que heredar).
 *   * Rol 1 (ADMINISTRADOR) y
 *     Rol 4 (SUPER USUARIO):    su propio permiso; no necesitan la app
 *                               sin conexión, solo administran.
 *
 *  OJO: esta regla también está implementada en
 *  api/lib/ApiAuth.php::tieneAccesoOffline() (que trabaja por usuario,
 *  con caché por petición). Este archivo agrega la versión "en lote"
 *  (una sola consulta para todo el listado). Si la regla cambia, hay
 *  que actualizar los dos lugares.
 * ─────────────────────────────────────────────────────────────────────
 */

/**
 * ¿Los productores dueños de los fundos activos del usuario tienen
 * habilitado el modo sin conexión? (regla del rol 3 / ENCARGADO)
 *
 * @param  \ActiveRecord\Connection $conexion Conexión de Active Record a usar.
 * @param  int                      $usuarioId
 * @return bool false si no se encontró ningún productor o si alguno de
 *              ellos tiene el permiso apagado.
 */
function bovs_acceso_offline_productores_permiten($conexion, $usuarioId)
{
    $valores = array((int) $usuarioId);
    $filas = $conexion->query(
        "SELECT DISTINCT p.id AS productor_id, p.acceso_offline
           FROM mfundosxusuarios mio
           JOIN mfundosxusuarios dueno
                ON dueno.mfundos_id = mio.mfundos_id
               AND dueno.status = 1
               AND dueno.musuarios_id <> mio.musuarios_id
           JOIN musuarios p
                ON p.id = dueno.musuarios_id
               AND p.mroles_id = 2
               AND p.status = 1
          WHERE mio.musuarios_id = ?
            AND mio.status = 1",
        $valores
    )->fetchAll();

    if (empty($filas)) {
        return false;
    }

    foreach ($filas as $fila) {
        if (!bovs_acceso_offline_a_booleano($fila['acceso_offline'])) {
            return false;
        }
    }

    return true;
}

/**
 * Estado del permiso para TODOS los usuarios en UNA sola consulta.
 *
 * @param  \ActiveRecord\Connection $conexion
 * @return array<int, array{efectivo:bool, heredado:bool, propio:bool, productores:string}>
 *         indexado por id de usuario. 'productores' son los usuarios
 *         (username) de los que hereda, separados por coma.
 */
function bovs_acceso_offline_mapa($conexion)
{
    $sql = "SELECT u.id,
                   u.mroles_id,
                   u.status AS cuenta_status,
                   u.acceso_offline AS propio,
                   hereda.n_activos,
                   hereda.todos_ok,
                   hereda.productores_activos,
                   hereda.productores_inactivos
              FROM musuarios u
              LEFT JOIN (
                   SELECT mio.musuarios_id,
                          count(*) FILTER (WHERE p.status = 1) AS n_activos,
                          bool_and(p.acceso_offline) FILTER (WHERE p.status = 1) AS todos_ok,
                          string_agg(DISTINCT p.username, ', ' ORDER BY p.username)
                              FILTER (WHERE p.status = 1) AS productores_activos,
                          string_agg(DISTINCT p.username, ', ' ORDER BY p.username)
                              FILTER (WHERE p.status = 0) AS productores_inactivos
                     FROM mfundosxusuarios mio
                     JOIN mfundosxusuarios dueno
                          ON dueno.mfundos_id = mio.mfundos_id
                         AND dueno.status = 1
                         AND dueno.musuarios_id <> mio.musuarios_id
                     JOIN musuarios p
                          ON p.id = dueno.musuarios_id
                         AND p.mroles_id = 2
                    WHERE mio.status = 1
                    GROUP BY mio.musuarios_id
              ) hereda ON hereda.musuarios_id = u.id";

    $mapa = array();

    foreach ($conexion->query($sql)->fetchAll() as $fila) {
        $id = (int) $fila['id'];
        $esEncargado = ((int) $fila['mroles_id'] === 3);
        $propio = bovs_acceso_offline_a_booleano($fila['propio']);
        $cuentaActiva = ((int) $fila['cuenta_status'] === 1);

        $activos = $fila['productores_activos'] !== null ? trim((string) $fila['productores_activos']) : '';
        $inactivos = $fila['productores_inactivos'] !== null ? trim((string) $fila['productores_inactivos']) : '';

        // Una cuenta INHABILITADA no puede entrar al sistema, así que el
        // permiso efectivo es siempre "sin acceso" (aunque el flag esté
        // activo: se vuelve a evaluar al reactivar la cuenta).
        $efectivo = false;
        if ($cuentaActiva) {
            $efectivo = $esEncargado
                ? (($activos !== '') && bovs_acceso_offline_a_booleano($fila['todos_ok']))
                : $propio;
        }

        $mapa[$id] = array(
            'efectivo'              => $efectivo,
            'heredado'              => $esEncargado,
            'propio'                => $propio,
            'cuenta_activa'         => $cuentaActiva,
            'productores'           => $activos,
            'productores_activos'   => $activos,
            'productores_inactivos' => $inactivos,
        );
    }

    return $mapa;
}

/**
 * Textos para mostrar el permiso en el listado de usuarios: etiqueta,
 * orden (para que "con acceso" quede arriba) y detalle para el tooltip.
 *
 * @param  array $estado Fila devuelta por bovs_acceso_offline_mapa().
 * @return array{etiqueta:string, orden:int, detalle:string}
 */
function bovs_acceso_offline_texto($estado)
{
    $efectivo = !empty($estado['efectivo']);
    $heredado = !empty($estado['heredado']);
    $cuentaActiva = !array_key_exists('cuenta_activa', $estado) || !empty($estado['cuenta_activa']);
    $activos = isset($estado['productores_activos']) ? trim((string) $estado['productores_activos']) : '';
    $inactivos = isset($estado['productores_inactivos']) ? trim((string) $estado['productores_inactivos']) : '';

    // Cuenta inhabilitada: no puede entrar al sistema, así que mostrar
    // "Activo" en el permiso sería engañoso.
    if (!$cuentaActiva) {
        return array(
            'etiqueta' => 'Inactivo',
            'orden'    => 0,
            'detalle'  => 'La cuenta está inhabilitada y no puede iniciar sesión. Se habilita con Reactivar en el listado; los usuarios ayudantes heredan la inhabilitación del productor.',
        );
    }

    if ($heredado) {
        if ($efectivo) {
            return array(
                'etiqueta' => 'Heredado',
                'orden'    => 1,
                'detalle'  => 'Usa el modo sin conexión porque el productor ' . $activos
                             . ' lo tiene habilitado y este usuario trabaja en su fundo.',
            );
        }

        if ($activos === '' && $inactivos !== '') {
            return array(
                'etiqueta' => 'Inactivo',
                'orden'    => 0,
                'detalle'  => 'El productor ' . $inactivos . ' está inactivo: sin un productor activo en el fundo no hay acceso sin conexión que heredar.',
            );
        }

        if ($activos !== '') {
            return array(
                'etiqueta' => 'Inactivo',
                'orden'    => 0,
                'detalle'  => 'El productor ' . $activos . ' no tiene habilitado el acceso sin conexión.',
            );
        }

        return array(
            'etiqueta' => 'Inactivo',
            'orden'    => 0,
            'detalle'  => 'Su fundo no tiene un productor (rol PRODUCTOR) vinculado, así que no hay permiso que heredar.',
        );
    }

    return $efectivo
        ? array(
            'etiqueta' => 'Activo',
            'orden'    => 2,
            'detalle'  => 'El productor tiene habilitado el uso de la app sin conexión.',
        )
        : array(
            'etiqueta' => 'Inactivo',
            'orden'    => 0,
            'detalle'  => 'No tiene habilitado el uso de la app sin conexión. Se asigna desde Editar usuario (solo rol PRODUCTOR).',
        );
}

/** Interpreta un booleano tal como lo devuelve PostgreSQL ('t'/'f', 'true'/1/0). */
function bovs_acceso_offline_a_booleano($valor)
{
    $v = strtolower(trim((string) $valor));
    return in_array($v, array('1', 't', 'true', 'y', 'yes', 'si', 'sí'), true);
}
