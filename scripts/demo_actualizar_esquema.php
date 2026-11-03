<?php
/**
 * =====================================================================
 * scripts/demo_actualizar_esquema.php — MANTIENE sovca_demo AL DÍA
 * ---------------------------------------------------------------------
 * El modo demostración usa una base aparte (`sovca_demo`) que se creó una
 * sola vez con `scripts/demo_instalar.php` a partir del esquema de la
 * base real. Desde entonces, cada mejora de SOVCA (vistas corregidas,
 * columnas nuevas, funciones, índices, restricciones, disparadores)
 * quedaba SOLO en la base real: la demo se quedaba con las vistas
 * viejas y sus pantallas fallaban o mostraban datos incompletos.
 *
 * Este script compara el esquema de la base real con el de la demo y
 * aplica las diferencias SIN tocar los datos de la demo:
 *
 *   · secuencias y tablas que falten,
 *   · columnas nuevas / con otro tipo / con otro valor por defecto,
 *   · funciones (CREATE OR REPLACE),
 *   · vistas (CREATE OR REPLACE; si cambiaron las columnas, se recrean),
 *   · índices, restricciones y disparadores.
 *
 * NUNCA borra datos ni tablas: solo agrega y reemplaza definiciones.
 * Lo único destructivo (quitar columnas o rehacer restricciones cuyas
 * definiciones cambiaron) se hace solo con --prune.
 *
 * USO
 *   php scripts/demo_actualizar_esquema.php            # informe (no cambia nada)
 *   php scripts/demo_actualizar_esquema.php --aplicar  # aplica los cambios
 *   php scripts/demo_actualizar_esquema.php --aplicar -v
 *   php scripts/demo_actualizar_esquema.php --aplicar --sql=scripts/migracion_x.sql
 *   php scripts/demo_actualizar_esquema.php --origen=sovca_server --destino=sovca_demo
 *
 * OPCIONES
 *   --aplicar        Ejecuta los cambios en la demo. Sin esta opción solo informa.
 *   --prune          Además quita columnas que ya no existen en el origen y
 *                    rehace las restricciones cuya definición cambió.
 *   --verificar      Como el informe, pero termina con código 1 si hay cambios
 *                    pendientes (útil en cron o en el script de despliegue).
 *   --sql=ARCHIVO    Aplica ese archivo SQL a la demo (se puede repetir).
 *                    Sirve para que una migración nueva corra también en la demo.
 *   --origen=BASE    Base real de la que se copia el esquema (def. SOVCA_DB_NAME).
 *   --destino=BASE   Base a actualizar (def. SOVCA_DB_DEMO_NAME / sovca_demo).
 *   -v, --verbose    Muestra el SQL de cada cambio.
 *   --ayuda          Muestra esta ayuda.
 *
 * REQUISITOS
 *   · Solo consola (se corta por HTTP).
 *   · PDO pgsql (ya lo usa lib/demo_helper.php).
 *   · `psql` en el PATH solo si se usa --sql.
 *
 * Orden recomendado en un despliegue:
 *   1) aplicar las migraciones a la base real,
 *   2) php scripts/demo_actualizar_esquema.php --aplicar \
 *        --sql=scripts/migracion_lo_que_sea.sql
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por consola.\n");
}

require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/../lib/demo_helper.php';

/* ---------------------------------------------------------------------
   OPCIONES
   --------------------------------------------------------------------- */
$opciones = getopt('', array(
    'origen::', 'destino::', 'aplicar', 'prune', 'verificar', 'sql:', 'verbose', 'v', 'ayuda', 'help',
));

if (isset($opciones['ayuda']) || isset($opciones['help'])) {
    echo "Mantiene al día el esquema de la base de la demostración\n\n";
    echo "  php scripts/demo_actualizar_esquema.php [--aplicar] [--prune] [-v]\n";
    echo "        [--sql=archivo.sql] [--origen=BASE] [--destino=BASE]\n\n";
    echo "  --aplicar      Ejecuta los cambios (sin esto solo informa).\n";
    echo "  --prune        Quita columnas sobrantes y rehace restricciones cambiadas.\n";
    echo "  --verificar    Sale con código 1 si encontró diferencias (para cron).\n";
    echo "  --sql=ARCHIVO  Aplica ese SQL a la demo (se puede repetir).\n";
    echo "  -v, --verbose  Muestra el SQL de cada cambio.\n";
    exit(0);
}

