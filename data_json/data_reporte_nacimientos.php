<?php
require_once '../models/Vmregnacimiento.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$resultado = array();

if ($mfundos_id > 0) {
    $sql = "SELECT
                n.codigo_rgb_id_animal_hembra,
                COALESCE(NULLIF(t.id_ternero, ''), n.id_ternero) AS id_ternero,
                n.codigo_ternero,
                n.f_parto,
                n.nro_parto,
                COALESCE(t.estatus_nacido, n.estatus_nacido) AS estatus_nacido,
                COALESCE(t.sexo, n.sexo) AS sexo,
                COALESCE(t.peso_nacimiento, n.peso_nacimiento) AS peso_nacimiento,
                COALESCE(t.aptitud, n.aptitud) AS aptitud,
                n.mtiposervicios_id,
                a.nro_arete AS arete_madre,
                a.nombre AS nombre_madre,
                COALESCE(ts.nombre, '') AS tipo_servicio
            FROM Vmregnacimientos n
            LEFT JOIN manimales a
                ON a.codigo_rgb_id_animal = n.codigo_rgb_id_animal_hembra
                AND a.mfundos_id = n.mfundos_id
            LEFT JOIN mtiposervicios ts ON ts.id = n.mtiposervicios_id
            LEFT JOIN mternerosxnacimientos t
                ON t.mregnacimientos_id = n.id AND t.status = 1
            WHERE n.mfundos_id = " . $mfundos_id . " AND n.mestatus_id = 1
            ORDER BY n.codigo_rgb_id_animal_hembra, n.f_parto";
    $registros = Vmregnacimiento::find_by_sql($sql);

    foreach ($registros as $registro) {
        $nacimiento = $registro->attributes();
        $fechaParto = $nacimiento['f_parto'];
        if ($fechaParto instanceof DateTimeInterface) {
            $fechaParto = $fechaParto->format('Y-m-d');
        } elseif ($fechaParto) {
            $fechaParto = substr((string) $fechaParto, 0, 10);
        }

        $resultado[] = array(
            'codigo_madre' => $nacimiento['codigo_rgb_id_animal_hembra'],
            'arete_madre' => $nacimiento['arete_madre'],
            'nombre_madre' => $nacimiento['nombre_madre'],
            'fecha' => $fechaParto ?: '',
            'nro_parto' => $nacimiento['nro_parto'],
            'tipo_servicio' => $nacimiento['tipo_servicio'],
            'nacio' => intval($nacimiento['estatus_nacido']) === 1 ? 'Vivo' : (intval($nacimiento['estatus_nacido']) === 2 ? 'Muerto' : '-'),
            'vivo' => intval($nacimiento['estatus_nacido']) === 1,
            'sexo_valor' => intval($nacimiento['sexo']),
            'sexo' => intval($nacimiento['sexo']) === 1 ? 'Macho' : (intval($nacimiento['sexo']) === 2 ? 'Hembra' : '-'),
            'peso' => $nacimiento['peso_nacimiento'] === '' || $nacimiento['peso_nacimiento'] === null ? null : floatval($nacimiento['peso_nacimiento']),
            'id_cria' => $nacimiento['id_ternero'] ? $nacimiento['id_ternero'] : $nacimiento['codigo_ternero'],
            'aptitud' => $nacimiento['aptitud'],
        );
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado);