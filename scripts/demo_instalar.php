<?php
/**
 * =====================================================================
 * scripts/demo_instalar.php — INSTALADOR DEL MODO DEMOSTRACIÓN
 * ---------------------------------------------------------------------
 * Crea (o recrea) la base de datos de pruebas que usa el acceso
 * demostrativo de SOVCA:
 *
 *   1. Crea la base sovca_demo si no existe.
 *   2. Clona SOLO LA ESTRUCTURA de la base real (tablas, vistas,
 *      secuencias, índices, restricciones) con pg_dump --schema-only.
 *   3. Carga los CATÁLOGOS básicos (roles, tipos, países, estados,
 *      municipios, parroquias, razas, categorías, causas, estatus,
 *      planes, suscripciones, productos, presentaciones, etc.).
 *   4. Limpia de esos catálogos las filas que pertenecen a fundos
 *      reales (mfundos_id <> 0), para que la demo arranque "en blanco".
 *   5. Ajusta las secuencias y crea las piezas propias del modo demo
 *      (tablas demo_registros y demo_verificaciones, y la suscripción de
 *      15 días).
 *
 * NO copia animales, servicios, nacimientos, controles, usuarios,
 * fundos ni ningún dato operativo de los productores reales.
 *
 * USO
 *   php scripts/demo_instalar.php                 # crea si falta
 *   php scripts/demo_instalar.php --recrear       # borra y vuelve a crear
 *   php scripts/demo_instalar.php --purgar        # desactiva cuentas vencidas
 *   php scripts/demo_instalar.php --origen=sovca_qas --destino=sovca_demo
 *
 * VARIABLES DE ENTORNO (opcionales, mismas que config/demo.php)
 *   SOVCA_DEMO_DB, SOVCA_DEMO_DB_ORIGEN, SOVCA_DEMO_HOST, SOVCA_DEMO_PUERTO,
 *   SOVCA_DEMO_USUARIO, SOVCA_DEMO_CLAVE
 *
 * REQUISITOS
 *   · pg_dump y psql en el PATH (misma versión o superior al servidor)
 *   · el usuario configurado debe poder crear bases de datos
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
$opciones = getopt('', array('origen::', 'destino::', 'recrear', 'purgar', 'ayuda', 'sin-catalogos'));

if (isset($opciones['ayuda'])) {
    echo "Instalador del modo demostración de SOVCA\n\n";
    echo "  php scripts/demo_instalar.php [--recrear] [--purgar] [--sin-catalogos]\n";
    echo "                                [--origen=sovca_qas] [--destino=sovca_demo]\n\n";
    echo "  --recrear        Borra la base destino y la vuelve a crear.\n";
    echo "  --purgar         Desactiva las cuentas demo vencidas y termina.\n";
    echo "  --sin-catalogos  Solo estructura, sin datos de catálogo.\n";
    exit(0);
}

$origen  = isset($opciones['origen']) && $opciones['origen'] !== '' ? (string) $opciones['origen'] : SOVCA_DEMO_DB_ORIGEN;
$destino = isset($opciones['destino']) && $opciones['destino'] !== '' ? (string) $opciones['destino'] : SOVCA_DEMO_DB;
$recrear = isset($opciones['recrear']);
$purgar  = isset($opciones['purgar']);
$sinCatalgos = isset($opciones['sin-catalogos']);

/* ---------------------------------------------------------------------
   UTILIDADES
   --------------------------------------------------------------------- */
function demo_salida($texto)
{
    echo $texto . "\n";
}

function demo_error($texto)
{
    fwrite(STDERR, "ERROR: " . $texto . "\n");
}

function demo_id_sql($nombre)
{
    return '"' . str_replace('"', '""', (string) $nombre) . '"';
}

function demo_binario($nombre)
{
    $ruta = trim((string) @shell_exec('command -v ' . escapeshellarg($nombre) . ' 2>/dev/null'));
    return $ruta !== '' ? $ruta : '';
}

function demo_ejecutar($comando, &$salida = null)
{
    $salida = array();
    $codigo = 0;
    @exec($comando . ' 2>&1', $salida, $codigo);
    return $codigo;
}

/* ---------------------------------------------------------------------
   CONEXIÓN ADMINISTRATIVA (base "postgres")
   --------------------------------------------------------------------- */
