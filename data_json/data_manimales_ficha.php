<?php
/**
 * Endpoint unificado que devuelve TODOS los datos del animal
 * para la ficha + el token QR de verificación pública.
 *
 * Compatible: PHP 7.4 y 8.3
 */

require_once '../models/Vanimale.php';
require_once '../models/Manimale.php';
require_once '../lib/firma.php';

// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// ---------- Validar sesión ----------
if (empty($_SESSION['mfundos_id'])) {
    http_response_code(401);
    echo json_encode([
        'error'   => 'sesion',
        'titulo'  => 'Sesión expirada',
        'mensaje' => 'Debe iniciar sesión nuevamente para ver esta ficha.'
    ]);
    exit;
}

$mfundos_id  = (int)$_SESSION['mfundos_id'];
$t_ganaderia = (int)(isset($_SESSION['tipo_ganaderia']) ? $_SESSION['tipo_ganaderia'] : 0);
$id_animal   = (int)(isset($_POST['id_animal']) ? $_POST['id_animal']
                    : (isset($_GET['id_animal']) ? $_GET['id_animal'] : 0));

if ($id_animal <= 0) {
    http_response_code(400);
    echo json_encode([
        'error'   => 'parametro',
        'titulo'  => 'Solicitud inválida',
        'mensaje' => 'No se especificó un animal válido.'
    ]);
    exit;
}

// ============================================================
// Helpers (compatibles PHP 7.4 y 8.3)
// ============================================================
function fx($val, $fmt = 'd-m-Y') {
    if ($val === null || $val === '' || $val === false) return '';
    if ($val instanceof DateTime) return $val->format($fmt);
    if (is_string($val)) {
        $ts = strtotime($val);
        return $ts ? date($fmt, $ts) : '';
    }
    return '';
}

