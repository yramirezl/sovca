<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$raw = isset($_GET['%$%']) ? $_GET['%$%'] : '';
$parametros = $raw ? base64_decode($raw) : '';
$id_animal = 0;
if ($parametros !== false && $parametros !== '') {
    if (strpos($parametros, '=') !== false) {
        $partes = explode('=', $parametros);
        $id_animal = (int)end($partes);
    } else {
        $id_animal = (int)$parametros;
    }
}
if ($id_animal <= 0) { die('ID de animal inválido'); }

/* ---------------------------------------------------------------------
   El parámetro de esta página es el id del NACIMIENTO, pero la ficha es
   la del ANIMAL. Hay que resolver el ternero:
     · si ya está registrado  -> se muestra su ficha (con el id del animal),
     · si todavía no existe   -> se ofrece CREARLA.
   Antes se llamaba al endpoint con el id del nacimiento y respondía
   404 "Animal no disponible", aunque el ternero estuviera "Sin Fichar".
   --------------------------------------------------------------------- */
$id_reg_nac      = (int)$id_animal;
$nac             = null;
$nac_pendiente   = false;
$nac_codigo_cria = '';
$nac_madre       = '';
$nac_padre       = '';
$nac_fecha_txt   = '';
$nac_sexo_txt    = '';
$nac_peso_txt    = '';
$nac_raza_id     = 0;
$nac_raza_nombre = '';
$bovs_alta_url   = '../animales/frm_registrar';

$cwd_ficha = getcwd();
chdir(__DIR__ . '/../../controllers');   // CWD de primer nivel (includes relativos)
require_once '../models/Mregnacimiento.php';
chdir($cwd_ficha);

/* ---------------------------------------------------------------------
   Helpers del alta de crías (se usan cuando el parto tiene mellizos y
   hay que preparar una ficha por cada ternero/a).
   --------------------------------------------------------------------- */
