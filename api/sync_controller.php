<?php

/**
 * POST /api/sync_controller.php
 *
 * Acciones (ambas requieren header Authorization):
 *
 *   - pull { modulo, desde?, dispositivo_id? }
 *       Trae los registros del fundo del usuario con cambios
 *       posteriores a "desde". El cursor es COMPUESTO ("marca|id", por
 *       ejemplo "2026-10-18 11:39:38.889941|553"); también se acepta un
 *       timestamp solo (clientes viejos). Vacío = traer todo la primera
 *       vez. Devuelve como máximo LIMITE_PULL filas por llamada; si
 *       "hay_mas" viene true, hay que volver a llamar con
 *       "desde" = "siguiente_desde" hasta que "hay_mas" sea false.
 *
 *   - push { modulo, cambios: [ { uuid_local, version_base, datos } ], dispositivo_id? }
 *       Aplica una tanda de creaciones/ediciones hechas offline.
 *       Cada cambio se procesa de forma independiente: si uno falla o
 *       tiene conflicto, no bloquea a los demás. La respuesta trae un
 *       resultado por cada uuid_local enviado.
 *
 * Sobre "dispositivo_id": cuando el cliente lo manda, se valida que
 * ese UUID tenga un vínculo activo en usuario_dispositivo_offline
 * para el usuario/fundo actual. Si no lo tiene, se corta la operación
 * con código 'dispositivo_desvinculado' — el cliente debe limpiar la
 * sesión local y avisar al usuario. Si el cliente NO lo manda (versión
 * vieja, o navegador sin localStorage), se omite la validación para no
 * romper compatibilidad.
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/lib/SyncModulos.php';
require_once __DIR__ . '/../lib/fecha_secado_helper.php';
require_once __DIR__ . '/../lib/especie_helper.php';
require_once __DIR__ . '/../lib/edad_hembra_helper.php';
require_once __DIR__ . '/../lib/arete_helper.php';
require_once __DIR__ . '/../models/Musuario.php';
require_once __DIR__ . '/../models/Vusuario.php';
require_once __DIR__ . '/../models/MregnacimientoSync.php';
require_once __DIR__ . '/../models/Mterneroxnacimiento.php';
require_once __DIR__ . '/../models/ManimalSync.php';
require_once __DIR__ . '/../models/Mregservicio.php';
require_once __DIR__ . '/../models/Mlote.php';
require_once __DIR__ . '/../models/Mcontrolsanitarioxlote.php';
require_once __DIR__ . '/../models/Mdetacrlsanitariolote.php';
require_once __DIR__ . '/../models/Mdetacsxlote.php';
require_once __DIR__ . '/../models/Mdesincorporacionanimale.php';
require_once __DIR__ . '/../models/Mmovimientorebano.php';
require_once __DIR__ . '/../models/Mmovimientorebanodetalle.php';
require_once __DIR__ . '/../models/Mpotrero.php';
require_once __DIR__ . '/../models/Mfundo.php';
require_once __DIR__ . '/../models/Mproducto.php';
require_once __DIR__ . '/../models/Mdetacrlsanitario.php';
require_once __DIR__ . '/../models/Mtiposervicio.php';

const LIMITE_PULL = 500;

$input = leer_json_input();
$action = $input['action'] ?? null;
$usuario = \Api\ApiAuth::requerirAutenticacion($input);

// RESUMEN DE SINCRONIZACIONES: se abre el registro antes de atender la
// acción. La fila se escribe en el shutdown, así también quedan anotados
// los rechazos (403/422/500) que cortan la respuesta con ApiResponse.
require_once __DIR__ . '/lib/SyncLogger.php';
\Api\SyncLogger::iniciar($input, $usuario);

try {
    switch ($action) {

        case 'pull':
            accion_pull($input, $usuario);
            break;

        case 'push':
            accion_push($input, $usuario);
            break;

        case 'subir_foto':
            accion_subir_foto($input, $usuario);
            break;

        default:
            \Api\ApiResponse::error('Acción no reconocida.', 404);
    }
} catch (\Throwable $e) {
    // Se anota el mensaje REAL antes de que el manejador global lo
    // convierta en el genérico "Error interno del servidor.".
    \Api\SyncLogger::falloPeticion('Error interno: ' . $e->getMessage(), 500);
    throw $e;
}

// ===========================================================================

function accion_pull(array $input, array $usuario): void
{
    // MULTIFUNDO: el fundo de esta petición lo decide el cliente y lo
    // valida el servidor contra sus accesos activos. Se inyecta en
    // $usuario para que TODO el resto del archivo siga usando
    // $usuario['fundo_id'] exactamente igual que antes.
    $usuario['fundo_id'] = \Api\ApiAuth::requerirFundo($input, $usuario);

    verificar_vinculo_dispositivo($input, $usuario, 'lectura');

    $modulo = $input['modulo'] ?? '';
    $config = \Api\SyncModulos::config($modulo);

    if (!$config) {
        \Api\ApiResponse::error("Módulo '$modulo' no reconocido para sincronización.", 422);
    }

    $desde = trim($input['desde'] ?? '');

    // CURSOR COMPUESTO "marca|id".
    //
    // Con la marca sola se perdían cambios: varias filas pueden tener la
    // MISMA marca (un push en lote escribe varias en el mismo instante) y,
    // sin el id, la última fila del lote podía quedar fuera para siempre.
    // El id desempata sin repetir ni saltear registros. Se acepta el
    // formato viejo (solo timestamp) para no romper equipos que todavía
    // tengan un cursor guardado.
    $desdeMarca = '1970-01-01 00:00:00';
    $desdeId = 0;
    if ($desde !== '') {
        $partes = explode('|', $desde, 2);
        if (!empty($partes[0])) {
            $desdeMarca = $partes[0];
        }
        if (isset($partes[1])) {
            $desdeId = (int) $partes[1];
        }
    }

    $tabla = $config['tabla'];

    // COALESCE(updated, created): un registro creado y nunca editado
    // tiene updated = NULL, pero igual debe sincronizarse. El trigger
    // de scripts/migracion_sync_cursor_updated.sql mantiene "updated"
    // siempre igual o posterior a "created" y siempre hacia adelante.
    $sql = "SELECT * FROM {$tabla}
            WHERE mfundos_id = ?
              AND (COALESCE(updated, created) > ?
                   OR (COALESCE(updated, created) = ? AND id > ?))
            ORDER BY COALESCE(updated, created) ASC, id ASC
            LIMIT " . (LIMITE_PULL + 1);

    $conexion = Musuario::connection();
    $valores = [$usuario['fundo_id'], $desdeMarca, $desdeMarca, $desdeId];
    $resultado = $conexion->query($sql, $valores);
    $filas = $resultado->fetchAll();

    $hayMas = count($filas) > LIMITE_PULL;
    if ($hayMas) {
        array_pop($filas); // sobraba una fila, solo era para detectar "hay más"
    }

    if ($modulo === 'control_sanitario' && $filas) {
        $filas = adjuntar_productos_control_sanitario($filas, (int) $usuario['fundo_id']);
    }

    // Nacimientos: se adjuntan las crías del parto (una o varias) para
    // que el dispositivo muestre el detalle igual que la app web.
    if ($modulo === 'nacimientos' && $filas) {
        $filas = adjuntar_terneros_nacimiento($filas, (int) $usuario['fundo_id']);
    }

    // Control sanitario por lotes: productos aplicados y animales incluidos.
    if ($modulo === 'control_sanitario_lotes' && $filas) {
        $filas = adjuntar_detalle_control_lote($filas, (int) $usuario['fundo_id']);
    }

    // Movimiento de rebaño: animales movidos en cada movimiento.
    if ($modulo === 'movimiento_rebano' && $filas) {
        $filas = adjuntar_animales_movimiento_rebano($filas);
    }

    $siguienteMarca = $desdeMarca;
    $siguienteId = $desdeId;
    foreach ($filas as $fila) {
        $marcaTiempo = $fila['updated'] ?? $fila['created'];
        $idFila = (int) ($fila['id'] ?? 0);
        if ($marcaTiempo > $siguienteMarca
            || ($marcaTiempo === $siguienteMarca && $idFila > $siguienteId)) {
            $siguienteMarca = $marcaTiempo;
            $siguienteId = $idFila;
        }
    }

    // RESUMEN DE SINCRONIZACIONES: un pull vacío es la revisión periódica
    // normal y no se registra (ver SyncLogger::volcar); uno que trae datos
    // deja constancia de qué módulo y cuántos registros bajaron.
    \Api\SyncLogger::anotar([
        'accion'  => 'pull',
        'modulo'  => $modulo,
        'total'   => count($filas),
        'exitos'  => count($filas),
        'mensaje' => 'Descarga de ' . \Api\SyncLogger::etiquetaModulo($modulo)
                   . ': ' . count($filas) . ' registro(s)',
    ]);

    \Api\ApiResponse::ok([
        'modulo' => $modulo,
        'fundo_id' => (int) $usuario['fundo_id'],
        'registros' => $filas,
        'cantidad' => count($filas),
        'hay_mas' => $hayMas,
        'siguiente_desde' => $siguienteMarca . '|' . $siguienteId,
        'servidor_ahora' => date('Y-m-d H:i:s'),
    ]);
}

function accion_push(array $input, array $usuario): void
{
    // MULTIFUNDO: mismo criterio que accion_pull — el fundo viaja en la
    // petición y se valida contra los accesos del usuario.
    $usuario['fundo_id'] = \Api\ApiAuth::requerirFundo($input, $usuario);

    verificar_vinculo_dispositivo($input, $usuario, 'escritura');
    $modulo = $input['modulo'] ?? '';
    $config = \Api\SyncModulos::config($modulo);

    if (!$config) {
        \Api\ApiResponse::error("Módulo '$modulo' no reconocido para sincronización.", 422);
    }

    $cambios = $input['cambios'] ?? [];
    if (!is_array($cambios) || count($cambios) === 0) {
        \Api\ApiResponse::error('No se recibió ningún cambio en "cambios".', 422);
    }
    if (count($cambios) > 200) {
        \Api\ApiResponse::error('Demasiados cambios en un solo lote (máximo 200). Divide el envío.', 422);
    }

    require_once __DIR__ . '/../models/' . $config['modelo'] . '.php';
    $modeloClase = $config['modelo'];

    $resultados = [];
    foreach ($cambios as $cambio) {
        $resultados[] = procesar_cambio($cambio, $config, $modeloClase, $usuario);
    }

    // RESUMEN DE SINCRONIZACIONES: se cuentan los resultados del lote y se
    // guarda el detalle de lo que no entró (registro + motivo), que es lo
    // que la pantalla web muestra como "dónde falló".
    $exitos = 0;
    $conflictos = 0;
    $errores = 0;
    foreach ($resultados as $indice => $resultado) {
        $tipo = isset($resultado['resultado']) ? $resultado['resultado'] : 'error';

        if ($tipo === 'conflicto') {
            $conflictos++;
        } elseif ($tipo === 'error') {
            $errores++;
        } else {
            $exitos++;
        }

        if ($tipo === 'conflicto' || $tipo === 'error') {
            $cambioOrigen = isset($cambios[$indice]) ? $cambios[$indice] : [];
            \Api\SyncLogger::itemFallido([
                'tipo'       => $tipo,
                'modulo'     => $modulo,
                'referencia' => referencia_cambio($cambioOrigen, $modulo),
                'uuid_local' => isset($resultado['uuid_local']) ? $resultado['uuid_local'] : null,
                'mensaje'    => isset($resultado['mensaje']) ? $resultado['mensaje']
                                : ($tipo === 'conflicto' ? 'Conflicto de versión: el registro cambió en el servidor.' : ''),
                'codigo'     => isset($resultado['tipo']) ? $resultado['tipo'] : '',
            ]);
        }
    }

    $mensajeResumen = 'Envío de ' . \Api\SyncLogger::etiquetaModulo($modulo)
        . ': ' . $exitos . ' de ' . count($cambios) . ' registro(s)';
    if ($conflictos > 0) {
        $mensajeResumen .= ' · ' . $conflictos . ' en conflicto';
    }
    if ($errores > 0) {
        $mensajeResumen .= ' · ' . $errores . ' rechazado(s)';
    }

    \Api\SyncLogger::anotar([
        'accion'      => 'push',
        'modulo'      => $modulo,
        'total'       => count($cambios),
        'exitos'      => $exitos,
        'conflictos'  => $conflictos,
        'errores'     => $errores,
        'mensaje'     => $mensajeResumen,
    ]);

    \Api\ApiResponse::ok([
        'modulo' => $modulo,
        'fundo_id' => (int) $usuario['fundo_id'],
        'resultados' => $resultados,
    ]);
}

/**
 * Arma una etiqueta corta para identificar el registro que falló dentro
 * del resumen de sincronizaciones: código del animal, código del
 * servicio, fecha del pesaje, etc. Sin esto, el administrador solo vería
 * un UUID y no sabría QUÉ registro rechazó el servidor.
 */
function referencia_cambio(array $cambio, string $modulo): string
{
    $datos = isset($cambio['datos']) && is_array($cambio['datos']) ? $cambio['datos'] : array();

    $claves = array(
        'codigo_rgb_id_animal',
        'codigo_rgb_id_animal_hembra',
        'codigo_servicio',
        'codigo_lote',
        'codigo',
        'nro_arete',
        'nombre',
    );

    foreach ($claves as $clave) {
        if (!empty($datos[$clave]) && is_scalar($datos[$clave])) {
            return \Api\SyncLogger::etiquetaModulo($modulo) . ' · ' . (string) $datos[$clave];
        }
    }

    if (!empty($datos['animales']) && is_array($datos['animales'])) {
        $animales = array_slice(array_filter($datos['animales'], 'is_scalar'), 0, 3);
        if ($animales) {
            return \Api\SyncLogger::etiquetaModulo($modulo) . ' · ' . implode(', ', $animales);
        }
    }

    foreach (array('fecha', 'fecha_pesaje', 'fecha_control', 'fecha_servicio', 'fecha_movimiento') as $clave) {
        if (!empty($datos[$clave]) && is_scalar($datos[$clave])) {
            return \Api\SyncLogger::etiquetaModulo($modulo) . ' · ' . (string) $datos[$clave];
        }
    }

    return \Api\SyncLogger::etiquetaModulo($modulo);
}

/**
 * Sube (o reemplaza) la foto de un animal tomada/adjuntada desde la app
 * offline.
 *
 * Se hace EXACTAMENTE lo mismo que la app web (ver
 * controllers/manimales_controller.php), para que la foto que se toma
 * en el corral se vea igual desde el navegador de escritorio:
 *
 *   1. El cliente manda la foto ya comprimida como data URI (JPEG).
 *   2. bovs_imagen_procesar() la redimensiona a 800 px de lado mayor y
 *      la recomprime a WebP (JPEG si el GD del servidor no trae WebP).
 *   3. El binario queda en dist/img/fotos_animales/ y en
 *      manimales.imagen SOLO se guarda el nombre del archivo (columna
 *      varchar(100), igual que la web).
 *
 * El nombre final es el código del animal, así que subir otra foto del
 * mismo animal reemplaza la anterior y borra el archivo viejo.
 */
function accion_subir_foto(array $input, array $usuario): void
{
    $usuario['fundo_id'] = \Api\ApiAuth::requerirFundo($input, $usuario);
    verificar_vinculo_dispositivo($input, $usuario, 'escritura');

    $codigo = trim((string) ($input['codigo_rgb_id_animal'] ?? ''));
    if ($codigo === '') {
        \Api\ApiResponse::error('Falta el código del animal.', 422);
    }

    $dataUri = (string) ($input['imagen_base64'] ?? '');
    if (stripos($dataUri, 'data:image/') !== 0) {
        \Api\ApiResponse::error('La imagen no llegó en formato data URI válido.', 422);
    }
    // Tope de seguridad ANTES de decodificar: base64 ocupa ~33 % más que
    // el binario, así que 8 MB de cadena equivalen a ~5 MB de imagen.
    if (strlen($dataUri) > 8 * 1024 * 1024) {
        \Api\ApiResponse::error('La imagen es demasiado grande (máximo 5 MB).', 413);
    }

    require_once __DIR__ . '/../models/ManimalSync.php';
    $animal = ManimalSync::find('first', [
        'conditions' => [
            'codigo_rgb_id_animal = ? AND mfundos_id = ?',
            $codigo,
            (int) $usuario['fundo_id'],
        ],
    ]);
    if (!$animal) {
        \Api\ApiResponse::error("No existe el animal '$codigo' en este fundo.", 404);
    }

    require_once __DIR__ . '/../lib/imagen_helper.php';
    $directorio = __DIR__ . '/../dist/img/fotos_animales';
    $nombreBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codigo);
    $anterior = trim((string) $animal->imagen);

    $archivo = bovs_imagen_procesar($dataUri, $directorio, $nombreBase);
    if ($archivo === null) {
        \Api\ApiResponse::error(
            'No se pudo procesar la imagen. Verifique que GD esté activo y que dist/img/fotos_animales tenga permisos de escritura.',
            500
        );
    }
    // La columna solo admite 100 caracteres: con el código del animal
    // nunca se llega cerca, pero se valida igual para no truncar nada.
    if (strlen($archivo) > 100) {
        @unlink(rtrim($directorio, '/') . '/' . $archivo);
        \Api\ApiResponse::error('El nombre del archivo de la imagen es demasiado largo.', 422);
    }

    // SQL directo: se escribe "imagen" y se avanza la versión para que el
    // resto de los equipos (y la web) reciban la foto en el próximo pull.
    // Se usa SQL y no $animal->save() por la misma razón que el resto del
    // archivo: php-activerecord no maneja bien todos los tipos.
    $conexion = ManimalSync::connection();
    $ok = $conexion->query(
        'UPDATE manimales
            SET imagen = ?, version = COALESCE(version, 0) + 1, updated = NOW(), user_modify = ?
          WHERE id = ?',
        [$archivo, (int) ($usuario['id'] ?? 0), (int) $animal->id]
    );
    if ($ok === false) {
        @unlink(rtrim($directorio, '/') . '/' . $archivo);
        \Api\ApiResponse::error('No se pudo guardar la referencia de la imagen en la base de datos.', 500);
    }

    // Recién ahora se borra la foto anterior: si algo falló antes, el
    // animal se queda con la que ya tenía.
    if ($anterior !== '' && $anterior !== $archivo) {
        bovs_imagen_eliminar($directorio, $anterior);
    }

    $fila = $conexion->query(
        'SELECT imagen, version, updated FROM manimales WHERE id = ?',
        [(int) $animal->id]
    )->fetch();

    \Api\SyncLogger::anotar([
        'accion'  => 'subir_foto',
        'modulo'  => 'fotos',
        'total'   => 1,
        'exitos'  => 1,
        'mensaje' => 'Foto de ' . $codigo . ' subida correctamente',
    ]);

    \Api\ApiResponse::ok([
        'codigo_rgb_id_animal' => $codigo,
        'imagen' => $fila['imagen'],
        'version' => (int) $fila['version'],
        'updated' => $fila['updated'],
        'fundo_id' => (int) $usuario['fundo_id'],
    ]);
}

