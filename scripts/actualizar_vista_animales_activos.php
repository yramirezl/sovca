<?php
/**
 * Redefine v_list_animales_activos con el criterio único del sistema:
 *     manimales.status = 1
 *     AND manimales.mestatus_id = 1
 *     AND manimales.estatus_animal IN (1, 2, 4)
 *
 * Criterio de "activo" para el control del plan: ACTIVO (1), DONADOR
 * ACTIVO (2) y DONADORA ACTIVO (4). Los donadores sin habilitar (3 y 5)
 * no cuentan para el cupo, igual que los animales con mestatus_id != 1
 * (descarte, muerto, vendido, extraviado, migrado).
 *
 * Si PostgreSQL responde "must be owner of view", ejecutar el CREATE OR
 * REPLACE VIEW con el usuario dueño (postgres) o cambiar el dueño:
 *     ALTER VIEW public.v_list_animales_activos OWNER TO sovca_app;
 *
 * Uso:
 *     php scripts/actualizar_vista_animales_activos.php
 *     php scripts/actualizar_vista_animales_activos.php --dry-run
 */

$ROOT   = dirname(__DIR__);
$dryRun = in_array('--dry-run', array_slice($argv, 1), true);

require_once $ROOT . '/config/db.php';
$cfg = sovca_db_config();

try {
    $pdo = new PDO(
        "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']}",
        $cfg['user'],
        $cfg['pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'ERROR de conexión: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$sql = <<<'SQL'
CREATE OR REPLACE VIEW public.v_list_animales_activos AS
SELECT
    a.id AS id_animal,
    a.codigo_rgb_id_animal AS codigo_animal,
    a.nombre AS nombre_animal,
    a.sexo,
    CASE WHEN a.sexo = 1 THEN 'HEMBRA'::text
         WHEN a.sexo = 2 THEN 'MACHO'::text
         ELSE NULL::text END AS sexo_animal,
    CASE WHEN a.estatus_animal = 1 THEN 'ACTIVO'::text
         WHEN a.estatus_animal = 2 THEN 'DONADOR ACTIVO'::text
         WHEN a.estatus_animal = 3 THEN 'DONADOR'::text
         WHEN a.estatus_animal = 4 THEN 'DONADORA ACTIVO'::text
         WHEN a.estatus_animal = 5 THEN 'DONADORA'::text
         ELSE NULL::text END AS estatus_animal,
    CASE WHEN a.imagen::text <> ''::text THEN a.imagen
         ELSE 'bovinos.png'::character varying END AS imagen,
    a.mestatus_id,
    a.nro_arete AS nro_chapeta,
    (SELECT x.nombre FROM mcategorias x WHERE x.id = a.categoria_ingreso_id) AS categoria_ingreso,
    (SELECT y.nombre FROM mcategorias y WHERE y.id = a.categoria_futura_id) AS categoria_futura,
    (SELECT z.nombre FROM maptitudes z WHERE z.id = a.aptitud) AS aptitud,
    m.nombre AS raza,
    a.f_nacimiento,
    a.peso_nacimiento,
    a.mfundos_id,
    c.nombre AS nombre_lote,
    c.id AS lote,
    date_part('year'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS anios,
    date_part('month'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS meses,
    date_part('day'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS dias
FROM manimales a
LEFT JOIN mrazas m ON m.id = a.mrazas_id
LEFT JOIN mlotes c ON c.id = a.mlotes_id,
manimalxfundos b,
mfundos e
WHERE a.mestatus_id = 1
  AND a.status = 1
  AND a.estatus_animal IN (1, 2, 4)
  AND a.mfundos_id = b.mfundos_id
  AND a.id = b.manimales_id
  AND b.mfundos_id = e.id;
SQL;

echo "Base: {$cfg['db']} @ {$cfg['host']}" . ($dryRun ? '   (DRY RUN)' : '') . PHP_EOL;

if ($dryRun) {
    echo $sql . PHP_EOL;
    exit(0);
}

try {
    $pdo->exec($sql);

    $total = $pdo->query("SELECT COUNT(*) FROM v_list_animales_activos WHERE mfundos_id = 64")->fetchColumn();
    echo "Vista actualizada. Fundo 64: {$total} animales activos." . PHP_EOL;
} catch (PDOException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