$origen  = isset($opciones['origen']) && $opciones['origen'] !== false
    ? (string) $opciones['origen'] : SOVCA_DB_NAME;
$destino = isset($opciones['destino']) && $opciones['destino'] !== false
    ? (string) $opciones['destino'] : SOVCA_DEMO_DB;
$aplicar = isset($opciones['aplicar']);
$prune   = isset($opciones['prune']);
$verificar = isset($opciones['verificar']);
$verbose = isset($opciones['verbose']) || isset($opciones['v']);

$archivosSql = array();
if (isset($opciones['sql'])) {
    foreach ((array) $opciones['sql'] as $archivo) {
        if ($archivo === false || trim((string) $archivo) === '') {
            continue;
        }
        $archivosSql[] = (string) $archivo;
    }
}

/* ---------------------------------------------------------------------
   SALIDA
   --------------------------------------------------------------------- */
function dax_titulo($texto)
{
    echo "\n" . $texto . "\n" . str_repeat('-', strlen($texto)) . "\n";
}

function dax_linea($texto)
{
    echo $texto . "\n";
}

function dax_error($texto)
{
    fwrite(STDERR, "\n[ERROR] " . $texto . "\n");
}

/* ---------------------------------------------------------------------
   AYUDAS DE CONSULTA
   --------------------------------------------------------------------- */
function dax_consulta(PDO $pdo, $sql, array $params = array())
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function dax_uno(PDO $pdo, $sql, array $params = array())
{
    $filas = dax_consulta($pdo, $sql, $params);
    return $filas ? $filas[0] : null;
}