function Cat_Actual_ficha($sexo, $fecha_nac, $mfundos_id, $t_ganaderia) {
    $edad = tiempo_t_ficha($fecha_nac);
    $q = Vanimale::find_by_sql("SELECT nombre FROM mcategorias
        WHERE mtipoganaderias_id=$t_ganaderia AND status=1 AND sexo=$sexo
          AND mfundos_id=$mfundos_id AND edadminima<=$edad AND edadmaxima>$edad
        ORDER BY id ASC LIMIT 1");
    if ($q) return $q[0]->nombre;

    $q = Vanimale::find_by_sql("SELECT nombre FROM mcategorias
        WHERE mtipoganaderias_id=$t_ganaderia AND status=1 AND sexo=$sexo
          AND mfundos_id=0 AND edadminima<=$edad AND edadmaxima>$edad
        ORDER BY id ASC LIMIT 1");
    if ($q) return $q[0]->nombre;

    return '';
}

function tiempo_t_ficha($fecha_nac) {
    if (!$fecha_nac) return 0;
    $dias = abs(floor((strtotime($fecha_nac) - strtotime(date('Y-m-d'))) / 86400));
    return (int)($dias / 30);
}

// ============================================================
// Cuerpo principal
// ============================================================
try {
    // ---------- Datos base del animal ----------
    $data = Manimale::find('all', [
        'conditions' => ['id=? and mfundos_id=? and status=1', $id_animal, $mfundos_id]
    ]);

    if (!$data) {
        http_response_code(404);
        echo json_encode([
            'error'   => 'no_encontrado',
            'titulo'  => 'Animal no disponible',
            'mensaje' => 'El animal que intentás consultar no existe o fue dado de baja del sistema.'
        ]);
        exit;
    }

    $rs = $data[0];
    $codigo  = $rs->codigo_rgb_id_animal;
    $cod_esc = addslashes($codigo);

    // ---------- Fechas ----------
    $f_nac_iso = fx($rs->f_nacimiento, 'Y-m-d');
    $f_nac     = fx($rs->f_nacimiento, 'd-m-Y');
    $f_ing     = fx($rs->f_ingreso,    'd-m-Y');

    // ---------- Edad legible ----------
    $edad_txt = '';
    if ($f_nac_iso) {
        $d = (new DateTime($f_nac_iso))->diff(new DateTime());
        $p = [];
        if ($d->y) $p[] = $d->y . ' año' . ($d->y != 1 ? 's' : '');
        if ($d->m) $p[] = $d->m . ' mes' . ($d->m != 1 ? 'es' : '');
        $edad_txt = $p ? implode(' ', $p) : $d->d . ' días';
    }

    // ---------- Foto ----------
    $foto = '';
    if ($rs->imagen && file_exists('../dist/img/fotos_animales/' . $rs->imagen)) {
        $foto = '../../dist/img/fotos_animales/' . $rs->imagen;
    }

    // ---------- Raza ----------
    $raza_txt = '';
    if ($rs->mrazas_id) {
        $q = Vanimale::find_by_sql("SELECT nombre FROM mrazas WHERE id=" . (int)$rs->mrazas_id . " LIMIT 1");
        if ($q) $raza_txt = $q[0]->nombre;
    }

    // ---------- Lote ----------
    $lote_txt = 'No tiene Lote Asignado';
    if ($rs->mlotes_id) {
        $q = Vanimale::find_by_sql("SELECT nombre FROM mlotes WHERE id=" . (int)$rs->mlotes_id . " LIMIT 1");
        if ($q) $lote_txt = $q[0]->nombre;
    }

    // ---------- Fundo ----------
    $fundo_nombre = '';
    $fundo_rif    = '';
    $q = Vanimale::find_by_sql("SELECT nombre, cid_rif FROM mfundos WHERE id=" . $mfundos_id . " LIMIT 1");
    if ($q) {
        $fundo_nombre = $q[0]->nombre;
        $fundo_rif    = $q[0]->cid_rif;
    }

    // ---------- Mapas de texto ----------
    $sexo_txt = ($rs->sexo == 1) ? 'HEMBRA' : (($rs->sexo == 2) ? 'MACHO' : 'N/D');
    $est_map  = array(1 => 'ACTIVO', 2 => 'DONADOR ACTIVO', 3 => 'DONADOR', 4 => 'DONADORA ACTIVO', 5 => 'DONADORA');
    $est_txt  = isset($est_map[$rs->estatus_animal]) ? $est_map[$rs->estatus_animal] : 'No Definido';
    $apt_map  = array(1 => 'LECHE', 2 => 'CARNE', 3 => 'DOBLE PROPÓSITO');
    $apt_txt  = isset($apt_map[$rs->aptitud]) ? $apt_map[$rs->aptitud] : '';

    // ---------- Categoría actual ----------
    $cat_actual = Cat_Actual_ficha($rs->sexo, $f_nac_iso, $mfundos_id, $t_ganaderia);

    // ---------- Movimientos ----------
    $movimientos = [];
    $mov_q = @Vanimale::find_by_sql(
        "SELECT mr.fecha_movimiento, mr.tipo_movimiento, mr.motivo, mr.observaciones,
                lo.nombre AS lote_origen, ld.nombre AS lote_destino,
                po.nombre AS potrero_origen, pd.nombre AS potrero_destino
           FROM mmovimientos_rebano_detalle mrd
     INNER JOIN mmovimientos_rebano mr ON mr.id = mrd.mmovimientos_rebano_id
      LEFT JOIN mlotes     lo ON lo.id = mr.mlotes_origen_id
      LEFT JOIN mlotes     ld ON ld.id = mr.mlotes_destino_id
      LEFT JOIN mpotreros  po ON po.id = mr.mpotreros_origen_id
      LEFT JOIN mpotreros  pd ON pd.id = mr.mpotreros_destino_id
          WHERE mrd.manimales_id = $id_animal
            AND mr.status = 1
       ORDER BY mr.fecha_movimiento DESC
          LIMIT 20"
    );

    if ($mov_q) {
        foreach ($mov_q as $m) {
            $orig = trim(($m->lote_origen ? $m->lote_origen : '') .
                         ($m->potrero_origen ? ' / ' . $m->potrero_origen : ''));
            $dest = trim(($m->lote_destino ? $m->lote_destino : '') .
                         ($m->potrero_destino ? ' / ' . $m->potrero_destino : ''));
            $movimientos[] = [
                'fecha'   => fx($m->fecha_movimiento, 'd-m-Y'),
                'tipo'    => $m->tipo_movimiento,
                'motivo'  => $m->motivo,
                'origen'  => $orig,
                'destino' => $dest,
                'obs'     => $m->observaciones
            ];
        }
    }

    // ---------- Leche ----------
    $leche = ['promedio' => 0, 'ultimo' => null, 'total' => 0];
    $lec_q = @Vanimale::find_by_sql(
        "SELECT ROUND(AVG(cantidad_leche_m + cantidad_leche_t), 2) AS promedio,
                MAX(fecha_produccion) AS ultimo,
                COUNT(*)              AS total
           FROM mprolecheras
          WHERE codigo_rgb_id_animal_hembra = '$cod_esc'
            AND mfundos_id = $mfundos_id
            AND status = 1"
    );

    if ($lec_q && (int)$lec_q[0]->total > 0) {
        $leche['promedio'] = (float)$lec_q[0]->promedio;
        $leche['ultimo']   = fx($lec_q[0]->ultimo, 'd-m-Y');
        $leche['total']    = (int)$lec_q[0]->total;
    }

    // ---------- Sanidad ----------
    $sanidad = null;
    $san_q = @Vanimale::find_by_sql(
        "SELECT fecha_control, evento, responsable, observaciones, fecha_prox_control
           FROM mcontrolsanitarios
          WHERE codigo_rgb_id_animal = '$cod_esc'
            AND mfundos_id = $mfundos_id
            AND status = 1
       ORDER BY fecha_control DESC
          LIMIT 1"
    );

    if ($san_q) {
        $sanidad = [
            'fecha'       => fx($san_q[0]->fecha_control,      'd-m-Y'),
            'evento'      => $san_q[0]->evento,
            'responsable' => $san_q[0]->responsable,
            'obs'         => $san_q[0]->observaciones,
            'prox'        => fx($san_q[0]->fecha_prox_control, 'd-m-Y')
        ];
    }

    // ---------- Texto plano para QR fallback ----------
    $qr_texto =
        "FICHA ANIMAL\n" .
        "Fundo: "  . $fundo_nombre . "\n" .
        "Codigo: " . $codigo . "\n" .
        "Nombre: " . $rs->nombre . "\n" .
        "Arete: "  . ($rs->nro_arete ? $rs->nro_arete : '-') . "\n" .
        "Sexo: "   . $sexo_txt . "\n" .
        "Raza: "   . ($raza_txt ? $raza_txt : '-') . "\n" .
        "Nac: "    . ($f_nac ? $f_nac : '-') . "\n" .
        "Edad: "   . ($edad_txt ? $edad_txt : '-') . "\n" .
        "Cat: "    . ($cat_actual ? $cat_actual : '-') . "\n" .
        "Lote: "   . $lote_txt . "\n" .
        "Padre: "  . trim(($rs->rgd_padre ? $rs->rgd_padre : '-') . ' ' . ($rs->nombre_padre ? $rs->nombre_padre : '')) . "\n" .
        "Madre: "  . trim(($rs->rgd_madre ? $rs->rgd_madre : '-') . ' ' . ($rs->nombre_madre ? $rs->nombre_madre : ''));

    // ---------- Respuesta final ----------
    echo json_encode([
        'id_animal'        => $rs->id,
        'cod_animal'       => $codigo,
        'nombre_animal'    => $rs->nombre,
        'sexo'             => $sexo_txt,
        'estatus'          => $est_txt,
        'raza'             => $raza_txt,
        'aptitud'          => $apt_txt,
        'nro_arete'        => $rs->nro_arete,
        'f_nacimiento'     => $f_nac,
        'f_ingreso'        => $f_ing,
        'edad'             => $edad_txt,
        'peso_nac'         => $rs->peso_nacimiento,
        'rena'             => $rs->rena,
        'alza'             => $rs->alza,
        'lote'             => $lote_txt,
        'procedencia'      => $rs->procedencia,
        'servicio'         => $rs->servicio,
        'observaciones'    => $rs->observaciones,
        'categoria_actual' => $cat_actual,
        'foto'             => $foto,
        'fundo'            => $fundo_nombre,
        'rif_fundo'        => $fundo_rif,
        'padre'            => ['codigo' => $rs->rgd_padre, 'nombre' => $rs->nombre_padre],
        'madre'            => ['codigo' => $rs->rgd_madre, 'nombre' => $rs->nombre_madre],
        'movimientos'      => $movimientos,
        'leche'            => $leche,
        'sanidad'          => $sanidad,
        'qr_texto'         => $qr_texto,
        'qr_url'           => firma_url($id_animal),

        // ⭐ Campos de migración
        'animal_origen_id'  => $rs->animal_origen_id,
        'codigo_origen'     => $rs->codigo_origen,
        'mfundos_origen_id' => $rs->mfundos_origen_id,
        'fecha_migracion'   => fx($rs->fecha_migracion, 'd-m-Y'),
        'es_migrado'        => !empty($rs->animal_origen_id)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'servidor',
        'titulo'  => 'Error del sistema',
        'mensaje' => 'No se pudieron obtener los datos. Intente nuevamente en unos minutos.'
    ]);
}