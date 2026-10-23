<?php

/**
 * POST /api/rangos_controller.php
 *
 * Acciones:
 *   - reservar { modulo, cantidad? } (requiere header Authorization)
 *     Reserva un bloque de números correlativos para que un
 *     dispositivo pueda registrar offline y generar de una vez su
 *     código definitivo (BOVS-<fundo>-<n> para animales,
 *     MA-<fundo>-<n> para nacimientos), sin riesgo de duplicados con
 *     otro dispositivo del mismo fundo.
 *
 *     Genérico sobre SyncModulos::config($modulo)['correlativo'] — no
 *     hace falta tocar este archivo para sumar un módulo nuevo con
 *     código reservable, solo agregar su entrada en SyncModulos.php.
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/../models/Musuario.php';
require_once __DIR__ . '/../models/RangoReservadoLog.php';
require_once __DIR__ . '/lib/SyncModulos.php';

$input = leer_json_input();
$action = $input['action'] ?? null;

switch ($action) {

    case 'reservar':
        $usuario = \Api\ApiAuth::requerirAutenticacion($input);

        // MULTIFUNDO: el rango se reserva para el fundo que el cliente
        // indica y el servidor valida (antes siempre era el fundo del
        // token). El resto del archivo sigue leyendo $usuario['fundo_id'].
        $usuario['fundo_id'] = \Api\ApiAuth::requerirFundo($input, $usuario);

        $modulo = $input['modulo'] ?? 'animales';
        $cantidad = (int) ($input['cantidad'] ?? 10);

        // Límites de seguridad: nunca reservar 0, y nunca dejar que un
        // error del cliente se lleve de un tirón todo el correlativo.
        $cantidad = max(1, min($cantidad, 200));

        $config = \Api\SyncModulos::config($modulo);
        $correlativo = $config['correlativo'] ?? null;

        // Solo los módulos que generan su código EN EL DISPOSITIVO
        // (offline) necesitan reservar un rango acá — hoy: animales y
        // nacimientos. Un módulo como servicios, con
        // 'solo_si_provisional' => false, nunca pasa por acá: su
        // código siempre lo asigna el servidor al sincronizar.
        if (!$correlativo || empty($correlativo['solo_si_provisional'])) {
            \Api\ApiResponse::error("El módulo '$modulo' no tiene un rango reservable en este dispositivo.", 422);
        }

        $funcionSql = $correlativo['funcion_sql'];
        $funcionesPermitidas = ['reservar_rango_animal', 'reservar_rango_ternero'];
        if (!in_array($funcionSql, $funcionesPermitidas, true)) {
            \Api\ApiResponse::error('Función de correlativo no reconocida.', 500);
        }

        $conexion = Musuario::connection();
        $valores = [$usuario['fundo_id'], $cantidad];
        // $funcionSql viene de SyncModulos.php y ya se validó contra la
        // lista blanca de arriba — nunca es texto libre del cliente.
        $resultado = $conexion->query("SELECT * FROM {$funcionSql}(?, ?)", $valores);
        $fila = $resultado->fetch();

        if (!$fila) {
            \Api\ApiResponse::error('No se pudo reservar el rango. Intenta de nuevo.', 500);
        }

        $desde = (int) $fila['desde'];
        $hasta = (int) $fila['hasta'];

        $log = new RangoReservadoLog();
        $log->musuarios_id = $usuario['usuario_id'];
        $log->mfundos_id = $usuario['fundo_id'];
        $log->modulo = $modulo;
        $log->dispositivo = $usuario['dispositivo'];
        $log->desde_numero = $desde;
        $log->hasta_numero = $hasta;
        $log->created = date('Y-m-d H:i:s');
        $log->save();

        \Api\ApiResponse::ok([
            'modulo' => $modulo,
            'fundo_id' => $usuario['fundo_id'],
            'desde' => $desde,
            'hasta' => $hasta,
            'cantidad' => $cantidad,
            // El cliente arma el código concatenando este prefijo + el
            // número que le toque usar de este rango.
            'prefijo_codigo' => $correlativo['prefijo'] . '-0' . $usuario['fundo_id'] . '-0',
        ]);
        break;

    default:
        \Api\ApiResponse::error('Acción no reconocida.', 404);
}