/**
 * Verifica que el dispositivo que está llamando tenga un vínculo
 * offline activo, si el cliente mandó su dispositivo_id. Si no lo
 * mandó (cliente viejo), se salta la verificación para no romper
 * compatibilidad. Si el vínculo no existe (o está desactivado), corta
 * la operación con un 403 y un código específico que el cliente sabe
 * interpretar para limpiar la sesión local.
 *
 * También actualiza el timestamp de última_sincronizacion — sirve para
 * que la pantalla web muestre "última vez que este dispositivo
 * reportó".
 */
/**
 * Verifica que el dispositivo que está llamando tenga un vínculo
 * offline activo, si el cliente mandó su dispositivo_id. También
 * valida el modo:
 *   - $modoRequerido = 'lectura'  → permite consulta y gestión.
 *   - $modoRequerido = 'escritura' → solo permite gestión.
 *
 * El modo 'consulta' puede hacer pull pero NO push. Esto garantiza
 * que un dispositivo de consulta no pueda generar registros en el
 * servidor, aunque el cliente sea modificado o tenga bugs.
 */
function verificar_vinculo_dispositivo(array $input, array $usuario, string $modoRequerido = 'lectura'): void
{
    $dispositivoId = $input['dispositivo_id'] ?? null;
    if (!$dispositivoId) {
        return; // compatibilidad con clientes viejos
    }

    require_once __DIR__ . '/../models/UsuarioDispositivoOffline.php';

    $vinculo = \UsuarioDispositivoOffline::find('first', [
        'conditions' => [
            'dispositivo_id = ? and musuarios_id = ? and mfundos_id = ? and status = 1',
            $dispositivoId, $usuario['usuario_id'], $usuario['fundo_id'],
        ],
    ]);

    if (!$vinculo) {
        \Api\ApiResponse::error(
            'Este dispositivo fue desvinculado. No se puede sincronizar.',
            403,
            ['codigo' => 'dispositivo_desvinculado']
        );
    }

    $modo = $vinculo->modo_offline ?: 'gestion';

    // El resumen de sincronizaciones muestra el nombre y el modo reales
    // del equipo, tomados del vínculo validado en esta misma petición.
    \Api\SyncLogger::anotar([
        'dispositivo_id'     => $dispositivoId,
        'nombre_dispositivo' => $vinculo->nombre_dispositivo,
        'modo_offline'       => $modo,
        'musuarios_id'       => (int) $vinculo->musuarios_id,
        'mfundos_id'         => (int) $vinculo->mfundos_id,
    ]);

    if ($modoRequerido === 'escritura' && $modo !== 'gestion') {
        \Api\ApiResponse::error(
            'Este dispositivo está en modo CONSULTA y no puede enviar cambios al servidor. '
            . 'Cambiá a modo gestión para registrar datos.',
            403,
            ['codigo' => 'modo_consulta_no_escribe']
        );
    }

    $vinculo->ultima_sincronizacion = date('Y-m-d H:i:s');
    $vinculo->save();
}

/**
 * Aplica un único cambio (creación o edición) y devuelve su resultado.
 * Nunca lanza: cualquier problema se refleja en el arreglo devuelto,
 * para que un error en un registro no tumbe el resto del lote.
 */
function procesar_cambio(array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $uuid = $cambio['uuid_local'] ?? null;

    if (!$uuid || !preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'uuid_local ausente o con formato inválido (se espera UUID v4).',
        ];
    }

    try {
        /** @var \ActiveRecord\Model $existente */
        $existente = $modeloClase::find('first', [
            'conditions' => ['uuid_local = ? and mfundos_id = ?', $uuid, $usuario['fundo_id']],
        ]);

        // ANULACIÓN (borrado lógico) desde el dispositivo. El cliente
        // manda "eliminado: true" en vez de datos; si el registro no
        // existe en el servidor (nunca llegó a subir) se responde
        // "eliminado" igual, para que el equipo se quede tranquilo.
        if (!empty($cambio['eliminado'])) {
            if (!$existente) {
                return ['uuid_local' => $uuid, 'resultado' => 'eliminado'];
            }
            return anular_registro($existente, $cambio, $config, $usuario);
        }

        // REVERSIÓN de un movimiento de rebaño que YA está en el
        // servidor. Los movimientos son de solo creación, así que este
        // pedido se atiende antes del bloqueo general.
        if (!empty($cambio['revertir'])) {
            if (!$existente) {
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'Este movimiento todavía no está en el servidor: se puede deshacer en el equipo.',
                ];
            }
            return revertir_movimiento_rebano($existente, $cambio, $config, $usuario);
        }

        if ($existente) {
            // Algunos módulos son de solo creación desde el dispositivo
            // (una baja o un movimiento no se editan en el campo).
            if (!empty($config['solo_crear'])) {
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'Este registro no se puede modificar desde el dispositivo.',
                ];
            }

            return actualizar_registro($existente, $cambio, $config, $usuario);
        }

        return crear_registro($uuid, $cambio, $config, $modeloClase, $usuario);

    } catch (\Throwable $e) {
        error_log('[SYNC PUSH] ' . $e->getMessage());


        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => mensaje_error_guardado($e),
        ];
    }
}

/**
 * Traduce el error de la base a un mensaje accionable para el equipo,
 * sin mostrarle el SQL al productor. El detalle técnico completo queda
 * en el log del servidor (`[SYNC PUSH]` / `[SYNC servicios]`).
 *
 * Antes, cualquier falla dentro de crear_registro_servicio se reportaba
 * como "No se pudo registrar el servicio. Verifica los datos de la
 * hembra y el macho", que mandaba a revisar animales cuando el problema
 * real era, por ejemplo, un campo del seguimiento en null.
 */
function mensaje_error_guardado(\Throwable $e): string
{
    $detalle = $e->getMessage();

    if (preg_match('/null value in column "([a-z_0-9]+)"/i', $detalle, $m)) {
        return 'Falta un dato obligatorio («' . $m[1] . '») en este registro. '
             . 'Complételo en el equipo y vuelva a sincronizar.';
    }
    if (preg_match('/value too long for type character varying\((\d+)\)/i', $detalle, $m)) {
        return 'Un dato de este registro es más largo de lo que acepta el servidor (máximo '
             . $m[1] . ' caracteres). Corríjalo en el equipo y vuelva a sincronizar.';
    }
    if (stripos($detalle, 'violates foreign key constraint') !== false) {
        return 'Este registro usa un dato que no existe en el servidor (animal, tipo de servicio o raza). '
             . 'Revíselo en el equipo y vuelva a sincronizar.';
    }
    if (stripos($detalle, 'duplicate key value') !== false) {
        if (stripos($detalle, 'Código del Ternero') !== false) {
            return 'El servidor ya tiene otro nacimiento con ese código de ternero. '
                 . 'El equipo volverá a intentarlo con el siguiente número; si se repite, '
                 . 'normalice el contador de terneros del fundo (scripts/migracion_correlativos_contador.sql).';
        }
        return 'Este registro ya existe en el servidor con los mismos datos. '
             . 'Actualice el equipo y vuelva a intentarlo.';
    }

    if (strpos($detalle, 'correlativo_sin_codigo_libre') !== false) {
        return 'No quedan códigos libres para este registro en el servidor. '
             . 'Avisá al administrador: hay que normalizar los correlativos del fundo.';
    }

    return 'El servidor no pudo guardar este registro. '
         . 'Revise los datos en el equipo y vuelva a sincronizar; si persiste, contacte al administrador.';
}

/**
 * REVERTIR un movimiento de rebaño ya sincronizado.
 *
 * Hace exactamente lo que el botón "Revertir" de la web
 * (`movreb_controller.php`, case `revertir_movimiento`): devuelve cada
 * animal a su lote/potrero de origen y deja la auditoría en el propio
 * movimiento (`revertido`, `fecha_reversion`, `motivo_reversion`,
 * `user_reversion`).
 *
 * Se usa SQL directo para el movimiento porque php-activerecord no
 * escribe bien las columnas booleanas.
 */
function revertir_movimiento_rebano($existente, array $cambio, array $config, array $usuario): array
{
    $uuid = $existente->uuid_local;

    if ((string) ($config['tabla'] ?? '') !== 'mmovimientos_rebano') {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'Este tipo de registro no se puede revertir desde el dispositivo.',
        ];
    }

    // Idempotencia: ya revertido, no se vuelve a tocar.
    if (!empty($existente->revertido)) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'revertido',
            'version' => (int) $existente->version,
            'updated' => $existente->updated,
        ];
    }

    $versionBase = $cambio['version_base'] ?? null;
    $versionActual = (int) $existente->version;

    if ($versionBase !== null && (int) $versionBase !== $versionActual) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'conflicto',
            'tipo' => 'version',
            'mensaje' => 'El movimiento cambió en el servidor después de tu última sincronización.',
            'version_servidor' => $versionActual,
            'servidor' => $existente->to_array(),
            'tuyo' => [],
        ];
    }

    $motivo = trim((string) ($cambio['motivo_reversion'] ?? ''));
    if (mb_strlen($motivo) < 10) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'El motivo de la reversión debe tener al menos 10 caracteres.',
        ];
    }

    $conexion = Musuario::connection();
    $fundoId = (int) $usuario['fundo_id'];
    $usuarioId = (int) $usuario['usuario_id'];
    $movimientoId = (int) $existente->id;
    $tipo = strtoupper((string) $existente->tipo_movimiento);
    $usaLote = in_array($tipo, ['LOTE', 'AMBOS'], true);
    $usaPotrero = in_array($tipo, ['POTRERO', 'AMBOS'], true);

    try {
        $conexion->transaction();

        $detalles = $conexion->query(
            'SELECT manimales_id, lote_anterior_id, potrero_anterior_id
             FROM mmovimientos_rebano_detalle
             WHERE mmovimientos_rebano_id = ?',
            [$movimientoId]
        )->fetchAll();

        $devueltos = 0;
        foreach ($detalles as $detalle) {
            $asignaciones = [];
            $valores = [];
            if ($usaLote) {
                $asignaciones[] = 'mlotes_id = ?';
                $valores[] = $detalle['lote_anterior_id'] !== null ? (int) $detalle['lote_anterior_id'] : null;
            }
            if ($usaPotrero) {
                $asignaciones[] = 'mpotreros_id = ?';
                $valores[] = $detalle['potrero_anterior_id'] !== null ? (int) $detalle['potrero_anterior_id'] : null;
            }
            if (!$asignaciones) {
                continue;
            }

            $valores[] = $usuarioId;
            $valores[] = date('Y-m-d');
            $valores[] = (int) $detalle['manimales_id'];
            $valores[] = $fundoId;

            $conexion->query(
                'UPDATE manimales SET ' . implode(', ', $asignaciones)
                . ', user_modify = ?, updated = ? WHERE id = ? AND mfundos_id = ?',
                $valores
            );
            $devueltos++;
        }

        $conexion->query(
            'UPDATE mmovimientos_rebano
             SET revertido = TRUE,
                 fecha_reversion = NOW(),
                 motivo_reversion = ?,
                 user_reversion = ?,
                 version = version + 1,
                 updated = NOW()
             WHERE id = ? AND mfundos_id = ?',
            [mb_substr($motivo, 0, 250), $usuarioId, $movimientoId, $fundoId]
        );

        $conexion->commit();
    } catch (\Throwable $e) {
        try {
            $conexion->rollBack();
        } catch (\Throwable $ignorado) {
            // nada que hacer: el error original es el que importa
        }
        error_log('[SYNC revertir movimiento] ' . $e->getMessage());
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'No se pudo revertir el movimiento: ' . $e->getMessage(),
        ];
    }

    return [
        'uuid_local' => $uuid,
        'resultado' => 'revertido',
        'version' => $versionActual + 1,
        'animales_devueltos' => $devueltos,
        'fecha_reversion' => date('Y-m-d H:i:s'),
    ];
}

/**
 * ANULAR un registro ya sincronizado (borrado lógico).
 *
 * La web elimina con `status = 0`; la app offline hace exactamente lo
 * mismo para que el dato desaparezca de los listados y reportes de
 * ambos lados sin perder la trazabilidad (el registro sigue en la BD).
 *
 * Reglas:
 *  - Solo módulos donde la web permite eliminar (leche, pesajes,
 *    controles sanitarios individuales y servicios).
 *  - Se respeta la versión: si el servidor cambió el registro después
 *    de la última sincronización, se devuelve conflicto para que la
 *    persona decida (igual que en una edición).
 *  - Un servicio que ya tiene un parto registrado no se anula, tal
 *    como lo bloquea el sistema web.
 */
function anular_registro($existente, array $cambio, array $config, array $usuario): array
{
    $uuid = $existente->uuid_local;
    $tabla = (string) ($config['tabla'] ?? '');

    $tablasAnulables = [
        'mprolecheras'       => 'producción de leche',
        'mconsecutivopesos'  => 'pesaje',
        'mcontrolsanitarios' => 'control sanitario',
        'mregservicios'      => 'servicio',
    ];

    if (!isset($tablasAnulables[$tabla])) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'Este tipo de registro no se puede eliminar desde el dispositivo.',
        ];
    }

    // Idempotencia: si ya estaba anulado, no se vuelve a tocar.
    if ((int) $existente->status === 0) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'eliminado',
            'version' => (int) $existente->version,
            'updated' => $existente->updated,
        ];
    }

    $versionBase = $cambio['version_base'] ?? null;
    $versionActual = (int) $existente->version;

    if ($versionBase !== null && (int) $versionBase !== $versionActual) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'conflicto',
            'tipo' => 'version',
            'mensaje' => 'El registro cambió en el servidor después de tu última sincronización.',
            'version_servidor' => $versionActual,
            'servidor' => $existente->to_array(),
            'tuyo' => $cambio['datos'] ?? [],
        ];
    }

    // Regla de la web (mservicios_controller, case 'delete'): un
    // servicio que ya tiene parto registrado no se elimina.
    if ($tabla === 'mregservicios' && (int) $existente->parto === 1) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'Este servicio ya tiene un parto registrado: no se puede eliminar.',
        ];
    }

    $existente->status = 0;
    $existente->version = $versionActual + 1;
    $existente->updated = date('Y-m-d H:i:s');
    $existente->user_modify = $usuario['usuario_id'];

    if (!$existente->save()) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'No se pudo eliminar el ' . $tablasAnulables[$tabla] . ': ' . primer_error_validacion($existente),
        ];
    }

    return [
        'uuid_local' => $uuid,
        'resultado' => 'eliminado',
        'version' => (int) $existente->version,
        'updated' => $existente->updated,
    ];
}

/**
 * Duplicados por ANIMAL + FECHA.
 *
 * La web rechaza un segundo registro del mismo animal en la misma
 * fecha en producción de leche, pesaje y control sanitario. La API no
 * lo hacía, así que un dispositivo offline podía insertar duplicados
 * que la web nunca habría aceptado (dos tomas de leche del mismo día,
 * dos pesajes, dos controles).
 *
 * @return string|null Mensaje de error, o null si no hay duplicado.
 */
function validar_duplicado_por_fecha(
    array $config,
    array $datos,
    array $usuario,
    ?int $idExcluir = null
): ?string {
    $reglas = [
        // OJO: producción de leche guarda el animal en
        // 'codigo_rgb_id_animal_hembra' (no 'codigo_rgb_id_animal').
        'mprolecheras' => [
            'animal' => 'codigo_rgb_id_animal_hembra',
            'fecha' => 'fecha_produccion',
            'etiqueta' => 'producción de leche',
        ],
        'mconsecutivopesos' => [
            'animal' => 'codigo_rgb_id_animal',
            'fecha' => 'fecha_pesaje',
            'etiqueta' => 'pesaje',
        ],
        'mcontrolsanitarios' => [
            'animal' => 'codigo_rgb_id_animal',
            'fecha' => 'fecha_control',
            'etiqueta' => 'control sanitario',
        ],
    ];

    $tabla = (string) ($config['tabla'] ?? '');
    if (!isset($reglas[$tabla])) {
        return null;
    }
    $regla = $reglas[$tabla];

    $codigo = strtoupper(trim((string) ($datos[$regla['animal']] ?? '')));
    $fecha = substr(trim((string) ($datos[$regla['fecha']] ?? '')), 0, 10);
    if ($codigo === '' || $fecha === '') {
        return null; // los campos obligatorios ya se validaron aparte
    }

    $sql = "SELECT id FROM {$tabla}
            WHERE mfundos_id = ?
              AND status = 1
              AND UPPER(TRIM({$regla['animal']})) = ?
              AND CAST({$regla['fecha']} AS date) = CAST(? AS date)";
    $parametros = [(int) $usuario['fundo_id'], $codigo, $fecha];

    if ($idExcluir) {
        $sql .= ' AND id <> ?';
        $parametros[] = $idExcluir;
    }
    $sql .= ' LIMIT 1';

    try {
        $existente = Musuario::connection()->query($sql, $parametros)->fetch();
    } catch (\Throwable $e) {
        // Un problema al comprobar no debe impedir guardar: se deja
        // pasar y queda registrado para revisión.
        error_log('[SYNC duplicado] ' . $e->getMessage());
        return null;
    }
    if (!$existente) {
        return null;
    }

    $fechaLegible = date('d-m-Y', strtotime($fecha));
    return 'El animal ' . $codigo . ' ya tiene un ' . $regla['etiqueta']
        . ' registrado para el ' . $fechaLegible . '.';
}

/**
 * Crea un registro nuevo. Delega a crear_registro_servicio() cuando el
 * módulo lo requiere (ver 'usa_ciclo_reproductivo' en SyncModulos.php)
 * — el resto de los módulos usa este camino genérico.
 */
