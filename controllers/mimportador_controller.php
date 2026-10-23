<?php
/* ============================================================
   Importador de animales - controller HTTP
   Endpoints:
     action=descargar_plantilla  → genera CSV con ejemplos reales
     action=descargar_catalogo   → CSV con razas/categorias/lotes/etc.
     action=validar              → recibe CSV, devuelve JSON
     action=confirmar            → inserta las filas decididas
   ============================================================ */

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../lib/importer/AnimalesImporter.php';

session_start();
$usuario_id       = isset($_SESSION['idusuariox'])        ? intval($_SESSION['idusuariox'])        : 0;
$mfundos_id       = isset($_SESSION['mfundos_id'])        ? intval($_SESSION['mfundos_id'])        : 0;
$t_ganaderia      = isset($_SESSION['tipo_ganaderia'])    ? intval($_SESSION['tipo_ganaderia'])    : 0;
$maximo_registros = isset($_SESSION['maximo_registros'])  ? intval($_SESSION['maximo_registros'])  : 999999;

if ($usuario_id <= 0 || $mfundos_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Sesion no valida'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    $imp = new AnimalesImporter($mfundos_id, $t_ganaderia, $usuario_id, $maximo_registros);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Error al iniciar importador: ' . $e->getMessage()));
    exit;
}

switch ($action) {

    case 'descargar_plantilla':
        $catalogos = array(
            'razas'      => $imp->getCatalogoPublico('razas'),
            'aptitudes'  => $imp->getCatalogoPublico('aptitudes'),
            'categorias' => $imp->getCatalogoPublico('categorias'),
            'lotes'      => $imp->getCatalogoPublico('lotes'),
            'servicios'  => $imp->getCatalogoPublico('servicios'),
        );

        $nombre_raza = !empty($catalogos['razas'])      ? $catalogos['razas'][0]['nombre']     : 'BRAHMAN ROJO';
        $apt1        = !empty($catalogos['aptitudes'])  ? $catalogos['aptitudes'][0]['nombre'] : 'LECHE';
        $lote1       = !empty($catalogos['lotes'])      ? $catalogos['lotes'][0]['nombre']     : 'LOTE DE ENGORDE';
        $serv1       = !empty($catalogos['servicios'])  ? $catalogos['servicios'][0]['nombre'] : 'MONTA NATURAL';

        $cat_h = 'NOVILLA';
        $cat_m = 'NOVILLO';
        foreach ($catalogos['categorias'] as $c) {
            if (intval($c['sexo']) === 1 && $cat_h === 'NOVILLA') $cat_h = $c['nombre'];
            if (intval($c['sexo']) === 2 && $cat_m === 'NOVILLO') $cat_m = $c['nombre'];
        }

        $headers = array(
            'nombre_animal','nro_arete','sexo','fecha_nacimiento',
            'raza','aptitud','categoria_ingreso','categoria_futura',
            'lote','servicio','estatus','peso_nacimiento','alza',
            'fecha_ingreso','rena','arete_madre','arete_padre',
            'procedencia','observaciones'
        );

        $ejemplo1 = array('LUNA', '541652', 'HEMBRA', '2024-10-08', $nombre_raza, $apt1, $cat_h, $cat_h, $lote1, $serv1, 'Activo', '35', '120', '', '', '', '', 'PROPIA', '');
        $ejemplo2 = array('ESTRELLA', '541653', 'HEMBRA', '2024-10-08', $nombre_raza, $apt1, $cat_h, $cat_h, $lote1, $serv1, 'Activo', '32', '115', '', '', '', '', 'PROPIA', '');
        $ejemplo3 = array('TORO BRAVO', '541654', 'MACHO', '2023-05-15', $nombre_raza, $apt1, $cat_m, $cat_m, $lote1, $serv1, 'Activo', '40', '130', '', '', '', '', 'PROPIA', '');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="plantilla_animales.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ',');
        fputcsv($out, $ejemplo1, ',');
        fputcsv($out, $ejemplo2, ',');
        fputcsv($out, $ejemplo3, ',');
        fclose($out);
        exit;

    case 'descargar_catalogo':
        $razas      = $imp->getCatalogoPublico('razas');
        $aptitudes  = $imp->getCatalogoPublico('aptitudes');
        $categorias = $imp->getCatalogoPublico('categorias');
        $lotes      = $imp->getCatalogoPublico('lotes');
        $servicios  = $imp->getCatalogoPublico('servicios');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="catalogo_referencia.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');

        fputcsv($out, array('=== RAZAS DISPONIBLES (propias del fundo + globales) ==='), ',');
        fputcsv($out, array('Nombre de Raza'), ',');
        foreach ($razas as $r) fputcsv($out, array($r['nombre']), ',');
        fputcsv($out, array(''), ',');

        fputcsv($out, array('=== APTITUDES DISPONIBLES (globales) ==='), ',');
        fputcsv($out, array('Nombre de Aptitud'), ',');
        foreach ($aptitudes as $a) fputcsv($out, array($a['nombre']), ',');
        fputcsv($out, array(''), ',');

        fputcsv($out, array('=== CATEGORIAS DISPONIBLES (propias + globales) ==='), ',');
        fputcsv($out, array('Nombre de Categoria', 'Sexo'), ',');
        foreach ($categorias as $c) {
            $sexo_txt = intval($c['sexo']) === 1 ? 'HEMBRA' : (intval($c['sexo']) === 2 ? 'MACHO' : 'AMBOS');
            fputcsv($out, array($c['nombre'], $sexo_txt), ',');
        }
        fputcsv($out, array(''), ',');

        fputcsv($out, array('=== LOTES DISPONIBLES (solo del fundo) ==='), ',');
        fputcsv($out, array('Nombre de Lote'), ',');
        foreach ($lotes as $l) fputcsv($out, array($l['nombre']), ',');
        fputcsv($out, array(''), ',');

        fputcsv($out, array('=== SERVICIOS DISPONIBLES (globales) ==='), ',');
        fputcsv($out, array('Nombre de Servicio'), ',');
        foreach ($servicios as $s) fputcsv($out, array($s['nombre']), ',');

        fclose($out);
        exit;

    case 'validar':
        if (empty($_FILES['archivo']['tmp_name'])) {
            header('Content-Type: application/json');
            echo json_encode(array('ok'=>false, 'error'=>'No se recibio archivo'));
            exit;
        }

        $res = $imp->validarArchivo($_FILES['archivo']['tmp_name']);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($res);
        exit;

    case 'confirmar':
        $payload = isset($_POST['filas']) ? json_decode($_POST['filas'], true) : null;
        if (!is_array($payload)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok'=>false, 'error'=>'Payload invalido'));
            exit;
        }

        $filas_ok = array();
        foreach ($payload as $fila) {
            if (!empty($fila['listo_para_importar']) && !empty($fila['datos'])) {
                $filas_ok[] = $fila;
            }
        }

        if (empty($filas_ok)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok'=>false, 'error'=>'No hay filas listas para importar'));
            exit;
        }

        $res = $imp->confirmarImportacion($filas_ok);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => true, 'resultado' => $res));
        exit;

    default:
        header('Content-Type: application/json');
        echo json_encode(array('ok'=>false, 'error'=>'Accion no reconocida'));
        exit;
}