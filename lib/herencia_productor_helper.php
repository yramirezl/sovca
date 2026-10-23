<?php

/**
 * HERENCIA DEL PRODUCTOR → USUARIOS AYUDANTES (rol 3).
 * ─────────────────────────────────────────────────────────────────────
 *  El PRODUCTOR (rol 2) es el dueño del fundo y los usuarios que trabajan
 *  en él (rol 3, ENCARGADO) heredan su situación:
 *
 *   * Si se inhabilita la cuenta del productor, los ayudantes que
 *     dependen de él quedan inhabilitados (y al reactivarlo, vuelven).
 *   * Un ayudante solo se inhabilita si TODOS los fundos donde trabaja
 *     se quedaron sin ningún productor activo: si comparte trabajo con
 *     otro fundo que sigue teniendo dueño activo, conserva su acceso.
 *   * Un fundo compartido por varios productores sigue operando mientras
 *     quede al menos uno activo (no se toca a los ayudantes).
 *   * Los roles 1 (Administrador) y 4 (Super Usuario) no se ven afectados.
 *
 *  Estas funciones se usan desde `controllers/musuarios_controller.php`
 *  (acciones `delete`, `readd` y `login`). Trabajan con la conexión de
 *  Active Record, así que también se pueden usar desde scripts.
 * ─────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../models/Musuario.php';

/**
 * Fundos donde el usuario tiene un enlace ACTIVO.
 *
 * @return array<int, array{id:int, nombre:string}>
 */
function bovs_prod_fundos_de_usuario($conexion, $usuarioId)
{
    $valores = array((int) $usuarioId);
    $filas = $conexion->query(
        "SELECT f.id, f.nombre
           FROM mfundosxusuarios l
           JOIN mfundos f ON f.id = l.mfundos_id
          WHERE l.musuarios_id = ?
            AND l.status = 1
       ORDER BY f.id",
        $valores
    )->fetchAll();

    $salida = array();
    foreach ($filas as $fila) {
        $salida[] = array('id' => (int) $fila['id'], 'nombre' => trim((string) $fila['nombre']));
    }

    return $salida;
}

/**
 * Productores (rol 2) vinculados a un fundo.
 *
 * @param int  $fundoId
 * @param int  $exceptoUsuarioId Productor que se ignora (el que se está
 *        inhabilitando o reactivando).
 * @param bool $soloActivos true = solo cuentas con status = 1.
 * @return array<int, array{id:int, username:string, status:int}>
 */
function bovs_prod_productores_del_fundo($conexion, $fundoId, $exceptoUsuarioId = 0, $soloActivos = true)
{
    $sql = "SELECT p.id, p.username, p.status
              FROM mfundosxusuarios l
              JOIN musuarios p ON p.id = l.musuarios_id
             WHERE l.mfundos_id = ?
               AND l.status = 1
               AND p.mroles_id = 2";

    if ((int) $exceptoUsuarioId > 0) {
        $sql .= " AND p.id <> " . (int) $exceptoUsuarioId;
    }
    if ($soloActivos) {
        $sql .= " AND p.status = 1";
    }

    $sql .= " ORDER BY p.id";

    $valores = array((int) $fundoId);
    $filas = $conexion->query($sql, $valores)->fetchAll();

    $salida = array();
    foreach ($filas as $fila) {
        $salida[] = array(
            'id' => (int) $fila['id'],
            'username' => trim((string) $fila['username']),
            'status' => (int) $fila['status'],
        );
    }

    return $salida;
}

/** ¿El fundo tiene algún productor ACTIVO? (sin contar a $exceptoUsuarioId) */
function bovs_prod_fundo_tiene_productor_activo($conexion, $fundoId, $exceptoUsuarioId = 0)
{
    return count(bovs_prod_productores_del_fundo($conexion, $fundoId, $exceptoUsuarioId, true)) > 0;
}

/** Ayudantes (rol 3) con enlace activo al fundo. */
function bovs_prod_ayudantes_del_fundo($conexion, $fundoId, array $excepto = array())
{
    $sql = "SELECT h.id, h.username, h.status
              FROM mfundosxusuarios l
              JOIN musuarios h ON h.id = l.musuarios_id
             WHERE l.mfundos_id = ?
               AND l.status = 1
               AND h.mroles_id = 3";

    foreach ($excepto as $id) {
        $sql .= " AND h.id <> " . (int) $id;
    }

    $sql .= " ORDER BY h.id";

    $valores = array((int) $fundoId);
    $filas = $conexion->query($sql, $valores)->fetchAll();

    $salida = array();
    foreach ($filas as $fila) {
        $salida[] = array(
            'id' => (int) $fila['id'],
            'username' => trim((string) $fila['username']),
            'status' => (int) $fila['status'],
        );
    }

    return $salida;
}

