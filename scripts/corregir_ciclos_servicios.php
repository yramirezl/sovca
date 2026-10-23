<?php

/**
 * scripts/corregir_ciclos_servicios.php
 *
 * Deja UN SOLO servicio abierto por hembra y fundo.
 *
 * PROBLEMA QUE CORRIGE
 * --------------------
 * Al registrar un servicio nuevo, el sistema reutiliza el ciclo abierto y no
 * cierra los anteriores. Con el tiempo una hembra acumula varios registros con
 * estatus_ciclo = 1 (el último, por ejemplo, de 2021), y la vista
 * Vregservicios calcula la "condición" de cada fila con el estado fisiológico
 * de esa fila. Resultado: servicios viejos siguen anunciando
 * "Disponible para Servicio" como si fueran el estatus actual de la hembra.
 *
 * QUÉ HACE
 * --------
 * Para cada (mfundos_id, codigo_rgb_id_animal_hembra) toma el registro con
 * max(id) y cierra los anteriores que sigan abiertos:
 *
 *      estatus_ciclo  = 2   (ciclo cerrado)
 *      estado_servicio = 2  (servicio cerrado)
 *
 * El registro más reciente NO se toca nunca: sigue siendo el vigente.
 * No modifica estado fisiológico, partos, palpaciones ni fechas.
 *
 * USO (siempre primero en simulación)
 * -----------------------------------
 *   cd /var/www/html/bovs
 *   php scripts/corregir_ciclos_servicios.php                    # simulación
 *   php scripts/corregir_ciclos_servicios.php --fundo=59
 *   php scripts/corregir_ciclos_servicios.php --aplicar          # escribe
 *
 * OPCIONES
 *   --fundo=ID    Limita la corrección a un fundo.
 *   --limite=N    Procesa como máximo N registros (prueba controlada).
 *   --lote=N      Filas por transacción (por defecto 500).
 *   --aplicar     Ejecuta los cambios. Sin esta opción es simulación.
 *   --ayuda       Muestra esta ayuda.
 *
 * AUDITORÍA / REVERSIÓN
 *   En modo --aplicar guarda el estado anterior de cada fila en
 *   correccion_ciclos_servicios_log, agrupado por un código de lote que permite
 *   revertir con scripts/corregir_ciclos_servicios_revertir.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se puede ejecutar por consola (CLI).\n");
}

// Los require relativos de config/conexion.php se resuelven desde el CWD,
// igual que cuando los controladores usan rutas relativas.
chdir(__DIR__ . '/../controllers');
require_once '../config/conexion.php';

$opciones = getopt('', array('fundo:', 'limite:', 'lote:', 'aplicar', 'ayuda'));

if (isset($opciones['ayuda'])) {
    echo ayuda_ciclos();
    exit(0);
}

$fundo   = isset($opciones['fundo']) ? max(0, (int)$opciones['fundo']) : 0;
$limite  = isset($opciones['limite']) ? max(0, (int)$opciones['limite']) : 0;
$tam     = isset($opciones['lote']) ? max(1, (int)$opciones['lote']) : 500;
$aplicar = isset($opciones['aplicar']);
$lote_id = date('Ymd_His');

try {
    $conexion = ActiveRecord\ConnectionManager::get_connection();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: no se pudo conectar a la base de datos: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\n";
echo "==============================================================\n";
echo " CORRECCIÓN DE CICLOS DE SERVICIO ABIERTOS\n";
echo "==============================================================\n";
echo ' Modo   : ' . ($aplicar ? 'APLICAR (escribe en la base)' : 'SIMULACIÓN (no escribe nada)') . "\n";
echo ' Fundo  : ' . ($fundo > 0 ? $fundo : 'todos') . "\n";
echo ' Límite : ' . ($limite > 0 ? $limite . ' registros' : 'sin límite') . "\n";
echo " Lote   : $tam filas por transacción\n";
if ($aplicar) {
    echo " Código : $lote_id\n";
}
echo "==============================================================\n\n";

if ($aplicar) {
    crear_tabla_log_ciclos($conexion);
}

try {
    $candidatos = buscar_candidatos_ciclos($conexion, $fundo, $limite);

    if (!$candidatos) {
        echo "No hay servicios abiertos de ciclos anteriores. Nada que corregir.\n\n";
        exit(0);
    }

    $total      = count($candidatos);
    $animales   = array();
    $fundos     = array();
    foreach ($candidatos as $fila) {
        $animales[$fila['mfundos_id'] . '|' . $fila['codigo_rgb_id_animal_hembra']] = true;
        $fundos[$fila['mfundos_id']] = true;
    }

    echo "Registros a cerrar : $total\n";
    echo 'Animales afectados: ' . count($animales) . "\n";
    echo 'Fundos afectados   : ' . count($fundos) . "\n\n";

    echo "Primeros registros (máximo 20):\n";
    echo "  " . str_pad('id', 8) . str_pad('fundo', 7) . str_pad('hembra', 18)
       . str_pad('servicio', 12) . str_pad('nro', 5) . "vigente (id)\n";
    echo "  " . str_repeat('-', 68) . "\n";
    foreach (array_slice($candidatos, 0, 20) as $fila) {
        echo '  ' . str_pad($fila['id'], 8)
           . str_pad($fila['mfundos_id'], 7)
           . str_pad($fila['codigo_rgb_id_animal_hembra'], 18)
           . str_pad(formatear_fecha($fila['f_servicio']), 12)
           . str_pad($fila['nro_servicio'], 5)
           . $fila['max_id'] . "\n";
    }
    if ($total > 20) {
        echo '  … y ' . ($total - 20) . " más\n";
    }
    echo "\n";

    if (!$aplicar) {
        echo "SIMULACIÓN: no se modificó ningún registro.\n";
        echo "Para aplicar los cambios ejecute el mismo comando con --aplicar.\n\n";
        exit(0);
    }

    $cerrados = aplicar_cierre_ciclos($conexion, $candidatos, $tam, $lote_id);

    echo "Registros cerrados : $cerrados\n\n";
    echo "Lote aplicado: $lote_id\n";
    echo "Revertir con:\n";
    echo "  psql -h HOST -U USUARIO -d BASE -v lote=\"$lote_id\" -f scripts/corregir_ciclos_servicios_revertir.sql\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nERROR durante la corrección: " . $e->getMessage() . "\n");
    if ($aplicar && isset($lote_id)) {
        fwrite(STDERR, "Los lotes ya confirmados quedaron registrados con el código $lote_id.\n");
    }
    exit(1);
}

/* ====================================================================== */