function crear_registro(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    if (!empty($config['usa_ciclo_reproductivo'])) {
        return crear_registro_servicio($uuid, $cambio, $config, $modeloClase, $usuario);
    }

    if (!empty($config['calcula_nacimiento'])) {
        return crear_registro_nacimiento($uuid, $cambio, $config, $modeloClase, $usuario);
    }

    if (!empty($config['calcula_control_lote'])) {
        return crear_control_lote($uuid, $cambio, $config, $modeloClase, $usuario);
    }

    if (!empty($config['desincorpora_animal'])) {
        return crear_desincorporacion($uuid, $cambio, $config, $modeloClase, $usuario);
    }

    if (!empty($config['crea_movimiento_rebano'])) {
        return crear_movimiento_rebano($uuid, $cambio, $config, $modeloClase, $usuario);
    }

    $datos = \Api\SyncModulos::filtrarCampos($cambio['datos'] ?? [], $config['campos_editables']);
    $datos = formatear_campos_especiales($datos);
    quitar_valores_nulos($datos);
    $nacimientoFicha = null;
    $nacimientoFichaNroTernero = null;
    if (($config['tabla'] ?? '') === 'manimales' && !empty($cambio['nacimiento_uuid_local'])) {
        $nacimientoFicha = \MregnacimientoSync::find('first', [
            'conditions' => [
                'uuid_local = ? and mfundos_id = ? and status = 1',
                (string) $cambio['nacimiento_uuid_local'], (int) $usuario['fundo_id'],
            ],
        ]);
        if (!$nacimientoFicha) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'No se encontró el nacimiento asociado a esta ficha.'];
        }

        $nroTerneroRecibido = $cambio['nacimiento_nro_cria'] ?? null;
        $nacimientoFichaNroTernero = $nroTerneroRecibido === null || $nroTerneroRecibido === ''
            ? 1
            : filter_var($nroTerneroRecibido, FILTER_VALIDATE_INT);
        if ($nacimientoFichaNroTernero === false || $nacimientoFichaNroTernero < 1) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El número de cría indicado no es válido.'];
        }
        $detalleTernero = \Mterneroxnacimiento::find('first', [
            'conditions' => [
                'mregnacimientos_id = ? and mfundos_id = ? and nro_ternero = ? and status = 1',
                (int) $nacimientoFicha->id, (int) $usuario['fundo_id'], $nacimientoFichaNroTernero,
            ],
        ]);
        if (!$detalleTernero) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'No se encontró la cría indicada dentro del nacimiento.'];
        }
        if ((int) $detalleTernero->estatus_nacido === 2) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'No se puede fichar una cría nacida muerta.'];
        }

        $cantidadCrias = Musuario::connection()->query(
            'SELECT COUNT(*) AS n FROM mternerosxnacimientos WHERE mregnacimientos_id = ? AND mfundos_id = ? AND status = 1',
            [(int) $nacimientoFicha->id, (int) $usuario['fundo_id']]
        )->fetch();
        if ((int) ($cantidadCrias['n'] ?? 0) > 1) {
            $criasVivasSinId = Musuario::connection()->query(
                "SELECT COUNT(*) AS n FROM mternerosxnacimientos
                 WHERE mregnacimientos_id = ? AND mfundos_id = ? AND status = 1
                   AND estatus_nacido <> 2 AND NULLIF(TRIM(id_ternero), '') IS NULL",
                [(int) $nacimientoFicha->id, (int) $usuario['fundo_id']]
            )->fetch();
            if ((int) ($criasVivasSinId['n'] ?? 0) > 0) {
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Asigne un ID/arete a cada cría viva antes de fichar un parto múltiple.'];
            }
        }
        $codigoCria = trim((string) $detalleTernero->id_ternero);
        if ($codigoCria === '' && (int) ($cantidadCrias['n'] ?? 0) > 1) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Asigne un ID/arete a cada cría antes de fichar un parto múltiple.'];
        }
        $datos['codigo_ternero'] = $codigoCria !== '' ? $codigoCria : (string) $nacimientoFicha->codigo_ternero;
        if ($datos['codigo_ternero'] === '') {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'La cría no tiene un ID/arete o código para asociar su ficha.'];
        }

        $fichaExistente = \ManimalSync::find('first', [
            'conditions' => [
                'codigo_ternero = ? and mfundos_id = ? and status = 1',
                $datos['codigo_ternero'], (int) $usuario['fundo_id'],
            ],
        ]);
        if ($fichaExistente) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Esta cría ya tiene una ficha de animal.'];
        }
    }
    $productosAplicados = [];
    if (!empty($config['sincroniza_productos_aplicados'])) {
        [$productosAplicados, $errorProductos] = validar_productos_control_sanitario(
            $cambio['datos']['productos_aplicados'] ?? [],
            (int) $usuario['fundo_id']
        );
        if ($errorProductos) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductos];
        }
    }

    $errorCampos = validar_campos_requeridos($datos, $config['campos_requeridos']);
    if ($errorCampos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCampos];
    }

    $errorProvisional = rechazar_codigos_provisionales($datos, $config);
    if ($errorProvisional) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProvisional];
    }

    // Un segundo registro del mismo animal en la misma fecha: misma
    // regla que aplica la web al guardar.
    $errorDuplicado = validar_duplicado_por_fecha($config, $datos, $usuario);
    if ($errorDuplicado) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorDuplicado];
    }

    // Unicidad (nro_arete en animales): el cliente ya la valida
    // localmente contra IndexedDB, pero dos dispositivos offline
    // pudieron registrar el mismo arete sin haberse visto. Este es el
    // único punto donde se puede detectar la colisión real — si no se
    // valida acá, el segundo dispositivo mete un animal con el mismo
    // arete que ya existe y nadie se entera. Se devuelve como
    // 'conflicto' (no como 'error') para que el cliente lo rutee por
    // la pantalla de conflictos y la persona decida qué hacer.
    $conflictoUnicidad = validar_campos_unicos($datos, $config, $usuario, $modeloClase);
    if ($conflictoUnicidad) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'conflicto',
            'tipo' => $conflictoUnicidad['tipo'],
            'campo' => $conflictoUnicidad['campo'],
            'mensaje' => 'El valor de ' . $conflictoUnicidad['campo'] . ' ya está registrado en otro registro activo de este fundo.',
            'servidor' => $conflictoUnicidad['existente'],
            'tuyo' => $datos,
        ];
    }

    completar_textos_obligatorios($datos, $config['campos_texto_obligatorios_bd']);
    completar_valores_por_defecto($datos, $config['valores_por_defecto'] ?? []);
    completar_enteros_con_defecto($datos, $config['enteros_con_defecto'] ?? []);

    if (!$nacimientoFicha && ($config['tabla'] ?? '') === 'manimales' && !empty($datos['codigo_ternero'])) {
        $nacimientoFicha = \MregnacimientoSync::find('first', [
            'conditions' => [
                'codigo_ternero = ? and mfundos_id = ? and status = 1',
                $datos['codigo_ternero'], $usuario['fundo_id'],
            ],
        ]);
        if ($nacimientoFicha && $nacimientoFicha->fichado) {
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Este nacimiento ya tiene una ficha de animal.',
            ];
        }
    }

    // Si el módulo genera su propio código correlativo (animales,
    // servicios), se resuelve aquí — ver resolver_correlativo().
    [$datos, $codigoAnterior] = resolver_correlativo($datos, $config, $usuario);

    $registro = new $modeloClase();
    foreach ($datos as $campo => $valor) {
        $registro->$campo = normalizar_valor($valor);
    }

    $registro->uuid_local = $uuid;
    $registro->mfundos_id = $usuario['fundo_id'];
    $registro->status = 1;
    $registro->version = 1;
    $registro->created = date('Y-m-d H:i:s');
    $registro->user_create = $usuario['usuario_id'];
    $registro->origen_dispositivo = $usuario['dispositivo'];

    // Algunos módulos (hoy: animales, nacimientos) necesitan crear
    // además una fila de relación en otra tabla (ej. manimalxfundos,
    // mnacimientoxfundos) para que el resto del sistema — conteos de
    // plan, reportes — vea el registro igual que si viniera de la app
    // web original. Ambos guardados van en una sola transacción: si
    // el segundo falla, el primero se deshace, en vez de dejar un
    // registro "a medias".
    //
    // OJO: es transaction() (API de PHP ActiveRecord), no
    // beginTransaction() (API de PDO crudo). Usar el segundo rompe
    // con "Call to undefined method" porque el wrapper no lo expone.
    $configRelacion = $config['crea_relacion_fundo'] ?? null;
    $sincronizaProductos = !empty($config['sincroniza_productos_aplicados']);
    $transaccionNecesaria = $configRelacion || $sincronizaProductos;
    $conexion = $transaccionNecesaria ? Musuario::connection() : null;

    if ($configRelacion) {
        require_once __DIR__ . '/../models/' . $configRelacion['modelo'] . '.php';
    }
    if ($transaccionNecesaria) {
        $conexion->transaction();
    }

    try {
        if (!$registro->save()) {
            if ($transaccionNecesaria) $conexion->rollBack();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        if ($configRelacion) {
            // Mismo patrón exacto que usa la app web original al
            // registrar (ver manimales_controller.php /
            // mnacimientos_controller.php, acción 'add'): el id del
            // registro recién creado, mfundos_id y user_create —
            // nada más.
            $claseRelacion = $configRelacion['modelo'];
            $campoId = $configRelacion['campo_id_relacionado'];

            $relacion = new $claseRelacion();
            $relacion->$campoId = (int) $registro->id;
            $relacion->mfundos_id = $usuario['fundo_id'];
            $relacion->user_create = $usuario['usuario_id'];

            if (!$relacion->save()) {
                $conexion->rollBack();
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'No se pudo crear la relación con el fundo: ' . primer_error_validacion($relacion),
                ];
            }
        }

        if ($nacimientoFicha) {
            $nacimientoFicha->fichado = true;
            $nacimientoFicha->updated = date('Y-m-d H:i:s');
            $nacimientoFicha->version = (int) $nacimientoFicha->version + 1;
            $nacimientoFicha->user_modify = $usuario['usuario_id'];
            if (!$nacimientoFicha->save()) {
                $conexion->rollBack();
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'No se pudo marcar el nacimiento como fichado.'];
            }
        }

        if ($sincronizaProductos) {
            $errorProductos = guardar_productos_control_sanitario(
                (int) $registro->id,
                $productosAplicados,
                $usuario
            );
            if ($errorProductos) {
                $conexion->rollBack();
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductos];
            }
        }

        if ($transaccionNecesaria) $conexion->commit();

    } catch (\Throwable $e) {
        if ($transaccionNecesaria) $conexion->rollBack();

        /* Si dos equipos subieron el mismo arete casi al mismo tiempo, la
           validación previa no lo vio pero el índice único de la base sí.
           Se devuelve como conflicto para que el operario lo resuelva. */
        $conflictoArete = conflicto_arete_por_indice($e, $datos, $usuario, $uuid);
        if ($conflictoArete) return $conflictoArete;

        throw $e; // lo maneja el catch genérico de procesar_cambio()
    }

    $resultado = [
        'uuid_local' => $uuid,
        'resultado' => 'creado',
        'id' => (int) $registro->id,
        'version' => (int) $registro->version,
        'updated' => $registro->updated,
        'created' => $registro->created,
    ];

    // El cliente usa este par para "corregir" localmente cualquier
    // otro registro (de este u otro módulo) que hubiera guardado el
    // código provisional viejo — por ejemplo, un control sanitario
    // creado offline sobre un animal que todavía no tenía número.
    if ($codigoAnterior !== null) {
        $campoCodigo = $config['correlativo']['campo'];
        $resultado['codigo_anterior'] = $codigoAnterior;
        $resultado['campos_resueltos'] = [$campoCodigo => $datos[$campoCodigo]];
    }
    if ($nacimientoFicha) {
        $resultado['nacimiento_fichado_uuid_local'] = (string) $nacimientoFicha->uuid_local;
        $resultado['nacimiento_fichado'] = [
            'uuid_local' => (string) $nacimientoFicha->uuid_local,
            'version' => (int) $nacimientoFicha->version,
            'updated' => $nacimientoFicha->updated,
        ];
        if ($nacimientoFichaNroTernero !== null) {
            $resultado['nacimiento_cria_fichada'] = [
                'nro_ternero' => $nacimientoFichaNroTernero,
                'codigo_ternero' => $datos['codigo_ternero'],
            ];
        }
    }

    return $resultado;
}

/**
 * Camino especial para 'servicios': además del correlativo (que aquí
 * SIEMPRE lo asigna el servidor, ver SyncModulos.php), hay que decidir
 * el ciclo_reproductivo de la hembra. Va en una transacción explícita
 * — no en autocommit — para que dos dispositivos registrando un
 * servicio de la MISMA hembra casi al mismo tiempo no puedan calcularle
 * dos ciclos contradictorios: el primero en llegar toma el lock del
 * contador del fundo (dentro de reservar_rango_servicio) y lo retiene
 * hasta el commit, así que el segundo espera y ve el estado ya
 * actualizado.
 */
function crear_registro_servicio(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $datos = \Api\SyncModulos::filtrarCampos($cambio['datos'] ?? [], $config['campos_editables']);

    /* El equipo manda en null los campos del seguimiento (nro_terneros,
       dnp, f_palpacion, ...) porque todavía no aplican. Un null explícito
       gana sobre el default de la tabla y rompe el INSERT con "null value
       in column": se descartan para que la base use su valor por defecto. */
    quitar_valores_nulos($datos);

    $errorCampos = validar_campos_requeridos($datos, $config['campos_requeridos']);
    if ($errorCampos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCampos];
    }

    $errorProvisional = rechazar_codigos_provisionales($datos, $config);
    if ($errorProvisional) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProvisional];
    }

    completar_textos_obligatorios($datos, $config['campos_texto_obligatorios_bd']);
    completar_enteros_con_defecto($datos, $config['enteros_con_defecto'] ?? []);

    $conexion = Musuario::connection();

    try {
        $conexion->transaction();

        [$datos, $errorValidacion] = validar_y_completar_servicio_nuevo($datos, $usuario, $conexion);
        if ($errorValidacion) {
            $conexion->rollback();
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorValidacion];
        }

        [$datos, ] = resolver_correlativo($datos, $config, $usuario, $conexion);

        $ciclo = (int) $datos['ciclo_reproductivo'];

        $registro = new $modeloClase();
        foreach ($datos as $campo => $valor) {
            $registro->$campo = normalizar_valor($valor);
        }

        $registro->uuid_local = $uuid;
        $registro->mfundos_id = $usuario['fundo_id'];
        $registro->status = 1;
        $registro->version = 1;
        $registro->created = date('Y-m-d H:i:s');
        $registro->user_create = $usuario['usuario_id'];
        $registro->origen_dispositivo = $usuario['dispositivo'];
        if (!$registro->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        $conexion->commit();

        return [
            'uuid_local' => $uuid,
            'resultado' => 'creado',
            'id' => (int) $registro->id,
            'version' => (int) $registro->version,
            'updated' => $registro->updated,
            'created' => $registro->created,
            'campos_resueltos' => [
                'codigo_servicio' => $registro->codigo_servicio,
                'nro_servicio' => (int) $registro->nro_servicio,
                'f_probable_palpacion' => $registro->f_probable_palpacion
                    ? (string) $registro->f_probable_palpacion->format('Y-m-d')
                    : $datos['f_probable_palpacion'],
                'ciclo_reproductivo' => $ciclo,
                'estatus_ciclo' => 1,
                'estado_servicio' => 1,
            ],
        ];

    } catch (\Throwable $e) {
        $conexion->rollback();
        error_log('[SYNC servicios] ' . $e->getMessage());
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => mensaje_error_guardado($e),
        ];
    }
}

function validar_y_completar_servicio_nuevo(array $datos, array $usuario, $conexion): array
{
    $fundoId = (int) $usuario['fundo_id'];
    $tipoServicio = (int) ($datos['mservicios_id'] ?? 0);
    $tipoActivo = Mtiposervicio::find('first', [
        'conditions' => ['id = ? and status = 1', $tipoServicio],
    ]);
    if (!$tipoActivo) {
        return [$datos, 'Selecciona un tipo de servicio válido.'];
    }

    $hembra = $conexion->query(
        'SELECT id, f_nacimiento FROM manimales
         WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?
           AND status = 1 AND mestatus_id = 1 AND sexo = 1
         LIMIT 1 FOR UPDATE',
        [$fundoId, $datos['codigo_rgb_id_animal_hembra']]
    )->fetch();
    if (!$hembra) {
        return [$datos, 'La hembra debe existir, estar activa y pertenecer a este fundo.'];
    }

    if (empty($hembra['f_nacimiento'])) {
        return [$datos, 'La hembra no tiene fecha de nacimiento y no se puede validar su edad.'];
    }
    try {
        $fechaNacimiento = new \DateTimeImmutable((string) $hembra['f_nacimiento']);
    } catch (\Throwable $e) {
        return [$datos, 'La fecha de nacimiento de la hembra no es válida.'];
    }

    /* En ovinos y caprinos la edad mínima son 210 días (≈ 7 meses),
       configurable en Parámetros → Períodos (código EHM). Antes se exigía
       1 año (regla bovina) y se rechazaban hembras aptas de 10 meses. */
    $minimoDiasHembra = bovs_edad_minima_hembra_dias(
        $fundoId,
        (int) ($usuario['tipo_ganaderia_id'] ?? 0)
    );
    $edadDiasHembra = (int) $fechaNacimiento->diff(new \DateTimeImmutable('today'))->days;

    if ($edadDiasHembra < $minimoDiasHembra) {
        return [$datos, 'La hembra debe tener al menos '
            . bovs_edad_meses_desde_dias($minimoDiasHembra) . ' meses ('
            . $minimoDiasHembra . ' días) para registrar un servicio.'];
    }

    if ($tipoServicio === 3) {
        $datos['codigo_rgb_id_animal_macho'] = '';
    } else {
        $codigoMacho = trim((string) ($datos['codigo_rgb_id_animal_macho'] ?? ''));
        if ($codigoMacho === '') {
            return [$datos, 'Monta e inseminación requieren seleccionar un macho.'];
        }

        $condicionEstatusMacho = match ($tipoServicio) {
            1 => ' AND estatus_animal = 1',
            2 => ' AND estatus_animal IN (2, 3)',
            default => '',
        };
        $macho = $conexion->query(
            "SELECT id FROM manimales
             WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?
               AND status = 1 AND mestatus_id = 1 AND sexo = 2{$condicionEstatusMacho}
             LIMIT 1",
            [$fundoId, $codigoMacho]
        )->fetch();
        if (!$macho) {
            return [$datos, 'El macho no está activo o no es elegible para el tipo de servicio seleccionado.'];
        }
    }

    if ($tipoServicio === 3) {
        $codigoDonadora = trim((string) ($datos['codigo_rgb_id_animal_dona'] ?? ''));
        if ($codigoDonadora === '') {
            return [$datos, 'La transferencia de embriones requiere una donadora.'];
        }
        if ($codigoDonadora === $datos['codigo_rgb_id_animal_hembra']) {
            return [$datos, 'La donadora y la hembra receptora deben ser animales distintos.'];
        }
        $donadora = $conexion->query(
            'SELECT id FROM manimales
             WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?
               AND status = 1 AND mestatus_id = 1 AND sexo = 1
               AND estatus_animal IN (4, 5)
             LIMIT 1',
            [$fundoId, $codigoDonadora]
        )->fetch();
        if (!$donadora) {
            return [$datos, 'La donadora debe ser una hembra activa clasificada como donadora en este fundo.'];
        }
    }

    $fechaServicio = (string) ($datos['f_servicio'] ?? '');
    try {
        $fechaServicioDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $fechaServicio);
        $erroresFecha = \DateTimeImmutable::getLastErrors();
        if (!$fechaServicioDate || ($erroresFecha && ($erroresFecha['warning_count'] || $erroresFecha['error_count']))
            || $fechaServicioDate->format('Y-m-d') !== $fechaServicio) {
            return [$datos, 'La fecha del servicio no es válida.'];
        }
    } catch (\Throwable $e) {
        return [$datos, 'La fecha del servicio no es válida.'];
    }
    if ($fechaServicioDate > new \DateTimeImmutable('today')) {
        return [$datos, 'La fecha del servicio no puede ser futura.'];
    }

    // La web rechaza un servicio anterior al último parto de la hembra
    // (sería un servicio "antes de parir"). Sin esta validación un
    // dispositivo offline podía insertarlo.
    $ultimoParto = $conexion->query(
        'SELECT MAX(f_parto) AS ultimo FROM mregnacimientos
         WHERE mfundos_id = ? AND codigo_rgb_id_animal_hembra = ? AND status = 1',
        [$fundoId, $datos['codigo_rgb_id_animal_hembra']]
    )->fetch();
    $fechaUltimoParto = $ultimoParto['ultimo'] ?? null;
    if (!empty($fechaUltimoParto) && substr((string) $fechaUltimoParto, 0, 10) > $fechaServicioDate->format('Y-m-d')) {
        return [$datos, 'La fecha del servicio no puede ser anterior a la del último parto de la hembra (' . date('d-m-Y', strtotime((string) $fechaUltimoParto)) . ').'];
    }

    $fundo = $conexion->query(
        'SELECT mtipoganaderias_id FROM mfundos WHERE id = ?',
        [$fundoId]
    )->fetch();
    $tipoGanaderia = (int) ($fundo['mtipoganaderias_id'] ?? 0);
    $periodo = $conexion->query(
        'SELECT dias_gestacion FROM mperiodos
         WHERE status > 0 AND codper_bov = ? AND mtipoganaderias_id = ? AND mfundos_id = ?
         LIMIT 1',
        ['PSP', $tipoGanaderia, $fundoId]
    )->fetch();
    if (!$periodo) {
        $periodo = $conexion->query(
            'SELECT dias_gestacion FROM mperiodos
             WHERE status > 0 AND codper_bov = ? AND mtipoganaderias_id = ? AND mfundos_id = 0
             LIMIT 1',
            ['PSP', $tipoGanaderia]
        )->fetch();
    }
    $diasPalpacion = (int) ($periodo['dias_gestacion'] ?? 0);
    if ($diasPalpacion < 1) {
        return [$datos, 'No hay un período de palpación configurado para este fundo.'];
    }
    $datos['f_probable_palpacion'] = $fechaServicioDate
        ->modify('+' . $diasPalpacion . ' days')
        ->format('Y-m-d');

    $servicioActivo = $conexion->query(
        'SELECT ciclo_reproductivo, nro_servicio, medofisiologicos_id
         FROM mregservicios
         WHERE codigo_rgb_id_animal_hembra = ? AND estatus_ciclo = 1
           AND status = 1 AND mfundos_id = ?
         ORDER BY id DESC LIMIT 1',
        [$datos['codigo_rgb_id_animal_hembra'], $fundoId]
    )->fetch();

    if ($servicioActivo) {
        if (!in_array((int) $servicioActivo['medofisiologicos_id'], [4, 5], true)) {
            return [$datos, 'La hembra ya tiene un servicio activo. Registra la palpación antes de crear otro.'];
        }
        $datos['ciclo_reproductivo'] = (int) $servicioActivo['ciclo_reproductivo'];
        $datos['nro_servicio'] = (int) $servicioActivo['nro_servicio'] + 1;
    } else {
        $filaMax = $conexion->query(
            'SELECT COALESCE(MAX(ciclo_reproductivo), 0) + 1 AS siguiente
             FROM mregservicios WHERE codigo_rgb_id_animal_hembra = ? AND mfundos_id = ?',
            [$datos['codigo_rgb_id_animal_hembra'], $fundoId]
        )->fetch();
        $datos['ciclo_reproductivo'] = (int) $filaMax['siguiente'];
        $datos['nro_servicio'] = 1;
    }

    $datos['estatus_ciclo'] = 1;
    $datos['estado_servicio'] = 1;
    return [$datos, null];
}