/** Normaliza una definición para compararla (espacios, saltos y mayúsculas). */
function dax_normalizar($texto)
{
    $texto = strtolower((string) $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

/** Objetos que pertenecen a una extensión (no se copian). */
function dax_no_extension()
{
    return "NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = %s AND d.deptype = 'e')";
}

/**
 * Tablas/objetos de respaldo o de trabajo (ej. _bkp_aretes_20261023).
 * No se copian a la demo: son restos de correcciones puntuales y solo
 * ensuciarían la base de pruebas.
 */
function dax_es_temporal($nombre)
{
    $n = strtolower((string) $nombre);
    foreach (array('_bkp', 'bkp_', '_tmp', 'tmp_', '_old', '_backup', '_respaldo', '_borrar') as $prefijo) {
        if (strpos($n, $prefijo) === 0) {
            return true;
        }
    }
    return false;
}

/* ---------------------------------------------------------------------
   LECTURA DEL ESQUEMA
   --------------------------------------------------------------------- */
function dax_leer_esquema(PDO $pdo)
{
    $esquema = array(
        'secuencias'  => array(),
        'tablas'      => array(),
        'funciones'   => array(),
        'vistas'      => array(),
        'indices'     => array(),
        'restricciones' => array(),
        'disparadores' => array(),
    );

    // --- Secuencias -------------------------------------------------
    $sql = "SELECT c.relname AS nombre
              FROM pg_class c
              JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'S'
               AND " . sprintf(dax_no_extension(), 'c.oid') . "
             ORDER BY 1";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        $esquema['secuencias'][$fila['nombre']] = $fila['nombre'];
    }

    // --- Tablas y sus columnas --------------------------------------
    $sql = "SELECT c.relname AS nombre, c.oid
              FROM pg_class c
              JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'r'
               AND " . sprintf(dax_no_extension(), 'c.oid') . "
             ORDER BY 1";
    $tablas = dax_consulta($pdo, $sql);
    // attgenerated existe desde PostgreSQL 12: se consulta con respaldo.
    $sqlColumnas = "SELECT a.attname AS nombre,
                           format_type(a.atttypid, a.atttypmod) AS tipo,
                           a.attnotnull AS obligatorio,
                           pg_get_expr(ad.adbin, ad.adrelid) AS defecto,
                           a.attidentity::text AS identidad,
                           %s AS generada
                      FROM pg_attribute a
                      LEFT JOIN pg_attrdef ad
                             ON ad.adrelid = a.attrelid AND ad.adnum = a.attnum
                     WHERE a.attrelid = :oid AND a.attnum > 0 AND NOT a.attisdropped
                     ORDER BY a.attnum";

    foreach ($tablas as $tabla) {
        if (dax_es_temporal($tabla['nombre'])) {
            continue;
        }
        try {
            $columnas = dax_consulta($pdo, sprintf($sqlColumnas, 'a.attgenerated::text'), array(':oid' => $tabla['oid']));
        } catch (PDOException $e) {
            $columnas = dax_consulta($pdo, sprintf($sqlColumnas, "''"), array(':oid' => $tabla['oid']));
        }

        $porNombre = array();
        foreach ($columnas as $col) {
            $porNombre[$col['nombre']] = $col;
        }
        $esquema['tablas'][$tabla['nombre']] = $porNombre;
    }

    // --- Funciones (sin agregadas ni de extensiones) ----------------
    $sql = "SELECT p.proname AS nombre,
                   pg_get_function_identity_arguments(p.oid) AS argumentos,
                   md5(p.prosrc) AS cuerpo,
                   pg_get_functiondef(p.oid) AS ddl
              FROM pg_proc p
              JOIN pg_namespace n ON n.oid = p.pronamespace
             WHERE n.nspname = 'public' AND p.prokind <> 'a'
               AND " . sprintf(dax_no_extension(), 'p.oid') . "
             ORDER BY 1, 2";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        if (dax_es_temporal($fila['nombre'])) {
            continue;
        }
        $clave = $fila['nombre'] . '(' . $fila['argumentos'] . ')';
        $esquema['funciones'][$clave] = $fila;
    }

    // --- Vistas ------------------------------------------------------
    $sql = "SELECT c.relname AS nombre, pg_get_viewdef(c.oid, true) AS definicion
              FROM pg_class c
              JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'v'
               AND " . sprintf(dax_no_extension(), 'c.oid') . "
             ORDER BY 1";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        if (dax_es_temporal($fila['nombre'])) {
            continue;
        }
        $esquema['vistas'][$fila['nombre']] = $fila['definicion'];
    }

    // --- Índices que NO sostienen una restricción --------------------
    $sql = "SELECT ic.relname AS nombre, tc.relname AS tabla, pg_get_indexdef(i.indexrelid) AS ddl
              FROM pg_index i
              JOIN pg_class ic ON ic.oid = i.indexrelid
              JOIN pg_class tc ON tc.oid = i.indrelid
              JOIN pg_namespace n ON n.oid = tc.relnamespace
             WHERE n.nspname = 'public'
               AND NOT EXISTS (SELECT 1 FROM pg_constraint con WHERE con.conindid = i.indexrelid)
               AND " . sprintf(dax_no_extension(), 'ic.oid') . "
             ORDER BY 1";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        if (dax_es_temporal($fila['tabla'])) {
            continue;
        }
        $esquema['indices'][$fila['nombre']] = $fila['ddl'];
    }

    // --- Restricciones ----------------------------------------------
    $sql = "SELECT con.conname AS nombre,
                   con.contype::text AS tipo,
                   tc.relname AS tabla,
                   pg_get_constraintdef(con.oid, true) AS ddl
              FROM pg_constraint con
              JOIN pg_class tc ON tc.oid = con.conrelid
              JOIN pg_namespace n ON n.oid = tc.relnamespace
             WHERE n.nspname = 'public' AND con.contype IN ('p', 'u', 'f', 'c', 'x')
             ORDER BY 1";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        if (dax_es_temporal($fila['tabla'])) {
            continue;
        }
        $clave = $fila['tabla'] . '.' . $fila['nombre'];
        $esquema['restricciones'][$clave] = $fila;
    }

    // --- Disparadores ------------------------------------------------
    $sql = "SELECT t.tgname AS nombre,
                   c.relname AS tabla,
                   pg_get_triggerdef(t.oid, true) AS ddl
              FROM pg_trigger t
              JOIN pg_class c ON c.oid = t.tgrelid
              JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND NOT t.tgisinternal
               AND " . sprintf(dax_no_extension(), 't.oid') . "
             ORDER BY 1";
    foreach (dax_consulta($pdo, $sql) as $fila) {
        if (dax_es_temporal($fila['tabla'])) {
            continue;
        }
        $clave = $fila['tabla'] . '.' . $fila['nombre'];
        $esquema['disparadores'][$clave] = $fila;
    }

    return $esquema;
}

/* ---------------------------------------------------------------------
   CONSTRUCCIÓN DEL PLAN DE CAMBIOS
   --------------------------------------------------------------------- */
