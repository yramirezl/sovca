<?php

namespace Api;

require_once __DIR__ . '/../../models/RangoReservadoLog.php';

/**
 * Resuelve un código provisional (generado offline por el cliente
 * cuando se quedó sin rango reservado) a un código DEFINITIVO, en el
 * mismo instante en que el registro se guarda por primera vez en el
 * servidor. Nunca debe quedar un código con 'PEND' guardado en la
 * base — ver 'correlativo' en SyncModulos.php.
 *
 * Usa exactamente el mismo mecanismo que la acción "reservar" de
 * rangos_controller.php: la función atómica de PostgreSQL
 * reservar_rango_animal (SELECT FOR UPDATE), pidiendo acá un rango de
 * tamaño 1, y arma el código con el mismo prefijo que ya usa
 * rangos_controller.php ('SOVCA-0<fundo>-0' + número con 2 dígitos).
 * También se registra en rangos_reservados_log, igual que una reserva
 * normal, para no perder trazabilidad de qué número se usó y por qué.
 */
class Correlativos
{
    public static function siguienteAnimal(array $usuario): string
    {
        $conexion = \Musuario::connection();
        $fundoId = $usuario['fundo_id'];

        $resultado = $conexion->query(
            'SELECT * FROM reservar_rango_animal(?, ?)',
            [$fundoId, 1]
        );
        $fila = $resultado->fetch();

        if (!$fila || !isset($fila['desde'])) {
            throw new \RuntimeException(
                'No se pudo reservar un código definitivo para el fundo ' . $fundoId . '.'
            );
        }

        $numero = (int) $fila['desde'];
        $prefijoCodigo = 'SOVCA-0' . $fundoId . '-0';

        $log = new \RangoReservadoLog();
        $log->musuarios_id = $usuario['usuario_id'];
        $log->mfundos_id = $fundoId;
        $log->modulo = 'animales';
        // Se distingue de una reserva de rango pedida explícitamente
        // desde el dispositivo: esta la disparó el propio servidor al
        // resolver un código provisional durante la sincronización.
        $log->dispositivo = $usuario['dispositivo'] . ' (resuelto en sync)';
        $log->desde_numero = $numero;
        $log->hasta_numero = $numero;
        $log->created = date('Y-m-d H:i:s');
        $log->save();

        return $prefijoCodigo . str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
    }
}