/**
 * Corrige el tipo de servicio de un servicio gestante que quedó en 0.
 *
 * Nunca pisa un tipo ya definido (la condición COALESCE(mservicios_id,0)=0)
 * y respeta el fundo del usuario. Es la misma regla que
 * nac_corregir_tipo_servicio() en la app web.
 */
function corregir_tipo_servicio_servicio(int $servicioId, int $tipoId, int $fundoId, int $usuarioId): bool
{
    if ($servicioId <= 0 || $tipoId <= 0) {
        return false;
    }
    Mregservicio::connection()->query(
        'UPDATE mregservicios
            SET mservicios_id = ?, updated = NOW(), user_modify = ?
          WHERE id = ? AND mfundos_id = ? AND COALESCE(mservicios_id, 0) = 0',
        [$tipoId, $usuarioId, $servicioId, $fundoId]
    );
    return true;
}

/**
 * Crear un nacimiento tiene dos cosas que crear_registro() genérico
 * no sabe hacer:
 *   1. Calcular f_servicio (fecha probable de celo/servicio siguiente)
 *      a partir de f_parto. OJO: la web original suma 60 días FIJOS —
 *      hay una consulta a la tabla mperiodos que trae los días de
 *      gestación reales del fundo, pero la línea que la usa está
 *      comentada en el controlador original (sumar_dias_fecha()), así
 *      que en la práctica siempre son 60 días, sin importar lo que
 *      diga mperiodos. Se replica ese comportamiento TAL CUAL (no el
 *      "correcto") para que un nacimiento offline termine igual que
 *      uno online. Si algún día se corrige ese bug en la web, hay que
 *      corregir esta misma cuenta acá.
 *   2. mtipoganaderias_id no lo manda el cliente — se toma del
 *      perfil del usuario, igual que la web original.
 * Además crea la relación con el fundo (mnacimientoxfundos), mismo
 * patrón que animales.
 */
function crear_registro_nacimiento(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $datos = \Api\SyncModulos::filtrarCampos($cambio['datos'] ?? [], $config['campos_editables']);
    $servicioUuid = trim((string) ($cambio['datos']['servicio_uuid_local'] ?? ''));
    // El cliente puede no mandar servicio: en ese caso se autodetecta por la
    // madre (cómodidad) y, si no sirve, el parto se registra igual sin él.
    $servicioExplicito = ($servicioUuid !== '') || !empty($datos['mregservicios_id']);
    $servicioRelacionado = null;

    if ($servicioUuid !== '') {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $servicioUuid)) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El servicio relacionado no tiene un UUID válido.'];
        }
        $servicioRelacionado = \Mregservicio::find('first', [
            'conditions' => ['uuid_local = ? and mfundos_id = ? and status = 1', $servicioUuid, $usuario['fundo_id']],
        ]);
        if (!$servicioRelacionado) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El servicio seleccionado aún no está sincronizado o no pertenece a este fundo.'];
        }
    } elseif (!empty($datos['mregservicios_id'])) {
        $servicioRelacionado = \Mregservicio::find('first', [
            'conditions' => ['id = ? and mfundos_id = ? and status = 1', (int) $datos['mregservicios_id'], $usuario['fundo_id']],
        ]);
        if (!$servicioRelacionado) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El servicio relacionado no existe en este fundo.'];
        }
    } elseif (!empty($datos['codigo_rgb_id_animal_hembra'])) {
        $serviciosActivosMadre = \Mregservicio::find('all', [
            'conditions' => [
                'codigo_rgb_id_animal_hembra = ? and mfundos_id = ? and status = 1 and estatus_ciclo = 1',
                $datos['codigo_rgb_id_animal_hembra'], $usuario['fundo_id'],
            ],
            'order' => 'f_servicio DESC, id DESC',
        ]);
        if ($serviciosActivosMadre) {
            $servicioRelacionado = $serviciosActivosMadre[0];
        }
    }

    /* Tipo de servicio informado por la app. Se valida una sola vez: se usa
       igual cuando el servicio gestante quedó en 0 y cuando el parto va sin
       servicio (hembra preñada en otro fundo). */
    $tipoInformado = (int) ($datos['mtiposervicios_id'] ?? 0);
    $tipoInformadoValido = ($tipoInformado > 0)
        && (bool) Mtiposervicio::find('first', ['conditions' => ['id = ? and status = 1', $tipoInformado]]);

    if ($servicioRelacionado) {
        $errorServicio = validar_servicio_apto_para_nacimiento(
            $servicioRelacionado,
            (int) $usuario['fundo_id'],
            Musuario::connection()
        );
        if ($errorServicio) {
            /* El servicio se autodetectó por la madre (el cliente no lo mandó):
               si el productor ya indicó el tipo, el parto se registra sin
               servicio en vez de bloquear la carga. */
            if ($servicioExplicito || !$tipoInformadoValido) {
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorServicio];
            }
            $servicioRelacionado = null;
        }
    }

    if (!$servicioRelacionado) {
        /* NORMATIVA (igual que la app web): si la hembra llegó preñada de otro
           fundo no hay servicio que asociar, pero el parto se registra igual.
           El tipo de servicio es obligatorio: sin él el nacimiento no aparece
           en los listados. */
        if (!$tipoInformadoValido) {
            return [
                'uuid_local' => $uuid,
                'resultado'  => 'error',
                'mensaje'    => 'Indica el tipo de servicio (monta, inseminación o transferencia). '
                              . 'Si la hembra llegó preñada de otro fundo no hay servicio gestante que asociar.',
            ];
        }
        $datos['mregservicios_id']  = null;
        $datos['mtiposervicios_id'] = $tipoInformado;
    } else {
        $datos['mregservicios_id'] = (int) $servicioRelacionado->id;
        $datos['codigo_rgb_id_animal_hembra'] = (string) $servicioRelacionado->codigo_rgb_id_animal_hembra;
        $datos['codigo_rgb_id_animal_macho'] = (string) $servicioRelacionado->codigo_rgb_id_animal_macho;
        $datos['codigo_rgb_id_animal_dona'] = (string) ($servicioRelacionado->codigo_rgb_id_animal_dona ?? '');

        /* El tipo del servicio manda. Si el servicio quedó sin tipo (dato
           viejo), se usa el que eligió el productor en el parto y se corrige
           el servicio —solo si estaba en 0—, igual que hace la web. */
        $tipoServicio = (int) $servicioRelacionado->mservicios_id;
        if ($tipoServicio <= 0) {
            if (!$tipoInformadoValido) {
                return [
                    'uuid_local' => $uuid,
                    'resultado'  => 'error',
                    'mensaje'    => 'El servicio gestante no tiene tipo de servicio. '
                                  . 'Selecciona el tipo para registrar el parto.',
                ];
            }
            corregir_tipo_servicio_servicio(
                (int) $servicioRelacionado->id,
                $tipoInformado,
                (int) $usuario['fundo_id'],
                (int) $usuario['usuario_id']
            );
            $tipoServicio = $tipoInformado;
        }
        $datos['mtiposervicios_id'] = $tipoServicio;

        if (empty($datos['aptitud'])) $datos['aptitud'] = $servicioRelacionado->aptitud;
    }

    // Terneros del parto: el cliente nuevo manda la lista completa (una
    // o varias crías). Los clientes anteriores mandan un único ternero
    // en campos planos, así que se normaliza todo a la misma lista. Los
    // campos planos del registro reflejan siempre al primer ternero,
    // igual que hace la app web (ver mnacimientos_controller.php).
    [$terneros, $errorTerneros] = normalizar_terneros_nacimiento(
        is_array($cambio['datos'] ?? null) ? $cambio['datos'] : [],
        $datos
    );
    if ($errorTerneros) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorTerneros];
    }

    /* Tope de crías por parto según la especie del fundo (3 caprinos,
       4 ovinos). La app sin conexión ya lo aplica; esto cierra el paso a
       cualquier cliente que mande más crías de las permitidas. */
    $fundoEspecie = Musuario::connection()->query(
        'SELECT mtipoganaderias_id FROM mfundos WHERE id = ?',
        [(int) $usuario['fundo_id']]
    )->fetch();
    $tipoGanaderiaFundo = (int) ($fundoEspecie['mtipoganaderias_id'] ?? 0);
    if (count($terneros) > bovs_max_crias_parto($tipoGanaderiaFundo)) {
        return [
            'uuid_local' => $uuid,
            'resultado'  => 'error',
            'mensaje'    => bovs_mensaje_max_crias_parto($tipoGanaderiaFundo),
        ];
    }

    $terneroPrincipal = $terneros[0];
    $datos['id_ternero'] = $terneroPrincipal['id_ternero'];
    $datos['peso_nacimiento'] = $terneroPrincipal['peso_nacimiento'];
    $datos['estatus_nacido'] = $terneroPrincipal['estatus_nacido'];
    $datos['sexo'] = $terneroPrincipal['sexo'];
    if (!empty($terneroPrincipal['aptitud'])) $datos['aptitud'] = $terneroPrincipal['aptitud'];

    $errorCampos = validar_campos_requeridos($datos, $config['campos_requeridos']);
    if ($errorCampos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCampos];
    }

    completar_textos_obligatorios($datos, $config['campos_texto_obligatorios_bd']);
    completar_valores_por_defecto($datos, $config['valores_por_defecto'] ?? []);
    completar_enteros_con_defecto($datos, $config['enteros_con_defecto'] ?? []);

    $conexion = Musuario::connection();

    try {
        $conexion->transaction();

        [$datos, $codigoTerneroAnterior] = resolver_correlativo($datos, $config, $usuario, $conexion);

        $codigoTerneroFinal = (string) ($datos['codigo_ternero'] ?? '');
        $codigosTernero = array_values(array_unique(array_filter([$codigoTerneroAnterior, $codigoTerneroFinal])));
        $animalFicha = null;
        foreach ($codigosTernero as $codigoTernero) {
            $animalFicha = ManimalSync::find('first', [
                'conditions' => ['codigo_ternero = ? and mfundos_id = ? and status = 1', $codigoTernero, $usuario['fundo_id']],
            ]);
            if ($animalFicha) break;
        }

        $fichaAnimalActualizada = null;
        if ($animalFicha) {
            if ((string) $animalFicha->codigo_ternero !== $codigoTerneroFinal) {
                $animalFicha->codigo_ternero = $codigoTerneroFinal;
                $animalFicha->version = (int) $animalFicha->version + 1;
                $animalFicha->updated = date('Y-m-d H:i:s');
                $animalFicha->user_modify = $usuario['usuario_id'];
                if (!$animalFicha->save()) {
                    $conexion->rollback();
                    return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'No se pudo reconciliar la ficha del ternero.'];
                }
            }
            $fichaAnimalActualizada = [
                'uuid_local' => (string) $animalFicha->uuid_local,
                'codigo_ternero' => $codigoTerneroFinal,
                'version' => (int) $animalFicha->version,
                'updated' => $animalFicha->updated,
            ];
        }

        if (!empty($datos['f_parto'])) {
            $fechaParto = new \DateTime($datos['f_parto']);
            $fechaParto->modify('+60 days'); // ver comentario arriba — a propósito fijo, replica el bug de la web
            $datos['f_servicio'] = $fechaParto->format('d-m-Y');
        }

        $datosUsuario = Vusuario::find($usuario['usuario_id']);
        $datos['mtipoganaderias_id'] = (int) ($datosUsuario->mtipoganaderias_id ?? 0);

        // Igual que la app web: cuando el ternero entra a producción de
        // leche la fecha de secado/destete la calcula el servidor con los
        // períodos del fundo (PBL para lactancia, PPL para producción de
        // leche). La pregunta "¿Entrará a producción de leche?" ya no
        // existe en los formularios: la define el PROPÓSITO.
        $datos['produccion_leche'] = in_array((int) ($datos['proposito'] ?? 0), [1, 2], true) ? 1 : 0;
        $fechaSecadoCalculada = calcular_fecha_secado_nacimiento(
            (string) ($datos['f_parto'] ?? ''),
            (int) ($datos['produccion_leche'] ?? 0),
            (int) ($datos['proposito'] ?? 0),
            (int) $usuario['fundo_id'],
            (int) $datos['mtipoganaderias_id']
        );
        if ($fechaSecadoCalculada !== null) $datos['f_secado'] = $fechaSecadoCalculada;

        $registro = new $modeloClase();
        foreach ($datos as $campo => $valor) {
            $registro->$campo = normalizar_valor($valor);
        }

        $registro->uuid_local = $uuid;
        $registro->mfundos_id = $usuario['fundo_id'];
        $registro->status = 1;
        $registro->version = 1;
        $registro->created = date('Y-m-d H:i:s');
        $registro->user_create = $usuario['usuario_id'];
        $registro->origen_dispositivo = $usuario['dispositivo'];
        // 0/1 y no false: el driver de PostgreSQL de Active Record serializa
        // `false` como cadena vacía y la columna boolean lo rechaza ('invalid
        // input syntax for type boolean: ""'), lo que impedía sincronizar
        // cualquier parto que todavía no tuviera ficha de animal.
        $registro->fichado = $animalFicha ? 1 : 0;

        if (!$registro->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        // Detalle de las crías del parto (una o varias). Antes este
        // bloque estaba duplicado y guardaba el mismo ternero dos veces
        // por evento; ahora se guarda una sola vez la lista completa.
        $errorDetalleTerneros = guardar_terneros_nacimiento((int) $registro->id, $terneros, $usuario);
        if ($errorDetalleTerneros) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => $errorDetalleTerneros,
            ];
        }

        // Igual que animales: además de mregnacimientos, crea la fila
        // de relación con el fundo (mnacimientoxfundos), para que el
        // resto del sistema (conteos de plan, reportes) vea el
        // nacimiento igual que si viniera de la app web original.
        if (!empty($config['crea_relacion_fundo'])) {
            $claseRelacion = $config['crea_relacion_fundo']['modelo'];
            $campoId = $config['crea_relacion_fundo']['campo_id_relacionado'];
            require_once __DIR__ . '/../models/' . $claseRelacion . '.php';

            $relacion = new $claseRelacion();
            $relacion->$campoId = (int) $registro->id;
            $relacion->mfundos_id = $usuario['fundo_id'];
            $relacion->user_create = $usuario['usuario_id'];

            if (!$relacion->save()) {
                $conexion->rollback();
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'No se pudo crear la relación con el fundo: ' . primer_error_validacion($relacion),
                ];
            }
        }

        // Igual que la app web: al confirmar el parto se cierra el ciclo
        // del servicio gestante (estatus_ciclo=2, estado_servicio=2,
        // parto=1) y se anota cuántos terneros nacieron. Sin esto la
        // madre quedaba con una gestación activa para siempre.
        $errorCicloServicio = cerrar_ciclo_servicio_por_parto(
            $servicioRelacionado,
            (string) $datos['codigo_rgb_id_animal_hembra'],
            count($terneros),
            (string) ($datos['f_parto'] ?? ''),
            $usuario
        );
        if ($errorCicloServicio) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => $errorCicloServicio,
            ];
        }

        $conexion->commit();

        return [
            'uuid_local' => $uuid,
            'resultado' => 'creado',
            'id' => (int) $registro->id,
            'version' => (int) $registro->version,
            'updated' => $registro->updated,
            'created' => $registro->created,
            'campos_resueltos' => [
                'codigo_ternero' => $registro->codigo_ternero,
                'f_servicio' => $registro->f_servicio,
                'mtipoganaderias_id' => $registro->mtipoganaderias_id,
                'mregservicios_id' => $registro->mregservicios_id,
                'servicio_uuid_local' => $servicioRelacionado ? (string) $servicioRelacionado->uuid_local : null,
                'codigo_rgb_id_animal_hembra' => $registro->codigo_rgb_id_animal_hembra,
                'codigo_rgb_id_animal_macho' => $registro->codigo_rgb_id_animal_macho,
                'codigo_rgb_id_animal_dona' => $registro->codigo_rgb_id_animal_dona,
                'mtiposervicios_id' => $registro->mtiposervicios_id,
                'aptitud' => $registro->aptitud,
                'fichado' => (bool) $registro->fichado,
                'f_secado' => $registro->f_secado,
            ],
            'terneros' => $terneros,
            'ficha_animal_actualizada' => $fichaAnimalActualizada,
        ];

    } catch (\Throwable $e) {
        $conexion->rollback();
        error_log('[SYNC nacimientos] ' . $e->getMessage());
        $mensaje = 'No se pudo registrar el nacimiento. Verifica los datos.';
        if (stripos($e->getMessage(), 'duplicate key') !== false
            || strpos($e->getMessage(), 'correlativo_sin_codigo_libre') !== false) {
            $mensaje = mensaje_error_guardado($e);
        }
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => $mensaje,
        ];
    }
}