function ayuda_ciclos()
{
    return <<<TXT

corregir_ciclos_servicios.php — deja un solo servicio abierto por hembra

USO
  php scripts/corregir_ciclos_servicios.php                 (simulación)
  php scripts/corregir_ciclos_servicios.php --fundo=59      (simulación de un fundo)
  php scripts/corregir_ciclos_servicios.php --aplicar       (escribe en la base)

OPCIONES
  --fundo=ID    Limita la corrección a un fundo.
  --limite=N    Procesa como máximo N registros.
  --lote=N      Filas por transacción (por defecto 500).
  --aplicar     Ejecuta los cambios.
  --ayuda       Muestra esta ayuda.

QUÉ CAMBIA
  Solo los servicios que NO son el último de la hembra y que siguen abiertos:
      estatus_ciclo   -> 2
      estado_servicio -> 2
  El último servicio (max id) nunca se modifica.

REVERSIÓN
  psql ... -v lote="CODIGO" -f scripts/corregir_ciclos_servicios_revertir.sql

TXT;
}

function formatear_fecha($valor)
{
    if ($valor instanceof DateTime) {
        return $valor->format('d-m-Y');
    }
    $texto = trim((string)$valor);
    if ($texto === '') {
        return '—';
    }
    $ts = strtotime($texto);
    return $ts ? date('d-m-Y', $ts) : '—';
}

/**
 * Registros abiertos que NO son el último de su hembra en el fundo.
 * El último (max id) queda siempre como vigente.
 */
