<?php

/**
 * scripts/migracion_fecha_secado.php
 *
 * Recalcula la fecha de secado / destete aplicando la regla única
 * (lib/fecha_secado_helper.php) sobre los datos históricos.
 *
 * USO (siempre primero en simulación):
 *
 *   cd /var/www/html/bovs
 *   php scripts/migracion_fecha_secado.php                 # simulación (no escribe nada)
 *   php scripts/migracion_fecha_secado.php --modulo=nacimientos
 *   php scripts/migracion_fecha_secado.php --fundo=63
 *   php scripts/migracion_fecha_secado.php --aplicar       # escribe en la base
 *
 * OPCIONES:
 *   --modulo=nacimientos|servicios|todos   (por defecto: todos)
 *   --fundo=ID                             Limita a un fundo.
 *   --lote=N                               Filas por transacción (por defecto 500).
 *   --solo-vacios                          Solo llena los f_secado NULL; no toca los existentes.
 *   --sin-sync                             No actualiza updated/version (no fuerza re-sincronización a la PWA).
 *   --aplicar                              Ejecuta los cambios. Sin esta opción es simulación.
 *   --ayuda                                Muestra esta ayuda.
 *
 * QUÉ HACE:
 *   - Nacimientos: f_parto + PBL (proposito 1) o + PPL (proposito 2), con el
 *     período del fundo y fallback global (mfundos_id = 0).
 *   - Servicios: solo gestantes con último parto y aptitud leche/doble propósito.
 *     Si el servicio fue antes de 180 días del parto → servicio + 210; si no,
 *     parto + PPL (y solo si esa fecha es posterior al servicio).
 *   - NUNCA borra una fecha existente cuando la regla ya no aplica: solo informa.
 *   - En modo --aplicar guarda el valor anterior en migracion_fecha_secado_log,
 *     agrupado por un código de lote que permite revertir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se puede ejecutar por consola (CLI).\n");
}

// Los require relativos de config/conexion.php y el directorio de modelos se
// resuelven desde el CWD, igual que cuando el script vive en /controllers.
chdir(__DIR__ . '/../controllers');
require_once '../config/conexion.php';
require_once __DIR__ . '/../lib/fecha_secado_helper.php';

$opciones = getopt('', array('modulo:', 'fundo:', 'lote:', 'aplicar', 'solo-vacios', 'sin-sync', 'ayuda'));

if (isset($opciones['ayuda'])) {
    echo ayuda();
    exit(0);
}

$modulo = isset($opciones['modulo']) ? strtolower(trim((string)$opciones['modulo'])) : 'todos';
if (!in_array($modulo, array('nacimientos', 'servicios', 'todos'), true)) {
    fwrite(STDERR, "ERROR: --modulo debe ser nacimientos, servicios o todos.\n");
    exit(2);
}

$fundo = isset($opciones['fundo']) ? max(0, (int)$opciones['fundo']) : 0;
$tam_lote = isset($opciones['lote']) ? max(1, (int)$opciones['lote']) : 500;
$aplicar = isset($opciones['aplicar']);
$solo_vacios = isset($opciones['solo-vacios']);
$sincronizar = !isset($opciones['sin-sync']);
$lote_id = date('Ymd_His');

$resumen = array(
    'nacimientos' => resumen_vacio(),
    'servicios'   => resumen_vacio(),
);

try {
    $conexion = ActiveRecord\ConnectionManager::get_connection();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: no se pudo conectar a la base de datos: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n";
echo "==============================================================\n";
echo " MIGRACIÓN FECHA DE SECADO / DESTETE\n";
echo "==============================================================\n";
echo " Modo          : " . ($aplicar ? 'APLICAR (escribe en la base)' : 'SIMULACIÓN (no escribe nada)') . "\n";
echo " Módulo        : $modulo\n";
echo " Fundo         : " . ($fundo > 0 ? $fundo : 'todos') . "\n";
echo " Tamaño de lote: $tam_lote\n";
echo " Solo vacíos   : " . ($solo_vacios ? 'sí (no toca fechas existentes)' : 'no') . "\n";
echo " Re-sincronizar: " . ($sincronizar ? 'sí (updated + version)' : 'no') . "\n";
if ($aplicar) {
    echo " Lote          : $lote_id\n";
}
echo "==============================================================\n\n";

if ($aplicar) {
    crear_tabla_log($conexion);
}

try {
    if ($modulo === 'nacimientos' || $modulo === 'todos') {
        $resumen['nacimientos'] = procesar_nacimientos($conexion, $fundo, $tam_lote, $aplicar, $solo_vacios, $sincronizar, $lote_id);
        imprimir_resumen('Nacimientos (mregnacimientos)', $resumen['nacimientos'], $aplicar);
    }

    if ($modulo === 'servicios' || $modulo === 'todos') {
        $resumen['servicios'] = procesar_servicios($conexion, $fundo, $tam_lote, $aplicar, $solo_vacios, $sincronizar, $lote_id);
        imprimir_resumen('Servicios (mregservicios)', $resumen['servicios'], $aplicar);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\nERROR durante la migración: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Los lotes ya confirmados quedaron registrados con el lote $lote_id.\n");
    exit(1);
}

if ($aplicar) {
    echo "\nLote aplicado: $lote_id\n";
    echo "Revertir con:\n";
    echo "  psql ... -v lote=\"$lote_id\" -f scripts/migracion_fecha_secado_revert.sql\n\n";
} else {
    echo "\nSimulación finalizada. Nada se ha modificado.\n";
    echo "Para ejecutar los cambios, repite el comando añadiendo --aplicar\n\n";
}

exit(0);

// ===========================================================================

function ayuda(): string
{
    return <<<TXT
Recalcula la fecha de secado/destete de nacimientos y servicios con la regla única.

  php scripts/migracion_fecha_secado.php [opciones]

Opciones:
  --modulo=nacimientos|servicios|todos  Módulo a procesar (por defecto todos).
  --fundo=ID                            Limita a un fundo.
  --lote=N                              Filas por transacción (por defecto 500).
  --solo-vacios                         Solo llena fechas vacías.
  --sin-sync                            No modifica updated/version.
  --aplicar                             Ejecuta los cambios (sin esto: simulación).
  --ayuda                               Esta ayuda.

Pasos recomendados en producción:
  1. pg_dump de mregnacimientos y mregservicios.
  2. Ejecutar sin --aplicar y revisar los contadores.
  3. Ejecutar con --aplicar.
  4. Verificar con las consultas de scripts/migracion_fecha_secado_verificar.sql.
  5. Si algo no cuadra: scripts/migracion_fecha_secado_revert.sql con el lote.

TXT;
}

function resumen_vacio(): array
{
    return array(
        'revisados'       => 0,
        'iguales'         => 0,
        'cambiados'       => 0,
        'llenados'        => 0,
        'omitidos'        => 0,
        'sin_regla'       => 0,
        'errores'         => 0,
    );
}

function crear_tabla_log($conexion): void
{
    $conexion->query(
        "CREATE TABLE IF NOT EXISTS migracion_fecha_secado_log (
            id               BIGSERIAL PRIMARY KEY,
            lote             VARCHAR(40) NOT NULL,
            tabla            VARCHAR(40) NOT NULL,
            registro_id      INTEGER NOT NULL,
            mfundos_id       INTEGER,
            f_secado_anterior TIMESTAMP,
            f_secado_nuevo   TIMESTAMP,
            regla            VARCHAR(40),
            ejecutado        TIMESTAMP NOT NULL DEFAULT NOW()
        )"
    );
    $conexion->query(
        "CREATE INDEX IF NOT EXISTS idx_mig_secado_log_lote
            ON migracion_fecha_secado_log (lote, tabla, registro_id)"
    );
}

function procesar_nacimientos($conexion, int $fundo, int $tam_lote, bool $aplicar, bool $solo_vacios, bool $sincronizar, string $lote_id): array
{
    $resumen = resumen_vacio();
    $ultimo_id = 0;

    while (true) {
        $sql = "SELECT n.id, n.mfundos_id, n.mtipoganaderias_id, n.proposito,
                       n.produccion_leche, n.f_parto, n.f_secado
                  FROM mregnacimientos n
                 WHERE n.status = 1
                   AND n.f_parto IS NOT NULL
                   AND n.id > ?";
        $parametros = array($ultimo_id);

        if ($fundo > 0) {
            $sql .= " AND n.mfundos_id = ?";
            $parametros[] = $fundo;
        }

        $sql .= " ORDER BY n.id LIMIT " . (int)$tam_lote;

        $sentencia = $conexion->query($sql, $parametros);
        $filas = $sentencia ? $sentencia->fetchAll(PDO::FETCH_ASSOC) : array();
        if (!$filas) {
            break;
        }

        $cambios = array();

        foreach ($filas as $fila) {
            $ultimo_id = (int)$fila['id'];
            $resumen['revisados']++;

            $actual = ($fila['f_secado'] !== null && $fila['f_secado'] !== '')
                ? substr((string)$fila['f_secado'], 0, 10)
                : null;

            $nueva = null;
            $regla = 'NO_APLICA';

            if ((int)$fila['produccion_leche'] === 1) {
                $calculo = bovs_calcular_secado_parto(
                    $fila['f_parto'],
                    (int)$fila['proposito'],
                    (int)$fila['mfundos_id'],
                    (int)$fila['mtipoganaderias_id']
                );

                if ($calculo['ok'] && $calculo['fecha']) {
                    $nueva = $calculo['fecha']->format('Y-m-d');
                    $regla = $calculo['periodo'];
                } else {
                    $regla = 'SIN_PERIODO';
                }
            }

            if ($nueva === null) {
                if ($actual !== null) {
                    // La regla actual no calcula, pero había un valor: no se borra.
                    $resumen['omitidos']++;
                } else {
                    $resumen['sin_regla']++;
                }
                continue;
            }

            if ($actual === $nueva) {
                $resumen['iguales']++;
                continue;
            }

            if ($solo_vacios && $actual !== null) {
                $resumen['omitidos']++;
                continue;
            }

            if ($actual === null) {
                $resumen['llenados']++;
            } else {
                $resumen['cambiados']++;
            }

            $cambios[] = array(
                'id'     => (int)$fila['id'],
                'fundo'  => (int)$fila['mfundos_id'],
                'actual' => $actual,
                'nueva'  => $nueva,
                'regla'  => $regla,
            );
        }

        if ($aplicar && $cambios) {
            aplicar_lote($conexion, 'mregnacimientos', $cambios, $lote_id, $sincronizar, $resumen);
        }
    }

    return $resumen;
}

function procesar_servicios($conexion, int $fundo, int $tam_lote, bool $aplicar, bool $solo_vacios, bool $sincronizar, string $lote_id): array
{
    $resumen = resumen_vacio();
    $ultimo_id = 0;

    while (true) {
        $sql = "SELECT s.id, s.mfundos_id, s.f_servicio, s.f_ultimo_parto, s.f_secado,
                       a.aptitud, a.mtipoganaderias_id
                  FROM mregservicios s
                  LEFT JOIN LATERAL (
                       SELECT am.aptitud, am.mtipoganaderias_id
                         FROM manimales am
                        WHERE am.codigo_rgb_id_animal = s.codigo_rgb_id_animal_hembra
                          AND am.mfundos_id = s.mfundos_id
                          AND am.status = 1
                     ORDER BY am.id DESC
                        LIMIT 1
                  ) a ON true
                 WHERE s.status = 1
                   AND s.medofisiologicos_id = 1
                   AND s.f_ultimo_parto IS NOT NULL
                   AND s.f_servicio IS NOT NULL
                   AND s.id > ?";
        $parametros = array($ultimo_id);

        if ($fundo > 0) {
            $sql .= " AND s.mfundos_id = ?";
            $parametros[] = $fundo;
        }

        $sql .= " ORDER BY s.id LIMIT " . (int)$tam_lote;

        $sentencia = $conexion->query($sql, $parametros);
        $filas = $sentencia ? $sentencia->fetchAll(PDO::FETCH_ASSOC) : array();
        if (!$filas) {
            break;
        }

        $cambios = array();

        foreach ($filas as $fila) {
            $ultimo_id = (int)$fila['id'];
            $resumen['revisados']++;

            $actual = ($fila['f_secado'] !== null && $fila['f_secado'] !== '')
                ? substr((string)$fila['f_secado'], 0, 10)
                : null;

            $calculo = bovs_calcular_secado_servicio(
                $fila['f_servicio'],
                $fila['f_ultimo_parto'],
                (int)$fila['aptitud'],
                (int)$fila['mfundos_id'],
                (int)$fila['mtipoganaderias_id']
            );

            $nueva = $calculo['fecha'] ? $calculo['fecha']->format('Y-m-d') : null;
            $regla = $calculo['regla'];

            if ($nueva === null) {
                if ($actual !== null) {
                    // No se borran fechas históricas (p. ej. animales de aptitud carne).
                    $resumen['omitidos']++;
                } else {
                    $resumen['sin_regla']++;
                }
                continue;
            }

            if ($actual === $nueva) {
                $resumen['iguales']++;
                continue;
            }

            if ($solo_vacios && $actual !== null) {
                $resumen['omitidos']++;
                continue;
            }

            if ($actual === null) {
                $resumen['llenados']++;
            } else {
                $resumen['cambiados']++;
            }

            $cambios[] = array(
                'id'     => (int)$fila['id'],
                'fundo'  => (int)$fila['mfundos_id'],
                'actual' => $actual,
                'nueva'  => $nueva,
                'regla'  => $regla,
            );
        }

        if ($aplicar && $cambios) {
            aplicar_lote($conexion, 'mregservicios', $cambios, $lote_id, $sincronizar, $resumen);
        }
    }

    return $resumen;
}

function aplicar_lote($conexion, string $tabla, array $cambios, string $lote_id, bool $sincronizar, array &$resumen): void
{
    $tabla_sql = ($tabla === 'mregnacimientos') ? 'mregnacimientos' : 'mregservicios';

    try {
        $conexion->transaction();

        foreach ($cambios as $cambio) {
            $conexion->query(
                "INSERT INTO migracion_fecha_secado_log
                    (lote, tabla, registro_id, mfundos_id, f_secado_anterior, f_secado_nuevo, regla)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                array(
                    $lote_id,
                    $tabla_sql,
                    $cambio['id'],
                    $cambio['fundo'],
                    $cambio['actual'],
                    $cambio['nueva'],
                    $cambio['regla'],
                )
            );

            if ($sincronizar) {
                $conexion->query(
                    "UPDATE $tabla_sql
                        SET f_secado = ?,
                            updated = NOW(),
                            version = COALESCE(version, 0) + 1
                      WHERE id = ?",
                    array($cambio['nueva'], $cambio['id'])
                );
            } else {
                $conexion->query(
                    "UPDATE $tabla_sql SET f_secado = ? WHERE id = ?",
                    array($cambio['nueva'], $cambio['id'])
                );
            }
        }

        $conexion->commit();
    } catch (Throwable $e) {
        $conexion->rollback();
        $resumen['errores'] += count($cambios);
        fwrite(STDERR, "ERROR en lote de $tabla_sql: " . $e->getMessage() . "\n");
    }
}

function imprimir_resumen(string $titulo, array $resumen, bool $aplicar): void
{
    printf("--- %s ---\n", $titulo);
    printf("  Revisados ............ %6d\n", $resumen['revisados']);
    printf("  Ya correctos ......... %6d\n", $resumen['iguales']);
    printf("  Fechas cambiadas ..... %6d\n", $resumen['cambiados']);
    printf("  Fechas llenadas ...... %6d\n", $resumen['llenados']);
    printf("  Omítidos (no aplica) . %6d\n", $resumen['omitidos']);
    printf("  Sin período config. .. %6d\n", $resumen['sin_regla']);
    if ($resumen['errores'] > 0) {
        printf("  ERRORES .............. %6d\n", $resumen['errores']);
    }
    printf("  %s\n\n", $aplicar ? 'APLICADO' : 'A calcular con --aplicar');
}