/**
 * Normaliza y valida la lista de crías de un parto.
 *
 * Formato nuevo: $payload['terneros'] = [{id_ternero, peso_nacimiento,
 * sexo, estatus_nacido, aptitud}, ...]. Formato antiguo (clientes ya
 * instalados): un solo ternero repartido en los campos planos del
 * registro — en ese caso se es tolerante con peso/aptitud para no
 * bloquear los pendientes que ya existen en los dispositivos.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: string|null}
 */
function normalizar_terneros_nacimiento(array $payload, array $datosPlanos): array
{
    $lista = $payload['terneros'] ?? null;
    $estricto = is_array($lista) && count($lista) > 0;

    if (!$estricto) {
        $lista = [[
            'id_ternero' => $datosPlanos['id_ternero'] ?? '',
            'peso_nacimiento' => $datosPlanos['peso_nacimiento'] ?? '',
            'sexo' => $datosPlanos['sexo'] ?? 0,
            'estatus_nacido' => $datosPlanos['estatus_nacido'] ?? 1,
            'aptitud' => $datosPlanos['aptitud'] ?? 0,
        ]];
    }

    if (count($lista) > 6) {
        return [[], 'Un parto no puede tener más de 6 terneros registrados.'];
    }

    $validados = [];
    $idsVistos = [];

    foreach ($lista as $indice => $ternero) {
        $nro = (int) $indice + 1;
        if (!is_array($ternero)) {
            return [[], 'Los datos del ternero #' . $nro . ' no son válidos.'];
        }

        $sexo = (int) ($ternero['sexo'] ?? 0);
        $estatus = (int) ($ternero['estatus_nacido'] ?? 0);
        $aptitud = (int) ($ternero['aptitud'] ?? 0);
        $peso = trim((string) ($ternero['peso_nacimiento'] ?? ''));
        $idTernero = strtoupper(trim((string) ($ternero['id_ternero'] ?? '')));

        if (!in_array($sexo, [1, 2], true)) {
            return [[], 'Selecciona el sexo del ternero #' . $nro . '.'];
        }
        if (!in_array($estatus, [1, 2], true)) {
            if ($estricto) {
                return [[], 'Indica si el ternero #' . $nro . ' nació vivo o muerto.'];
            }
            // Clientes anteriores mandaban el estatus como texto libre
            // (o vacío) y la columna es smallint: se asume "vivo" para
            // no bloquear los pendientes que ya están en los equipos.
            $estatus = 1;
        }

        if ($estricto) {
            if ($peso === '') {
                return [[], 'Registra el peso al nacer del ternero #' . $nro . '.'];
            }
            if (!is_numeric($peso) || (float) $peso <= 0 || (float) $peso > 200) {
                return [[], 'El peso del ternero #' . $nro . ' no es válido (debe estar entre 1 y 200 kg).'];
            }
            if ($aptitud <= 0) {
                return [[], 'Selecciona la aptitud del ternero #' . $nro . '.'];
            }
        }

        if ($idTernero !== '') {
            if (mb_strlen($idTernero) > 20) {
                return [[], 'El ID/arete del ternero #' . $nro . ' no puede tener más de 20 caracteres.'];
            }
            if (isset($idsVistos[$idTernero])) {
                return [[], 'El ID/arete ' . $idTernero . ' está repetido entre los terneros de este parto.'];
            }
            $idsVistos[$idTernero] = $nro;
        }

        $validados[] = [
            'nro_ternero' => $nro,
            'id_ternero' => $idTernero,
            'peso_nacimiento' => $peso,
            'sexo' => $sexo,
            'estatus_nacido' => $estatus,
            'aptitud' => $aptitud,
        ];
    }

    return [$validados, null];
}

/**
 * Guarda el detalle de las crías en mternerosxnacimientos. El
 * ID/arete es único por fundo (índice parcial de la tabla), así que se
 * valida antes de insertar para devolver un mensaje claro.
 */
function guardar_terneros_nacimiento(int $nacimientoId, array $terneros, array $usuario): ?string
{
    foreach ($terneros as $ternero) {
        if ($ternero['id_ternero'] !== '') {
            $existente = \Mterneroxnacimiento::find('first', [
                'conditions' => [
                    'mfundos_id = ? and status = 1 and upper(id_ternero) = ?',
                    (int) $usuario['fundo_id'], $ternero['id_ternero'],
                ],
            ]);
            if ($existente) {
                return 'El ID/arete ' . $ternero['id_ternero'] . ' ya está asociado a otro nacimiento en este fundo.';
            }
        }

        $detalle = new \Mterneroxnacimiento();
        $detalle->mregnacimientos_id = $nacimientoId;
        $detalle->mfundos_id = (int) $usuario['fundo_id'];
        $detalle->nro_ternero = (int) $ternero['nro_ternero'];
        $detalle->id_ternero = $ternero['id_ternero'] !== '' ? $ternero['id_ternero'] : null;
        $detalle->peso_nacimiento = (string) $ternero['peso_nacimiento'];
        $detalle->sexo = (int) $ternero['sexo'];
        $detalle->estatus_nacido = (int) $ternero['estatus_nacido'];
        $detalle->aptitud = (int) $ternero['aptitud'];
        $detalle->created = date('Y-m-d H:i:s');
        $detalle->status = 1;
        $detalle->user_create = $usuario['usuario_id'];

        if (!$detalle->save()) {
            return 'No se pudo guardar el ternero #' . (int) $ternero['nro_ternero'] . ': ' . primer_error_validacion($detalle);
        }
    }

    return null;
}

/** Reemplaza el detalle de crías al editar un nacimiento. */
function reemplazar_terneros_nacimiento(int $nacimientoId, array $terneros, array $usuario): ?string
{
    $actuales = Mterneroxnacimiento::find('all', [
        'conditions' => [
            'mregnacimientos_id = ? and mfundos_id = ? and status = 1',
            $nacimientoId, (int) $usuario['fundo_id'],
        ],
    ]);

    foreach ($actuales as $detalle) {
        if (!$detalle->delete()) {
            return 'No se pudieron actualizar los terneros del nacimiento.';
        }
    }

    return guardar_terneros_nacimiento($nacimientoId, $terneros, $usuario);
}

/** Adjunta las crías del parto a las filas del pull de nacimientos. */
function adjuntar_terneros_nacimiento(array $filas, int $fundoId): array
{
    $ids = array_values(array_unique(array_map('intval', array_column($filas, 'id'))));
    if (!$ids) return $filas;

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT mregnacimientos_id, nro_ternero, id_ternero, peso_nacimiento,
                   sexo, estatus_nacido, aptitud
              FROM mternerosxnacimientos
             WHERE mfundos_id = ?
               AND status = 1
               AND mregnacimientos_id IN ({$marcadores})
             ORDER BY mregnacimientos_id ASC, nro_ternero ASC, id ASC";
    $valores = array_merge([$fundoId], $ids);
    $resultado = Musuario::connection()->query($sql, $valores)->fetchAll();

    $porNacimiento = [];
    foreach ($resultado as $detalle) {
        $porNacimiento[(int) $detalle['mregnacimientos_id']][] = [
            'nro_ternero' => (int) $detalle['nro_ternero'],
            'id_ternero' => (string) ($detalle['id_ternero'] ?? ''),
            'peso_nacimiento' => (string) ($detalle['peso_nacimiento'] ?? ''),
            'sexo' => (int) $detalle['sexo'],
            'estatus_nacido' => (int) $detalle['estatus_nacido'],
            'aptitud' => (int) $detalle['aptitud'],
        ];
    }

    foreach ($filas as &$fila) {
        $fila['terneros'] = $porNacimiento[(int) $fila['id']] ?? [];
        $fila['nro_terneros'] = count($fila['terneros']);
    }
    unset($fila);

    return $filas;
}

/**
 * Cierra el ciclo del servicio gestante al confirmar el parto, igual
 * que la app web: cierra todos los ciclos activos de la madre y deja
 * marcado el servicio relacionado con parto, fecha y número de crías.
 */
function cerrar_ciclo_servicio_por_parto($servicio, string $codigoHembra, int $nroTerneros, string $fechaParto, array $usuario): ?string
{
    if (!$servicio) return null;

    $ciclos = \Mregservicio::find('all', [
        'conditions' => [
            'mfundos_id = ? and codigo_rgb_id_animal_hembra = ? and estatus_ciclo = 1',
            (int) $usuario['fundo_id'], $codigoHembra,
        ],
    ]);

    $encontrado = false;
    foreach ($ciclos as $ciclo) {
        $ciclo->estatus_ciclo = 2;
        $ciclo->estado_servicio = 2;
        $ciclo->user_modify = $usuario['usuario_id'];
        $ciclo->updated = date('Y-m-d H:i:s');
        if ((int) $ciclo->id === (int) $servicio->id) {
            $ciclo->parto = 1;
            if ($fechaParto !== '') $ciclo->fecha_parto = substr($fechaParto, 0, 10);
            $ciclo->nro_terneros = $nroTerneros;
            $encontrado = true;
        }
        if (!$ciclo->save()) {
            return 'No se pudo cerrar el ciclo del servicio gestante.';
        }
    }

    if (!$encontrado) {
        return 'No se encontró el ciclo activo del servicio gestante para cerrarlo.';
    }

    return null;
}

/**
 * Fecha de secado/destete calculada por el servidor a partir del parto.
 * Delega en la regla única (lib/fecha_secado_helper.php): propósito 1 →
 * PBL (destete), propósito 2 → PPL (secado), con los períodos del fundo.
 * Devuelve null cuando no aplica o cuando no hay período configurado.
 */
function calcular_fecha_secado_nacimiento(string $fechaParto, int $produccionLeche, int $proposito, int $fundoId, int $tipoGanaderia): ?string
{
    if ($produccionLeche !== 1) return null;

    $calculo = bovs_calcular_secado_parto($fechaParto, $proposito, $fundoId, $tipoGanaderia);
    if (!$calculo['ok'] || !$calculo['fecha']) return null;

    return $calculo['fecha']->format('Y-m-d');
}

// ===========================================================================
// CONTROL SANITARIO POR LOTES (mcontrolsanitarioxlotes)
//
// Equivalente offline de views/controlsanitariomasivo/frm_reglote.php:
// un control se aplica a un lote completo, con productos y la lista de
// animales incluidos. El código RCS-0<fundo>-0<n> lo genera el servidor
// (la web usa un correlativo por conteo que no se puede reservar desde
// el dispositivo).
// ===========================================================================

function crear_control_lote(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $payload = is_array($cambio['datos'] ?? null) ? $cambio['datos'] : [];
    $datos = \Api\SyncModulos::filtrarCampos($payload, $config['campos_editables']);

    $lote = \Mlote::find('first', [
        'conditions' => ['id = ? and mfundos_id = ? and status = 1', (int) ($datos['mlotes_id'] ?? 0), (int) $usuario['fundo_id']],
    ]);
    if (!$lote) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El lote seleccionado no existe o no pertenece a este fundo.'];
    }

    [$productos, $errorProductos] = validar_productos_control_sanitario(
        $payload['productos_aplicados'] ?? [],
        (int) $usuario['fundo_id']
    );
    if ($errorProductos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductos];
    }

    [$animales, $errorAnimales] = validar_animales_control_lote(
        $payload['animales'] ?? [],
        (int) $usuario['fundo_id']
    );
    if ($errorAnimales) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorAnimales];
    }

    $errorCampos = validar_campos_requeridos($datos, $config['campos_requeridos']);
    if ($errorCampos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCampos];
    }

    completar_textos_obligatorios($datos, $config['campos_texto_obligatorios_bd']);
    completar_valores_por_defecto($datos, $config['valores_por_defecto'] ?? []);

    $conexion = Musuario::connection();

    try {
        $conexion->transaction();

        $datos['mlotes_id'] = (int) $lote->id;
        $datos['codigo_mcs_lote'] = generar_codigo_control_lote((int) $usuario['fundo_id']);

        $registro = new $modeloClase();
        foreach ($datos as $campo => $valor) {
            $registro->$campo = normalizar_valor($valor);
        }
        $registro->uuid_local = $uuid;
        $registro->mfundos_id = (int) $usuario['fundo_id'];
        $registro->status = 1;
        $registro->version = 1;
        $registro->created = date('Y-m-d H:i:s');
        $registro->user_create = $usuario['usuario_id'];
        $registro->origen_dispositivo = $usuario['dispositivo'];

        if (!$registro->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        $errorDetalle = guardar_detalle_control_lote(
            (int) $registro->id,
            (string) $registro->codigo_mcs_lote,
            (int) $lote->id,
            $productos,
            $animales,
            $usuario
        );
        if ($errorDetalle) {
            $conexion->rollback();
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorDetalle];
        }

        $conexion->commit();

        return [
            'uuid_local' => $uuid,
            'resultado' => 'creado',
            'id' => (int) $registro->id,
            'version' => (int) $registro->version,
            'updated' => $registro->updated,
            'created' => $registro->created,
            'campos_resueltos' => [
                'codigo_mcs_lote' => $registro->codigo_mcs_lote,
                'mlotes_id' => (int) $registro->mlotes_id,
                'lote_nombre' => (string) $lote->nombre,
                'total_animales' => count($animales),
            ],
            'productos_aplicados' => $productos,
            'animales' => array_column($animales, 'codigo'),
        ];
    } catch (\Throwable $e) {
        $conexion->rollback();
        error_log('[SYNC control_sanitario_lotes] ' . $e->getMessage());
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'No se pudo registrar el control sanitario del lote. Verifica los datos.',
        ];
    }
}

/**
 * Desincorporación de un animal (baja).
 *
 * Réplica de mdesincorporacion_controller.php (case 'add'):
 *   - solo tipos 2 (descarte), 3 (muerto), 4 (vendido), 5 (extraviado).
 *     El 7 (MIGRADO A OTRO FUNDO) lo aplica el módulo de migraciones y
 *     es informativo: no se elige desde el dispositivo.
 *   - no se permite una segunda baja activa para el mismo animal.
 *   - si la hembra tiene crías vivas sin fichar, se rechaza.
 *   - al guardar, el animal queda con manimales.mestatus_id = tipo.
 */
function crear_desincorporacion(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $payload = is_array($cambio['datos'] ?? null) ? $cambio['datos'] : [];
    $datos = \Api\SyncModulos::filtrarCampos($payload, $config['campos_editables']);
    $fundoId = (int) $usuario['fundo_id'];

    $tipo = (int) ($datos['tipo_desincorporacion'] ?? 0);
    if (!in_array($tipo, [2, 3, 4, 5], true)) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'Solo se puede dar de baja por descarte, muerte, venta o extravío. '
                . 'La migración a otro fundo la registra el sistema automáticamente.',
        ];
    }

    $codigo = strtoupper(trim((string) ($datos['codigo_rgb_id_animal'] ?? '')));
    if ($codigo === '') {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Falta el animal a desincorporar.'];
    }
    $datos['codigo_rgb_id_animal'] = $codigo;

    $errorCampos = validar_campos_requeridos($datos, $config['campos_requeridos']);
    if ($errorCampos) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCampos];
    }

    $animal = ManimalSync::find('first', [
        'conditions' => ['mfundos_id = ? and codigo_rgb_id_animal = ? and status = 1', $fundoId, $codigo],
    ]);
    if (!$animal) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'El animal ' . $codigo . ' no existe o no está activo en este fundo.',
        ];
    }

    // Idempotencia: si el servidor ya lo tiene con ese mismo estado, se
    // responde "creado" para que el dispositivo cierre la cola sin error.
    if ((int) $animal->mestatus_id === $tipo) {
        $baja = \Mdesincorporacionanimale::find('first', [
            'conditions' => ['mfundos_id = ? and codigo_rgb_id_animal = ? and status = 1', $fundoId, $codigo],
            'order' => 'id desc',
        ]);
        if ($baja) {
            return [
                'uuid_local' => $uuid,
                'resultado' => 'sin_cambios',
                'id' => (int) $baja->id,
                'campos_resueltos' => [
                    'codigo_rgb_id_animal' => $codigo,
                    'estatus_animal' => $tipo,
                ],
            ];
        }
    } elseif ((int) $animal->mestatus_id !== 1) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'El animal ' . $codigo . ' ya tiene otra baja registrada en el servidor.',
        ];
    }

    $bajaActiva = \Mdesincorporacionanimale::find('first', [
        'conditions' => ['mfundos_id = ? and codigo_rgb_id_animal = ? and status = 1', $fundoId, $codigo],
    ]);
    if ($bajaActiva) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'El animal ' . $codigo . ' ya tiene una desincorporación registrada.',
        ];
    }

    $conexion = Musuario::connection();

    $errorCrias = validar_crias_pendientes_desincorporacion($conexion, $fundoId, $codigo);
    if ($errorCrias) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorCrias];
    }

    completar_textos_obligatorios($datos, $config['campos_texto_obligatorios_bd']);
    completar_valores_por_defecto($datos, $config['valores_por_defecto'] ?? []);

    try {
        $conexion->transaction();

        $datos['tipo_desincorporacion'] = $tipo;

        $registro = new $modeloClase();
        foreach ($datos as $campo => $valor) {
            $registro->$campo = normalizar_valor($valor);
        }
        $registro->uuid_local = $uuid;
        $registro->mfundos_id = $fundoId;
        $registro->status = 1;
        $registro->version = 1;
        $registro->created = date('Y-m-d H:i:s');
        $registro->user_create = $usuario['usuario_id'];
        $registro->origen_dispositivo = $usuario['dispositivo'] ?? null;

        // Estado previo del animal: permite restaurarlo cuando se anule la baja.
        if (array_key_exists('mestatus_anterior', $registro->attributes())) {
            $registro->mestatus_anterior = (int) $animal->mestatus_id;
        }

        if (!$registro->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        $animal->mestatus_id = $tipo;
        $animal->user_modify = $usuario['usuario_id'];
        $animal->updated = date('Y-m-d H:i:s');
        if (!$animal->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'No se pudo actualizar el estado del animal.',
            ];
        }

        $conexion->commit();

        return [
            'uuid_local' => $uuid,
            'resultado' => 'creado',
            'id' => (int) $registro->id,
            'version' => (int) $registro->version,
            'updated' => $registro->updated,
            'created' => $registro->created,
            'campos_resueltos' => [
                'codigo_rgb_id_animal' => $codigo,
                'tipo_desincorporacion' => $tipo,
                'estatus_animal' => $tipo,
                'f_desincorporacion' => $registro->f_desincorporacion,
            ],
        ];
    } catch (\Throwable $e) {
        $conexion->rollback();
        error_log('[SYNC desincorporacion] ' . $e->getMessage());
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'No se pudo registrar la desincorporación. Verifica los datos.',
        ];
    }
}

/**
 * Mismo control que la web: una hembra con crías vivas y activas sin
 * ficha no se puede desincorporar.
 */