try {
    $pdoAdmin = new PDO(
        bovs_demo_dsn('postgres'),
        SOVCA_DEMO_USUARIO,
        SOVCA_DEMO_CLAVE,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        )
    );
} catch (Throwable $e) {
    demo_error('No se pudo conectar al servidor PostgreSQL (' . SOVCA_DEMO_HOST . ':' . SOVCA_DEMO_PUERTO . '): ' . $e->getMessage());
    exit(1);
}

function demo_bd_existe(PDO $pdo, $nombre)
{
    $st = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $st->execute(array($nombre));
    return (bool) $st->fetchColumn();
}

/* ---------------------------------------------------------------------
   MODO --purgar
   --------------------------------------------------------------------- */
if ($purgar) {
    if (!demo_bd_existe($pdoAdmin, $destino)) {
        demo_error('La base ' . $destino . ' no existe. Ejecuta primero el instalador.');
        exit(1);
    }
    $r = bovs_demo_purgar_vencidas();
    demo_salida('Cuentas demo desactivadas: ' . (int) $r['cuentas']);
    exit(0);
}

/* ---------------------------------------------------------------------
   HERRAMIENTAS DEL SISTEMA
   --------------------------------------------------------------------- */
$psql   = demo_binario('psql');
$pgdump = demo_binario('pg_dump');

if ($psql === '' || $pgdump === '') {
    demo_error('No se encontraron psql y/o pg_dump en el PATH. Instala postgresql-client y vuelve a intentar.');
    exit(1);
}

$credenciales = 'PGPASSWORD=' . escapeshellarg(SOVCA_DEMO_CLAVE)
    . ' ' . escapeshellarg($psql);
$dsnBase = ' -h ' . escapeshellarg(SOVCA_DEMO_HOST)
    . ' -p ' . escapeshellarg(SOVCA_DEMO_PUERTO)
    . ' -U ' . escapeshellarg(SOVCA_DEMO_USUARIO);

$cmdPsql = $credenciales . $dsnBase;
$cmdDump = 'PGPASSWORD=' . escapeshellarg(SOVCA_DEMO_CLAVE) . ' ' . escapeshellarg($pgdump) . $dsnBase;

/* ---------------------------------------------------------------------
   PASO 1 — BASE DESTINO
   --------------------------------------------------------------------- */
if (!demo_bd_existe($pdoAdmin, $origen)) {
    demo_error('La base de origen "' . $origen . '" no existe en el servidor.');
    exit(1);
}

$existeDestino = demo_bd_existe($pdoAdmin, $destino);

if ($existeDestino && $recrear) {
    demo_salida('· Eliminando la base ' . $destino . ' (--recrear)...');
    try {
        $pdoAdmin->exec('DROP DATABASE IF EXISTS ' . demo_id_sql($destino) . ' WITH (FORCE)');
        $existeDestino = false;
    } catch (Throwable $e) {
        demo_error('No se pudo eliminar ' . $destino . ': ' . $e->getMessage());
        demo_error('Cierra las sesiones demo activas y vuelve a intentar.');
        exit(1);
    }
}

if (!$existeDestino) {
    demo_salida('· Creando la base ' . $destino . '...');
    try {
        $pdoAdmin->exec("CREATE DATABASE " . demo_id_sql($destino) . " WITH ENCODING 'UTF8' TEMPLATE template0");
    } catch (Throwable $e) {
        demo_error('No se pudo crear ' . $destino . ': ' . $e->getMessage());
        exit(1);
    }
} else {
    demo_salida('· La base ' . $destino . ' ya existe (se reutiliza). Usa --recrear para dejarla limpia.');
}

/* ---------------------------------------------------------------------
   PASO 2 — ESTRUCTURA
   --------------------------------------------------------------------- */
$tmpSchema = sys_get_temp_dir() . '/sovca_demo_schema_' . getmypid() . '.sql';

demo_salida('· Copiando estructura desde ' . $origen . '...');

$codigo = demo_ejecutar(
    $cmdDump . ' -d ' . escapeshellarg($origen)
    . ' --schema-only --no-owner --no-privileges'
    . ' -f ' . escapeshellarg($tmpSchema),
    $salida
);
if ($codigo !== 0) {
    demo_error('pg_dump --schema-only falló:');
    demo_error(implode("\n", array_slice($salida, 0, 15)));
    @unlink($tmpSchema);
    exit(1);
}

/* Si la base destino ya tenía tablas, el restore puede quejarse de
   objetos existentes. Se restaura con ON_ERROR_STOP y se informa. */