if (!function_exists('bovs_nac_nombre_animal')) {
    function bovs_nac_nombre_animal($codigo, $mfundos_id)
    {
        $codigo = trim((string)$codigo);
        if ($codigo === '' || (int)$mfundos_id <= 0) {
            return '';
        }
        try {
            $cod_sql = "'" . str_replace("'", "''", $codigo) . "'";
            $q = Mregnacimiento::find_by_sql(
                'SELECT nombre FROM manimales
                  WHERE mfundos_id = ' . (int)$mfundos_id . '
                    AND status = 1
                    AND UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER(' . $cod_sql . ')
                  LIMIT 1'
            );
            return $q ? trim((string)$q[0]->nombre) : '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('bovs_nac_raza_esperada')) {
    function bovs_nac_raza_esperada($nac, $mfundos_id, $madre, $padre)
    {
        $consultas = array();

        if (!empty($nac->mregservicios_id)) {
            $consultas[] = 'SELECT mrazas_id FROM mregservicios
                             WHERE id = ' . (int)$nac->mregservicios_id . '
                               AND mfundos_id = ' . (int)$mfundos_id . '
                             LIMIT 1';
        }

        foreach (array($madre, $padre) as $cod) {
            $cod = trim((string)$cod);
            if ($cod === '') {
                continue;
            }
            $consultas[] = 'SELECT mrazas_id FROM manimales
                             WHERE mfundos_id = ' . (int)$mfundos_id . '
                               AND status = 1
                               AND UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER(\''
                                . str_replace("'", "''", $cod) . "')
                             LIMIT 1";
        }

        foreach ($consultas as $sql) {
            try {
                $q = Mregnacimiento::find_by_sql($sql);
                if ($q && (int)$q[0]->mrazas_id > 0) {
                    return (int)$q[0]->mrazas_id;
                }
            } catch (Throwable $e) {
                // se prueba la siguiente fuente
            }
        }

        return 0;
    }
}

try {
    $nac = Mregnacimiento::find('first', array(
        'conditions' => array('id=? and mfundos_id=? and status=1', $id_reg_nac, (int)$_SESSION['mfundos_id'])
    ));
} catch (Throwable $e) {
    $nac = null;
}

if ($nac) {
    $nac_codigo_cria = trim((string)$nac->codigo_ternero);
    if ($nac_codigo_cria === '') {
        $nac_codigo_cria = trim((string)$nac->id_ternero);
    }
    $nac_madre = trim((string)$nac->codigo_rgb_id_animal_hembra);
    $nac_padre = trim((string)$nac->codigo_rgb_id_animal_macho);
    $nac_fecha_txt = ($nac->f_parto instanceof DateTimeInterface)
        ? $nac->f_parto->format('d-m-Y')
        : trim((string)$nac->f_parto);
    $nac_sexo_txt = ((int)$nac->sexo === 1) ? 'Hembra' : (((int)$nac->sexo === 2) ? 'Macho' : '');
    $nac_peso_txt = trim((string)$nac->peso_nacimiento);

    /* ¿El ternero ya tiene ficha? El vínculo es manimales.codigo_ternero
       (el animal se crea con su código SOVCA y guarda aquí el código del
       parto, p. ej. MA-063-0140). Se acepta también el código del animal
       como respaldo. */
    $id_animal_ficha = 0;
    $nac_fichado = in_array($nac->fichado, array(true, 1, '1', 't', 'true'), true);
    if ($nac_codigo_cria === '') {
        $nac_codigo_cria = trim((string)$nac->id_ternero);
    }
    if ($nac_codigo_cria !== '') {
        try {
            /* El adaptador de esta versión de php-activerecord NO tiene
               quote(): se escapa a mano para la consulta. */
            $cod_cria_sql = "'" . str_replace("'", "''", $nac_codigo_cria) . "'";
            $q_ficha = Mregnacimiento::find_by_sql(
                'SELECT id FROM manimales
                  WHERE mfundos_id = ' . (int)$_SESSION['mfundos_id'] . '
                    AND status = 1
                    AND ( UPPER(BTRIM(codigo_ternero)) = UPPER(' . $cod_cria_sql . ')
                       OR UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER(' . $cod_cria_sql . ') )
                  ORDER BY CASE WHEN UPPER(BTRIM(codigo_ternero)) = UPPER(' . $cod_cria_sql . ') THEN 0 ELSE 1 END
                  LIMIT 1'
            );
            if ($q_ficha) {
                $id_animal_ficha = (int)$q_ficha[0]->id;
            }
        } catch (Throwable $e) {
            $id_animal_ficha = 0;
        }
    }

    if ($id_animal_ficha > 0) {
        $id_animal = $id_animal_ficha;   // la ficha se pide por id de ANIMAL
    } else {
        $nac_pendiente = true;

        /* Nombres de la madre y del padre para precargarlos en el alta. */
        $nac_madre_nombre = '';
        $nac_padre_nombre = '';
        $bovs_padres = array('madre' => $nac_madre, 'padre' => $nac_padre);
        foreach ($bovs_padres as $bovs_rol => $bovs_cod) {
            if ($bovs_cod === '') {
                continue;
            }
            $bovs_nombre = '';
            try {
                $bovs_cod_sql = "'" . str_replace("'", "''", $bovs_cod) . "'";
                $bovs_q = Mregnacimiento::find_by_sql(
                    'SELECT nombre FROM manimales
                      WHERE mfundos_id = ' . (int)$_SESSION['mfundos_id'] . '
                        AND status = 1
                        AND UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER(' . $bovs_cod_sql . ')
                      LIMIT 1'
                );
                $bovs_nombre = $bovs_q ? trim((string)$bovs_q[0]->nombre) : '';
            } catch (Throwable $e) {
                $bovs_nombre = '';
            }
            if ($bovs_rol === 'madre') {
                $nac_madre_nombre = $bovs_nombre;
            } else {
                $nac_padre_nombre = $bovs_nombre;
            }
        }

        /* Raza esperada de la cría: la del servicio gestante (la que se
           eligió en el formulario de servicios); si no hay servicio, la de
           la madre; y como último recurso, la del padre. */
        $nac_raza_id = 0;
        $bovs_consultas_raza = array();

        if (!empty($nac->mregservicios_id)) {
            $bovs_consultas_raza[] = 'SELECT mrazas_id FROM mregservicios
                                       WHERE id = ' . (int)$nac->mregservicios_id . '
                                         AND mfundos_id = ' . (int)$_SESSION['mfundos_id'] . '
                                       LIMIT 1';
        }
        foreach (array($nac_madre, $nac_padre) as $bovs_cod_raza) {
            if ($bovs_cod_raza === '') {
                continue;
            }
            $bovs_consultas_raza[] = "SELECT mrazas_id FROM manimales
                                       WHERE mfundos_id = " . (int)$_SESSION['mfundos_id'] . "
                                         AND status = 1
                                         AND UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER('" . str_replace("'", "''", $bovs_cod_raza) . "')
                                       LIMIT 1";
        }
        foreach ($bovs_consultas_raza as $bovs_sql_raza) {
            try {
                $bovs_q_raza = Mregnacimiento::find_by_sql($bovs_sql_raza);
                if ($bovs_q_raza && (int)$bovs_q_raza[0]->mrazas_id > 0) {
                    $nac_raza_id = (int)$bovs_q_raza[0]->mrazas_id;
                    break;
                }
            } catch (Throwable $e) {
                $nac_raza_id = 0;
            }
        }

        if ($nac_raza_id > 0) {
            try {
                $bovs_q_raza_nom = Mregnacimiento::find_by_sql(
                    'SELECT nombre FROM mrazas WHERE id = ' . $nac_raza_id . ' LIMIT 1'
                );
                $nac_raza_nombre = $bovs_q_raza_nom
                    ? trim((string)$bovs_q_raza_nom[0]->nombre)
                    : '';
            } catch (Throwable $e) {
                $nac_raza_nombre = '';
            }
        }

        /* Se pasan TODOS los datos que ya trae el parto para que el alta los
           precargue (y el usuario solo complete lo que falte). */
        $params_alta = 'id_reg_nac=' . $id_reg_nac
            . '&sexo=' . (int)$nac->sexo
            . '&fecha=' . rawurlencode($nac_fecha_txt)
            . '&peso=' . rawurlencode($nac_peso_txt)
            . '&ternero=' . rawurlencode($nac_codigo_cria)
            . '&aptitud=' . (int)$nac->aptitud
            . '&raza=' . $nac_raza_id
            . '&servicio=' . (int)$nac->mtiposervicios_id
            . '&madre=' . rawurlencode($nac_madre)
            . '&madre_nombre=' . rawurlencode($nac_madre_nombre)
            . '&padre=' . rawurlencode($nac_padre)
            . '&padre_nombre=' . rawurlencode($nac_padre_nombre);
        $bovs_alta_url = '../animales/frm_registrar?%$%=' . rawurlencode(base64_encode($params_alta));
    }
}

/* ---------------------------------------------------------------------
   CRÍAS DEL PARTO (MELLIZOS)
   ---------------------------------------------------------------------
   Un nacimiento puede tener 2 crías y cada una necesita SU PROPIA ficha
   de animal. Antes esta pantalla resolvía únicamente el ternero principal
   del registro, así que la segunda cría no se podía fichar y la ficha
   mostraba los datos de una sola.
   --------------------------------------------------------------------- */
$nac_crias     = array();
$nac_multiples = false;
$bovs_alta_crias = array();

if ($nac) {
    try {
        $q_crias = Mregnacimiento::find_by_sql(
            'SELECT t.nro_ternero,
                    COALESCE(t.id_ternero, \'\')            AS id_ternero,
                    COALESCE(t.sexo, 0)                   AS sexo,
                    COALESCE(t.estatus_nacido, 0)         AS estatus_nacido,
                    COALESCE(t.peso_nacimiento, \'\')      AS peso_nacimiento,
                    COALESCE(t.aptitud, 0)                AS aptitud,
                    a.id                                  AS id_animal,
                    COALESCE(a.codigo_rgb_id_animal, \'\') AS codigo_animal
               FROM mternerosxnacimientos t
          LEFT JOIN manimales a
                 ON a.mfundos_id = t.mfundos_id
                AND a.status = 1
                AND UPPER(BTRIM(a.codigo_ternero)) = UPPER(BTRIM(t.id_ternero))
              WHERE t.mregnacimientos_id = ' . (int)$id_reg_nac . '
                AND t.mfundos_id = ' . (int)$_SESSION['mfundos_id'] . '
                AND t.status = 1
              ORDER BY t.nro_ternero, t.id'
        );
    } catch (Throwable $e) {
        $q_crias = null;
    }

    if ($q_crias) {
        foreach ($q_crias as $c) {
            $sexo_c  = (int)$c->sexo;
            $nacio_c = (int)$c->estatus_nacido;

            $nac_crias[] = array(
                'nro_ternero' => (int)$c->nro_ternero,
                'id_ternero'  => (string)$c->id_ternero,
                'sexo_txt'    => $sexo_c === 1 ? 'Hembra' : ($sexo_c === 2 ? 'Macho' : ''),
                'estatus_txt' => $nacio_c === 2 ? 'Muerto' : ($nacio_c === 1 ? 'Vivo' : ''),
                'peso'        => trim((string)$c->peso_nacimiento),
                'aptitud'     => (int)$c->aptitud,
                'id_animal'   => (int)$c->id_animal,
                'codigo_animal' => (string)$c->codigo_animal,
                'sexo'        => $sexo_c
            );
        }
    }

    /* Nacimientos antiguos sin detalle: la cría del registro. */
    if (count($nac_crias) === 0) {
        $nac_crias[] = array(
            'nro_ternero' => 1,
            'id_ternero'  => $nac_codigo_cria,
            'sexo_txt'    => $nac_sexo_txt,
            'estatus_txt' => '',
            'peso'        => $nac_peso_txt,
            'aptitud'     => (int)$nac->aptitud,
            'id_animal'   => ($id_animal !== (int)$id_reg_nac) ? (int)$id_animal : 0,
            'codigo_animal' => '',
            'sexo'        => (int)$nac->sexo
        );
    }

    $nac_multiples = count($nac_crias) > 1;

    if ($nac_multiples) {

        /* Con mellizos se muestra SIEMPRE la tarjeta de crías (con el
           estado de ficha de cada una), no la ficha de un solo animal. */
        $nac_pendiente = true;
        $id_animal     = 0;

        $nac_madre_nombre = isset($nac_madre_nombre) ? $nac_madre_nombre : '';
        $nac_padre_nombre = isset($nac_padre_nombre) ? $nac_padre_nombre : '';

        if (trim((string)$nac_madre_nombre) === '') {
            $nac_madre_nombre = bovs_nac_nombre_animal($nac_madre, $_SESSION['mfundos_id']);
        }
        if (trim((string)$nac_padre_nombre) === '') {
            $nac_padre_nombre = bovs_nac_nombre_animal($nac_padre, $_SESSION['mfundos_id']);
        }

        if ((int)$nac_raza_id <= 0) {
            $nac_raza_id = bovs_nac_raza_esperada($nac, $_SESSION['mfundos_id'], $nac_madre, $nac_padre);
        }
        if ((int)$nac_raza_id > 0 && $nac_raza_nombre === '') {
            try {
                $bovs_q_raza_nom = Mregnacimiento::find_by_sql(
                    'SELECT nombre FROM mrazas WHERE id = ' . (int)$nac_raza_id . ' LIMIT 1'
                );
                $nac_raza_nombre = $bovs_q_raza_nom
                    ? trim((string)$bovs_q_raza_nom[0]->nombre)
                    : '';
            } catch (Throwable $e) {
                $nac_raza_nombre = '';
            }
        }

        /* Una URL de alta por cada cría sin ficha. */
        foreach ($nac_crias as $i => $cria) {
            if ((int)$cria['id_animal'] > 0) {
                continue;
            }

            $params_cria = 'id_reg_nac=' . $id_reg_nac
                . '&sexo=' . (int)$cria['sexo']
                . '&fecha=' . rawurlencode($nac_fecha_txt)
                . '&peso=' . rawurlencode($cria['peso'])
                . '&ternero=' . rawurlencode($cria['id_ternero'])
                . '&aptitud=' . (int)$cria['aptitud']
                . '&raza=' . (int)$nac_raza_id
                . '&servicio=' . (int)$nac->mtiposervicios_id
                . '&madre=' . rawurlencode($nac_madre)
                . '&madre_nombre=' . rawurlencode($nac_madre_nombre)
                . '&padre=' . rawurlencode($nac_padre)
                . '&padre_nombre=' . rawurlencode($nac_padre_nombre);

            $bovs_alta_crias[$i] = '../animales/frm_registrar?%$%=' . rawurlencode(base64_encode($params_cria));
        }

        if (isset($bovs_alta_crias[0])) {
            $bovs_alta_url = $bovs_alta_crias[0];
        }
    }
}
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
/* ============================================================
   ESTILOS BASE (DESKTOP)
   ============================================================ */
.ficha-wrap {
    max-width: 1000px;
    margin: 0 auto;
    background: #fff;
    padding: 20px;
    border: 1px solid #d2d6de;
    border-radius: 4px;
}
.ficha-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #3c8dbc;
    padding-bottom: 10px;
    margin-bottom: 15px;
    gap: 12px;
}
.ficha-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: bold;
    color: #3c8dbc;
    letter-spacing: 1px;
}
.ficha-header .fundo { font-size: 13px; }
.ficha-header .fundo strong { font-size: 16px; }

.ficha-main { display: flex; gap: 20px; margin-bottom: 15px; }
.ficha-foto { width: 160px; flex-shrink: 0; text-align: center; }
.ficha-foto img {
    width: 160px;
    height: 160px;
    object-fit: cover;
    border: 2px solid #3c8dbc;
    border-radius: 4px;
    background: #f4f4f4;
}
.ficha-main .datos { flex: 1; min-width: 0; }
.ficha-codigo {
    font-size: 22px;
    font-weight: bold;
    color: #3c8dbc;
    margin-bottom: 8px;
    word-break: break-word;
}

.ficha-tabla { width: 100%; font-size: 12px; }
.ficha-tabla th {
    width: 38%;
    color: #666;
    font-weight: 600;
    padding: 2px 0;
    vertical-align: top;
}
.ficha-tabla td { padding: 2px 0; }

.ficha-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.ficha-bloque {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 10px 12px;
    margin-bottom: 12px;
    background: #fafafa;
}
.ficha-bloque h4 {
    margin: 0 0 8px 0;
    font-size: 13px;
    font-weight: bold;
    color: #3c8dbc;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 4px;
}

.ficha-tabla-full {
    width: 100%;
    font-size: 11px;
    border-collapse: collapse;
}
.ficha-tabla-full th {
    background: #3c8dbc;
    color: #fff;
    padding: 4px 6px;
    text-align: left;
}
.ficha-tabla-full td {
    padding: 4px 6px;
    border-bottom: 1px solid #eee;
}

.ficha-obs { font-size: 12px; color: #333; min-height: 40px; }

.ficha-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px dashed #ccc;
    gap: 20px;
}
.ficha-qr { text-align: center; }
.ficha-qr img { width: 110px; height: 110px; }
.ficha-firma { text-align: center; width: 220px; }
.linea-firma {
    border-bottom: 1px solid #333;
    height: 40px;
    margin-bottom: 4px;
}

.ficha-botones { text-align: right; margin-top: 20px; }
.ficha-botones .btn { margin-left: 8px; margin-bottom: 6px; }

/* ============================================================
   SPINNER DE CARGA
   ============================================================ */
.ficha-cargando {
    text-align: center;
    padding: 80px 20px;
    color: #888;
}
.ficha-cargando .spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 4px solid #e0e0e0;
    border-top-color: #3c8dbc;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 12px;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============================================================
   PANTALLA DE "NO DISPONIBLE"
   ============================================================ */
.ficha-no-disponible {
    max-width: 520px;
    margin: 40px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
    overflow: hidden;
    text-align: center;
}
.ficha-no-disponible .nd-header {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: #fff;
    padding: 30px 24px;
}
.ficha-no-disponible .nd-icon {
    font-size: 56px;
    line-height: 1;
    margin-bottom: 10px;
}
.ficha-no-disponible .nd-header h2 {
    border: none;
    color: #fff;
    font-size: 20px;
    margin: 0;
    padding: 0;
    letter-spacing: 1px;
}
.ficha-no-disponible .nd-body { padding: 24px; }
.ficha-no-disponible .nd-msg {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}
.ficha-no-disponible .nd-codigo {
    display: inline-block;
    background: #f1f3f5;
    color: #495057;
    padding: 6px 14px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
    margin-bottom: 20px;
}

/* ============================================================
   BANNER DE MIGRACIÓN
   ============================================================ */
.banner-migracion {
    background: #e7f3ff;
    border-left: 4px solid #3c8dbc;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 13px;
}
.banner-migracion .info { flex: 1 1 auto; min-width: 200px; }
.banner-migracion .info strong { color: #1a4d6b; display: block; margin-bottom: 4px; }
.banner-migracion .info small { color: #4a5f70; font-size: 12px; }
.banner-migracion .info code {
    background: #fff; padding: 1px 6px; border-radius: 3px;
    color: #3c8dbc; font-weight: 600; font-size: 11.5px;
}
.banner-migracion .btn { flex-shrink: 0; }

/* ============================================================
   PANEL LATERAL (DRAWER) DE HISTORIAL
   ============================================================ */
.hist-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(30, 40, 50, 0.35);
    z-index: 1040;
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s ease;
}
.hist-overlay.open {
    opacity: 1;
    pointer-events: auto;
}

.hist-drawer {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: 460px;
    max-width: 90vw;
    background: #fff;
    box-shadow: -4px 0 20px rgba(0,0,0,.15);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform .3s cubic-bezier(.25,.8,.25,1);
}
.hist-drawer.open { transform: translateX(0); }

.hist-drawer-header {
    background: linear-gradient(135deg, #234e35, #2c6e46);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.hist-drawer-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .3px;
}
.hist-drawer-header .hist-close {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    font-size: 12px;
    line-height: 1.4;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 4px;
}
.hist-drawer-header .hist-close:hover { background: rgba(255,255,255,.22); }

.hist-drawer-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 16px 18px;
    background: #f8fafc;
}

.hist-origen-info {
    background: #fff;
    border: 1px solid #e5e8eb;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 14px;
    font-size: 12.5px;
}
.hist-origen-info .row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px dashed #eef1f4;
}
.hist-origen-info .row:last-child { border-bottom: none; }
.hist-origen-info .lbl { color: #8895a4; font-weight: 500; }
.hist-origen-info .val { color: #233e2d; font-weight: 600; text-align: right; }

.hist-seccion {
    background: #fff;
    border: 1px solid #e5e8eb;
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
}
.hist-seccion-head {
    padding: 10px 14px;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    user-select: none;
    transition: background .12s;
}
.hist-seccion-head:hover { background: #f8fafc; }
.hist-seccion-head .icon {
    width: 26px; height: 26px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 12px;
    flex-shrink: 0;
}
.hist-seccion-head .titulo {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: #233e2d;
}
.hist-seccion-head .count {
    background: #e8f5e9;
    color: #1b5e20;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}
.hist-seccion-head .caret {
    color: #8895a4;
    font-size: 11px;
    transition: transform .2s;
}
.hist-seccion.open .hist-seccion-head .caret {
    transform: rotate(180deg);
}
.hist-seccion-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height .25s ease;
    background: #fafbfc;
}
.hist-seccion.open .hist-seccion-body {
    max-height: 2000px;
    border-top: 1px solid #eef1f4;
}
.hist-seccion-body .inner {
    padding: 8px 14px 12px;
}
.hist-acciones {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
}
.hist-evento-acciones {
    text-align: right;
    padding: 2px 0 8px !important;
    border-bottom: 1px solid #eef1f4;
}
.hist-evento-reporte-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #1672a5 !important;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    text-decoration: underline;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative;
    z-index: 1;
}
.hist-evento-reporte-link:hover,
.hist-evento-reporte-link:focus { color: #0b4f75 !important; }
.hist-reporte-preview-overlay { position: fixed; inset: 0; z-index: 1060; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(25,35,42,.48); }
.hist-reporte-preview-overlay.open { display: flex; }
.hist-reporte-preview { width: min(960px, 96vw); height: min(820px, 92vh); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 8px; box-shadow: 0 18px 55px rgba(0,0,0,.28); }
.hist-reporte-preview-header, .hist-reporte-preview-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; background: #fff; flex-shrink: 0; }
.hist-reporte-preview-header { border-bottom: 1px solid #e5e8eb; }
.hist-reporte-preview-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: #233e2d; }
.hist-reporte-preview-footer { justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid #e5e8eb; }
.hist-reporte-preview-opcion { margin: 0 auto 0 0; display: flex; align-items: center; gap: 7px; color: #46545a; font-size: 12px; font-weight: 600; cursor: pointer; }
.hist-reporte-preview-opcion input { width: 15px; height: 15px; margin: 0; accent-color: #27834a; }
.hist-reporte-preview-ayuda { margin: 0 auto 0 0; color: #748087; font-size: 11px; font-weight: 400; }
.hist-reporte-preview-header .hist-reporte-preview-ayuda { display: block; margin: 3px 0 0; }
.hist-reporte-preview-body { min-height: 0; flex: 1; background: #eef1f3; }
.hist-reporte-preview-body iframe { display: block; width: 100%; height: 100%; border: 0; background: #fff; }
@media (max-width: 600px) { .hist-reporte-preview-overlay { padding: 0; } .hist-reporte-preview { width: 100%; height: 100%; max-height: 100%; border-radius: 0; } }

.hist-tabla {
    width: 100%;
    font-size: 11.5px;
    border-collapse: collapse;
}
.hist-tabla tr { border-bottom: 1px solid #eef1f4; }
.hist-tabla tr:last-child { border-bottom: none; }
.hist-tabla td {
    padding: 5px 0;
    color: #333;
    vertical-align: top;
}
.hist-tabla .fecha {
    width: 85px;
    color: #8895a4;
    font-size: 11px;
    white-space: nowrap;
    padding-right: 8px;
}
.hist-tabla .destacado {
    font-weight: 600;
    color: #233e2d;
}
.hist-tabla .extra {
    color: #666;
    font-size: 11px;
}
.hist-tabla .num {
    text-align: right;
    font-weight: 700;
    color: #2f8f4a;
    white-space: nowrap;
}

.hist-vacio {
    font-size: 12px;
    color: #b5bbc8;
    font-style: italic;
    text-align: center;
    padding: 12px 0;
}

.bg-icon-leche     { background: linear-gradient(135deg, #3c8dbc, #5dade2); }
.bg-icon-sanidad   { background: linear-gradient(135deg, #27ae60, #52be80); }
.bg-icon-pesos     { background: linear-gradient(135deg, #f39c12, #f7b955); }
.bg-icon-servicios { background: linear-gradient(135deg, #8e44ad, #b07cc6); }
.bg-icon-movs      { background: linear-gradient(135deg, #e74c3c, #ec7063); }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 991px) {
    .ficha-wrap { padding: 16px; }
    .ficha-header h2 { font-size: 18px; }
}

@media (max-width: 767px) {
    .ficha-wrap {
        padding: 12px;
        border-radius: 0;
        border-left: none;
        border-right: none;
        margin: 0;
    }
    .ficha-header {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
        gap: 8px;
    }
    .ficha-header .text-right { text-align: left !important; width: 100%; }
    .ficha-header h2 { font-size: 17px; }
    .ficha-header .fundo strong { font-size: 14px; }

    .ficha-main { flex-direction: column; align-items: center; gap: 12px; }
    .ficha-foto { width: 100%; }
    .ficha-foto img { width: 140px; height: 140px; margin: 0 auto; display: block; }
    .ficha-main .datos { width: 100%; }
    .ficha-codigo { font-size: 18px; text-align: center; }

    .ficha-tabla th, .ficha-tabla td { display: block; width: 100%; padding: 2px 0; }
    .ficha-tabla th { color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; }
    .ficha-tabla td { margin-bottom: 6px; }

    .ficha-grid-2 { grid-template-columns: 1fr; gap: 10px; }

    .ficha-tabla-full thead { display: none; }
    .ficha-tabla-full, .ficha-tabla-full tbody, .ficha-tabla-full tr, .ficha-tabla-full td { display: block; width: 100%; }
    .ficha-tabla-full tr { background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; }
    .ficha-tabla-full td { border: none; padding: 3px 0; display: flex; justify-content: space-between; font-size: 11.5px; }
    .ficha-tabla-full td:before {
        content: attr(data-label); font-weight: 600; color: #888;
        font-size: 10.5px; text-transform: uppercase; padding-right: 8px; flex-shrink: 0;
    }

    .ficha-footer { flex-direction: column; align-items: center; gap: 20px; padding-top: 12px; }
    .ficha-firma { width: 100%; max-width: 260px; }

    .ficha-botones { text-align: center; display: flex; flex-direction: column; gap: 8px; }
    .ficha-botones .btn, .ficha-botones a.btn { width: 100%; margin: 0; padding: 10px 12px; font-size: 14px; }

    .ficha-bloque { padding: 10px; }
    .ficha-bloque h4 { font-size: 12px; }

    .hist-drawer { width: 100%; max-width: 100%; }
}

@media (max-width: 480px) {
    .ficha-codigo { font-size: 16px; }
    .ficha-foto img { width: 120px; height: 120px; }
    .ficha-qr img { width: 100px; height: 100px; }
    .ficha-header h2 { font-size: 15px; letter-spacing: .5px; }
}

@media print {
    @page { size: A4; margin: 8mm; }
    .no-print, .main-header, .main-sidebar, .main-footer, .content-header { display: none !important; }
    .content-wrapper { margin: 0 !important; background: #fff !important; }
    .ficha-wrap { border: none; padding: 0; max-width: 100%; }
    .ficha-botones { display: none !important; }
    body { background: #fff !important; }
    .hist-drawer, .hist-overlay { display: none !important; }
}
</style>

<div class="message"></div>

<!-- Spinner de carga -->
<div id="ficha_loading" class="ficha-cargando">
    <div class="spinner"></div>
    <div>Cargando ficha del animal...</div>
</div>

<!-- Pantalla de "no disponible" -->
<div id="ficha_no_disponible" class="ficha-no-disponible" style="display:none;">
    <div class="nd-header">
        <div class="nd-icon" id="nd_icon">✕</div>
        <h2 id="nd_titulo">Animal no disponible</h2>
    </div>
    <div class="nd-body">
        <p class="nd-msg" id="nd_mensaje">
            El animal que intentás consultar no está disponible en este momento.
        </p>
        <div class="nd-codigo" id="nd_codigo" style="display:none;"></div>
        <div>
            <button type="button" class="btn btn-primary" onclick="window.location.href='../animales/';">
                <i class="fa fa-reply"></i> Volver al listado
            </button>
        </div>
    </div>
</div>

<!-- Ficha principal -->
<?php if ($nac_pendiente): ?>
<div id="ficha_pendiente" style="max-width:<?php echo $nac_multiples ? '900px' : '640px'; ?>;margin:30px auto;background:#fff;border:1px solid #e3e8ee;border-radius:10px;box-shadow:0 6px 22px rgba(0,0,0,.06);overflow:hidden;">
    <div style="background:linear-gradient(135deg,#2f8f4a,#237a3c);color:#fff;padding:22px 24px;">
        <h2 style="margin:0;font-size:20px;color:#fff;border:none;"><i class="fa fa-id-card-o"></i> <?php echo $nac_multiples ? 'Crías del parto' : 'Ficha pendiente'; ?></h2>
        <p style="margin:6px 0 0;font-size:13px;opacity:.95;"><?php echo $nac_multiples ? 'Cada ternero/a del parto lleva su propia ficha de animal.' : 'Este nacimiento todavía no tiene ficha de animal registrada.'; ?></p>
    </div>
    <div style="padding:20px 24px;">
        <?php if ($nac_multiples): ?>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f7fa;">
                    <th style="text-align:left;padding:6px 8px;">#</th>
                    <th style="text-align:left;padding:6px 8px;">ID / Arete</th>
                    <th style="text-align:left;padding:6px 8px;">Sexo</th>
                    <th style="text-align:left;padding:6px 8px;">Peso</th>
                    <th style="text-align:left;padding:6px 8px;">¿Nació?</th>
                    <th style="text-align:left;padding:6px 8px;">Estado</th>
                    <th style="text-align:right;padding:6px 8px;">Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($nac_crias as $i_cria => $cria): ?>
                <tr style="border-bottom:1px solid #eef1f5;">
                    <td style="padding:8px;color:#6b7a8c;"><?php echo (int)$cria['nro_ternero']; ?></td>
                    <td style="padding:8px;font-weight:700;"><?php echo htmlspecialchars($cria['id_ternero'] !== '' ? $cria['id_ternero'] : '(sin ID)', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="padding:8px;"><?php echo htmlspecialchars($cria['sexo_txt'] !== '' ? $cria['sexo_txt'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="padding:8px;"><?php echo htmlspecialchars($cria['peso'] !== '' ? $cria['peso'] . ' kg' : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="padding:8px;"><?php echo htmlspecialchars($cria['estatus_txt'] !== '' ? $cria['estatus_txt'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="padding:8px;">
                        <?php if ((int)$cria['id_animal'] > 0): ?>
                        <span style="background:#e8f5ec;color:#146b2e;border-radius:10px;padding:2px 9px;font-size:11.5px;font-weight:700;">Fichada</span>
                        <?php else: ?>
                        <span style="background:#fdeceb;color:#b3261e;border-radius:10px;padding:2px 9px;font-size:11.5px;font-weight:700;">Sin fichar</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:8px;text-align:right;">
                        <?php if ((int)$cria['id_animal'] > 0): ?>
                        <a class="btn btn-default btn-xs" href="../animales/frm_ficha.php?%$%=<?php echo rawurlencode(base64_encode('id=' . (int)$cria['id_animal'])); ?>">
                            <i class="fa fa-eye"></i> Ver ficha
                        </a>
                        <?php elseif (isset($bovs_alta_crias[$i_cria])): ?>
                        <a class="btn btn-success btn-xs" href="<?php echo htmlspecialchars($bovs_alta_crias[$i_cria], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa fa-plus"></i> Crear ficha
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table style="width:100%;font-size:13px;margin-top:16px;">
        <?php else: ?>
        <table style="width:100%;font-size:13px;">
        <?php endif; ?>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;"><?php echo $nac_multiples ? 'Código del parto' : 'Cría'; ?></th><td style="padding:4px 0;font-weight:700;"><?php echo htmlspecialchars($nac_codigo_cria !== '' ? $nac_codigo_cria : '(sin código)', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Madre</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_madre !== '' ? $nac_madre : '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Padre</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_padre !== '' ? $nac_padre : '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Fecha de parto</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_fecha_txt !== '' ? $nac_fecha_txt : '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php if (!$nac_multiples): ?>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Sexo</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_sexo_txt !== '' ? $nac_sexo_txt : '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Peso al nacer</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_peso_txt !== '' ? $nac_peso_txt : '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            <?php if ($nac_raza_nombre !== ''): ?>
            <tr><th style="text-align:left;color:#6b7a8c;font-weight:600;padding:4px 0;">Raza esperada</th><td style="padding:4px 0;"><?php echo htmlspecialchars($nac_raza_nombre, ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
        </table>
        <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
            <?php if (!$nac_multiples): ?>
            <a class="btn btn-success" href="<?php echo htmlspecialchars($bovs_alta_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-plus"></i> Crear ficha del ternero</a>
            <?php endif; ?>
            <a class="btn btn-default" href="index.php">Volver al listado</a>
        </div>
        <?php if ($nac_fichado): ?>
        <p style="margin-top:14px;font-size:12px;color:#b45309;"><b>Atención:</b> este nacimiento figura como <b>Fichado</b>, pero su animal no aparece en el hato (puede estar dado de baja, migrado o con otro código). Revise antes de crear una ficha nueva.</p>
        <?php else: ?>
        <p style="margin-top:14px;font-size:12px;color:#6b7a8c;"><?php echo $nac_multiples ? 'Al guardar la ficha de cada cría, este nacimiento quedará marcado como <b>Fichado</b>; cada ternero/a conserva su propio estado en la lista de arriba.' : 'Al guardar el animal, este nacimiento quedará marcado como <b>Fichado</b>.'; ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="ficha-wrap" id="ficha_container" style="display:none;">

    <!-- ⭐ Banner de migración -->
    <div id="banner_migracion" class="banner-migracion no-print" style="display:none;">
        <div class="info">
            <strong><i class="fa fa-exchange"></i> Animal migrado desde otro fundo</strong>
            <small>
                Código original: <code id="mig_codigo_origen">—</code> ·
                Fundo origen: <strong id="mig_fundo_origen">—</strong> ·
                Fecha: <span id="mig_fecha">—</span>
            </small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="btn_ver_historial_origen">
            <i class="fa fa-history"></i> Ver historial de origen
        </button>
    </div>

    <div class="ficha-header">
        <div class="fundo">
            <strong id="fundo_nombre">—</strong><br>
            <small>RIF: <span id="rif_fundo">—</span></small>
        </div>
        <div class="text-right">
            <h2>FICHA DEL ANIMAL</h2>
            <small>Fecha: <?php echo date("d-m-Y"); ?></small>
        </div>
    </div>

    <div class="ficha-main">
        <div class="ficha-foto">
            <img id="foto_animal"
                 src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Crect width='160' height='160' fill='%23f4f4f4'/%3E%3Ctext x='80' y='85' font-family='Arial' font-size='13' fill='%23999' text-anchor='middle'%3ESin foto%3C/text%3E%3C/svg%3E"
                 alt="Foto del animal">
        </div>
        <div class="datos">
            <div class="ficha-codigo" id="cod_animal">—</div>
            <table class="ficha-tabla">
                <tr><th>Nombre:</th>    <td id="nombre_animal">—</td>
                    <th>Arete:</th>     <td id="numero_arete">—</td></tr>
                <tr><th>Sexo:</th>      <td id="sexo">—</td>
                    <th>Raza:</th>      <td id="raza_animal">—</td></tr>
                <tr><th>Edad:</th>      <td id="edad">—</td>
                    <th>Estatus:</th>   <td id="estatus">—</td></tr>
                <tr><th>Categoría:</th> <td id="categoria_actual">—</td>
                    <th>Aptitud:</th>   <td id="aptitud_animal">—</td></tr>
            </table>
        </div>
    </div>

    <div class="ficha-grid-2">
        <div class="ficha-bloque">
            <h4>DATOS DE REGISTRO</h4>
            <table class="ficha-tabla">
                <tr><th>Fecha nac.:</th>    <td id="fech_nac">—</td></tr>
                <tr><th>Fecha ingreso:</th> <td id="fecha_ingreso">—</td></tr>
                <tr><th>Peso nac.:</th>     <td id="peso_nac">—</td></tr>
                <tr><th>RENA:</th>          <td id="rena">—</td></tr>
                <tr><th>Alzada:</th>        <td id="alza">—</td></tr>
                <tr><th>Lote:</th>          <td id="lote">—</td></tr>
                <tr><th>Procedencia:</th>   <td id="procedencia">—</td></tr>
                <tr><th>Servicio:</th>      <td id="servicio">—</td></tr>
            </table>
        </div>
        <div class="ficha-bloque">
            <h4>GENEALOGÍA</h4>
            <table class="ficha-tabla">
                <tr><th>Madre:</th>  <td id="madre">—</td></tr>
                <tr><th>Padre:</th>  <td id="padre">—</td></tr>
            </table>
            <h4 style="margin-top:12px;">OBSERVACIONES</h4>
            <div class="ficha-obs" id="observaciones">—</div>
        </div>
    </div>

    <div class="ficha-bloque" id="bloque_movimientos" style="display:none;">
        <h4>HISTORIAL DE MOVIMIENTOS</h4>
        <table class="ficha-tabla-full">
            <thead>
                <tr>
                    <th style="width:80px;">Fecha</th>
                    <th style="width:70px;">Tipo</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Motivo / Obs.</th>
                </tr>
            </thead>
            <tbody id="tbody_mov"></tbody>
        </table>
    </div>

    <div class="ficha-bloque" id="bloque_leche" style="display:none;">
        <h4>PRODUCCIÓN DE LECHE</h4>
        <p style="font-size:13px; margin:0;">
            Promedio: <strong id="leche_promedio">—</strong> L/día ·
            Último registro: <strong id="leche_ultimo">—</strong> ·
            Registros: <strong id="leche_total">—</strong>
        </p>
    </div>

    <div class="ficha-bloque" id="bloque_sanidad" style="display:none;">
        <h4>CONTROLES SANITARIOS</h4>
        <table class="ficha-tabla">
            <tr><th>Último control:</th>    <td id="san_fecha">—</td></tr>
            <tr><th>Evento:</th>            <td id="san_evento">—</td></tr>
            <tr><th>Responsable:</th>       <td id="san_resp">—</td></tr>
            <tr><th>Próximo control:</th>   <td id="san_prox">—</td></tr>
            <tr><th>Observaciones:</th>     <td id="san_obs">—</td></tr>
        </table>
    </div>

    <div class="ficha-footer">
        <div class="ficha-qr">
            <div id="qr_container"></div>
            <small style="color:#666;">Escanear para verificar autenticidad</small>
        </div>
        <div class="ficha-firma">
            <div class="linea-firma"></div>
            <small>Firma responsable</small>
        </div>
    </div>

    <div class="ficha-botones no-print">
        <button type="button" class="btn btn-warning" id="exit">
            <i class="fa fa-reply"></i> Regresar
        </button>
        <a id="ver_publico" class="btn btn-info" href="#" target="_blank" style="display:none;">
            <i class="fa fa-external-link"></i> Ver página pública
        </a>
        <button type="button" class="btn btn-success" id="pdf">
            <i class="fa fa-file-pdf-o"></i> Exportar PDF
        </button>
        <button type="button" class="btn btn-primary" id="imprimir">
            <i class="fa fa-print"></i> Imprimir
        </button>
    </div>
</div>

<!-- ⭐ PANEL LATERAL DE HISTORIAL (reemplaza al modal) -->
<div class="hist-overlay" id="hist_overlay"></div>

<div class="hist-drawer" id="hist_drawer">
    <div class="hist-drawer-header">
        <h3><i class="fa fa-history"></i> Historial de Origen</h3>
        <button type="button" class="hist-close" id="hist_close"><i class="fa fa-sign-out"></i> Salir</button>
    </div>

    <div class="hist-drawer-body" id="hist_drawer_body">
        <div style="text-align:center; padding:40px; color:#8895a4;">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top:8px;">Cargando historial...</p>
        </div>
    </div>
</div>

<div class="hist-reporte-preview-overlay" id="hist_reporte_preview_overlay" aria-hidden="true">
    <section class="hist-reporte-preview" role="dialog" aria-modal="true" aria-labelledby="hist_reporte_preview_title">
        <div class="hist-reporte-preview-header">
            <div><h3 id="hist_reporte_preview_title"><i class="fa fa-file-text-o"></i> Vista previa del reporte</h3><small class="hist-reporte-preview-ayuda">El reporte del proceso solicitado siempre se muestra; la procedencia del fundo es opcional.</small></div>
            <button type="button" class="btn btn-default btn-sm" id="hist_reporte_preview_close"><i class="fa fa-times"></i> Cerrar</button>
        </div>
        <div class="hist-reporte-preview-body"><iframe id="hist_reporte_preview_frame" title="Vista previa del reporte"></iframe></div>
        <div class="hist-reporte-preview-footer">
            <label class="hist-reporte-preview-opcion"><input type="checkbox" id="hist_reporte_preview_origen"> Mostrar datos del fundo anterior</label>
            <span class="hist-reporte-preview-ayuda">Opcional; el reporte del evento se incluye siempre.</span>
            <button type="button" class="btn btn-default" id="hist_reporte_preview_cancel">Cerrar</button>
            <button type="button" class="btn btn-primary" id="hist_reporte_preview_print"><i class="fa fa-print"></i> Imprimir / Guardar PDF</button>
        </div>
    </section>
</div>

<form id="form_pdf" method="post" action="../../controllers/rficha.php" target="_blank" style="display:none;">
    <input type="hidden" name="id_animal" id="pdf_id_animal" value="<?php echo (int)$id_animal; ?>">
    <input type="hidden" name="qr"        id="pdf_qr">
</form>

<script src="../../plugins/qrcode/qrcode.min.js"></script>
<script>
var ID_ANIMAL   = <?php echo json_encode((int)$id_animal); ?>;
var FICHA_PENDIENTE = <?php echo $nac_pendiente ? 'true' : 'false'; ?>;
var QR_DATA_URL = '';
var HISTORIAL_ORIGEN = null;
var DATOS_ANIMAL_REPORTE = null;
var REPORTE_EVENTO_ORIGEN = null;
var REPORTE_EVENTO_SOLICITUD = 0;

function escaparReporte(valor) {
    return $('<div>').text(valor === null || valor === undefined || valor === '' ? '—' : String(valor)).html();
}

function mostrarVistaPreviaReporte(titulo, html) {
    $('#hist_reporte_preview_title').text(titulo || 'Vista previa del reporte');
    document.getElementById('hist_reporte_preview_frame').srcdoc = html;
    $('#hist_reporte_preview_overlay').addClass('open').attr('aria-hidden', 'false');
}

function cerrarVistaPreviaReporte() {
    $('#hist_reporte_preview_overlay').removeClass('open').attr('aria-hidden', 'true');
    document.getElementById('hist_reporte_preview_frame').srcdoc = '';
    $('#hist_reporte_preview_origen').prop('checked', false);
    REPORTE_EVENTO_ORIGEN = null;
    REPORTE_EVENTO_SOLICITUD++;
}

function imprimirVistaPreviaReporte() {
    var frame = document.getElementById('hist_reporte_preview_frame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}

function cargarReporteEventoOrigen(incluirOrigen) {
    if (!REPORTE_EVENTO_ORIGEN || !HISTORIAL_ORIGEN || !DATOS_ANIMAL_REPORTE) return;
    var solicitud = ++REPORTE_EVENTO_SOLICITUD;
    var tipo = REPORTE_EVENTO_ORIGEN.tipo;
    var eventoId = REPORTE_EVENTO_ORIGEN.eventoId;
    var titulos = { leche: 'Producción de leche', sanidad: 'Control sanitario', pesos: 'Pesaje', servicios: 'Servicio', movimientos: 'Movimiento de rebaño' };
    $('#hist_reporte_preview_print').prop('disabled', true);
    $('#hist_reporte_preview_title').html('<i class="fa fa-file-text-o"></i> Reporte de ' + escaparReporte(titulos[tipo]));
    document.getElementById('hist_reporte_preview_frame').srcdoc = '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#53635a">Preparando el reporte de ' + escaparReporte(titulos[tipo].toLowerCase()) + '...</body></html>';
    $.post('../../data_json/data_historial_origen.php', {
        action: 'detalle_evento', id_animal: ID_ANIMAL, tipo: tipo,
        evento_id: eventoId, incluir_fundo_origen: incluirOrigen ? 1 : 0
    }, function(resp) {
        if (solicitud !== REPORTE_EVENTO_SOLICITUD) return;
        if (!resp.ok) {
            mostrarVistaPreviaReporte('No se pudo cargar el reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#a33">No se pudo cargar el detalle de este evento.</body></html>');
            return;
        }
        var filas = (resp.campos || []).map(function(item) {
            var etiqueta = item.etiqueta || item.campo.replace(/_/g, ' ').replace(/\b\w/g, function(letra) { return letra.toUpperCase(); });
            return '<tr><th>' + escaparReporte(etiqueta) + '</th><td>' + escaparReporte(item.valor) + '</td></tr>';
        }).join('');
        var animal = DATOS_ANIMAL_REPORTE;
        var datosOrigen = resp.fundo
            ? '<p>Fundo anterior: <strong>' + escaparReporte(resp.fundo.nombre) + '</strong> · RIF: ' + escaparReporte(resp.fundo.rif) + '</p><p>Dirección: ' + escaparReporte(resp.fundo.direccion) + '</p><p>Descripción: ' + escaparReporte(resp.fundo.descripcion) + '</p><p>Código original: ' + escaparReporte(resp.fundo.codigo) + ' · Fecha de migración: ' + escaparReporte(resp.fundo.fecha_migracion) + '</p>'
            : '';
        mostrarVistaPreviaReporte('Reporte de ' + titulos[tipo], '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' + escaparReporte(titulos[tipo]) + '</title><style>body{font:14px Arial,sans-serif;color:#25332b;margin:32px}h1{font-size:22px;margin:0 0 12px}p{margin:5px 0;color:#53635a}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #ccd5ce;padding:9px;text-align:left;vertical-align:top}th{background:#e9f1eb;width:32%}@media print{body{margin:12mm}}</style></head><body>' +
            '<h1>' + escaparReporte(titulos[tipo]) + '</h1><p>Animal: <strong>' + escaparReporte(animal.nombre) + '</strong> · Código: ' + escaparReporte(animal.codigo) + ' · Arete: ' + escaparReporte(animal.arete) + ' · Sexo: ' + escaparReporte(animal.sexo) + '</p>' +
            datosOrigen + '<table>' + filas + '</table></body></html>');
        $('#hist_reporte_preview_print').prop('disabled', false);
    }, 'json').fail(function() {
        if (solicitud !== REPORTE_EVENTO_SOLICITUD) return;
        mostrarVistaPreviaReporte('No se pudo cargar el reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#a33">No se pudo consultar el evento. Verifique su conexión y vuelva a intentar.</body></html>');
    });
}

function generarReporteOrigen(tipo, eventoId) {
    if (!HISTORIAL_ORIGEN || !DATOS_ANIMAL_REPORTE) return;
    REPORTE_EVENTO_ORIGEN = { tipo: tipo, eventoId: eventoId };
    $('#hist_reporte_preview_origen').prop('checked', false);
    $('#hist_reporte_preview_print').prop('disabled', true);
    mostrarVistaPreviaReporte('Vista previa del reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#53635a">Preparando el reporte...</body></html>');
    cargarReporteEventoOrigen(false);
}

function mostrarFicha() {
    $("#ficha_loading").hide();
    $("#ficha_container").show();
}

function mostrarNoDisponible(titulo, mensaje) {
    $("#ficha_loading").hide();
    $("#ficha_container").hide();
    $("#nd_titulo").text(titulo || 'Animal no disponible');
    $("#nd_mensaje").text(mensaje || 'El animal no está disponible en este momento.');
    $("#ficha_no_disponible").show();
}

// ============================================================
// CONTROL DEL DRAWER
// ============================================================
function abrirDrawer() {
    $("#hist_overlay").addClass('open');
    $("#hist_drawer").addClass('open');
    $('body').css('overflow', 'hidden');
}
function cerrarDrawer() {
    $("#hist_overlay").removeClass('open');
    $("#hist_drawer").removeClass('open');
    $('body').css('overflow', '');
}

$(document).on('click', '#hist_overlay, #hist_close', cerrarDrawer);
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#hist_reporte_preview_overlay').hasClass('open')) {
        cerrarVistaPreviaReporte();
    } else if (e.key === 'Escape' && $("#hist_drawer").hasClass('open')) {
        cerrarDrawer();
    }
});

$(document).on('click', '.hist-seccion-head', function() {
    $(this).closest('.hist-seccion').toggleClass('open');
});
$(document).on('click', '.hist-evento-reporte', function(e) {
    e.preventDefault();
    generarReporteOrigen($(this).data('tipo'), Number($(this).data('evento-id')));
});
$(document).on('click', '#hist_reporte_preview_close, #hist_reporte_preview_cancel', cerrarVistaPreviaReporte);
$(document).on('click', '#hist_reporte_preview_print', imprimirVistaPreviaReporte);
$(document).on('change', '#hist_reporte_preview_origen', function() {
    cargarReporteEventoOrigen(this.checked);
});
$(document).on('click', '#hist_reporte_preview_overlay', function(e) {
    if (e.target === this) cerrarVistaPreviaReporte();
});

$(document).ready(function () {

    if (FICHA_PENDIENTE) {
        $("#ficha_loading").hide();
    } else {
    $.ajax({
        url: "../../data_json/data_manimales_ficha.php",
        type: "POST",
        dataType: "json",
        data: { id_animal: ID_ANIMAL }
    })
    .done(function (data) {

        if (data && data.error) {
            if (data.error === 'no_encontrado') {
                mostrarNoDisponible(data.titulo, data.mensaje);
            } else if (data.error === 'sesion') {
                mostrarNoDisponible('Sesión expirada', 'Debe iniciar sesión nuevamente para ver esta ficha.');
            } else {
                mostrarNoDisponible(data.titulo || 'Error del sistema', data.mensaje || 'No se pudieron cargar los datos.');
            }
            return;
        }

        DATOS_ANIMAL_REPORTE = {
            codigo: data.cod_animal,
            nombre: data.nombre_animal,
            arete: data.nro_arete || 'NO Registrado',
            sexo: data.sexo
        };

        $("#fundo_nombre").text(data.fundo || '—');
        $("#rif_fundo").text(data.rif_fundo || '—');

        $("#cod_animal").text(data.cod_animal);
        $("#nombre_animal").text(data.nombre_animal);
        $("#numero_arete").text(data.nro_arete || 'NO Registrado');
        $("#sexo").text(data.sexo);
        $("#raza_animal").text(data.raza || 'NO Registrado');
        $("#edad").text(data.edad || '—');
        $("#estatus").text(data.estatus);
        $("#categoria_actual").text(data.categoria_actual || '—');
        $("#aptitud_animal").text(data.aptitud || '—');

        $("#fech_nac").text(data.f_nacimiento || '—');
        $("#fecha_ingreso").text(data.f_ingreso || '—');
        $("#peso_nac").text(data.peso_nac || '—');
        $("#rena").text(data.rena || '—');
        $("#alza").text(data.alza || '—');
        $("#lote").text(data.lote || '—');
        $("#procedencia").text(data.procedencia || '—');
        $("#servicio").text(data.servicio || '—');

        var madreTxt = ((data.madre && data.madre.codigo ? data.madre.codigo : '') + ' ' +
                        (data.madre && data.madre.nombre ? data.madre.nombre : '')).trim();
        var padreTxt = ((data.padre && data.padre.codigo ? data.padre.codigo : '') + ' ' +
                        (data.padre && data.padre.nombre ? data.padre.nombre : '')).trim();
        $("#madre").text(madreTxt || 'NO Registrado');
        $("#padre").text(padreTxt || 'NO Registrado');

        $("#observaciones").text(data.observaciones || 'Sin observaciones');

        if (data.foto) { $("#foto_animal").attr('src', data.foto); }

        // Movimientos
        if (data.movimientos && data.movimientos.length) {
            var html = '';
            data.movimientos.forEach(function (m) {
                html += '<tr>' +
                        '<td data-label="Fecha">'   + (m.fecha   || '') + '</td>' +
                        '<td data-label="Tipo">'    + (m.tipo    || '') + '</td>' +
                        '<td data-label="Origen">'  + (m.origen  || '') + '</td>' +
                        '<td data-label="Destino">' + (m.destino || '') + '</td>' +
                        '<td data-label="Motivo">'  +
                            ((m.motivo || '') + (m.obs ? ' — ' + m.obs : '')) +
                        '</td>' +
                        '</tr>';
            });
            $("#tbody_mov").html(html);
            $("#bloque_movimientos").show();
        }

        // Leche
        if (data.leche && data.leche.promedio > 0) {
            $("#leche_promedio").text(data.leche.promedio);
            $("#leche_ultimo").text(data.leche.ultimo || '—');
            $("#leche_total").text(data.leche.total);
            $("#bloque_leche").show();
        }

        // Sanidad
        if (data.sanidad) {
            $("#san_fecha").text(data.sanidad.fecha || '—');
            $("#san_evento").text(data.sanidad.evento || '—');
            $("#san_resp").text(data.sanidad.responsable || '—');
            $("#san_prox").text(data.sanidad.prox || '—');
            $("#san_obs").text(data.sanidad.obs || '—');
            $("#bloque_sanidad").show();
        }

        // QR
        if (typeof qrcode !== 'function') {
            console.error('QR: librería no cargada');
            mostrarFicha();
            return;
        }

        var contenidoQR = data.qr_url || data.qr_texto || 'Sin datos';
        var qr = qrcode(0, 'M');
        qr.addData(contenidoQR);
        qr.make();
        $("#qr_container").html(qr.createImgTag(4, 0));

        try {
            QR_DATA_URL = qr.createDataURL(4, 0, 'image/png');
        } catch (e) {
            QR_DATA_URL = qr.createDataURL(4, 0);
        }

        if (data.qr_url) {
            $("#ver_publico").attr('href', data.qr_url).show();
        }

        // Banner de migración
        if (data.es_migrado && data.animal_origen_id) {
            $("#mig_codigo_origen").text(data.codigo_origen || '—');
            $("#mig_fecha").text(data.fecha_migracion || '—');
            $("#banner_migracion").show();
        }

        mostrarFicha();
    })
    .fail(function (xhr, st, err) {

        var resp = null;
        try { resp = JSON.parse(xhr.responseText); } catch (e) {}

        if (xhr.status === 404) {
            mostrarNoDisponible(
                (resp && resp.titulo)  ? resp.titulo  : 'Animal no disponible',
                (resp && resp.mensaje) ? resp.mensaje : 'El animal no existe o fue dado de baja.'
            );
        } else if (xhr.status === 401) {
            mostrarNoDisponible('Sesión expirada', 'Debe iniciar sesión nuevamente para ver esta ficha.');
        } else if (xhr.status === 500) {
            mostrarNoDisponible('Error del sistema', 'No se pudieron cargar los datos. Intente nuevamente en unos minutos.');
        } else if (xhr.status === 0) {
            mostrarNoDisponible('Sin conexión', 'Verificá tu conexión a internet e intentá nuevamente.');
        } else {
            mostrarNoDisponible(
                'Error inesperado',
                (resp && resp.mensaje) ? resp.mensaje : ('Error ' + xhr.status + ' al cargar la ficha.')
            );
        }

        console.error('AJAX fail:', xhr.status, st, err, xhr.responseText);
    });
    }

    // ═══════════════════════════════════════════════════════════
    // BOTÓN "VER HISTORIAL DE ORIGEN"
    // ═══════════════════════════════════════════════════════════
    $("#btn_ver_historial_origen").on('click', function() {
        $("#hist_drawer_body").html(
            '<div style="text-align:center; padding:40px; color:#8895a4;">' +
            '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
            '<p style="margin-top:8px;">Cargando historial...</p></div>'
        );
        abrirDrawer();

        $.post("../../data_json/data_historial_origen.php", {
            id_animal: ID_ANIMAL
        }, function(resp) {
            if (!resp.ok || !resp.tiene_origen) {
                $("#hist_drawer_body").html(
                    '<div class="alert alert-warning" style="margin:0;">' +
                    'No hay historial disponible para este animal.</div>'
                );
                return;
            }

            HISTORIAL_ORIGEN = resp;
            var esHembra = Number(resp.sexo) === 1;

            $("#mig_fundo_origen").text(resp.fundo_origen || '—');

            var html = '';

            html += '<div class="hist-origen-info">';
            html += '  <div class="row"><span class="lbl">Fundo origen:</span><span class="val">' + (resp.fundo_origen || '—') + '</span></div>';
            html += '  <div class="row"><span class="lbl">Código original:</span><span class="val"><code>' + (resp.codigo_origen || '—') + '</code></span></div>';
            html += '  <div class="row"><span class="lbl">Fecha de migración:</span><span class="val">' + (resp.fecha_migracion || '—') + '</span></div>';
            html += '</div>';

            function seccion(id, titulo, iconoClass, count, contenido, abierta) {
                var openClass = abierta ? ' open' : '';
                var h = '<div class="hist-seccion' + openClass + '" data-seccion="' + id + '">';
                h += '  <div class="hist-seccion-head">';
                h += '    <div class="icon ' + iconoClass + '"><i class="fa fa-circle"></i></div>';
                h += '    <span class="titulo">' + titulo + '</span>';
                h += '    <span class="count">' + count + '</span>';
                h += '    <i class="fa fa-chevron-down caret"></i>';
                h += '  </div>';
                h += '  <div class="hist-seccion-body"><div class="inner">' + contenido + '</div></div>';
                h += '</div>';
                return h;
            }

            function botonReporteEvento(tipo, indice) {
                return '<a href="#" role="button" class="hist-evento-reporte hist-evento-reporte-link" data-tipo="' + tipo + '" data-evento-id="' + indice + '"><i class="fa fa-file-text-o"></i> Ver reporte</a>';
            }

            // Leche y servicios corresponden a hembras.
            if (esHembra) {
                var lecheHtml = '';
                if (resp.leche && resp.leche.length > 0) {
                    lecheHtml += '<table class="hist-tabla">';
                    resp.leche.forEach(function(l, indice) {
                        lecheHtml += '<tr>';
                        lecheHtml += '<td class="fecha">' + l.fecha + '</td>';
                        lecheHtml += '<td>M: <span class="destacado">' + l.m + ' L</span> · T: <span class="destacado">' + l.t + ' L</span></td>';
                        lecheHtml += '<td class="num">' + l.total + ' L</td>';
                        lecheHtml += '</tr>';
                        lecheHtml += '<tr><td colspan="3" class="hist-evento-acciones">' + botonReporteEvento('leche', l.evento_id) + '</td></tr>';
                    });
                    lecheHtml += '</table>';
                } else {
                    lecheHtml = '<div class="hist-vacio">Sin registros de leche.</div>';
                }
                html += seccion('leche', 'Producción de Leche', 'bg-icon-leche', (resp.leche || []).length, lecheHtml, false);
            }

            // SANIDAD
            var sanHtml = '';
            if (resp.sanidad && resp.sanidad.length > 0) {
                sanHtml += '<table class="hist-tabla">';
                resp.sanidad.forEach(function(s, indice) {
                    sanHtml += '<tr>';
                    sanHtml += '<td class="fecha">' + s.fecha + '</td>';
                    sanHtml += '<td><span class="destacado">' + (s.evento || '') + '</span>';
                    if (s.responsable) sanHtml += '<br><span class="extra">Resp: ' + s.responsable + '</span>';
                    if (s.prox) sanHtml += '<br><span class="extra">Próximo control: ' + s.prox + '</span>';
                    if (s.obs) sanHtml += '<br><span class="extra">' + s.obs + '</span>';
                    sanHtml += '</td></tr>';
                    sanHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('sanidad', s.evento_id) + '</td></tr>';
                });
                sanHtml += '</table>';
            } else {
                sanHtml = '<div class="hist-vacio">Sin controles sanitarios.</div>';
            }
            html += seccion('sanidad', 'Controles Sanitarios', 'bg-icon-sanidad', (resp.sanidad || []).length, sanHtml, false);

            // PESOS
            var pesosHtml = '';
            if (resp.pesos && resp.pesos.length > 0) {
                pesosHtml += '<table class="hist-tabla">';
                resp.pesos.forEach(function(p, indice) {
                    pesosHtml += '<tr>';
                    pesosHtml += '<td class="fecha">' + p.fecha + '</td>';
                    pesosHtml += '<td><span class="destacado">' + p.peso + ' kg</span></td>';
                    pesosHtml += '</tr>';
                    pesosHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('pesos', p.evento_id) + '</td></tr>';
                });
                pesosHtml += '</table>';
            } else {
                pesosHtml = '<div class="hist-vacio">Sin pesajes registrados.</div>';
            }
            html += seccion('pesos', 'Consecutivo de Pesos', 'bg-icon-pesos', (resp.pesos || []).length, pesosHtml, false);

            if (esHembra) {
                var servHtml = '';
                if (resp.servicios && resp.servicios.length > 0) {
                    servHtml += '<table class="hist-tabla">';
                    resp.servicios.forEach(function(s, indice) {
                        servHtml += '<tr>';
                        servHtml += '<td class="fecha">' + s.fecha + '</td>';
                        servHtml += '<td><span class="destacado">' + (s.codigo || '') + '</span> · Servicio #' + s.nro;
                        if (s.obs) servHtml += '<br><span class="extra">' + s.obs + '</span>';
                        servHtml += '</td></tr>';
                        servHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('servicios', s.evento_id) + '</td></tr>';
                    });
                    servHtml += '</table>';
                } else {
                    servHtml = '<div class="hist-vacio">Sin servicios registrados.</div>';
                }
                html += seccion('servicios', 'Servicios', 'bg-icon-servicios', (resp.servicios || []).length, servHtml, false);
            }

            // MOVIMIENTOS
            var movHtml = '';
            if (resp.movimientos && resp.movimientos.length > 0) {
                movHtml += '<table class="hist-tabla">';
                resp.movimientos.forEach(function(m, indice) {
                    movHtml += '<tr>';
                    movHtml += '<td class="fecha">' + m.fecha + '</td>';
                    movHtml += '<td><span class="destacado">' + (m.tipo || '') + '</span>';
                    if (m.origen || m.destino) movHtml += '<br><span class="extra">' + (m.origen || '—') + ' → ' + (m.destino || '—') + '</span>';
                    if (m.motivo) movHtml += '<br><span class="extra">' + m.motivo + '</span>';
                    if (m.obs) movHtml += '<br><span class="extra">' + m.obs + '</span>';
                    movHtml += '</td></tr>';
                    movHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('movimientos', m.evento_id) + '</td></tr>';
                });
                movHtml += '</table>';
            } else {
                movHtml = '<div class="hist-vacio">Sin movimientos de rebaño.</div>';
            }
            html += seccion('movimientos', 'Movimientos de Rebaño', 'bg-icon-movs', (resp.movimientos || []).length, movHtml, false);

            $("#hist_drawer_body").html(html);

        }, "json").fail(function(xhr) {
            var msg = 'Error al cargar el historial.';
            try {
                var r = JSON.parse(xhr.responseText);
                if (r && r.msg) msg = r.msg;
            } catch (e) {}
            $("#hist_drawer_body").html('<div class="alert alert-danger" style="margin:0;">' + msg + '</div>');
        });
    });

    // Botones generales
    $("#imprimir").on('click', function () { window.print(); });

    $("#pdf").on('click', function () {
        $("#pdf_id_animal").val(ID_ANIMAL);
        $("#pdf_qr").val(QR_DATA_URL);
        $("#form_pdf").submit();
    });

    $("#exit").on('click', function () { window.location.href = '../animales/'; });
});
</script>

<?php include_once("../layouts/pie.php") ?>