function validar_crias_pendientes_desincorporacion($conexion, int $fundoId, string $codigo): ?string{
    try {
        $filas = $conexion->query(
            'SELECT id_ternero, codigo_ternero FROM mregnacimientos
              WHERE mfundos_id = ? AND codigo_rgb_id_animal_hembra = ? AND status = 1
                AND f_parto IS NOT NULL AND estatus_nacido = 1 AND mestatus_id = 1
                AND fichado = false
              ORDER BY f_parto, id',
            [$fundoId, $codigo]
        )->fetchAll();
    } catch (\Throwable $e) {
        // Si el control falla no se bloquea la baja (mismo criterio que
        // la web, que solo bloquea cuando encuentra crías).
        return null;
    }

    if (!$filas) {
        return null;
    }

    $codigos = [];
    foreach ($filas as $fila) {
        $identificador = trim((string) ($fila['id_ternero'] ?? ''));
        if ($identificador === '') {
            $identificador = trim((string) ($fila['codigo_ternero'] ?? ''));
        }
        if ($identificador !== '') {
            $codigos[] = $identificador;
        }
    }

    $detalle = $codigos ? implode(', ', $codigos) : 'sin identificar';

    return 'No se puede desincorporar esta hembra: tiene crías vivas que aún no tienen ficha ('
        . $detalle . '). Fichá las crías en Nacimientos, o actualizá su estado si murieron.';
}

/**
 * Movimiento de rebaño (cambio de lote y/o potrero).
 *
 * Réplica de movreb_controller.php: crea la cabecera, una fila de detalle
 * por animal (lote/potrero/categoría antes y después) y actualiza el
 * animal. El destino de potrero 0 significa "sin potrero".
 */
function crear_movimiento_rebano(string $uuid, array $cambio, array $config, string $modeloClase, array $usuario): array
{
    $payload = is_array($cambio['datos'] ?? null) ? $cambio['datos'] : [];
    $datos = \Api\SyncModulos::filtrarCampos($payload, $config['campos_editables']);
    $fundoId = (int) $usuario['fundo_id'];

    $tipo = strtoupper(trim((string) ($datos['tipo_movimiento'] ?? '')));
    if (!in_array($tipo, ['LOTE', 'POTRERO', 'AMBOS'], true)) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'Tipo de movimiento inválido: se espera LOTE, POTRERO o AMBOS.',
        ];
    }
    $datos['tipo_movimiento'] = $tipo;

    $motivo = trim((string) ($datos['motivo'] ?? ''));
    if ($motivo === '') {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Indicá el motivo del movimiento.'];
    }
    $datos['motivo'] = mb_substr($motivo, 0, 150);

    $observaciones = trim((string) ($datos['observaciones'] ?? ''));
    $datos['observaciones'] = $observaciones === '' ? null : mb_substr($observaciones, 0, 500);

    $fecha = trim((string) ($datos['fecha_movimiento'] ?? ''));
    $marca = $fecha === '' ? false : strtotime($fecha);
    $datos['fecha_movimiento'] = $marca ? date('Y-m-d H:i:s', $marca) : date('Y-m-d H:i:s');

    $usaLote = in_array($tipo, ['LOTE', 'AMBOS'], true);
    $usaPotrero = in_array($tipo, ['POTRERO', 'AMBOS'], true);

    $loteOrigen = null;
    $loteDestino = null;
    if ($usaLote) {
        $loteOrigenId = (int) ($datos['mlotes_origen_id'] ?? 0);
        $loteDestinoId = (int) ($datos['mlotes_destino_id'] ?? 0);

        if ($loteOrigenId <= 0) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Elegí el lote de origen.'];
        }
        if ($loteDestinoId <= 0) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Elegí el lote de destino.'];
        }
        if ($loteOrigenId === $loteDestinoId) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El lote de destino debe ser distinto al de origen.'];
        }

        $loteOrigen = \Mlote::find('first', [
            'conditions' => ['id = ? and mfundos_id = ? and status = 1', $loteOrigenId, $fundoId],
        ]);
        if (!$loteOrigen) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El lote de origen no existe o no pertenece a este fundo.'];
        }

        $loteDestino = \Mlote::find('first', [
            'conditions' => ['id = ? and mfundos_id = ? and status = 1', $loteDestinoId, $fundoId],
        ]);
        if (!$loteDestino) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El lote de destino no existe o no pertenece a este fundo.'];
        }
    }

    $potreroOrigen = null;
    $potreroDestino = null;
    if ($usaPotrero) {
        $potreroOrigenId = (int) ($datos['mpotreros_origen_id'] ?? 0);
        $potreroDestinoId = (int) ($datos['mpotreros_destino_id'] ?? 0);

        if ($potreroOrigenId <= 0) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'Elegí el potrero de origen.'];
        }
        if ($potreroOrigenId === $potreroDestinoId) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El potrero de destino debe ser distinto al de origen.'];
        }

        $potreroOrigen = \Mpotrero::find('first', [
            'conditions' => ['id = ? and mfundos_id = ? and status = 1', $potreroOrigenId, $fundoId],
        ]);
        if (!$potreroOrigen) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El potrero de origen no existe o no pertenece a este fundo.'];
        }

        if ($potreroDestinoId > 0) {
            $potreroDestino = \Mpotrero::find('first', [
                'conditions' => ['id = ? and mfundos_id = ? and status = 1', $potreroDestinoId, $fundoId],
            ]);
            if (!$potreroDestino) {
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => 'El potrero de destino no existe o no pertenece a este fundo.'];
            }
        }
    }

    [$animales, $errorAnimales] = validar_animales_movimiento_rebano(
        $payload['animales'] ?? [],
        $fundoId,
        $loteOrigen,
        $potreroOrigen,
        $usaLote,
        $usaPotrero
    );
    if ($errorAnimales) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorAnimales];
    }

    $datos['mlotes_origen_id'] = $loteOrigen ? (int) $loteOrigen->id : null;
    $datos['mlotes_destino_id'] = $loteDestino ? (int) $loteDestino->id : null;
    $datos['mpotreros_origen_id'] = $potreroOrigen ? (int) $potreroOrigen->id : null;
    $datos['mpotreros_destino_id'] = $potreroDestino ? (int) $potreroDestino->id : null;

    $tipoGanaderia = 0;
    $fundo = \Mfundo::find('first', ['conditions' => ['id = ?', $fundoId]]);
    if ($fundo) {
        $tipoGanaderia = (int) $fundo->mtipoganaderias_id;
    }

    $conexion = Musuario::connection();

    try {
        $conexion->transaction();

        $registro = new $modeloClase();
        foreach ($datos as $campo => $valor) {
            $registro->$campo = normalizar_valor($valor);
        }
        $registro->uuid_local = $uuid;
        $registro->mfundos_id = $fundoId;
        $registro->total_animales = count($animales);
        $registro->status = 1;
        $registro->version = 1;
        // 0/false como entero: PDO manda '' si es un booleano nativo y
        // PostgreSQL rechaza '' en una columna boolean.
        $registro->revertido = 0;
        $registro->created = date('Y-m-d H:i:s');
        $registro->updated = date('Y-m-d H:i:s');
        $registro->user_create = $usuario['usuario_id'];
        $registro->origen_dispositivo = $usuario['dispositivo'] ?? null;

        if (!$registro->save()) {
            $conexion->rollback();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($registro),
            ];
        }

        $movimientoId = (int) $registro->id;
        $ahora = date('Y-m-d H:i:s');
        $codigosMovidos = [];

        foreach ($animales as $item) {
            $animal = $item['animal'];
            $codigo = $item['codigo'];

            $categoriaAnterior = (int) $animal->categoria_ingreso_id;
            $categoriaNueva = categoria_actual_animal(
                (int) $animal->sexo,
                $animal->f_nacimiento,
                $fundoId,
                $tipoGanaderia
            );
            if (!$categoriaNueva) {
                $categoriaNueva = $categoriaAnterior;
            }

            $detalle = new \Mmovimientorebanodetalle();
            $detalle->mmovimientos_rebano_id = $movimientoId;
            $detalle->manimales_id = (int) $animal->id;
            $detalle->codigo_rgb_id_animal = $codigo;
            $detalle->lote_anterior_id = $animal->mlotes_id ? (int) $animal->mlotes_id : null;
            $detalle->lote_nuevo_id = $loteDestino ? (int) $loteDestino->id : null;
            $detalle->potrero_anterior_id = $animal->mpotreros_id ? (int) $animal->mpotreros_id : null;
            $detalle->potrero_nuevo_id = $potreroDestino ? (int) $potreroDestino->id : null;
            $detalle->categoria_anterior_id = $categoriaAnterior ?: null;
            $detalle->categoria_nueva_id = $categoriaNueva ?: null;
            $detalle->created = $ahora;

            if (!$detalle->save()) {
                $conexion->rollback();
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'No se pudo guardar el detalle del movimiento: ' . primer_error_validacion($detalle),
                ];
            }

            if ($usaLote && $loteDestino) {
                $animal->mlotes_id = (int) $loteDestino->id;
            }
            if ($usaPotrero) {
                $animal->mpotreros_id = $potreroDestino ? (int) $potreroDestino->id : null;
            }
            if ($categoriaNueva && $categoriaNueva !== $categoriaAnterior) {
                $animal->categoria_ingreso_id = $categoriaNueva;
            }
            $animal->user_modify = $usuario['usuario_id'];
            $animal->updated = $ahora;

            if (!$animal->save()) {
                $conexion->rollback();
                return [
                    'uuid_local' => $uuid,
                    'resultado' => 'error',
                    'mensaje' => 'No se pudo actualizar el animal ' . $codigo . '.',
                ];
            }

            $codigosMovidos[] = $codigo;
        }

        $conexion->commit();

        return [
            'uuid_local' => $uuid,
            'resultado' => 'creado',
            'id' => $movimientoId,
            'version' => (int) $registro->version,
            'updated' => $registro->updated,
            'created' => $registro->created,
            'campos_resueltos' => [
                'tipo_movimiento' => $tipo,
                'total_animales' => count($animales),
                'mlotes_destino_id' => $loteDestino ? (int) $loteDestino->id : null,
                'mpotreros_destino_id' => $potreroDestino ? (int) $potreroDestino->id : null,
            ],
            'animales' => $codigosMovidos,
        ];
    } catch (\Throwable $e) {
        $conexion->rollback();
        error_log('[SYNC movimiento_rebano] ' . $e->getMessage());
        return [
            'uuid_local' => $uuid,
            'resultado' => 'error',
            'mensaje' => 'No se pudo registrar el movimiento de rebaño. Verifica los datos.',
        ];
    }
}

/**
 * Valida que los animales existan, estén activos y pertenezcan al origen
 * elegido. Devuelve [ [ 'animal' => $modelo, 'codigo' => 'RGB-...' ], ... ].
 */
function validar_animales_movimiento_rebano(
    $entrada,
    int $fundoId,
    $loteOrigen,
    $potreroOrigen,
    bool $usaLote,
    bool $usaPotrero
): array {
    $codigos = [];
    foreach ((array) $entrada as $item) {
        $codigo = is_array($item)
            ? ($item['codigo_rgb_id_animal'] ?? $item['codigo'] ?? '')
            : $item;
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo !== '') {
            $codigos[$codigo] = true;
        }
    }

    if (!$codigos) {
        return [[], 'Seleccioná al menos un animal para mover.'];
    }

    $filas = ManimalSync::find('all', [
        'conditions' => [
            'mfundos_id = ? and status = 1 and codigo_rgb_id_animal in (?)',
            $fundoId,
            array_keys($codigos),
        ],
        'order' => 'codigo_rgb_id_animal asc',
    ]);

    $validos = [];
    // Se guardan los códigos, no sólo el total: el mensaje de error los
    // nombra para que el operario sepa cuál revisar.
    $codigosFuera = [];
    $codigosInactivos = [];

    // 3 MUERTO, 4 VENDIDO, 5 EXTRAVIADO, 7 MIGRADO: no se mueven.
    $noMovibles = [3, 4, 5, 7];

    foreach ($filas as $animal) {
        $codigo = (string) $animal->codigo_rgb_id_animal;

        if (in_array((int) $animal->mestatus_id, $noMovibles, true)) {
            $codigosInactivos[] = $codigo;
            continue;
        }

        if ($usaLote && (int) $animal->mlotes_id !== (int) $loteOrigen->id) {
            $codigosFuera[] = $codigo;
            continue;
        }

        if ($usaPotrero && (int) $animal->mpotreros_id !== (int) $potreroOrigen->id) {
            $codigosFuera[] = $codigo;
            continue;
        }

        $validos[] = ['animal' => $animal, 'codigo' => $codigo];
    }

    // Códigos pedidos que el servidor no encontró activos en el fundo.
    $encontrados = [];
    foreach ($filas as $animal) {
        $encontrados[strtoupper((string) $animal->codigo_rgb_id_animal)] = true;
    }
    $faltantes = [];
    $provisionales = [];
    foreach (array_keys($codigos) as $codigo) {
        if (isset($encontrados[$codigo])) {
            continue;
        }
        $faltantes[] = $codigo;
        if (strpos($codigo, '-PEND-') !== false) {
            $provisionales[] = $codigo;
        }
    }

    // El movimiento se aplica COMPLETO o no se aplica.
    // Antes, si una parte de los animales no era válida, se creaba el
    // movimiento sólo con los válidos: el registro subía bien y el
    // operario nunca se enteraba de que el resto del rebaño se había
    // quedado en el potrero de origen.
    if ($faltantes || $codigosInactivos || $codigosFuera) {
        $motivos = [];
        if ($faltantes) {
            $motivos[] = 'No se encontraron en el servidor: ' . resumen_codigos($faltantes) . '.';
            if ($provisionales) {
                $motivos[] = 'Los códigos con "-PEND-" son animales creados sin conexión que todavía no subieron.';
            }
        }
        if ($codigosInactivos) {
            $motivos[] = 'Están dados de baja (muerto, vendido, extraviado o migrado): '
                . resumen_codigos($codigosInactivos) . '.';
        }
        if ($codigosFuera) {
            $motivos[] = 'No están en el origen elegido: ' . resumen_codigos($codigosFuera) . '.';
        }

        $cabecera = $validos
            ? 'No se movió ningún animal: el movimiento se rechazó completo.'
            : 'Ninguno de los animales seleccionados está activo en este fundo.';

        return [[], $cabecera . ' ' . implode(' ', $motivos) . ' Sincronizá el equipo, recargá la lista de animales y volvé a intentar.'];
    }

    return [$validos, null];
}

/**
 * Arma una lista corta de códigos para los mensajes de error:
 * "A, B, C y 2 más".
 */
function resumen_codigos(array $codigos, int $maximo = 4): string
{
    $codigos = array_values(array_unique($codigos));
    $total = count($codigos);
    if ($total === 0) {
        return '';
    }
    if ($total <= $maximo) {
        return implode(', ', $codigos);
    }

    $resto = $total - $maximo;
    return implode(', ', array_slice($codigos, 0, $maximo))
        . ($resto === 1 ? ' y 1 más' : ' y ' . $resto . ' más');
}

/**
 * Categoría que le corresponde al animal hoy, según sexo y edad.
 * Mismo criterio que calcularCategoriaActual() de movreb_controller.php:
 * primero busca categorías propias del fundo y si no, las estándar
 * (mfundos_id = 0).
 */
function categoria_actual_animal(int $sexo, $fechaNac, int $fundoId, int $tipoGanaderia)
{
    // OJO: las fechas de php-activerecord son ActiveRecord\DateTime y su
    // __toString() devuelve formato RFC ("Wed, 01 Mar 2023 ..."), no
    // "Y-m-d": hay que formatearlas antes de usarlas.
    if ($fechaNac instanceof \DateTime) {
        $fechaNac = $fechaNac->format('Y-m-d');
    }

    $fechaNac = trim((string) $fechaNac);
    if ($fechaNac === '' || $fechaNac === '0000-00-00') {
        return null;
    }

    $marca = strtotime(substr($fechaNac, 0, 10));
    if (!$marca) {
        $marca = strtotime($fechaNac);
    }
    if (!$marca) {
        return null;
    }

    $dias = (int) floor(abs(($marca - strtotime(date('Y-m-d'))) / 86400));
    $meses = (int) ($dias / 30);

    $sql = 'SELECT id FROM mcategorias
             WHERE mtipoganaderias_id = ? AND status = 1 AND sexo = ?
               AND mfundos_id = ? AND edadminima <= ? AND edadmaxima > ?
             ORDER BY id ASC LIMIT 1';

    try {
        $conexion = Musuario::connection();

        $propia = $conexion->query($sql, [$tipoGanaderia, $sexo, $fundoId, $meses, $meses])->fetchAll();
        if ($propia) {
            return (int) $propia[0]['id'];
        }

        $estandar = $conexion->query($sql, [$tipoGanaderia, $sexo, 0, $meses, $meses])->fetchAll();
        if ($estandar) {
            return (int) $estandar[0]['id'];
        }
    } catch (\Throwable $e) {
        error_log('[SYNC movimiento_rebano categoria] ' . $e->getMessage());
    }

    return null;
}

/**
 * Valida los animales que el dispositivo incluyó en el control. El
 * cliente manda códigos (los que vio en el lote sin conexión); acá se
 * resuelven al id real y se verifica que sigan activos en el fundo.
 *
 * @return array{0: array<int, array{manimales_id: int, codigo: string}>, 1: string|null}
 */
function validar_animales_control_lote($animales, int $fundoId): array
{
    if (!is_array($animales) || !count($animales)) {
        return [[], 'Un control por lote debe incluir al menos un animal.'];
    }
    if (count($animales) > 2000) {
        return [[], 'El control incluye demasiados animales (máximo 2000).'];
    }

    $codigos = [];
    foreach ($animales as $animal) {
        $codigo = is_array($animal)
            ? trim((string) ($animal['codigo_rgb_id_animal'] ?? $animal['codigo'] ?? ''))
            : trim((string) $animal);
        if ($codigo !== '') $codigos[$codigo] = $codigo;
    }

    if (!count($codigos)) {
        return [[], 'Un control por lote debe incluir al menos un animal.'];
    }

    $marcadores = implode(',', array_fill(0, count($codigos), '?'));
    $sql = "SELECT id, codigo_rgb_id_animal
              FROM manimales
             WHERE mfundos_id = ?
               AND status = 1
               AND codigo_rgb_id_animal IN ({$marcadores})";
    $valores = array_merge([$fundoId], array_values($codigos));
    $filas = Musuario::connection()->query($sql, $valores)->fetchAll();

    $encontrados = [];
    foreach ($filas as $fila) {
        $encontrados[strtoupper((string) $fila['codigo_rgb_id_animal'])] = [
            'manimales_id' => (int) $fila['id'],
            'codigo' => (string) $fila['codigo_rgb_id_animal'],
        ];
    }

    $faltantes = [];
    foreach ($codigos as $codigo) {
        if (!isset($encontrados[strtoupper($codigo)])) $faltantes[] = $codigo;
    }
    if ($faltantes) {
        return [[], 'Estos animales ya no están activos en el fundo: ' . implode(', ', array_slice($faltantes, 0, 5))
            . (count($faltantes) > 5 ? '…' : '') . '. Sincroniza y vuelve a intentarlo.'];
    }

    return [array_values($encontrados), null];
}