function dax_plan(PDO $pdoOrigen, array $origen, array $destino, $prune)
{
    $pasos = array();

    $agregar = function ($grupo, $objeto, $sql, $nota = '') use (&$pasos) {
        $pasos[] = array('grupo' => $grupo, 'objeto' => $objeto, 'sql' => $sql, 'nota' => $nota);
    };

    // 1) Secuencias ---------------------------------------------------
    foreach ($origen['secuencias'] as $nombre => $_) {
        if (!isset($destino['secuencias'][$nombre])) {
            $agregar('secuencia', $nombre, 'CREATE SEQUENCE IF NOT EXISTS public.' . $nombre);
        }
    }

    // 2) Tablas -------------------------------------------------------
    foreach ($origen['tablas'] as $tabla => $columnas) {
        if (isset($destino['tablas'][$tabla])) {
            continue;
        }

        $partes = array();
        foreach ($columnas as $nombre => $col) {
            if ($col['identidad'] === 'a' || $col['identidad'] === 'd') {
                $partes[] = $nombre . ' ' . $col['tipo']
                    . ($col['identidad'] === 'a' ? ' GENERATED ALWAYS AS IDENTITY' : ' GENERATED BY DEFAULT AS IDENTITY');
                continue;
            }
            $parte = $nombre . ' ' . $col['tipo'];
            if ($col['generada'] === 's') {
                // Columna generada: se crea sin la expresión y se avisa.
                $parte .= ' /* generada */';
            }
            if ($col['defecto'] !== null && $col['defecto'] !== '') {
                $parte .= ' DEFAULT ' . $col['defecto'];
            }
            if ($col['obligatorio']) {
                $parte .= ' NOT NULL';
            }
            $partes[] = $parte;
        }

        $agregar('tabla', $tabla, 'CREATE TABLE public.' . $tabla . " (\n    " . implode(",\n    ", $partes) . "\n)",
            'Tabla nueva: las demás restricciones se agregan en los pasos siguientes.');
    }

    // 3) Columnas -----------------------------------------------------
    foreach ($origen['tablas'] as $tabla => $columnas) {
        if (!isset($destino['tablas'][$tabla])) {
            continue; // es nueva, ya se creó completa
        }

        $destinoCols = $destino['tablas'][$tabla];

        foreach ($columnas as $nombre => $col) {
            $objeto = $tabla . '.' . $nombre;

            if (isset($destinoCols[$nombre])) {
                $dst = $destinoCols[$nombre];
                if (dax_normalizar($dst['tipo']) !== dax_normalizar($col['tipo'])) {
                    $sql = 'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre
                        . ' TYPE ' . $col['tipo'] . ' USING ' . $nombre . '::' . $col['tipo'];
                    $agregar('columna', $objeto, $sql, 'Cambió el tipo (' . $dst['tipo'] . ' → ' . $col['tipo'] . ').');
                }
                if (dax_normalizar($dst['defecto']) !== dax_normalizar($col['defecto'])) {
                    $sql = $col['defecto'] === null || $col['defecto'] === ''
                        ? 'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre . ' DROP DEFAULT'
                        : 'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre . ' SET DEFAULT ' . $col['defecto'];
                    $agregar('columna', $objeto, $sql, 'Cambió el valor por defecto.');
                }
                if ($col['obligatorio'] && !$dst['obligatorio']) {
                    $agregar('columna', $objeto,
                        'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre . ' SET NOT NULL',
                        'En el origen es obligatoria. Si la demo ya tiene filas con vacíos, este paso falla y se informa.');
                }
                if (!$col['obligatorio'] && $dst['obligatorio']) {
                    $agregar('columna', $objeto,
                        'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre . ' DROP NOT NULL',
                        'En el origen admite vacíos.');
                }
                continue;
            }

            // Columna que falta en la demo.
            $parte = 'ALTER TABLE public.' . $tabla . ' ADD COLUMN ' . $nombre . ' ' . $col['tipo'];
            if ($col['defecto'] !== null && $col['defecto'] !== '') {
                $parte .= ' DEFAULT ' . $col['defecto'];
            }
            if ($col['obligatorio']) {
                // Con valor por defecto se puede agregar NOT NULL en un solo paso;
                // sin él se agrega primero la columna y luego se intenta el NOT NULL.
                if ($col['defecto'] !== null && $col['defecto'] !== '') {
                    $parte .= ' NOT NULL';
                    $agregar('columna', $objeto, $parte, 'Columna nueva.');
                } else {
                    $agregar('columna', $objeto, $parte, 'Columna nueva (se agrega admitiendo vacíos).');
                    $agregar('columna', $objeto,
                        'ALTER TABLE public.' . $tabla . ' ALTER COLUMN ' . $nombre . ' SET NOT NULL',
                        'Paso aparte: puede fallar si la demo ya tiene filas.');
                }
                continue;
            }
            $agregar('columna', $objeto, $parte, 'Columna nueva.');
        }

        // Columnas que ya no existen en el origen.
        foreach ($destinoCols as $nombre => $_) {
            if (isset($columnas[$nombre])) {
                continue;
            }
            $objeto = $tabla . '.' . $nombre;
            if ($prune) {
                $agregar('columna', $objeto,
                    'ALTER TABLE public.' . $tabla . ' DROP COLUMN IF EXISTS ' . $nombre,
                    'Ya no existe en el origen (se borra por --prune).');
            } else {
                $agregar('aviso', $objeto, '',
                    'La demo tiene esta columna y el origen no. Usa --prune para quitarla.');
            }
        }
    }

    // 4) Funciones ----------------------------------------------------
    foreach ($origen['funciones'] as $clave => $fn) {
        if (!isset($destino['funciones'][$clave])) {
            $agregar('funcion', $clave, $fn['ddl'], 'Función nueva.');
            continue;
        }
        if ($destino['funciones'][$clave]['cuerpo'] !== $fn['cuerpo']) {
            $agregar('funcion', $clave, $fn['ddl'], 'Cambió el cuerpo de la función.');
        }
    }

    // 5) Vistas -------------------------------------------------------
    foreach ($origen['vistas'] as $nombre => $definicion) {
        $ddl = 'CREATE OR REPLACE VIEW public.' . $nombre . " AS\n" . rtrim(trim($definicion), ';');

        if (!isset($destino['vistas'][$nombre])) {
            $agregar('vista', $nombre, $ddl, 'Vista nueva.');
            continue;
        }
        if (dax_normalizar($destino['vistas'][$nombre]) !== dax_normalizar($definicion)) {
            $agregar('vista', $nombre, $ddl, 'Cambió la definición de la vista.');
        }
    }

    // Vistas que existen solo en la demo.
    foreach ($destino['vistas'] as $nombre => $_) {
        if (isset($origen['vistas'][$nombre])) {
            continue;
        }
        $agregar('aviso', 'vista ' . $nombre, '',
            'La demo tiene esta vista y el origen no. Revísala antes de borrarla (no se toca automáticamente).');
    }

    // 6) Índices ------------------------------------------------------
    foreach ($origen['indices'] as $nombre => $ddl) {
        if (isset($destino['indices'][$nombre])) {
            if (dax_normalizar($destino['indices'][$nombre]) !== dax_normalizar($ddl)) {
                $agregar('indice', $nombre,
                    preg_replace('/^CREATE (UNIQUE )?INDEX /i', 'CREATE $1INDEX IF NOT EXISTS ', $ddl),
                    'Cambió la definición del índice (revisa si conviene recrearlo).');
            }
            continue;
        }
        $agregar('indice', $nombre,
            preg_replace('/^CREATE (UNIQUE )?INDEX /i', 'CREATE $1INDEX IF NOT EXISTS ', $ddl),
            'Índice nuevo.');
    }

    // 7) Restricciones ------------------------------------------------
    foreach ($origen['restricciones'] as $clave => $con) {
        if (!isset($destino['restricciones'][$clave])) {
            $agregar('restriccion', $clave,
                'ALTER TABLE public.' . $con['tabla'] . ' ADD CONSTRAINT ' . $con['nombre'] . ' ' . $con['ddl'],
                'Restricción nueva.');
            continue;
        }
        $dst = $destino['restricciones'][$clave];
        if (dax_normalizar($dst['ddl']) === dax_normalizar($con['ddl'])) {
            continue;
        }

        if ($prune) {
            $agregar('restriccion', $clave,
                'ALTER TABLE public.' . $con['tabla'] . ' DROP CONSTRAINT IF EXISTS ' . $con['nombre'],
                'Cambió la definición: se quita la actual (--prune).');
            $agregar('restriccion', $clave,
                'ALTER TABLE public.' . $con['tabla'] . ' ADD CONSTRAINT ' . $con['nombre'] . ' ' . $con['ddl'],
                'Y se agrega la del origen (--prune).');
        } else {
            $agregar('aviso', 'restricción ' . $clave, '',
                'Cambió la definición. Usa --prune para rehacerla.');
        }
    }

    // 8) Disparadores -------------------------------------------------
    foreach ($origen['disparadores'] as $clave => $tr) {
        if (!isset($destino['disparadores'][$clave])) {
            $agregar('disparador', $clave, $tr['ddl'], 'Disparador nuevo.');
            continue;
        }
        if (dax_normalizar($destino['disparadores'][$clave]['ddl']) !== dax_normalizar($tr['ddl'])) {
            $agregar('disparador', $clave,
                'DROP TRIGGER IF EXISTS ' . $tr['nombre'] . ' ON public.' . $tr['tabla'],
                'Cambió la definición: se quita el actual.');
            $agregar('disparador', $clave, $tr['ddl'], 'Y se crea el del origen.');
        }
    }

    return $pasos;
}

/* ---------------------------------------------------------------------
   EJECUCIÓN
   --------------------------------------------------------------------- */
function dax_ejecutar(PDO $pdoDestino, array $pasos, $verbose)
{
    $aplicados = 0;
    $fallidos = 0;
    $errores = array();
    $avisos = array();

    // Las vistas pueden depender de otras vistas que también cambiaron:
    // se reintentan las que fallan hasta estabilizarse (máximo 5 vueltas).
    $vistasPendientes = array();

    foreach ($pasos as $paso) {
        if ($paso['grupo'] === 'aviso') {
            $avisos[] = $paso['objeto'] . ': ' . $paso['nota'];
            continue;
        }
        if ($paso['grupo'] === 'vista') {
            $vistasPendientes[] = $paso;
            continue;
        }

        try {
            if ($verbose) {
                echo "\n  → " . $paso['objeto'] . "\n" . dax_sangrar($paso['sql']) . "\n";
            }
            $pdoDestino->exec($paso['sql']);
            $aplicados++;
        } catch (PDOException $e) {
            $fallidos++;
            $errores[] = $paso['objeto'] . ': ' . $e->getMessage();
        }
    }

    if ($vistasPendientes) {
        dax_titulo('Vistas');
        for ($vuelta = 1; $vuelta <= 5 && $vistasPendientes; $vuelta++) {
            $siguientes = array();
            foreach ($vistasPendientes as $paso) {
                try {
                    if ($verbose) {
                        echo "\n  → vista " . $paso['objeto'] . "\n" . dax_sangrar($paso['sql']) . "\n";
                    }
                    $pdoDestino->exec($paso['sql']);
                    $aplicados++;
                    dax_linea('  ok   vista ' . $paso['objeto'] . ' — ' . $paso['nota']);
                } catch (PDOException $e) {
                    // Si no se puede reemplazar (por ejemplo porque cambió
                    // el número o el tipo de columnas), se recrearla a la
                    // fuerza: una vista no guarda datos.
                    $nombre = $paso['objeto'];
                    try {
                        $pdoDestino->exec('DROP VIEW IF EXISTS public.' . $nombre . ' CASCADE');
                        $pdoDestino->exec($paso['sql']);
                        $aplicados++;
                        dax_linea('  ok   vista ' . $nombre . ' — recreada (cambiaron sus columnas)');
                    } catch (PDOException $e2) {
                        // Se guarda el motivo REAL: el mensaje genérico no
                        // permite distinguir permisos, columnas faltantes o
                        // dependencias rotas.
                        $paso['error'] = $e2->getMessage();
                        $siguientes[] = $paso;
                    }
                }
            }
            if (count($siguientes) === count($vistasPendientes)) {
                // No hubo progreso: no tiene sentido seguir intentando.
                $vistasPendientes = $siguientes;
                break;
            }
            $vistasPendientes = $siguientes;
        }
        if ($vistasPendientes) {
            foreach ($vistasPendientes as $paso) {
                $fallidos++;
                $detalle = (isset($paso['error']) && $paso['error'] !== '')
                    ? $paso['error']
                    : 'no se pudo crear o reemplazar la vista.';
                $errores[] = $paso['objeto'] . ': ' . $detalle;
            }
        }
    }

    return array($aplicados, $fallidos, $errores, $avisos);
}