/**
 * ¿El usuario se quedó SIN ningún productor activo?
 *
 * true solo cuando trabaja en al menos un fundo que SI tiene productores
 * vinculados y ninguno de esos fundos tiene un productor activo. Si sus
 * fundos no tienen productor registrado, devuelve false: no hay nada que
 * heredar y la cuenta la administra el sistema.
 */
function bovs_prod_usuario_sin_productor_activo($conexion, $usuarioId)
{
    $fundos = bovs_prod_fundos_de_usuario($conexion, $usuarioId);
    if (empty($fundos)) {
        return false;
    }

    $conProductor = 0;
    $conProductorActivo = 0;

    foreach ($fundos as $fundo) {
        if (count(bovs_prod_productores_del_fundo($conexion, $fundo['id'], 0, false)) > 0) {
            $conProductor++;
        }
        if (bovs_prod_fundo_tiene_productor_activo($conexion, $fundo['id'])) {
            $conProductorActivo++;
            break;
        }
    }

    return ($conProductor > 0 && $conProductorActivo === 0);
}

/**
 * Inhabilita a los ayudantes que dependen de un productor recién
 * inhabilitado. Se debe llamar DESPUÉS de guardar el productor con
 * status = 0 (si no, se vería a sí mismo como productor activo).
 *
 * @return array<int, array{id:int, username:string, fundo:int}>
 */
function bovs_prod_inhabilitar_ayudantes($conexion, $productorId, $actorId, $fecha)
{
    $detalle = array();
    $vistos = array();

    foreach (bovs_prod_fundos_de_usuario($conexion, $productorId) as $fundo) {

        foreach (bovs_prod_ayudantes_del_fundo($conexion, $fundo['id'], array($productorId)) as $ayudante) {

            $id = (int) $ayudante['id'];
            if (isset($vistos[$id])) {
                continue; // ya se evaluó en otro fundo de este mismo productor
            }
            $vistos[$id] = true;

            if ((int) $ayudante['status'] === 0) {
                continue; // ya estaba inhabilitado
            }

            // Si le queda algún fundo con productor activo, conserva el acceso.
            if (!bovs_prod_usuario_sin_productor_activo($conexion, $id)) {
                continue;
            }

            $usr = \Musuario::find($id);
            if (!$usr) {
                continue;
            }

            $usr->user_modify = (int) $actorId;
            $usr->updated = $fecha;
            $usr->status = 0;

            if ($usr->save()) {
                $detalle[] = array(
                    'id' => $id,
                    'username' => $ayudante['username'],
                    'fundo' => (int) $fundo['id'],
                );
            }
        }
    }

    return $detalle;
}

/**
 * Reactiva a los ayudantes que se inhabilitaron JUNTO con el productor.
 *
 * Se reconocen por la firma que dejó la inhabilitación: mismo
 * `user_modify` (quien la hizo) y misma fecha `updated` que tenía el
 * productor antes de reactivarse. Así no se reviven cuentas que se
 * inhabilitaron por otros motivos en otra fecha.
 *
 * @param int         $productorId
 * @param int         $actorId        Administrador que reactiva.
 * @param string      $fecha          Fecha 'd-m-Y' del cambio.
 * @param int         $firmaUserModify user_modify que tenía el productor.
 * @param string|null $firmaFecha     updated (Y-m-d) que tenía el productor.
 * @return array<int, array{id:int, username:string}>
 */
function bovs_prod_reactivar_ayudantes($conexion, $productorId, $actorId, $fecha, $firmaUserModify, $firmaFecha)
{
    $detalle = array();

    if (empty($firmaFecha)) {
        return $detalle;
    }

    $valores = array((int) $productorId, (int) $firmaUserModify, (string) $firmaFecha);
    $filas = $conexion->query(
        "SELECT DISTINCT h.id, h.username
           FROM mfundosxusuarios lmio
           JOIN mfundosxusuarios lh ON lh.mfundos_id = lmio.mfundos_id AND lh.status = 1
           JOIN musuarios h ON h.id = lh.musuarios_id
          WHERE lmio.musuarios_id = ?
            AND lmio.status = 1
            AND h.mroles_id = 3
            AND h.status = 0
            AND h.user_modify = ?
            AND h.updated::date = ?::date
       ORDER BY h.id",
        $valores
    )->fetchAll();

    foreach ($filas as $fila) {

        $usr = \Musuario::find((int) $fila['id']);
        if (!$usr) {
            continue;
        }

        $usr->user_modify = (int) $actorId;
        $usr->updated = $fecha;
        $usr->fecha_activacion = $fecha;
        $usr->status = 1;

        if ($usr->save()) {
            $detalle[] = array(
                'id' => (int) $fila['id'],
                'username' => trim((string) $fila['username']),
            );
        }
    }

    return $detalle;
}