/** Código consecutivo del control por lote (RCS-0<fundo>-0<n>). */
function generar_codigo_control_lote(int $fundoId): string
{
    $conexion = Musuario::connection();

    $base = $conexion->query(
        'SELECT count(id) AS total FROM mcontrolsanitarioxlotes WHERE mfundos_id = ? AND status = 1',
        [$fundoId]
    )->fetchAll();
    $total = (int) ($base[0]['total'] ?? 0);

    for ($intento = 1; $intento <= 50; $intento++) {
        $codigo = 'RCS-0' . $fundoId . '-0' . ($total + $intento);
        $existe = $conexion->query(
            'SELECT 1 FROM mcontrolsanitarioxlotes WHERE mfundos_id = ? AND codigo_mcs_lote = ? LIMIT 1',
            [$fundoId, $codigo]
        )->fetchAll();
        if (!$existe) return $codigo;
    }

    return 'RCS-0' . $fundoId . '-0' . ($total + 1);
}

/** Guarda productos aplicados + animales incluidos del control de lote. */
function guardar_detalle_control_lote(int $controlId, string $codigoControl, int $loteId, array $productos, array $animales, array $usuario): ?string
{
    foreach ($productos as $producto) {
        $detalle = new \Mdetacrlsanitariolote();
        $detalle->mcontrolsanitarioxlotes_id = $controlId;
        $detalle->mproductos_id = (int) $producto['mproductos_id'];
        $detalle->dosis = (string) $producto['dosis'];
        $detalle->cantidad = 0;
        $detalle->detalles = (string) $producto['detalles'];
        $detalle->mfundos_id = (int) $usuario['fundo_id'];
        $detalle->status = 1;
        $detalle->created = date('Y-m-d H:i:s');
        $detalle->user_create = $usuario['usuario_id'];

        if (!$detalle->save()) {
            return 'No se pudo guardar uno de los productos aplicados: ' . primer_error_validacion($detalle);
        }
    }

    foreach ($animales as $animal) {
        $miembro = new \Mdetacsxlote();
        $miembro->mcontrolsanitarioxlotes_id = $controlId;
        $miembro->codigo_mcs_lote = $codigoControl;
        $miembro->id_lotes = $loteId;
        $miembro->manimales_id = (int) $animal['manimales_id'];
        $miembro->mfundos_id = (int) $usuario['fundo_id'];
        $miembro->status = 1;
        $miembro->created = date('Y-m-d H:i:s');
        $miembro->user_create = $usuario['usuario_id'];

        if (!$miembro->save()) {
            return 'No se pudo guardar uno de los animales del control: ' . primer_error_validacion($miembro);
        }
    }

    return null;
}

/** Reemplaza el detalle (productos + animales) al editar un control. */
function reemplazar_detalle_control_lote(int $controlId, string $codigoControl, int $loteId, array $productos, array $animales, array $usuario): ?string
{
    $actualesProductos = \Mdetacrlsanitariolote::find('all', [
        'conditions' => ['mcontrolsanitarioxlotes_id = ? and mfundos_id = ?', $controlId, (int) $usuario['fundo_id']],
    ]);
    foreach ($actualesProductos as $detalle) {
        if (!$detalle->delete()) return 'No se pudieron actualizar los productos aplicados del control.';
    }

    $actualesAnimales = \Mdetacsxlote::find('all', [
        'conditions' => ['mcontrolsanitarioxlotes_id = ? and mfundos_id = ?', $controlId, (int) $usuario['fundo_id']],
    ]);
    foreach ($actualesAnimales as $miembro) {
        if (!$miembro->delete()) return 'No se pudieron actualizar los animales del control.';
    }

    return guardar_detalle_control_lote($controlId, $codigoControl, $loteId, $productos, $animales, $usuario);
}

/** Adjunta productos y animales a las filas del pull de control por lote. */
function adjuntar_detalle_control_lote(array $filas, int $fundoId): array
{
    $ids = array_values(array_unique(array_map('intval', array_column($filas, 'id'))));
    if (!$ids) return $filas;

    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $productos = Musuario::connection()->query(
        "SELECT d.mcontrolsanitarioxlotes_id, d.mproductos_id,
                COALESCE(p.nombre, 'Producto') AS producto_nombre,
                COALESCE(d.dosis, '') AS dosis,
                COALESCE(d.detalles, '') AS detalles
           FROM mdetacrlsanitariolotes d
           LEFT JOIN mproductos p ON p.id = d.mproductos_id
          WHERE d.mfundos_id = ?
            AND d.status > 0
            AND d.mcontrolsanitarioxlotes_id IN ({$marcadores})
          ORDER BY d.id ASC",
        array_merge([$fundoId], $ids)
    )->fetchAll();

    $animales = Musuario::connection()->query(
        "SELECT x.mcontrolsanitarioxlotes_id, m.codigo_rgb_id_animal, COALESCE(m.nombre, '') AS nombre
           FROM mdetacsxlotes x
           JOIN manimales m ON m.id = x.manimales_id
          WHERE x.mfundos_id = ?
            AND x.status = 1
            AND x.mcontrolsanitarioxlotes_id IN ({$marcadores})
          ORDER BY m.codigo_rgb_id_animal ASC",
        array_merge([$fundoId], $ids)
    )->fetchAll();

    $porControl = [];
    foreach ($productos as $detalle) {
        $porControl[(int) $detalle['mcontrolsanitarioxlotes_id']]['productos_aplicados'][] = [
            'mproductos_id' => (int) $detalle['mproductos_id'],
            'producto_nombre' => (string) $detalle['producto_nombre'],
            'dosis' => (string) $detalle['dosis'],
            'detalles' => (string) $detalle['detalles'],
        ];
    }
    foreach ($animales as $miembro) {
        $porControl[(int) $miembro['mcontrolsanitarioxlotes_id']]['animales'][] = (string) $miembro['codigo_rgb_id_animal'];
    }

    foreach ($filas as &$fila) {
        $detalle = $porControl[(int) $fila['id']] ?? [];
        $fila['productos_aplicados'] = $detalle['productos_aplicados'] ?? [];
        $fila['animales'] = $detalle['animales'] ?? [];
        $fila['total_animales'] = count($fila['animales']);
    }
    unset($fila);

    return $filas;
}

function validar_servicio_apto_para_nacimiento($servicio, int $fundoId, $conexion): ?string
{
    if ((int) $servicio->mfundos_id !== $fundoId
        || (int) $servicio->status !== 1
        || (int) $servicio->estatus_ciclo !== 1
        || (int) ($servicio->estado_servicio ?? 1) === 2) {
        return 'El servicio ya no está activo para vincular un nacimiento.';
    }
    if ((int) $servicio->medofisiologicos_id !== 1 || (int) ($servicio->parto ?? 0) !== 0) {
        return 'El servicio no está en estado gestante o ya tiene un parto registrado.';
    }

    try {
        $fechaRaw = $servicio->f_servicio;
        $fechaTexto = $fechaRaw instanceof \DateTimeInterface
            ? $fechaRaw->format('Y-m-d')
            : substr((string) $fechaRaw, 0, 10);
        $fechaServicio = new \DateTimeImmutable($fechaTexto);
        $edadGestacion = $fechaServicio->diff(new \DateTimeImmutable('today'));
        $mesesCompletos = ($edadGestacion->y * 12) + $edadGestacion->m;
        if ($edadGestacion->invert || $mesesCompletos < 7 || $mesesCompletos > 12) {
            return 'El servicio debe tener entre siete y doce meses de gestación para registrar el nacimiento.';
        }
    } catch (\Throwable $e) {
        return 'El servicio no tiene una fecha válida para calcular la gestación.';
    }

    $madreActiva = $conexion->query(
        'SELECT id FROM manimales
         WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?
           AND status = 1 AND mestatus_id = 1 AND sexo = 1
         LIMIT 1',
        [$fundoId, $servicio->codigo_rgb_id_animal_hembra]
    )->fetch();
    if (!$madreActiva) {
        return 'La madre del servicio debe estar activa y pertenecer a este fundo.';
    }

    return null;
}

/**
 * Si el módulo tiene 'correlativo' configurado, decide si hay que
 * generarlo ahora (siempre, o solo si el valor del cliente viene en
 * formato provisional "...-PEND-...") y lo resuelve reservando 1 del
 * contador real del fundo. Devuelve [$datos_actualizados, $codigo_anterior_o_null].
 */
function resolver_correlativo(array $datos, array $config, array $usuario, $conexion = null): array
{
    $correlativo = $config['correlativo'] ?? null;
    if (!$correlativo) {
        return [$datos, null];
    }

    $campo = $correlativo['campo'];
    $valorActual = $datos[$campo] ?? null;
    $esProvisional = $valorActual && strpos($valorActual, '-PEND-') !== false;
    $debeGenerar = !$correlativo['solo_si_provisional'] || $esProvisional;

    if (!$debeGenerar) {
        return [$datos, null];
    }

    $funcionesPermitidas = ['reservar_rango_animal', 'reservar_rango_servicio', 'reservar_rango_ternero'];
    if (!in_array($correlativo['funcion_sql'], $funcionesPermitidas, true)) {
        throw new \RuntimeException('Función de correlativo no reconocida: ' . $correlativo['funcion_sql']);
    }

    $conexion = $conexion ?? Musuario::connection();

    // El contador del fundo (manimalxfundos_contador, mnacimientoxfundos_
    // contador, mserviciosxfundos_contador) puede quedar POR DETRÁS de los
    // códigos que ya existen: cargas manuales, importaciones, restauraciones o
    // registros hechos desde la web con otro criterio. En ese caso la función
    // SQL entrega un número ya usado.
    //
    // En terneros es fatal: `mregnacimientos.codigo_ternero` tiene un índice
    // ÚNICO GLOBAL (constraint "Código del Ternero"), así que el INSERT revienta
    // y el productor veía "No se pudo registrar el nacimiento" sin forma de
    // resolverlo desde el equipo (2026-11-01: contador 15 con MA-02-016 ya
    // guardado). En servicios el choque es silencioso. Se pide el siguiente
    // número hasta encontrar uno libre.
    $tabla = (string) ($config['tabla'] ?? '');
    $intentosMaximos = 25;

    for ($intento = 1; $intento <= $intentosMaximos; $intento++) {
        $fila = $conexion->query(
            'SELECT * FROM ' . $correlativo['funcion_sql'] . '(?, ?)',
            [$usuario['fundo_id'], 1]
        )->fetch();
        $numero = (int) ($fila['desde'] ?? 0);
        $codigo = $correlativo['prefijo'] . '-0' . $usuario['fundo_id'] . '-0'
            . str_pad((string) $numero, 2, '0', STR_PAD_LEFT);

        $ocupado = false;
        if ($tabla !== '') {
            $existe = $conexion->query(
                "SELECT 1 FROM {$tabla} WHERE {$campo} = ? LIMIT 1",
                [$codigo]
            )->fetch();
            $ocupado = (bool) $existe;
        }

        if (!$ocupado) {
            $datos[$campo] = $codigo;
            return [$datos, $esProvisional ? $valorActual : null];
        }

        error_log('[SYNC correlativo] ' . $campo . ' = ' . $codigo
            . ' ya existe; se pide el siguiente (' . $intento . '/' . $intentosMaximos . ')');
    }

    throw new \RuntimeException('correlativo_sin_codigo_libre: ' . $campo);
}

/**
 * Deja en su forma canónica los campos que tienen formato propio antes de
 * validar y guardar. Hoy: `nro_arete` — mayúsculas, símbolos y espacios como
 * guion, máximo 15 caracteres (ver lib/arete_helper.php). El cliente (web u
 * offline) ya lo formatea; esto es la garantía del servidor.
 */
function formatear_campos_especiales(array $datos): array
{
    if (array_key_exists('nro_arete', $datos)) {
        $datos['nro_arete'] = bovs_arete_formatear($datos['nro_arete']);
    }

    return $datos;
}

/**
 * Comprueba la unicidad de los campos declarados en
 * config['campos_unicos'] para este fundo. Devuelve null si no hay
 * choque, o un arreglo con la info del registro existente que ya usa
 * ese valor. `nro_arete` se compara con la regla única de
 * lib/arete_helper.php (mayúsculas, guiones, máximo 15).
 *
 * Solo contra registros con status=1: un animal dado de baja libera su
 * arete, así que no debe bloquear.
 *
 * $idExcluir (opcional) es el id del PROPIO registro cuando se está
 * editando, para no encontrarse a sí mismo como duplicado si el
 * payload incluye un campo único sin haberlo cambiado.
 */
/**
 * Si la base rechazó el guardado por el índice único del N° de arete (dos
 * equipos subieron el mismo arete casi al mismo tiempo), devuelve el cambio
 * como CONFLICTO 'arete_duplicado' —que el operario sabe resolver desde la
 * pantalla de conflictos— en vez de un error genérico que se reintentaría sin
 * solución.
 */
function conflicto_arete_por_indice(\Throwable $e, array $datos, array $usuario, string $uuid): ?array
{
    $mensaje = $e->getMessage();
    $esChoque = (stripos($mensaje, '23505') !== false)
             || (stripos($mensaje, 'duplicate key') !== false);

    if (!$esChoque || stripos($mensaje, 'arete') === false) {
        return null;
    }
    if (empty($datos['nro_arete'])) {
        return null;
    }

    $existente = bovs_arete_existente((int) $usuario['fundo_id'], $datos['nro_arete']);

    return [
        'uuid_local' => $uuid,
        'resultado'  => 'conflicto',
        'tipo'       => 'arete_duplicado',
        'campo'      => 'nro_arete',
        'mensaje'    => 'El N° de arete ya está registrado en otro animal activo de este fundo.',
        'servidor'   => $existente ?: null,
        'tuyo'       => $datos,
    ];
}

function validar_campos_unicos(array $datos, array $config, array $usuario, string $modeloClase, ?int $idExcluir = null): ?array
{
    $camposUnicos = $config['campos_unicos'] ?? [];
    if (empty($camposUnicos)) return null;

    foreach ($camposUnicos as $campo => $tipoConflicto) {
        if (!array_key_exists($campo, $datos)) continue;
        $valor = trim((string) $datos[$campo]);
        if ($campo === 'nro_arete') {
            $valor = bovs_arete_normalizar($valor);
        }
        if ($valor === '') continue;

        /* nro_arete: se compara con la MISMA regla que la web y la app
           offline (lib/arete_helper.php) — mayúsculas y espacios
           normalizados, solo animales con status=1 del fundo. */
        if ($campo === 'nro_arete') {
            $existenteArete = bovs_arete_existente($usuario['fundo_id'], $valor, array(
                'excluir_id' => $idExcluir,
            ));
            if ($existenteArete) {
                return array(
                    'campo' => $campo,
                    'tipo' => $tipoConflicto,
                    'existente' => $existenteArete,
                );
            }
            continue;
        }

        $condiciones = ['UPPER(TRIM(' . $campo . ')) = ? and status = 1 and mfundos_id = ?'];
        $valores = [strtoupper($valor), $usuario['fundo_id']];

        if ($idExcluir !== null) {
            $condiciones[0] .= ' and id != ?';
            $valores[] = $idExcluir;
        }

        $existente = $modeloClase::find('first', [
            'conditions' => array_merge([$condiciones[0]], $valores),
        ]);

        if ($existente) {
            return [
                'campo' => $campo,
                'tipo' => $tipoConflicto,
                'existente' => $existente->to_array(),
            ];
        }
    }

    return null;
}

function validar_campos_requeridos(array $datos, array $requeridos): ?string
{
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo]) && $datos[$campo] !== '0') {
            return "Falta el campo requerido '$campo'.";
        }
    }
    return null;
}

/**
 * Rechaza registros que apunten a un animal con código PROVISIONAL
 * ('SOVCA-0x-PEND-…').
 *
 * Ese código lo inventa el dispositivo cuando no tiene rango reservado y
 * solo significa algo DENTRO del equipo: si el alta del animal todavía no
 * subió, guardar el control sanitario, el pesaje o el servicio con ese
 * código deja un registro huérfano (apunta a un animal que en el servidor
 * no existe) que además nadie puede relacionar después. Es preferible
 * rechazarlo con una instrucción clara: primero sincronizar el alta.
 */
function rechazar_codigos_provisionales(array $datos, array $config): ?string
{
    foreach ($config['campos_animal'] ?? [] as $campo) {
        $valor = $datos[$campo] ?? '';
        if (!is_string($valor) || $valor === '') {
            continue;
        }
        if (stripos($valor, '-PEND-') === false) {
            continue;
        }

        return 'Este registro apunta a un animal que todavía no tiene código definitivo en el servidor. '
             . 'Sincronice el equipo para que suba el alta del animal y vuelva a intentarlo.';
    }

    return null;
}

/**
 * PDO/PostgreSQL no interpreta bien un booleano PHP `false` en columnas
 * boolean: PHP lo convierte a "" (string vacío) al armar el parámetro,
 * y Postgres no puede parsear "" como boolean (SQLSTATE 22P02). `true`
 * no falla porque (string) true da "1", que Postgres sí entiende — por
 * eso el bug solo aparece con `false`. Se normaliza a 't'/'f' (la
 * representación nativa de Postgres) antes de asignar al modelo, para
 * cualquier campo booleano de cualquier módulo (hoy: nac_fundo).
 */
function normalizar_valor($valor)
{
    if (is_bool($valor)) {
        return $valor ? 't' : 'f';
    }
    return $valor;
}

function completar_textos_obligatorios(array &$datos, array $campos): void
{
    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $datos)) {
            $datos[$campo] = '';
        }
    }
}

/**
 * Completa campos que el cliente nunca manda (no están en ningún
 * formulario) pero necesitan un valor razonable al crear. A propósito
 * separado de completar_textos_obligatorios(): ese es para NOT NULL
 * de texto sin valor por defecto sensato ('' está bien); esto es para
 * campos donde SÍ hay un default de negocio concreto (hoy: nac_fundo
 * => true, "se asume que el animal nació en este fundo salvo que se
 * indique lo contrario" — pendiente de agregar ese campo al
 * formulario si en algún momento hace falta editarlo).
 */
function completar_valores_por_defecto(array &$datos, array $defaults): void
{
    foreach ($defaults as $campo => $valorDefault) {
        if (!array_key_exists($campo, $datos)) {
            $datos[$campo] = $valorDefault;
        }
    }
}

/**
 * Rellena con 0 los enteros que la tabla declara NOT NULL y que no tienen
 * default, cuando el cliente no los manda o los manda en null.
 *
 * Es el caso de `aptitud`, `mrazas_id` y `servicio`: en JavaScript
 * Number('') / Number(undefined) dan NaN, que JSON.stringify convierte en
 * null, y Postgres rechazaba el INSERT con
 * "null value in column ... violates not-null constraint".
 *
 * Se usa SOLO al crear: al editar hay que respetar lo que ya está guardado
 * (ver limpiar_enteros_nulos()).
 */
/**
 * Descarta del payload los campos que llegan en null.
 *
 * En la creación desde el equipo (crear_registro / crear_registro_servicio)
 * un null explícito se escribe tal cual en el INSERT y gana sobre el
 * DEFAULT de la tabla, así que un campo del seguimiento que la app manda
 * en null (`nro_terneros`, `dnp`, `f_palpacion`, ...) tumbaba la
 * sincronización con "null value in column ... violates not-null
 * constraint". Al no enviar la columna, la base aplica su default (0 para
 * los enteros) o la deja en null si la columna lo permite.
 */
function quitar_valores_nulos(array &$datos): void
{
    foreach ($datos as $campo => $valor) {
        if ($valor === null) {
            unset($datos[$campo]);
        }
    }
}