function dax_sangrar($sql)
{
    $lineas = explode("\n", trim((string) $sql));
    return '     ' . implode("\n     ", $lineas);
}

/** Ejecuta un archivo .sql contra la demo con psql. */
function dax_aplicar_sql($archivo, $base)
{
    if (!is_file($archivo)) {
        return array(false, 'No existe el archivo ' . $archivo);
    }

    $psql = trim((string) shell_exec('command -v psql 2>/dev/null'));
    if ($psql === '') {
        return array(false, 'No se encontró psql en el PATH (necesario para --sql).');
    }

    $comando = 'PGPASSWORD=' . escapeshellarg(SOVCA_DEMO_CLAVE)
        . ' ' . escapeshellarg($psql)
        . ' -h ' . escapeshellarg(SOVCA_DEMO_HOST)
        . ' -p ' . escapeshellarg(SOVCA_DEMO_PUERTO)
        . ' -U ' . escapeshellarg(SOVCA_DEMO_USUARIO)
        . ' -d ' . escapeshellarg($base)
        . ' -v ON_ERROR_STOP=1 -q -f ' . escapeshellarg($archivo) . ' 2>&1';

    $salida = array();
    $codigo = 0;
    exec($comando, $salida, $codigo);

    return array($codigo === 0, trim(implode("\n", $salida)));
}

/* ---------------------------------------------------------------------
   PROGRAMA PRINCIPAL
   --------------------------------------------------------------------- */
dax_linea('Sincronizador de esquema para la base de la demostración');
dax_linea('Origen : ' . $origen);
dax_linea('Destino: ' . $destino . ($aplicar ? '  (APLICANDO)' : '  (solo informe)'));
if ($prune) {
    dax_linea('Modo   : --prune (quita columnas sobrantes y rehace restricciones cambiadas)');
}

try {
    $pdoOrigen = bovs_demo_pdo($origen);
} catch (PDOException $e) {
    dax_error('No se pudo conectar a la base origen "' . $origen . '": ' . $e->getMessage());
    exit(1);
}

try {
    $pdoDestino = bovs_demo_pdo($destino);
} catch (PDOException $e) {
    dax_error('No se pudo conectar a la base destino "' . $destino . '": ' . $e->getMessage());
    dax_linea('Si la demo todavía no existe, créala con: php scripts/demo_instalar.php');
    exit(1);
}

$esquemaOrigen  = dax_leer_esquema($pdoOrigen);
$esquemaDestino = dax_leer_esquema($pdoDestino);

$pasos = dax_plan($pdoOrigen, $esquemaOrigen, $esquemaDestino, $prune);

/* Resumen por grupo ---------------------------------------------------- */
$conteo = array();
foreach ($pasos as $paso) {
    $g = $paso['grupo'];
    $conteo[$g] = isset($conteo[$g]) ? $conteo[$g] + 1 : 1;
}

dax_titulo('Diferencias encontradas');
if (!$pasos) {
    dax_linea('La demo ya tiene el mismo esquema que ' . $origen . '.');
} else {
    $nombreGrupos = array(
        'secuencia'    => 'Secuencias',
        'tabla'        => 'Tablas nuevas',
        'columna'      => 'Columnas',
        'funcion'      => 'Funciones',
        'vista'        => 'Vistas',
        'indice'       => 'Índices',
        'restriccion'  => 'Restricciones',
        'disparador'   => 'Disparadores',
        'aviso'        => 'Avisos (no se aplican solos)',
    );
    foreach ($nombreGrupos as $clave => $etiqueta) {
        if (empty($conteo[$clave])) {
            continue;
        }
        dax_linea(sprintf('  %-32s %d', $etiqueta . ':', $conteo[$clave]));
    }

    echo "\n";
    foreach ($pasos as $paso) {
        if ($paso['grupo'] === 'aviso') {
            dax_linea('  ! ' . $paso['objeto'] . ' — ' . $paso['nota']);
            continue;
        }
        dax_linea('  · [' . $paso['grupo'] . '] ' . $paso['objeto'] . ($paso['nota'] ? ' — ' . $paso['nota'] : ''));
        if ($verbose) {
            dax_linea(dax_sangrar($paso['sql']));
        }
    }
}

/* Aplicación ----------------------------------------------------------- */
$aplicados = 0;
$fallidos = 0;
$errores = array();
$avisos = array();

