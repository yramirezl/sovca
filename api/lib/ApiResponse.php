<?php

namespace Api;

/**
 * Da forma consistente a todas las respuestas JSON de la API, y corta
 * la ejecución igual que hacen los controllers clásicos de la app
 * (echo + fin), para no arrastrar código después del error.
 */
class ApiResponse
{
    public static function ok(array $datos = [], int $codigoHttp = 200): void
    {
        // Solo tiene efecto cuando el controlador abrió un SyncLogger
        // (api/sync_controller.php); en el resto del API es inocuo.
        if (class_exists(__NAMESPACE__ . '\\SyncLogger', false)) {
            SyncLogger::cerrarPeticion();
        }

        http_response_code($codigoHttp);
        echo json_encode(
            array_merge(['resultado' => 'ok'], $datos),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    public static function error(string $mensaje, int $codigoHttp = 400, array $extra = []): void
    {
        if (class_exists(__NAMESPACE__ . '\\SyncLogger', false)) {
            SyncLogger::falloPeticion($mensaje, $codigoHttp, $extra);
        }

        http_response_code($codigoHttp);
        echo json_encode(
            array_merge(['resultado' => 'error', 'mensaje' => $mensaje], $extra),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}