$codigo = demo_ejecutar(
    $cmdPsql . ' -d ' . escapeshellarg($destino)
    . ' -v ON_ERROR_STOP=1 -q -f ' . escapeshellarg($tmpSchema),
    $salida
);
if ($codigo !== 0) {
    demo_error('La restauración de la estructura falló:');
    demo_error(implode("\n", array_slice($salida, 0, 20)));
    demo_error('Sugerencia: ejecuta con --recrear para partir de una base vacía.');
    @unlink($tmpSchema);
    exit(1);
}
@unlink($tmpSchema);
demo_salida('  estructura copiada.');

/* ---------------------------------------------------------------------
   PASO 3 — CATÁLOGOS BÁSICOS
   --------------------------------------------------------------------- */
/* Catálogos globales: se copian completos. */
$catalogosGlobales = array(
    'mroles',
    'mtipousuarios',
    'mtipofundos',
    'mtipoganaderias',
    'mtipoganados',
    'mtiposervicios',
    'mtipomensajes',
    'mplanes',
    'msuscripciones',
    'maptitudes',
    'mestadofisiologicos',
    'mestatusganados',
    'mestatusanimales',
    'mcausadescartes',
    'mcausamortalidades',
    'mcategoriaganados',
    'mpaises',
    'mestados',
    'mmunicipios',
    'mparroquias',
    'mmensajes',
    'mdetamensajes',
    'mpromomensajes',
);

/* Catálogos que en la base real mezclan filas globales (mfundos_id = 0)
   con filas propias de cada fundo. Solo interesan las globales. */
$catalogosPorFundo = array(
    'mcategorias',
    'mcategoriaproductos',
    'mconsecutivopesos',
    'mperiodos',
    'mpresentaciones',
    'mproductos',
    'mrazas',
    'mmetaservicios',
);

if (!$sinCatalgos) {
    demo_salida('· Copiando catálogos básicos...');

    $tmpDatos = sys_get_temp_dir() . '/sovca_demo_datos_' . getmypid() . '.sql';

    $tablas = array_merge($catalogosGlobales, $catalogosPorFundo);
    $filtros = '';
    foreach ($tablas as $tabla) {
        $filtros .= ' -t ' . escapeshellarg($tabla);
    }

    $codigo = demo_ejecutar(
        $cmdDump . ' -d ' . escapeshellarg($origen)
        . ' --data-only --no-owner --no-privileges --disable-triggers'
        . $filtros
        . ' -f ' . escapeshellarg($tmpDatos),
        $salida
    );
    if ($codigo !== 0) {
        demo_error('pg_dump --data-only falló:');
        demo_error(implode("\n", array_slice($salida, 0, 15)));
        @unlink($tmpDatos);
        exit(1);
    }

    /* --disable-triggers necesita superusuario; si no lo es, se reintenta
       el restore sin esa opción (los catálogos solo tienen una FK real). */
    $codigo = demo_ejecutar(
        $cmdPsql . ' -d ' . escapeshellarg($destino)
        . ' -v ON_ERROR_STOP=1 -q -f ' . escapeshellarg($tmpDatos),
        $salida
    );
    if ($codigo !== 0) {
        demo_error('La carga de catálogos falló:');
        demo_error(implode("\n", array_slice($salida, 0, 20)));
        @unlink($tmpDatos);
        exit(1);
    }
    @unlink($tmpDatos);

    /* Limpieza de filas propias de fundos reales. */
    demo_salida('· Limpiando filas de fundos reales en los catálogos...');
    try {
        $pdoDemo = bovs_demo_pdo($destino);
        foreach ($catalogosPorFundo as $tabla) {
            $pdoDemo->exec('DELETE FROM ' . demo_id_sql($tabla) . ' WHERE mfundos_id IS NOT NULL AND mfundos_id <> 0');
        }
    } catch (Throwable $e) {
        demo_error('No se pudieron limpiar los catálogos: ' . $e->getMessage());
        exit(1);
    }

    demo_salida('  catálogos cargados.');
} else {
    demo_salida('· Catálogos omitidos (--sin-catalogos).');
}

/* ---------------------------------------------------------------------
   PASO 4 — SECUENCIAS
   --------------------------------------------------------------------- */
demo_salida('· Ajustando secuencias...');
$sqlSecuencias = <<<'SQL'
DO $$
DECLARE
    r record;
    seq text;