if ($aplicar && $pasos) {
    dax_titulo('Aplicando cambios en ' . $destino);

    // Dos vueltas: la segunda vuelve a comparar y recrea lo que se haya
    // quedado en el camino. Es lo que recupera las vistas que un
    // DROP VIEW ... CASCADE se llevó al recrear una vista base (esas
    // vistas dependientes no estaban en el plan porque su definición no
    // había cambiado... hasta que desaparecieron).
    for ($vuelta = 1; $vuelta <= 2; $vuelta++) {
        if ($vuelta > 1) {
            $esquemaDestino = dax_leer_esquema($pdoDestino);
            $pasos = dax_plan($pdoOrigen, $esquemaOrigen, $esquemaDestino, $prune);
            $pasos = array_values(array_filter($pasos, function ($paso) {
                return $paso['grupo'] !== 'aviso';
            }));
            if (!$pasos) {
                break;
            }
            dax_linea('  (segunda pasada: ' . count($pasos) . ' ajuste(s) pendientes)');
        }

        list($ok, $mal, $errs, $avs) = dax_ejecutar($pdoDestino, $pasos, $verbose);
        $aplicados += $ok;
        $fallidos = $mal;
        $errores = $errs;
        $avisos = $avs;

        if ($ok === 0) {
            break; // no progresó: no tiene sentido repetir
        }
    }

    dax_linea('  Aplicados: ' . $aplicados);
    if ($fallidos) {
        dax_linea('  Con error: ' . $fallidos);
    }
    if ($avisos) {
        foreach ($avisos as $aviso) {
            dax_linea('  ! ' . $aviso);
        }
    }
    if ($errores) {
        echo "\n";
        foreach (array_unique($errores) as $error) {
            dax_linea('  x ' . $error);
        }
    }
} elseif (!$aplicar && $pasos) {
    dax_titulo('Para aplicar');
    dax_linea('  php scripts/demo_actualizar_esquema.php --aplicar' . ($prune ? ' --prune' : ''));
}

/* Archivos SQL adicionales (migraciones nuevas) ------------------------ */
if ($archivosSql) {
    dax_titulo('Archivos SQL para la demo');
    foreach ($archivosSql as $archivo) {
        if (!$aplicar) {
            dax_linea('  · pendiente: ' . $archivo);
            continue;
        }
        list($ok, $salida) = dax_aplicar_sql($archivo, $destino);
        if ($ok) {
            dax_linea('  ok   ' . $archivo);
        } else {
            dax_linea('  x    ' . $archivo);
            if ($salida !== '') {
                dax_linea(dax_sangrar($salida));
            }
        }
    }
    if (!$aplicar) {
        dax_linea('  (con --aplicar se ejecutan con psql contra ' . $destino . ')');
    }
}

/* Cierre --------------------------------------------------------------- */
if (!$aplicar) {
    dax_titulo('Resultado');
    dax_linea($pasos ? 'Hay cambios pendientes en la demo.' : 'Sin cambios pendientes.');
    if ($verificar && $pasos) {
        // Pensado para cron / scripts de despliegue: que el aviso no pase
        // desapercibido.
        dax_linea('(--verificar: se devuelve código 1 porque la demo está desfasada)');
        exit(1);
    }
    exit(0);
}

if ($fallidos || $errores) {
    dax_titulo('Resultado');
    dax_linea('Se aplicaron ' . $aplicados . ' cambios, con ' . $fallidos . ' error(es). Revisa el detalle arriba.');
    // Pista para el caso más habitual en producción: los objetos de la demo
    // creados con otro rol (postgres) no los puede reemplazar el usuario de
    // la aplicación.
    $textoErrores = implode("\n", $errores);
    if (stripos($textoErrores, 'must be owner') !== false
        || stripos($textoErrores, 'permiso denegado') !== false
        || stripos($textoErrores, 'permission denied') !== false) {
        dax_linea('');
        dax_linea('Los objetos de la demo pertenecen a otro rol (normalmente postgres).');
        dax_linea('No se puede usar REASSIGN OWNED BY postgres: PostgreSQL lo rechaza');
        dax_linea('porque postgres también es dueño de objetos del sistema.');
        dax_linea('Usá el script que cambia el dueño objeto por objeto:');
        dax_linea('');
        dax_linea('  sudo -u postgres psql -d ' . $destino . ' -v duenio=' . SOVCA_DEMO_USUARIO . ' \\');
        dax_linea('       < scripts/demo_reasignar_duenio.sql');
        dax_linea('');
        dax_linea('Después repetí: sudo php scripts/demo_actualizar_esquema.php --aplicar');
    }
    exit(1);
}

dax_titulo('Resultado');
dax_linea($aplicados ? 'Esquema de la demo actualizado (' . $aplicados . ' cambios).' : 'No hubo cambios que aplicar.');
exit(0);