function buscar_candidatos_ciclos($conexion, $fundo, $limite)
{
    $where_fundo = ($fundo > 0) ? ' and s.mfundos_id = ' . (int)$fundo : '';
    $where_limite = ($limite > 0) ? ' limit ' . (int)$limite : '';

    $sql = "select s.id,
                   s.mfundos_id,
                   s.codigo_rgb_id_animal_hembra,
                   s.ciclo_reproductivo,
                   s.nro_servicio,
                   s.f_servicio,
                   s.estatus_ciclo,
                   s.estado_servicio,
                   s.medofisiologicos_id,
                   u.max_id
              from mregservicios s
              join (select mfundos_id,
                           codigo_rgb_id_animal_hembra as cod,
                           max(id) as max_id
                      from mregservicios
                     where status = 1
                     group by 1, 2) u
                on u.mfundos_id = s.mfundos_id
               and u.cod = s.codigo_rgb_id_animal_hembra
             where s.status = 1
               and s.estatus_ciclo = 1
               and s.id < u.max_id
               $where_fundo
             order by s.mfundos_id, s.codigo_rgb_id_animal_hembra, s.id
             $where_limite;";

    $sth = $conexion->query($sql);

    return $sth ? $sth->fetchAll(PDO::FETCH_ASSOC) : array();
}

function crear_tabla_log_ciclos($conexion)
{
    $conexion->query("
        create table if not exists correccion_ciclos_servicios_log (
            id serial primary key,
            lote varchar(40) not null,
            mregservicios_id integer not null,
            mfundos_id integer,
            codigo_rgb_id_animal_hembra varchar(60),
            ciclo_reproductivo integer,
            nro_servicio smallint,
            f_servicio timestamp,
            estatus_ciclo_antes smallint,
            estado_servicio_antes smallint,
            medofisiologicos_id integer,
            aplicado_en timestamp not null default now()
        );
    ");
    $conexion->query("create index if not exists correccion_ciclos_servicios_log_lote_idx
                      on correccion_ciclos_servicios_log (lote);");
}

/**
 * Inserta la auditoría y cierra los registros por lotes.
 * Cada lote es una transacción independiente: si algo falla a mitad de camino,
 * lo ya confirmado queda en el log y se puede revertir por lote.
 */
function aplicar_cierre_ciclos($conexion, $candidatos, $tam, $lote_id)
{
    $cerrados = 0;
    $lotes    = array_chunk($candidatos, $tam);

    foreach ($lotes as $indice => $grupo) {
        $conexion->transaction();

        try {
            $ids = array();
            foreach ($grupo as $fila) {
                $ids[] = (int)$fila['id'];

                $conexion->query(
                    "insert into correccion_ciclos_servicios_log
                        (lote, mregservicios_id, mfundos_id, codigo_rgb_id_animal_hembra,
                         ciclo_reproductivo, nro_servicio, f_servicio,
                         estatus_ciclo_antes, estado_servicio_antes, medofisiologicos_id)
                     values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array(
                        $lote_id,
                        (int)$fila['id'],
                        (int)$fila['mfundos_id'],
                        (string)$fila['codigo_rgb_id_animal_hembra'],
                        (int)$fila['ciclo_reproductivo'],
                        (int)$fila['nro_servicio'],
                        $fila['f_servicio'],
                        (int)$fila['estatus_ciclo'],
                        (int)$fila['estado_servicio'],
                        (int)$fila['medofisiologicos_id'],
                    )
                );
            }

            $lista_ids = implode(',', $ids);
            $conexion->query(
                "update mregservicios
                    set estatus_ciclo = 2,
                        estado_servicio = 2,
                        updated = now()
                  where id in ($lista_ids)
                    and status = 1
                    and estatus_ciclo = 1;"
            );

            $conexion->commit();
            $cerrados += count($ids);

            echo '  Lote ' . ($indice + 1) . '/' . count($lotes) . ' — ' . count($ids) . " registros\n";
        } catch (Throwable $e) {
            $conexion->rollback();
            throw $e;
        }
    }

    return $cerrados;
}