BEGIN
    FOR r IN
        SELECT c.table_name, c.column_name, c.column_default
          FROM information_schema.columns c
         WHERE c.table_schema = 'public'
           AND c.column_default LIKE 'nextval(%'
    LOOP
        /* La secuencia se deduce del propio DEFAULT: en esta base hay
           columnas que usan nextval() sin OWNED BY, así que
           pg_get_serial_sequence() devolvería NULL. */
        seq := regexp_replace(r.column_default, '^nextval\(''([^'']+)''.*$', '\1');
        IF seq <> '' AND to_regclass(seq) IS NOT NULL THEN
            /* Si la tabla quedó vacía se deja la secuencia lista para
               entregar el 1 (setval con is_called = false). */
            EXECUTE format(
                'SELECT setval(%L,
                                GREATEST(COALESCE((SELECT MAX(%I) FROM %I), 0), 1),
                                COALESCE((SELECT MAX(%I) FROM %I), 0) > 0)',
                seq, r.column_name, r.table_name, r.column_name, r.table_name
            );
        END IF;
    END LOOP;
END $$;
SQL;

$tmpSeq = sys_get_temp_dir() . '/sovca_demo_seq_' . getmypid() . '.sql';
@file_put_contents($tmpSeq, $sqlSecuencias);
$codigo = demo_ejecutar(
    $cmdPsql . ' -d ' . escapeshellarg($destino) . ' -v ON_ERROR_STOP=1 -q -f ' . escapeshellarg($tmpSeq),
    $salida
);
@unlink($tmpSeq);

if ($codigo !== 0) {
    demo_error('No se pudieron ajustar las secuencias:');
    demo_error(implode("\n", array_slice($salida, 0, 15)));
    exit(1);
}

/* ---------------------------------------------------------------------
   PASO 5 — PIEZAS DEL MODO DEMO
   --------------------------------------------------------------------- */
demo_salida('· Preparando tablas propias del modo demo...');
try {
    $pdoDemo = bovs_demo_pdo($destino);
    bovs_demo_preparar($pdoDemo);
    $suscripcion = bovs_demo_suscripcion_id($pdoDemo);
} catch (Throwable $e) {
    demo_error('No se pudo preparar el modo demo: ' . $e->getMessage());
    exit(1);
}

/* ---------------------------------------------------------------------
   RESUMEN
   --------------------------------------------------------------------- */
$mail = bovs_mail_config();
$sin_smtp = empty($mail['guardar_en_archivo']) && trim((string) $mail['password']) === '';

demo_salida('');
demo_salida('BASE DE PRUEBAS LISTA');
demo_salida('  Base de datos : ' . $destino);
demo_salida('  Vigencia demo : ' . (int) SOVCA_DEMO_DIAS . ' días (suscripción id ' . $suscripcion . ')');
demo_salida('  Plan por defecto: ' . (int) SOVCA_DEMO_PLAN_ID);
demo_salida('  Código de correo: ' . (int) SOVCA_DEMO_VERIF_MINUTOS . ' min · '
    . (int) SOVCA_DEMO_VERIF_INTENTOS . ' intentos · reenvío cada ' . (int) SOVCA_DEMO_REENVIO_SEG . ' s');
demo_salida('');

if ($sin_smtp) {
    demo_salida('AVISO IMPORTANTE');
    demo_salida('  No hay contraseña SMTP configurada (config/mail.php o SOVCA_SMTP_PASS).');
    demo_salida('  El alta demo exige verificar el correo: sin SMTP nadie podrá crear una cuenta.');
    demo_salida('  Configura el correo saliente antes de publicar el acceso demostrativo.');
    demo_salida('');
}

$resumen = array(
    'mroles', 'mtipofundos', 'mtipoganaderias', 'mpaises', 'mestados',
    'mmunicipios', 'mparroquias', 'mrazas', 'mcategorias', 'mproductos',
    'mpresentaciones', 'mplanes', 'msuscripciones', 'musuarios', 'mfundos',
);

foreach ($resumen as $tabla) {
    try {
        $total = (int) $pdoDemo->query('SELECT count(*) FROM ' . demo_id_sql($tabla))->fetchColumn();
        demo_salida(sprintf('  %-18s %6d', $tabla, $total));
    } catch (Throwable $e) {
        demo_salida(sprintf('  %-18s %6s', $tabla, 'n/d'));
    }
}

demo_salida('');
demo_salida('Siguiente paso: abrir /views/login y usar "Acceso demostrativo".');
demo_salida('Para apagar la demo: SOVCA_DEMO_ACTIVO = false en config/demo.php.');