function completar_enteros_con_defecto(array &$datos, array $defaults): void
{
    foreach ($defaults as $campo => $valorDefault) {
        if (!array_key_exists($campo, $datos) || $datos[$campo] === null || $datos[$campo] === '') {
            $datos[$campo] = $valorDefault;
        } else {
            $datos[$campo] = (int) $datos[$campo];
        }
    }
}

/**
 * Igual que completar_enteros_con_defecto() pero para las EDICIONES: un
 * campo que llega en null o vacío simplemente se OMITE, no se pisa con 0
 * el valor que ya está guardado en la base.
 */
function limpiar_enteros_nulos(array &$datos, array $campos): void
{
    foreach ($campos as $campo => $_) {
        if (!array_key_exists($campo, $datos)) {
            continue;
        }
        if ($datos[$campo] === null || $datos[$campo] === '') {
            unset($datos[$campo]);
        } else {
            $datos[$campo] = (int) $datos[$campo];
        }
    }
}

function actualizar_registro($existente, array $cambio, array $config, array $usuario): array
{
    $uuid = $existente->uuid_local;
    $versionBase = $cambio['version_base'] ?? null;
    $versionActual = (int) $existente->version;

    // Reintento idempotente: el cliente no traía base de versión
    // (posible reenvío tras corte de conexión a mitad de sync). No se
    // toca nada, solo se informa el estado actual.
    if ($versionBase === null) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'sin_cambios',
            'version' => $versionActual,
            'updated' => $existente->updated,
        ];
    }

    if ((int) $versionBase !== $versionActual) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'conflicto',
            'mensaje' => 'El registro cambió en el servidor después de tu última sincronización.',
            'version_servidor' => $versionActual,
            'servidor' => $existente->to_array(),
            'tuyo' => $cambio['datos'] ?? [],
        ];
    }

    $datos = \Api\SyncModulos::filtrarCampos($cambio['datos'] ?? [], $config['campos_editables']);
    $datos = formatear_campos_especiales($datos);

    /* Nacimientos: el tipo de servicio es obligatorio (ver
       crear_registro_nacimiento). Si el cliente manda el campo vacío se
       rechaza la edición en vez de dejar el parto sin tipo, que lo haría
       desaparecer de los listados. Cuando el campo no viene (cliente viejo)
       no se toca el valor guardado. */
    if (($config['tabla'] ?? '') === 'mregnacimientos' && array_key_exists('mtiposervicios_id', $datos)) {
        $tipoNac = (int) $datos['mtiposervicios_id'];
        $tipoNacOk = ($tipoNac > 0)
            && (bool) Mtiposervicio::find('first', ['conditions' => ['id = ? and status = 1', $tipoNac]]);
        if (!$tipoNacOk) {
            return [
                'uuid_local' => $uuid,
                'resultado'  => 'error',
                'mensaje'    => 'Selecciona el tipo de servicio del parto.',
            ];
        }
    }

    /* Enteros NOT NULL (aptitud, mrazas_id, servicio): si llegan en null o
       vacíos se quitan del UPDATE para NO pisar el valor ya guardado. */
    limpiar_enteros_nulos($datos, $config['enteros_con_defecto'] ?? []);

    // Al editar también se controla el duplicado por animal + fecha
    // (por ejemplo, mover un pesaje a un día que ya tiene otro).
    $errorDuplicado = validar_duplicado_por_fecha($config, $datos, $usuario, (int) $existente->id);
    if ($errorDuplicado) {
        return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorDuplicado];
    }

    // Nacimientos: el propósito (1 = lactancia, 2 = producción de leche)
    // define si la cría entra a producción; la pregunta "¿Entrará a
    // producción de leche?" ya no existe en los formularios.
    if (($config['tabla'] ?? '') === 'mregnacimientos' && array_key_exists('proposito', $datos)) {
        $datos['produccion_leche'] = in_array((int) $datos['proposito'], [1, 2], true) ? 1 : 0;
    }

    $actualizarProductos = !empty($config['sincroniza_productos_aplicados'])
        && array_key_exists('productos_aplicados', $cambio['datos'] ?? []);
    $productosAplicados = [];
    if ($actualizarProductos) {
        [$productosAplicados, $errorProductos] = validar_productos_control_sanitario(
            $cambio['datos']['productos_aplicados'],
            (int) $usuario['fundo_id']
        );
        if ($errorProductos) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductos];
        }
    }

    // Nacimientos: si el dispositivo manda la lista de crías se
    // reemplaza el detalle completo (mternerosxnacimientos), igual que
    // hace la app web al editar un parto.
    $actualizarTerneros = !empty($config['sincroniza_terneros'])
        && array_key_exists('terneros', $cambio['datos'] ?? []);
    $terneros = [];
    if ($actualizarTerneros) {
        [$terneros, $errorTerneros] = normalizar_terneros_nacimiento($cambio['datos'], $datos);
        if ($errorTerneros) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorTerneros];
        }
    }

    // Control sanitario por lotes: si el dispositivo manda productos o
    // animales se reemplaza el detalle completo del control.
    $actualizarDetalleLote = !empty($config['calcula_control_lote'])
        && (array_key_exists('productos_aplicados', $cambio['datos'] ?? [])
            || array_key_exists('animales', $cambio['datos'] ?? []));
    $productosLote = [];
    $animalesLote = [];
    if ($actualizarDetalleLote) {
        [$productosLote, $errorProductosLote] = validar_productos_control_sanitario(
            $cambio['datos']['productos_aplicados'] ?? [],
            (int) $usuario['fundo_id']
        );
        if ($errorProductosLote) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductosLote];
        }
        [$animalesLote, $errorAnimalesLote] = validar_animales_control_lote(
            $cambio['datos']['animales'] ?? [],
            (int) $usuario['fundo_id']
        );
        if ($errorAnimalesLote) {
            return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorAnimalesLote];
        }
    }

    // Validación de unicidad también al editar (defensa en profundidad):
    // el cliente bloquea el arete al editar, así que esto no debería
    // dispararse en uso normal — pero si algún cliente malicioso o un
    // bug futuro manda un nro_arete ajeno en un update, acá se corta.
    $conflictoUnicidad = validar_campos_unicos(
        $datos,
        $config,
        $usuario,
        get_class($existente),
        (int) $existente->id
    );
    if ($conflictoUnicidad) {
        return [
            'uuid_local' => $uuid,
            'resultado' => 'conflicto',
            'tipo' => $conflictoUnicidad['tipo'],
            'campo' => $conflictoUnicidad['campo'],
            'mensaje' => 'El valor de ' . $conflictoUnicidad['campo'] . ' ya está registrado en otro registro activo de este fundo.',
            'servidor' => $conflictoUnicidad['existente'],
            'tuyo' => $datos,
        ];
    }

    $conexionDetalles = ($actualizarProductos || $actualizarTerneros || $actualizarDetalleLote) ? Musuario::connection() : null;
    if ($conexionDetalles) $conexionDetalles->transaction();

    try {
        foreach ($datos as $campo => $valor) {
            $existente->$campo = normalizar_valor($valor);
        }

        $existente->version = $versionActual + 1;
        $existente->updated = date('Y-m-d H:i:s');
        $existente->user_modify = $usuario['usuario_id'];

    // Excepción específica de 'servicios': si esta edición confirma un
    // parto (medofisiologicos_id=1 y parto=1), hay que cerrar
    // cualquier OTRO ciclo activo de la misma hembra — replica el
    // comportamiento que ya tiene la app web hoy en 'edit_control'.
    if (!empty($config['usa_ciclo_reproductivo'])
        && (int) ($datos['medofisiologicos_id'] ?? 0) === 1
        && (int) ($datos['parto'] ?? 0) === 1
    ) {
        $existente->estatus_ciclo = 2;
        $existente->estado_servicio = 2;
        cerrar_otros_ciclos_activos(
            $existente->codigo_rgb_id_animal_hembra,
            (int) $existente->id,
            $usuario['fundo_id'],
            $usuario['usuario_id']
        );
    }

        if (!$existente->save()) {
            if ($conexionDetalles) $conexionDetalles->rollBack();
            return [
                'uuid_local' => $uuid,
                'resultado' => 'error',
                'mensaje' => 'Datos inválidos: ' . primer_error_validacion($existente),
            ];
        }

        if ($actualizarProductos) {
            $errorProductos = reemplazar_productos_control_sanitario(
                (int) $existente->id,
                $productosAplicados,
                $usuario
            );
            if ($errorProductos) {
                $conexionDetalles->rollBack();
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorProductos];
            }
        }

        if ($actualizarTerneros) {
            $errorTernerosDetalle = reemplazar_terneros_nacimiento(
                (int) $existente->id,
                $terneros,
                $usuario
            );
            if ($errorTernerosDetalle) {
                $conexionDetalles->rollBack();
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorTernerosDetalle];
            }
        }

        if ($actualizarDetalleLote) {
            $errorDetalleLote = reemplazar_detalle_control_lote(
                (int) $existente->id,
                (string) ($existente->codigo_mcs_lote ?? ''),
                (int) ($existente->mlotes_id ?? 0),
                $productosLote,
                $animalesLote,
                $usuario
            );
            if ($errorDetalleLote) {
                $conexionDetalles->rollBack();
                return ['uuid_local' => $uuid, 'resultado' => 'error', 'mensaje' => $errorDetalleLote];
            }
        }

        if ($conexionDetalles) $conexionDetalles->commit();
    } catch (\Throwable $e) {
        if ($conexionDetalles) $conexionDetalles->rollBack();

        $conflictoArete = conflicto_arete_por_indice($e, $datos, $usuario, $uuid);
        if ($conflictoArete) return $conflictoArete;

        throw $e;
    }

    return [
        'uuid_local' => $uuid,
        'resultado' => 'actualizado',
        'version' => (int) $existente->version,
        'updated' => $existente->updated,
    ];
}

function validar_productos_control_sanitario($productos, int $fundoId): array
{
    if (!is_array($productos)) {
        return [[], 'La lista de productos aplicados tiene un formato inválido.'];
    }
    if (count($productos) > 100) {
        return [[], 'Un control sanitario no puede tener más de 100 productos aplicados.'];
    }

    $validados = [];
    foreach ($productos as $indice => $producto) {
        if (!is_array($producto)) {
            return [[], 'El detalle de producto #' . ($indice + 1) . ' no es válido.'];
        }

        $productoId = filter_var($producto['mproductos_id'] ?? null, FILTER_VALIDATE_INT);
        $dosis = trim((string) ($producto['dosis'] ?? ''));
        if (!$productoId || $productoId < 1 || $dosis === '') {
            return [[], 'Cada producto aplicado debe incluir producto y dosis.'];
        }

        $productoCatalogo = Mproducto::find('first', [
            'conditions' => [
                'id = ? and status = 1 and (mfundos_id = ? or mfundos_id = 0)',
                $productoId, $fundoId,
            ],
        ]);
        if (!$productoCatalogo) {
            return [[], 'Uno de los productos ya no está activo o no pertenece a este fundo. Actualiza el catálogo e intenta de nuevo.'];
        }

        $validados[] = [
            'mproductos_id' => (int) $productoCatalogo->id,
            'dosis' => $dosis,
            'detalles' => trim((string) ($producto['detalles'] ?? '')),
        ];
    }

    return [$validados, null];
}

function guardar_productos_control_sanitario(int $controlId, array $productos, array $usuario): ?string
{
    foreach ($productos as $producto) {
        $detalle = new Mdetacrlsanitario();
        $detalle->mcontrolsanitarios_id = $controlId;
        $detalle->mproductos_id = $producto['mproductos_id'];
        $detalle->dosis = $producto['dosis'];
        $detalle->detalles = $producto['detalles'];
        $detalle->mfundos_id = (int) $usuario['fundo_id'];
        $detalle->user_create = (int) $usuario['usuario_id'];
        $detalle->created = date('Y-m-d H:i:s');
        $detalle->status = 1;

        if (!$detalle->save()) {
            return 'No se pudo guardar uno de los productos aplicados: ' . primer_error_validacion($detalle);
        }
    }

    return null;
}

function reemplazar_productos_control_sanitario(int $controlId, array $productos, array $usuario): ?string
{
    $actuales = Mdetacrlsanitario::find('all', [
        'conditions' => [
            'mcontrolsanitarios_id = ? and mfundos_id = ? and status = 1',
            $controlId, (int) $usuario['fundo_id'],
        ],
    ]);

    foreach ($actuales as $detalle) {
        if (!$detalle->delete()) {
            return 'No se pudieron actualizar los productos aplicados del control.';
        }
    }

    return guardar_productos_control_sanitario($controlId, $productos, $usuario);
}

function adjuntar_productos_control_sanitario(array $filas, int $fundoId): array
{
    $ids = array_values(array_unique(array_map(
        'intval',
        array_column($filas, 'id')
    )));
    if (!$ids) return $filas;

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT d.mcontrolsanitarios_id,
                   d.mproductos_id,
                   COALESCE(p.nombre, 'Producto') AS producto_nombre,
                   d.dosis,
                   d.detalles
            FROM mdetacrlsanitarios d
            LEFT JOIN mproductos p ON p.id = d.mproductos_id
            WHERE d.mfundos_id = ?
              AND d.status = 1
              AND d.mcontrolsanitarios_id IN ({$marcadores})
            ORDER BY d.id ASC";
    $valores = array_merge([$fundoId], $ids);
    $resultado = Musuario::connection()->query($sql, $valores)->fetchAll();

    $porControl = [];
    foreach ($resultado as $detalle) {
        $controlId = (int) $detalle['mcontrolsanitarios_id'];
        $porControl[$controlId][] = [
            'mproductos_id' => (int) $detalle['mproductos_id'],
            'producto_nombre' => (string) $detalle['producto_nombre'],
            'dosis' => (string) ($detalle['dosis'] ?? ''),
            'detalles' => (string) ($detalle['detalles'] ?? ''),
        ];
    }

    foreach ($filas as &$fila) {
        $fila['productos_aplicados'] = $porControl[(int) $fila['id']] ?? [];
    }
    unset($fila);

    return $filas;
}

/**
 * Cierra (estatus_ciclo=2, estado_servicio=2) cualquier otro registro
 * de servicio con ciclo activo para la misma hembra — se usa cuando se
 * confirma un parto, para que no queden dos ciclos "abiertos" a la vez.
 */
function cerrar_otros_ciclos_activos(string $codigoHembra, int $idExcluir, int $fundoId, int $usuarioId): void
{
    $otros = \Mregservicio::find('all', [
        'conditions' => [
            'codigo_rgb_id_animal_hembra = ? and estatus_ciclo = 1 and mfundos_id = ? and id != ?',
            $codigoHembra, $fundoId, $idExcluir,
        ],
    ]);

    foreach ($otros as $otro) {
        $otro->estatus_ciclo = 2;
        $otro->estado_servicio = 2;
        $otro->user_modify = $usuarioId;
        $otro->updated = date('Y-m-d H:i:s');
        $otro->save();
    }
}

function primer_error_validacion($registro): string
{
    $errores = $registro->errors ?? null;

    if ($errores instanceof \ActiveRecord\Errors) {
        $mensajes = $errores->full_messages();
        if (!empty($mensajes)) {
            return $mensajes[0];
        }
    }

    return 'revisa los datos enviados.';
}

/**
 * Adjunta a cada fila del pull de movimientos la lista de animales movidos
 * (con lote/potrero/categoría antes y después) y los nombres legibles de
 * lote y potrero, para que el dispositivo muestre el detalle sin consultar
 * el servidor otra vez.
 */
function adjuntar_animales_movimiento_rebano(array $filas): array
{
    $ids = [];
    foreach ($filas as $fila) {
        if (!empty($fila['id'])) {
            $ids[] = (int) $fila['id'];
        }
    }

    if (!$ids) {
        return $filas;
    }

    $conexion = Musuario::connection();

    $detalles = [];
    $nombreLote = [];
    $nombrePotrero = [];

    try {
        $sql = 'SELECT d.mmovimientos_rebano_id, d.manimales_id, d.codigo_rgb_id_animal,
                       d.lote_anterior_id, d.lote_nuevo_id,
                       d.potrero_anterior_id, d.potrero_nuevo_id,
                       d.categoria_anterior_id, d.categoria_nueva_id,
                       lo.nombre AS lote_anterior_nombre,
                       ln.nombre AS lote_nuevo_nombre,
                       po.nombre AS potrero_anterior_nombre,
                       pn.nombre AS potrero_nuevo_nombre,
                       ca.nombre AS categoria_anterior_nombre,
                       cn.nombre AS categoria_nueva_nombre
                  FROM mmovimientos_rebano_detalle d
                  LEFT JOIN mlotes lo ON lo.id = d.lote_anterior_id
                  LEFT JOIN mlotes ln ON ln.id = d.lote_nuevo_id
                  LEFT JOIN mpotreros po ON po.id = d.potrero_anterior_id
                  LEFT JOIN mpotreros pn ON pn.id = d.potrero_nuevo_id
                  LEFT JOIN mcategorias ca ON ca.id = d.categoria_anterior_id
                  LEFT JOIN mcategorias cn ON cn.id = d.categoria_nueva_id
                 WHERE d.mmovimientos_rebano_id IN (?)';

        $params = $ids;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = str_replace('(?)', '(' . $in . ')', $sql);

        foreach ($conexion->query($sql, $params)->fetchAll() as $fila) {
            $clave = (int) $fila['mmovimientos_rebano_id'];
            $detalles[$clave][] = [
                'manimales_id' => (int) $fila['manimales_id'],
                'codigo_rgb_id_animal' => $fila['codigo_rgb_id_animal'],
                'lote_anterior_id' => $fila['lote_anterior_id'] !== null ? (int) $fila['lote_anterior_id'] : null,
                'lote_nuevo_id' => $fila['lote_nuevo_id'] !== null ? (int) $fila['lote_nuevo_id'] : null,
                'potrero_anterior_id' => $fila['potrero_anterior_id'] !== null ? (int) $fila['potrero_anterior_id'] : null,
                'potrero_nuevo_id' => $fila['potrero_nuevo_id'] !== null ? (int) $fila['potrero_nuevo_id'] : null,
                'categoria_anterior_id' => $fila['categoria_anterior_id'] !== null ? (int) $fila['categoria_anterior_id'] : null,
                'categoria_nueva_id' => $fila['categoria_nueva_id'] !== null ? (int) $fila['categoria_nueva_id'] : null,
                'lote_anterior' => $fila['lote_anterior_nombre'],
                'lote_nuevo' => $fila['lote_nuevo_nombre'],
                'potrero_anterior' => $fila['potrero_anterior_nombre'],
                'potrero_nuevo' => $fila['potrero_nuevo_nombre'],
                'categoria_anterior' => $fila['categoria_anterior_nombre'],
                'categoria_nueva' => $fila['categoria_nueva_nombre'],
            ];
        }
    } catch (\Throwable $e) {
        error_log('[SYNC movimiento_rebano detalle] ' . $e->getMessage());
    }

    foreach ($filas as &$fila) {
        $clave = (int) ($fila['id'] ?? 0);
        $fila['animales'] = $detalles[$clave] ?? [];
        $fila['total_animales'] = count($fila['animales']);
    }
    unset($fila);

    return $filas;
}