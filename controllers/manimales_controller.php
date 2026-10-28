<?php
require_once __DIR__ . '/../lib/especie_helper.php';
require_once '../models/Manimale.php';
require_once '../models/Manimalxfundo.php';
require_once '../models/Mcategoria.php';
require_once '../models/Mregnacimiento.php';
require_once '../models/Vmfichaxanimale.php';


include '../lib/sanitizar.php';
require_once '../lib/estatus_animal_helper.php';
include '../lib/imagen_helper.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);
@$id = ($_POST['id_animal']);
@$id_animal = ($_POST['id_animal']);
@$record = ($_POST['record']);
// recientes
@$rena = ($_POST['rena']);
/* manimales.mlotes_id es integer NOT NULL (0 = sin lote). Si el usuario no
   elige lote el select manda '' y PostgreSQL rechaza el INSERT con
   "invalid input syntax for type integer", que el front mostraba como
   "el servidor no respondió". Es el caso de los donadores, que a veces no
   están en ningún lote. */
@$lote = (isset($_POST['lote']) && $_POST['lote'] !== '' && (int) $_POST['lote'] > 0)
    ? (int) $_POST['lote']
    : 0;
@$alza = ($_POST['alza']);
//
@$nombre_animal = strtoupper($_POST['nombre_animal']);
@$raza_animal = ($_POST['raza_animal']);
@$sexo = ($_POST['sexo']);
@$fech_nac = ($_POST['fech_nac']);
@$estatus = ($_POST['estatus']);
@$peso_nac = ($_POST['peso_nac']);
@$fecha_ingreso = ($_POST['fecha_ingreso']);
@$estatus_animal = ($_POST['estatus_animal']);
@$aptitud_animal = ($_POST['aptitud_animal']);
@$numero_arete = strtoupper($_POST['numero_arete']);
@$categoria_ingreso = ($_POST['categoria_ingreso']);
@$categoria_futura = ($_POST['categoria_futura']);
@$rgd_madre = ($_POST['rgd_madre']);
@$nombre_madre = strtoupper($_POST['nombre_madre']);
@$rgd_padre = ($_POST['rgd_padre']);
@$nombre_padre = strtoupper($_POST['nombre_padre']);
@$procedencia = ($_POST['procedencia']);
@$observaciones = ($_POST['observaciones']);
@$servicio_animal = ($_POST['servicio_animal']);
@$cod_animal = ($_POST['cod_animal']);
@$codigo_ternero = ($_POST['codigo_ternero']);
@$id_reg_nac = ($_POST['id_reg_nac']);

/**
 * Marca un nacimiento como "Fichado" cuando ya se creó/actualizó su animal.
 * El id llega por POST (alta desde el parto) y puede venir vacío en un alta
 * normal: Mregnacimiento::find() lanza RecordNotFound con null/''/0, así que
 * se valida antes de tocar la base.
 */
function bovs_marcar_nacimiento_fichado($id_reg_nac)
{
    $id_reg_nac = (int)$id_reg_nac;
    if ($id_reg_nac <= 0) {
        return false;
    }

    try {
        $update_nacimiento = Mregnacimiento::find($id_reg_nac);
        if (!$update_nacimiento) {
            return false;
        }
        $update_nacimiento->fichado = true;
        return (bool)$update_nacimiento->save();
    } catch (Throwable $e) {
        error_log('[manimales] no se pudo marcar el nacimiento ' . $id_reg_nac . ': ' . $e->getMessage());
        return false;
    }
}

switch ($action) {

    case 'get_buscar_rgb_padre':

        $buscar = intval($_POST['buscarx_m']);
        $nro_arete_codigo = ($_POST['rgd_padre']);

        $cadena = ' ';
        $cadena_2 = '';

        if ($nro_arete_codigo == '') {

            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete del MACHO.
          </div>');
        } else {

            session_start();
            $mfundos_id = $_SESSION['mfundos_id'];

            if ($buscar == 1) {

                $rgb_animal = $nro_arete_codigo;
                $partes = explode('-', $rgb_animal);
                $prefijo = $partes[0];
                $nro = 0;
                if ($prefijo == 'SOVCA') {
                    $nro = intval($partes[2]);
                } else {
                    $nro = intval($rgb_animal);
                }

                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

                $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
                $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

                $criterio = 'Código';
            } else   if ($buscar == 2) {

                $nro_arete = $nro_arete_codigo;

                $cadena = "and nro_arete='$nro_arete' ";
                $cadena_2 = "and nro_arete='$nro_arete' ";
                $criterio = 'Nro de Arete';
            }

            @$data = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre_m,codigo_rgb_id_animal as cod_animal_m,f_nacimiento from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 and sexo=2 and " . bovs_estatus_animal_sql(2) . " $cadena;");

            if ($data != null) {

                foreach ($data as $rs) {

                    $fecha_nac = $rs->f_nacimiento;
                    @$fecha_nac = (string)$fecha_nac->format('Y-m-d');
                    $edad = tiempo_t($fecha_nac);

                    if ($edad < 12) {
                        $resp = array('resultado' => 'error', 'condicion' => 'none', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                 La Edad del TORO no puede ser Menor de 12 Meses.. EDAD ACTUAL  [ ' . $edad . ' ] meses !!.
                  </div>');
                    } else {

                        $estatus = $rs->estatus;
                        if ($estatus == 1) {
                            $nombre = $rs->cod_animal_m . '*' . $rs->nombre_m;
                            $resp = $nombre;
                        } else {
                            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El ' . $criterio . ' del Animal NO EXISTE!!.
                  </div>');
                        }
                    }
                }
            } else {
                $resp = array('resultado' => 'error', 'condicion' => 'none', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-ban"></i>
                      Alerta!
                      </h4>
                      No existe un TORO con el ' . $criterio . ' [' . $cod_rgb_animal . '] !!.
                      </div>');
            }
        }

        echo json_encode($resp);
        break;

    //// **************************

    /// LISTADO PARA EL BUSCADOR AMIGABLE DE LA FICHA DEL ANIMAL
    /// rol = madre | padre. Devuelve TODOS los animales que cumplen la condición
    /// del rol y marca `disponible` con las MISMAS reglas de get_buscar_rgb_madre
    /// (hembra ≥ 24 meses) y get_buscar_rgb_padre (macho ≥ 12 meses).

    case 'listar_candidatos_ficha':

        session_start();
        $mfundos_id = intval($_SESSION['mfundos_id']);
        $rol = isset($_POST['rol']) ? $_POST['rol'] : '';

        $filas = array();

        $edad_sql = '(extract(year from age(current_date, a.f_nacimiento)) * 12
                      + extract(month from age(current_date, a.f_nacimiento)))';

        if ($rol == 'padre' || $rol == 'macho') {

            $sexo   = 2;
            $minimo = 12;
            $catalogo = bovs_estatus_animal_sql(2, 'a');

        } else {

            $sexo   = 1;
            $minimo = 24;
            $catalogo = bovs_estatus_animal_sql(1, 'a');
        }

        $datos = Manimale::find_by_sql("select a.codigo_rgb_id_animal as codigo,
                                               coalesce(a.nro_arete, '') as nro_arete,
                                               coalesce(a.nombre, '')    as nombre,
                                               " . $edad_sql . "::int    as edad_meses
                                          from manimales a
                                         where a.mfundos_id = " . $mfundos_id . "
                                           and a.mestatus_id = 1
                                           and a.sexo = " . $sexo . "
                                           and a.f_nacimiento is not null
                                           and " . $catalogo . "
                                         order by a.nombre, a.codigo_rgb_id_animal;");

        foreach ($datos as $rs) {

            $edad = intval($rs->edad_meses);

            if ($edad < $minimo) {
                $disponible = 0;
                $condicion  = 'Menor de ' . $minimo . ' meses';
            } else {
                $disponible = 1;
                $condicion  = ($sexo == 1) ? 'Madre' : 'Padre';
            }

            $filas[] = array(
                'codigo'       => $rs->codigo,
                'nro_arete'    => ($rs->nro_arete === null) ? '' : $rs->nro_arete,
                'nombre'       => ($rs->nombre === null) ? '' : $rs->nombre,
                'edad_meses'   => $edad,
                'condicion'    => $condicion,
                'disponible'   => $disponible,
                'nro_servicio' => 0
            );
        }

        echo json_encode(array(
            'resultado' => 'ok',
            'rol'       => $rol,
            'filas'     => $filas,
            'total'     => count($filas)
        ));

        break;

    //// **************************

    /// LISTADO PARA EL SELECTOR DEL GESTOR DE REPORTES PARAMETRIZABLES
    /// Devuelve los animales del fundo para que el usuario elija el animal del
    /// reporte de una lista filtrable (por nombre, código SOVCA o N° de arete)
    /// en lugar de teclear el código. NO se filtra por estatus: un reporte
    /// histórico puede necesitar un animal ya desincorporado.

    case 'listar_animales_reporte':

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        $filas = array();

        /* Reporte elegido en el gestor. Si llega, el catálogo se limitA a los
           animales que TIENEN datos para ese reporte, de modo que el PDF
           nunca salga vacío. Sin `reporte` se listan todos (uso genérico). */
        $reporte = isset($_POST['reporte']) ? trim($_POST['reporte']) : '';

        $f_ini = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : '';
        $f_fin = isset($_POST['fecha_final'])  ? trim($_POST['fecha_final'])  : '';

        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $f_ini)) { $f_ini = ''; }
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $f_fin)) { $f_fin = ''; }
        if ($f_ini === '' || $f_fin === '') { $f_ini = ''; $f_fin = ''; }

        /* Tabla que respalda cada reporte individual: el requisito es que el
           animal tenga al menos un registro vigente en esa tabla. */
        $fuentes = array(
            'rep_prod_leche'  => array('tabla' => 'mprolecheras',       'codigo' => 'codigo_rgb_id_animal_hembra', 'fecha' => 'fecha_produccion', 'unidad' => 'producciones', 'unidad1' => 'producción'),
            'rep_cons_peso'   => array('tabla' => 'mconsecutivopesos',  'codigo' => 'codigo_rgb_id_animal',        'fecha' => 'fecha_pesaje',     'unidad' => 'pesajes',      'unidad1' => 'pesaje'),
            'rep_ctl_animal'  => array('tabla' => 'mcontrolsanitarios', 'codigo' => 'codigo_rgb_id_animal',        'fecha' => 'fecha_control',    'unidad' => 'controles',    'unidad1' => 'control'),
            'rep_nacimientos' => array('tabla' => 'mregnacimientos',    'codigo' => 'codigo_rgb_id_animal_hembra', 'fecha' => 'f_parto',          'unidad' => 'partos',       'unidad1' => 'parto')
        );

        $sql_registros    = '0';
        $filtro_requisito = '';
        $unidad           = '';
        $unidad1          = '';

        if (isset($fuentes[$reporte])) {

            $f = $fuentes[$reporte];

            /* Si el gestor ya tiene un rango de fechas marcado, el animal solo
               se ofrece cuando tiene datos DENTRO de ese rango. */
            $rango = '';
            if ($f_ini !== '' && $f_fin !== '') {
                $rango = ' and x.' . $f['fecha']
                       . " between cast('" . $f_ini . "' as date)"
                       . " and cast('" . $f_fin . "' as date)";
            }

            /* Nacimientos: el reporte solo toma registros vigentes. */
            $extra = ($reporte === 'rep_nacimientos') ? ' and x.mestatus_id = 1 ' : '';

            $sub = 'select count(*)'
                 . ' from ' . $f['tabla'] . ' x'
                 . ' where x.mfundos_id = a.mfundos_id'
                 . ' and x.' . $f['codigo'] . ' = a.codigo_rgb_id_animal'
                 . ' and x.status = 1' . $extra . $rango;

            $sql_registros    = '(' . $sub . ')';
            $filtro_requisito = ' and (' . $sub . ') > 0';
            $unidad           = $f['unidad'];
            $unidad1          = $f['unidad1'];

        } elseif ($reporte === 'rep_ser_animal') {

            $sub = 'select count(*)'
                 . ' from mregservicios x'
                 . ' where x.mfundos_id = a.mfundos_id'
                 . ' and x.status = 1'
                 . ' and (x.codigo_rgb_id_animal_hembra = a.codigo_rgb_id_animal'
                 . ' or x.codigo_rgb_id_animal_dona = a.codigo_rgb_id_animal'
                 . ' or x.codigo_rgb_id_animal_macho = a.codigo_rgb_id_animal)';

            $sql_registros    = '(' . $sub . ')';
            $filtro_requisito = ' and (' . $sub . ') > 0';
            $unidad           = 'servicios';
            $unidad1          = 'servicio';
        }

        if ($mfundos_id > 0) {

            $edad_sql = '(extract(year from age(current_date, a.f_nacimiento)) * 12
                          + extract(month from age(current_date, a.f_nacimiento)))';

            $datos = Manimale::find_by_sql("select a.codigo_rgb_id_animal       as codigo,
                                                   coalesce(a.nro_arete, '')   as nro_arete,
                                                   coalesce(a.nombre, '')      as nombre,
                                                   coalesce(a.sexo, 0)         as sexo,
                                                   case when a.f_nacimiento is null then 0
                                                        else " . $edad_sql . "::int
                                                   end                         as edad_meses,
                                                   " . $sql_registros . "       as registros
                                              from manimales a
                                             where a.mfundos_id = " . $mfundos_id . "
                                               and a.status = 1
                                               " . $filtro_requisito . "
                                             order by a.nombre, a.codigo_rgb_id_animal;");

            if ($datos) {
                foreach ($datos as $rs) {

                    $sexo      = intval($rs->sexo);
                    $registros = intval($rs->registros);

                    if ($sexo === 1) {
                        $etiqueta_sexo = 'Hembra';
                    } elseif ($sexo === 2) {
                        $etiqueta_sexo = 'Macho';
                    } else {
                        $etiqueta_sexo = 'Sin sexo';
                    }

                    /* Con reporte elegido, el tag dice cuántos datos tiene el
                       animal (garantía de que el PDF saldrá con contenido). */
                    if ($unidad !== '' && $registros > 0) {
                        $etiqueta = $registros . ' '
                                  . ($registros === 1 ? $unidad1 : $unidad);
                    } else {
                        $etiqueta = $etiqueta_sexo;
                    }

                    $filas[] = array(
                        'codigo'         => $rs->codigo,
                        'nro_arete'      => (string)$rs->nro_arete,
                        'nombre'         => (string)$rs->nombre,
                        'edad_meses'     => intval($rs->edad_meses),
                        'condicion'      => $etiqueta_sexo,
                        'disponible'     => 1,
                        'nro_servicio'   => 0,
                        'registros'      => $registros,
                        'etiqueta'       => $etiqueta,
                        'etiqueta_clase' => 'is-ok'
                    );
                }
            }
        }

        echo json_encode(array(
            'resultado' => 'ok',
            'filas'     => $filas,
            'total'     => count($filas)
        ));

        break;

    //// **************************

    case 'get_buscar_rgb_madre':

        $buscar = intval($_POST['buscarx']);
        $nro_arete_codigo = ($_POST['rgd_madre']);

        $cadena = ' ';
        $cadena_2 = '';

        if ($nro_arete_codigo == '') {

            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete de la HEMBRA.
          </div>');
        } else {

            session_start();
            $mfundos_id = $_SESSION['mfundos_id'];

            if ($buscar == 1) {

                $rgb_animal = $nro_arete_codigo;
                $partes = explode('-', $rgb_animal);
                $prefijo = $partes[0];
                $nro = 0;
                if ($prefijo == 'SOVCA') {
                    $nro = intval($partes[2]);
                } else {
                    $nro = intval($rgb_animal);
                }

                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

                $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
                $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

                $criterio = 'Código';
            } else   if ($buscar == 2) {

                $nro_arete = $nro_arete_codigo;

                $cadena = "and nro_arete='$nro_arete' ";
                $cadena_2 = "and nro_arete='$nro_arete' ";
                $criterio = 'Nro de Arete';
            }

            @$data = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h,f_nacimiento from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 and sexo=1 and " . bovs_estatus_animal_sql(1) . " $cadena;");

            if ($data != null) {

                foreach ($data as $rs) {

                    $fecha_nac = $rs->f_nacimiento;
                    @$fecha_nac = (string)$fecha_nac->format('Y-m-d');
                    $edad = tiempo_t($fecha_nac);

                    if ($edad < 24) {
                        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                 La Edad de la ' . bovs_especie_hembra(false, 'upper') . ' no puede ser Menor de 24 Meses.. EDAD ACTUAL  [ ' . $edad . ' ] meses !!.
                  </div>');
                    } else {

                        $estatus = $rs->estatus;
                        if ($estatus == 1) {
                            $nombre = $rs->cod_animal_h . '*' . $rs->nombre_h;
                            $resp = $nombre;
                        } else {
                            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El ' . $criterio . ' del Animal NO EXISTE!!.
                  </div>');
                        }
                    }
                }
            } else {
                $resp = array('resultado' => 'error', 'condicion' => 'none', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-ban"></i>
                      Alerta!
                      </h4>
                      No existe una HEMBRA con el ' . $criterio . ' [' . $cod_rgb_animal . '] !!.
                      </div>');
            }
        }

        echo json_encode($resp);
        break;

    //// **************************

    case 'save_ficha':

        if ($nombre_animal == '') {

            echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        Campo nombre del animal vacío..
        </div>';
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            // Cargar esta variable en el starsession inicial
            @$t_ganaderia = $_SESSION['tipo_ganaderia'];
            $hoy = date('d-m-Y');
            $continue = false;

            @$busca_arete = Manimale::find_by_sql("SELECT nro_arete  FROM Manimales  WHERE  nro_arete<>'' and nro_arete='$numero_arete' and status=1 and mfundos_id=" . $mfundos_id . ' ;');

            if ($busca_arete == null) {

                $continue = true;
            }

            if ($continue) {

                /// GENERAMOS EL CODIGO PARA EL ANIMAL
                //***************** */
                $data_genera_codigo = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
            FROM Manimalxfundos
            WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                foreach ($data_genera_codigo as $fund_ani) {
                    $cant_animal = $fund_ani->cant_animal;
                }
                if ($cant_animal > 0) {
                    $contados = $cant_animal + 1;
                } else {
                    $contados = 1;
                }
                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $contados;

                // La imagen se guarda DESPUÉS de crear el registro, con el código generado
                //***************** */

                $reg_animal = new Manimale();
                $reg_animal->codigo_rgb_id_animal = $cod_rgb_animal;
                $reg_animal->codigo_ternero = $codigo_ternero;
                $reg_animal->nombre = $nombre_animal;
                $reg_animal->sexo = $sexo;
                $reg_animal->estatus_animal = $estatus_animal;
                $reg_animal->rena = $rena;
                $reg_animal->alza = $alza;
                $reg_animal->mlotes_id = $lote;
                $reg_animal->nro_arete = $numero_arete;
                $reg_animal->categoria_ingreso_id = $categoria_ingreso;
                $reg_animal->categoria_futura_id = $categoria_futura;
                $reg_animal->aptitud = $aptitud_animal;
                $reg_animal->mrazas_id = $raza_animal;
                $reg_animal->f_nacimiento = $fech_nac;
                $reg_animal->peso_nacimiento = $peso_nac;
                $reg_animal->f_ingreso = $fecha_ingreso;
                $reg_animal->servicio = $servicio_animal;
                $reg_animal->rgd_madre = $rgd_madre;
                $reg_animal->nombre_madre = $nombre_madre;
                $reg_animal->rgd_padre = $rgd_padre;
                $reg_animal->nombre_padre = $nombre_padre;
                $reg_animal->procedencia = $procedencia;
                $reg_animal->observaciones = $observaciones;
                $reg_animal->imagen = '';
                $reg_animal->user_create = $usuario_id;
                $reg_animal->mfundos_id = $mfundos_id;
                $reg_animal->mtipoganaderias_id = $t_ganaderia;
                $reg_animal->nac_fundo = true;

                $reg_animal->created = $hoy;

                $bovs_guardado = false;
                try {
                    $bovs_guardado = (bool) $reg_animal->save();
                } catch (Throwable $e) {
                    /* Sin esto cualquier dato incompatible con el esquema
                       devolvía un 500 y el front solo decía "el servidor no
                       respondió". Mejor decir qué pasó. */
                    echo '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="0">
                        <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                        No se pudo guardar el animal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '
                      </div>';
                    break;
                }

                if ($bovs_guardado) {
                    // da el mensaje de guardado...

                    // Imagen: se guarda una vez creado el animal, nombrada con su código
                    $img_aviso = '';
                    if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
                        $err_img = bovs_imagen_error_archivo($_FILES['file']);
                        if ($err_img !== null) {
                            $img_aviso = '<div class="alert alert-warning alert-dismissable"><button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button><input type="hidden" id="tocon" value="1"><h4><i class="icon fa fa-warning"></i> Aviso</h4>' . htmlspecialchars($err_img, ENT_QUOTES, 'UTF-8') . '</div>';
                        } else {
                            $img_proc = bovs_imagen_procesar($_FILES['file']['tmp_name'], '../dist/img/fotos_animales/', $cod_rgb_animal);
                            if ($img_proc) {
                                $reg_animal->imagen = $img_proc;
                                $reg_animal->save();
                            } else {
                                $img_aviso = '<div class="alert alert-warning alert-dismissable"><button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button><input type="hidden" id="tocon" value="1"><h4><i class="icon fa fa-warning"></i> Aviso</h4>El animal se registró, pero no se pudo guardar la imagen (revise permisos de <b>dist/img/fotos_animales</b>). Puede adjuntarla luego desde Editar.</div>';
                            }
                        }
                    }

                    /* Nacimiento de origen: se marca como fichado (si aplica). */
                    bovs_marcar_nacimiento_fichado($id_reg_nac);

                    $data_search = Manimale::find_by_sql("SELECT max(id) as cod_animal
                 FROM Manimales
                  WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                    foreach ($data_search as $fund_ani) {
                        $cod_animal = $fund_ani->cod_animal;
                    }

                    $reg_animal_fundo = new Manimalxfundo();
                    $reg_animal_fundo->manimales_id = $cod_animal;
                    $reg_animal_fundo->mfundos_id = $mfundos_id;
                    $reg_animal_fundo->user_create = $usuario_id;

                    if ($reg_animal_fundo->save()) {

                        echo '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="1">
                        <h4>
                        <i class="fa fa-check"></i>
                        Alerta!
                        </h4>
                        El Animal  [' . $nombre_animal . '] registrado exitosamente...
                        </div>' . $img_aviso;
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="0">
                        <h4>
                        <i class="icon fa fa-ban"></i>
                        Alerta!
                        </h4>
                        Error al Intentar Guardar el Registro.
                      </div>';
                }
            } else {

                echo '<div class="alert alert-danger alert-dismissable">
      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
      <input type="hidden" id="tocon" value="0">
      <h4>
      <i class="icon fa fa-ban"></i>
      Alerta!
      </h4>
      Error al Intentar Guardar el Registro NRO DE ARETE DUPLICADO.
    </div>';
            }
        }

        break;
    #*******************************************************************************

    case 'add':

        if ($nombre_animal == '') {

            echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        Campo nombre del animal vacío..
        </div>';
        } else if (!bovs_estatus_animal_valido($estatus_animal, $sexo)) {

            echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        El Estatus seleccionado no corresponde al Sexo del animal. Para '
        . (intval($sexo) === 1 ? 'HEMBRA' : 'MACHO') . ' use: '
        . bovs_estatus_animal_descripcion($sexo) . '.
        </div>';
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            session_start();
            $usuario_id          = $_SESSION['idusuariox'];
            $mfundos_id          = $_SESSION['mfundos_id'];
            $maximo_registros    = $_SESSION['maximo_registros'];

            // Cargar esta variable en el starsession inicial
            @$t_ganaderia = $_SESSION['tipo_ganaderia'];
            $hoy = date('d-m-Y');
            $continue = false;

            $max_animal = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
        FROM Manimalxfundos
        WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

            foreach ($max_animal as $fund_ani) {
                $cant_animal = $fund_ani->cant_animal;
            }

            if ($maximo_registros  >= $cant_animal) {

                $continuar = 1;
            } else {
                $continuar = 0;
            }


            if ($continuar == 0) {

                echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        Debe Ampliar su Plan de Registro de Animales..
        </div>';
                break;
            }

            @$busca_arete = Manimale::find_by_sql("SELECT nro_arete  FROM Manimales  WHERE  nro_arete<>'' and nro_arete='$numero_arete' and status=1 and mfundos_id=" . $mfundos_id . ' ;');

            if ($busca_arete == null) {

                $continue = true;
            }

            if ($continue) {

                /// GENERAMOS EL CODIGO PARA EL ANIMAL
                //***************** */
                $data_genera_codigo = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
            FROM Manimalxfundos
            WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                foreach ($data_genera_codigo as $fund_ani) {
                    $cant_animal = $fund_ani->cant_animal;
                }
                if ($cant_animal > 0) {
                    $contados = $cant_animal + 1;
                } else {
                    $contados = 1;
                }
                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $contados;

                // La imagen se guarda DESPUÉS de crear el registro, con el código generado
                //***************** */

                $reg_animal = new Manimale();
                $reg_animal->codigo_rgb_id_animal = $cod_rgb_animal;
                $reg_animal->nombre = $nombre_animal;
                $reg_animal->sexo = $sexo;
                $reg_animal->estatus_animal = $estatus_animal;
                $reg_animal->rena = $rena;
                $reg_animal->alza = $alza;
                $reg_animal->mlotes_id = $lote;
                $reg_animal->nro_arete = $numero_arete;
                $reg_animal->categoria_ingreso_id = $categoria_ingreso;
                $reg_animal->categoria_futura_id = $categoria_futura;
                $reg_animal->aptitud = $aptitud_animal;
                $reg_animal->mrazas_id = $raza_animal;
                $reg_animal->f_nacimiento = $fech_nac;
                $reg_animal->peso_nacimiento = $peso_nac;
                $reg_animal->f_ingreso = $fecha_ingreso;
                $reg_animal->servicio = $servicio_animal;
                $reg_animal->rgd_madre = $rgd_madre;
                $reg_animal->nombre_madre = $nombre_madre;
                $reg_animal->rgd_padre = $rgd_padre;
                $reg_animal->nombre_padre = $nombre_padre;
                $reg_animal->procedencia = $procedencia;
                $reg_animal->observaciones = $observaciones;
                $reg_animal->imagen = '';
                $reg_animal->user_create = $usuario_id;
                $reg_animal->mfundos_id = $mfundos_id;
                $reg_animal->mtipoganaderias_id = $t_ganaderia;

                $reg_animal->created = $hoy;

                if ($reg_animal->save()) {
                    // da el mensaje de guardado...

                    // Imagen: se guarda una vez creado el animal, nombrada con su código
                    $img_aviso = '';
                    if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
                        $err_img = bovs_imagen_error_archivo($_FILES['file']);
                        if ($err_img !== null) {
                            $img_aviso = '<div class="alert alert-warning alert-dismissable"><button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button><input type="hidden" id="tocon" value="1"><h4><i class="icon fa fa-warning"></i> Aviso</h4>' . htmlspecialchars($err_img, ENT_QUOTES, 'UTF-8') . '</div>';
                        } else {
                            $img_proc = bovs_imagen_procesar($_FILES['file']['tmp_name'], '../dist/img/fotos_animales/', $cod_rgb_animal);
                            if ($img_proc) {
                                $reg_animal->imagen = $img_proc;
                                $reg_animal->save();
                            } else {
                                $img_aviso = '<div class="alert alert-warning alert-dismissable"><button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button><input type="hidden" id="tocon" value="1"><h4><i class="icon fa fa-warning"></i> Aviso</h4>El animal se registró, pero no se pudo guardar la imagen (revise permisos de <b>dist/img/fotos_animales</b>). Puede adjuntarla luego desde Editar.</div>';
                            }
                        }
                    }

                    $data_search = Manimale::find_by_sql("SELECT max(id) as cod_animal
                 FROM Manimales
                  WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                    foreach ($data_search as $fund_ani) {
                        $cod_animal = $fund_ani->cod_animal;
                    }

                    $reg_animal_fundo = new Manimalxfundo();
                    $reg_animal_fundo->manimales_id = $cod_animal;
                    $reg_animal_fundo->mfundos_id = $mfundos_id;
                    $reg_animal_fundo->user_create = $usuario_id;

                    if ($reg_animal_fundo->save()) {

                        $max_animal = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
                    FROM Manimalxfundos
                    WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                        foreach ($max_animal as $fund_ani) {
                            $cant_animal = $fund_ani->cant_animal;
                        }

                        if ($maximo_registros  >= $cant_animal) {

                            $_SESSION['acceso_reg_animales'] = 1;
                        } else {
                            $_SESSION['acceso_reg_animales'] = 0;
                        }

                        echo '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="1">
                        <h4>
                        <i class="fa fa-check"></i>
                        Alerta!
                        </h4>
                        El Animal  [' . $nombre_animal . '] registrado exitosamente...
                        </div>' . $img_aviso;
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="0">
                        <h4>
                        <i class="icon fa fa-ban"></i>
                        Alerta!
                        </h4>
                        Error al Intentar Guardar el Registro.
                      </div>';
                }
            } else {

                echo '<div class="alert alert-danger alert-dismissable">
      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
      <input type="hidden" id="tocon" value="0">
      <h4>
      <i class="icon fa fa-ban"></i>
      Alerta!
      </h4>
      Error al Intentar Guardar el Registro NRO DE ARETE DUPLICADO.
    </div>';
            }
        }

        break;
    #*******************************************************************************

    case 'edit':

        if ($nombre_animal == '') {

            echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        Campo nombre del animal vacío..
        </div>';
        } else if (!bovs_estatus_animal_valido($estatus_animal, $sexo)) {

            echo  '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <input type="hidden" id="tocon" value="0">
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        El Estatus seleccionado no corresponde al Sexo del animal. Para '
        . (intval($sexo) === 1 ? 'HEMBRA' : 'MACHO') . ' use: '
        . bovs_estatus_animal_descripcion($sexo) . '.
        </div>';
        } else {

            $consulta = Manimale::find('all', array('conditions' => array('id=?', $id_animal)));

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            // Cargar esta variable en el starsession inicial

            $hoy = date('d-m-Y');
            $nro_arete = $numero_arete;

            @$buscar_arete = Manimale::find_by_sql("SELECT nro_arete,codigo_rgb_id_animal
         FROM Manimales
         WHERE  nro_arete='$nro_arete' 
          and	nro_arete NOT  in (select a.nro_arete FROM Manimales a
           WHERE a.nro_arete='$nro_arete' and a.codigo_rgb_id_animal='$cod_animal'
            and  a.status=1 and a.mfundos_id=" . $mfundos_id . ') and  status=1 and mfundos_id=' . $mfundos_id . ';');

            @$buscar_nombre_imagen = Manimale::find_by_sql("SELECT imagen,codigo_rgb_id_animal
          FROM Manimales
          WHERE   id='$id_animal' and status=1 and mfundos_id=" . $mfundos_id . ';');

            $continuar = false;

            if ($buscar_arete == null) {
                $continuar = true;
            }

            if ($continuar) {

                if ($consulta != null) {

                    /// CARGAR IMAGEN — redimensiona y convierte a WebP
                    if ($buscar_nombre_imagen != null) {

                        foreach ($buscar_nombre_imagen as $ima) {

                            $imagen = $ima->imagen;
                            $codigo_rgb_id_animal = $ima->codigo_rgb_id_animal;
                        }
                    }

                    // Si no se adjunta una imagen nueva se CONSERVA la actual.
                    $nombre_imagen = isset($imagen) ? $imagen : '';

                    if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
                        $err_img = bovs_imagen_error_archivo($_FILES['file']);
                        if ($err_img !== null) {
                            echo '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <input type="hidden" id="tocon" value="0">
                        <h4><i class="icon fa fa-ban"></i> Alerta!</h4>' . htmlspecialchars($err_img, ENT_QUOTES, 'UTF-8') . '</div>';
                            break;
                        }
                        if (!empty($codigo_rgb_id_animal)) {
                            $img_proc = bovs_imagen_procesar($_FILES['file']['tmp_name'], '../dist/img/fotos_animales/', $codigo_rgb_id_animal);
                            if ($img_proc) {
                                // Eliminar la imagen anterior (si existe y es distinta a la nueva).
                                if (!empty($imagen) && $imagen !== $img_proc) {
                                    bovs_imagen_eliminar('../dist/img/fotos_animales/', $imagen);
                                }
                                $nombre_imagen = $img_proc;
                            } else {
                                echo '<div class="alert alert-danger alert-dismissable">
                            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                            <input type="hidden" id="tocon" value="0">
                            <h4><i class="icon fa fa-ban"></i> Alerta!</h4>No se pudo procesar y guardar la imagen. Verifique los permisos de escritura de <b>dist/img/fotos_animales</b>.</div>';
                                break;
                            }
                        }
                    }

                    $reg_animal = Manimale::find($id_animal);
                    $reg_animal->nombre = $nombre_animal;
                    $reg_animal->sexo = $sexo;
                    $reg_animal->rena = $rena;
                    $reg_animal->alza = $alza;
                    $reg_animal->estatus_animal = $estatus_animal;
                    $reg_animal->mlotes_id = $lote;
                    $reg_animal->nro_arete = $numero_arete;
                    $reg_animal->categoria_ingreso_id = $categoria_ingreso;
                    $reg_animal->categoria_futura_id = $categoria_futura;
                    $reg_animal->aptitud = $aptitud_animal;
                    $reg_animal->mrazas_id = $raza_animal;
                    $reg_animal->f_nacimiento = $fech_nac;
                    $reg_animal->peso_nacimiento = $peso_nac;
                    $reg_animal->f_ingreso = $fecha_ingreso;
                    $reg_animal->servicio = $servicio_animal;
                    $reg_animal->rgd_madre = $rgd_madre;
                    $reg_animal->nombre_madre = $nombre_madre;
                    $reg_animal->rgd_padre = $rgd_padre;
                    $reg_animal->nombre_padre = $nombre_padre;
                    $reg_animal->procedencia = $procedencia;
                    $reg_animal->observaciones = $observaciones;
                    $reg_animal->imagen = $nombre_imagen;
                    $reg_animal->user_modify = $usuario_id;
                    $reg_animal->updated = $hoy;

                    if ($reg_animal->save()) {

                        @$buscar_reg_nac = Manimale::find_by_sql("SELECT nac_fundo,codigo_ternero
            FROM Manimales
            WHERE   id='$id_animal' and status=1 and mfundos_id=" . $mfundos_id . " 
                    and nac_fundo=true ;");

                        if ($buscar_reg_nac != null) {

                            foreach ($buscar_reg_nac as $reg) {
                                $codigo_ternero = $reg->codigo_ternero;
                            }

                            @$buscar_reg = Mregnacimiento::find_by_sql("SELECT id,codigo_rgb_id_animal_hembra,codigo_rgb_id_animal_macho
                                                              FROM Mregnacimientos
                                                              WHERE  codigo_ternero= '$codigo_ternero'  and  mfundos_id=" . $mfundos_id . "  ;");
                        }

                        if ($buscar_reg != null) {

                            foreach ($buscar_reg as $reg_nac) {
                                $codigo_rgb_id_animal_macho = $reg_nac->codigo_rgb_id_animal_macho;
                                $codigo_rgb_id_animal_hembra = $reg_nac->codigo_rgb_id_animal_hembra;
                                $id_nac = $reg_nac->id;
                            }

                            $sigue = false;
                            $reg_nac_animal = Mregnacimiento::find($id_nac);
                            $reg_nac_animal->user_modify = $usuario_id;
                            $reg_nac_animal->updated = $hoy;

                            if ($codigo_rgb_id_animal_macho != $rgd_padre) {

                                $reg_nac_animal->codigo_rgb_id_animal_macho = $rgd_padre;
                                $sigue = true;
                            }
                            if ($codigo_rgb_id_animal_hembra != $rgd_madre) {

                                $reg_nac_animal->codigo_rgb_id_animal_hembra = $rgd_madre;

                                $sigue = true;
                            }
                            if ($sigue) {
                                $reg_nac_animal->save();
                            }
                        }

                        echo '<div class="alert alert-success alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <input type="hidden" id="tocon" value="1">
              <h4>
              <i class="fa fa-check"></i>
              Alerta!
              </h4>
              Los datos han sido actualizados exitosamente !.
              </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <input type="hidden" id="tocon" value="0">
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al actualizar los datos.
              </div>';
                    }
                }
            } else {

                echo '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <input type="hidden" id="tocon" value="0">
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al Intentar ACTUALIZAR.. El Número de arete ya se encuentra registrado !!.
              </div>';
            }
        }
        break;
    #*******************************************************************************

    case 'check_historial':

        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) session_start();
        $usuario_check = isset($_SESSION['idusuariox']) ? $_SESSION['idusuariox'] : '';
        $mfundos_check = isset($_SESSION['mfundos_id']) ? (int)$_SESSION['mfundos_id'] : 0;

        if ($usuario_check === '' || $mfundos_check <= 0) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'tiene' => false, 'msg' => 'Sesión expirada'));
            break;
        }

        $id_check = (int)$record;
        if ($id_check <= 0) {
            echo json_encode(array('ok' => false, 'tiene' => false, 'msg' => 'Registro inválido'));
            break;
        }

        require_once __DIR__ . '/../lib/animal_historial_helper.php';

        $historial   = animal_tiene_historial($id_check, $mfundos_check);
        $cod_hist    = Manimale::find($id_check);
        $codigo_hist = $cod_hist ? (string)$cod_hist->codigo_rgb_id_animal : '';

        echo json_encode(array(
            'ok'      => true,
            'tiene'   => !empty($historial['tiene']),
            'modulos' => isset($historial['modulos']) ? $historial['modulos'] : array(),
            'codigo'  => $codigo_hist,
            'mensaje' => animal_historial_mensaje($historial, $codigo_hist)
        ));
        break;

    #*******************************************************************************

    case 'delete':

        if ($record != null) {

            session_start();
            $usuario_id = (int) (isset($_SESSION['idusuariox']) ? $_SESSION['idusuariox'] : 0);
            $rol        = (int) (isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
            $maximo_registros = isset($_SESSION['maximo_registros']) ? $_SESSION['maximo_registros'] : 0;
            $mfundos_id       = (int) (isset($_SESSION['mfundos_id']) ? $_SESSION['mfundos_id'] : 0);

            $hoy = date('d-m-Y');

            // Roles autorizados a eliminar animales:
            //   1 = Administrador, 2 = Propietario/Productor, 4 = Super Usuario.
            // El Encargado/Colaborador (3) es de solo consulta (perfil auditor).
            $roles_permitidos = array(1, 2, 4);

            if ($usuario_id > 0 && $mfundos_id > 0 && in_array($rol, $roles_permitidos, true)) {

                // El animal debe existir, estar vigente y pertenecer al fundo activo.
                $reg_animal = Manimale::find('first', array(
                    'conditions' => array('id = ? AND mfundos_id = ? AND status = 1', (int) $record, $mfundos_id)
                ));
                if (!$reg_animal) {
                    echo '<div class="alert alert-warning alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-warning"></i>
                      Alerta!
                      </h4>
                      El animal no existe, ya fue eliminado o no pertenece al fundo activo.
                      </div>';
                    break;
                }

                // ---- Guardia: no eliminar animales que ya tienen historial ----
                require_once __DIR__ . '/../lib/animal_historial_helper.php';
                $historial = animal_tiene_historial((int) $record, $mfundos_id);
                if (!empty($historial['tiene'])) {
                    $codigo_hist = (string) $reg_animal->codigo_rgb_id_animal;
                    echo animal_historial_mensaje($historial, $codigo_hist);
                    break;
                }

                $reg_animal->user_modify = $usuario_id;
                $reg_animal->updated = $hoy;
                $reg_animal->status = 0;

                if ($reg_animal->save()) {


                    $max_animal = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
                FROM Manimalxfundos
                WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                    foreach ($max_animal as $fund_ani) {
                        $cant_animal = $fund_ani->cant_animal;
                    }

                    if ($maximo_registros  >= $cant_animal) {

                        $_SESSION['acceso_reg_animales'] = 1;
                    } else {
                        $_SESSION['acceso_reg_animales'] = 0;
                    }

                    echo '<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="fa fa-check"></i>
                    Alerta!
                    </h4>
                    Registro Eliminado exitosamente...
                  </div>';
                } else {
                    echo  '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Disculpe! Ha ocurrido un error al eliminar el registro..
                  </div>';
                }
            } else {

                echo  '<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Disculpe! Usted no puede realizar esta transacción.
                  </div>';
            }
        }
        break;

    #*******************************************************************************
    case 'search':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        @$t_ganaderia = $_SESSION['tipo_ganaderia'];

        if ($record != null) {

            @$data = Manimale::find('all', array('conditions' => array('id=? and mfundos_id=?', $record, $mfundos_id)));

            if ($data != null) {

                foreach ($data as $rs) {

                    $f_nacimiento = '';
                    $f_ingreso = '';

                    if ($rs->f_nacimiento != '') {
                        $f_nacimiento = (string)$rs->f_nacimiento->format('d-m-Y');
                    }
                    if ($rs->f_ingreso != '') {
                        $f_ingreso = (string)$rs->f_ingreso->format('d-m-Y');
                    }
                    $cat_actual = Cat_Actual($rs->sexo, $f_nacimiento, $mfundos_id, $t_ganaderia);
                    $resp = array(
                        'id_animal' => $rs->id,
                        'cod_animal' => $rs->codigo_rgb_id_animal,
                        'nombre_animal' => $rs->nombre,
                        'sexo' => $rs->sexo,
                        'estatus_animal' => $rs->estatus_animal,
                        'lote' => $rs->mlotes_id,
                        'rena' => $rs->rena,
                        'alza' => $rs->alza,
                        'raza_animal' => $rs->mrazas_id,
                        'fech_nac' => $f_nacimiento,
                        'peso_nac' => $rs->peso_nacimiento,
                        'fecha_ingreso' =>  $f_ingreso,
                        'aptitud_animal' => $rs->aptitud,
                        'numero_arete' => $rs->nro_arete,
                        'categoria_ingreso' => $rs->categoria_ingreso_id,
                        'categoria_futura' => $rs->categoria_futura_id,
                        'rgd_madre' => $rs->rgd_madre,
                        'nombre_madre' => $rs->nombre_madre,
                        'rgd_padre' => $rs->rgd_padre,
                        'nombre_padre' => $rs->nombre_padre,
                        'servicio_animal' => $rs->servicio,
                        'procedencia' => $rs->procedencia,
                        'observaciones' => $rs->observaciones,
                        'categoria_actual' => $cat_actual,
                        'imagen' => $rs->imagen

                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    #*******************************************************************************
    case 'search_ficha':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        @$t_ganaderia = $_SESSION['tipo_ganaderia'];

        if ($record != null) {

            @$data = Vmfichaxanimale::find('all', array('conditions' => array('id_animal=? and mfundos_id=?', $record, $mfundos_id)));



            @$data_fundo = Mfundo::find('all', array('conditions' => array('id=?', $mfundos_id)));

            foreach ($data_fundo as $rs_fundo) {

                $nombre_fundo = $rs_fundo->nombre;
                $rif_fundo = $rs_fundo->cid_rif;
            }


            if ($data != null) {

                foreach ($data as $rs) {

                    $f_nacimiento = '';
                    $f_ingreso = '';

                    if ($rs->f_nacimiento != '') {
                        $f_nacimiento = (string)$rs->f_nacimiento->format('d-m-Y');
                    }
                    if ($rs->f_ingreso != '') {
                        $f_ingreso = (string)$rs->f_ingreso->format('d-m-Y');
                    }
                    $cat_actual = Cat_Actual($rs->id_sexo, $f_nacimiento, $mfundos_id, $t_ganaderia);
                    $resp = array(
                        'cod_animal' => $rs->codigo_rgb_id_animal,
                        'nombre_animal' => $rs->nombre,
                        'sexo' => $rs->sexo,
                        'lote' => $rs->lote,
                        'rena' => $rs->rena,
                        'raza_animal' => $rs->raza,
                        'fech_nac' => $f_nacimiento,
                        'peso_nac' => $rs->peso_nacimiento,
                        'fecha_ingreso' =>  $f_ingreso,
                        'aptitud_animal' => $rs->aptitud,
                        'numero_arete' => $rs->nro_arete,
                        'rgd_madre' => $rs->rgd_madre,
                        'nombre_madre' => $rs->nombre_madre,
                        'rgd_padre' => $rs->rgd_padre,
                        'nombre_padre' => $rs->nombre_padre,
                        'servicio_animal' => $rs->servicio,
                        'procedencia' => $rs->procedencia,
                        'observaciones' => $rs->observaciones,
                        'categoria_actual' => $cat_actual,
                        'fundo' => $nombre_fundo,
                        'rif_fundo' => $rif_fundo

                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    #******************************************************************************

    case 'get_causa_mortalidad_animal':

        @$data = Manimale::find('all', array('conditions' => array('status=?', 1), 'order' => 'id desc'));

        if ($data != null) {
            $resp = '<option value="">Indique la Causa de Mortalidad</option>';
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
                $resp .= '<hidden>';
            }
        } else {
            $resp = '<option value="">No hay Causa de Mortalidad para Agregar</option>';
        }

        echo $resp;
        break;

    #******************************************************************************

    // ---- Estatus del animal según el SEXO (catálogo mestatusanimales) ----
    //   hembra → 1 ACTIVO · 4 DONADORA ACTIVO · 5 DONADORA
    //   macho  → 1 ACTIVO · 2 DONADOR ACTIVO  · 3 DONADOR
    case 'get_estatus_animal':

        $sexo_estatus    = isset($_POST['sexo']) ? intval($_POST['sexo']) : 0;
        $seleccionado    = isset($_POST['estatus_animal']) ? intval($_POST['estatus_animal']) : 0;

        $resp = bovs_estatus_animal_opciones_html($sexo_estatus, $seleccionado);

        if ($resp == '') {
            $resp = '<option value="1">ACTIVO</option>';
        }

        echo $resp;
        break;

    #******************************************************************************

    case 'search_nro_arete':
        session_start();

        $nro_arete = ($_POST['nro_arete']);
        $codigo_rgb_id_animal = ($_POST['cod_animal']);

        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        // Cargar esta variable en el starsession inicial

        if ($nro_arete != null) {

            if ($codigo_rgb_id_animal != null) {
                @$data = Manimale::find_by_sql("SELECT nro_arete,codigo_rgb_id_animal
        FROM Manimales
        WHERE  nro_arete='$nro_arete' 
         and	nro_arete NOT  in (select a.nro_arete FROM Manimales a
          WHERE a.nro_arete='$nro_arete' and a.codigo_rgb_id_animal='$codigo_rgb_id_animal'
           and  a.status=1 and a.mfundos_id=" . $mfundos_id . ') and  status=1 and mfundos_id=' . $mfundos_id . ';');

                if ($data != null) {

                    foreach ($data as $rs) {

                        $resp = array(
                            'nro_arete' => $rs->nro_arete,
                            'codigo_rgb_id_animal' => $rs->codigo_rgb_id_animal

                        );
                    }
                } else {
                    $resp = array(
                        'nro_arete' => 0,
                        'codigo_rgb_id_animal' => 0

                    );
                }
            } else {
                @$data = Manimale::find_by_sql("SELECT nro_arete
                      FROM Manimales
                      WHERE  nro_arete='$nro_arete' and status=1 and mfundos_id=" . $mfundos_id . ' ;');

                if ($data != null) {

                    foreach ($data as $rs) {

                        $resp = array(
                            'nro_arete' => $rs->nro_arete

                        );
                    }
                } else {
                    $resp = array(
                        'nro_arete' => 0,
                        'codigo_rgb_id_animal' => 0

                    );
                }
            }

            echo json_encode($resp);
        }
        break;

    #******************************************************************************

    case 'search_nombre_animal':
        session_start();

        $nombre_animal = strtoupper($_POST['nombre_animal']);
        $codigo_rgb_id_animal = ($_POST['cod_animal']);

        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        // Cargar esta variable en el starsession inicial

        if ($nombre_animal != null) {

            if ($codigo_rgb_id_animal != null) {
                @$data = Manimale::find_by_sql("SELECT nombre,codigo_rgb_id_animal
        FROM Manimales
        WHERE  nombre='$nombre_animal' 
         and	nombre NOT  in (select a.nombre FROM Manimales a
          WHERE a.nombre='$nombre_animal' and a.codigo_rgb_id_animal='$codigo_rgb_id_animal'
           and  a.status=1 and a.mfundos_id=" . $mfundos_id . ') and  status=1 and mfundos_id=' . $mfundos_id . ';');

                if ($data != null) {

                    foreach ($data as $rs) {

                        $resp = array(
                            'nombre_animal' => $rs->nombre,
                            'codigo_rgb_id_animal' => $rs->codigo_rgb_id_animal

                        );
                    }
                } else {
                    $resp = array(
                        'nombre_animal' => 0,
                        'codigo_rgb_id_animal' => 0

                    );
                }
            } else {
                @$data = Manimale::find_by_sql("SELECT nombre
                      FROM Manimales
                      WHERE  nombre='$nombre_animal' and status=1 and mfundos_id=" . $mfundos_id . ' ;');

                if ($data != null) {

                    foreach ($data as $rs) {

                        $resp = array(
                            'nombre_animal' => $rs->nombre

                        );
                    }
                } else {
                    $resp = array(
                        'nombre_animal' => 0,
                        'codigo_rgb_id_animal' => 0

                    );
                }
            }
        } else {
            $resp = array(
                'nombre_animal' => 0,
                'codigo_rgb_id_animal' => 0

            );
        }
        echo json_encode($resp);

        break;

    /// Nuevas Busquedas

    case 'search_reg_animal':

        $buscar = intval($_POST['buscar_cod']);
        $nro_arete_codigo = ($_POST['rgb_animal']);

        $cadena = ' ';

        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

        if ($buscar == 1) {

            $rgb_animal = $nro_arete_codigo;
            $partes = explode('-', $rgb_animal);
            $prefijo = $partes[0];
            $nro = 0;
            if ($prefijo == 'SOVCA') {
                $nro = intval($partes[2]);
            } else {
                $nro = intval($rgb_animal);
            }

            $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

            $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";

            $criterio = 'Código';
        } else   if ($buscar == 2) {

            $nro_arete = $nro_arete_codigo;

            $cadena = "and nro_arete='$nro_arete' ";
            $criterio = 'Nro de Arete';
        }

        @$data = Manimale::find_by_sql('select id as cod_animal from Manimales where mfundos_id=' . $mfundos_id . " and status=1  $cadena;");

        if ($data != null) {

            foreach ($data as $rs) {

                $resp = array(
                    'cod_animal' => $rs->cod_animal

                );
            }
        } else {
            $resp = array(
                'cod_animal' => 0

            );
        }
        echo json_encode($resp);

        break;
}
//end switch

function Cat_Actual($sexo_categoria, $fecha_nac, $mfundos_id, $t_ganaderia)
{

    $edad = tiempo_t($fecha_nac);

    $data_propia = Mcategoria::find_by_sql("select nombre from mcategorias 
                                          WHERE 
                                          mtipoganaderias_id=" . $t_ganaderia . " and 
                                          status=1 and 
                                          sexo=" . $sexo_categoria . " and 
                                          mfundos_id=" . $mfundos_id . " and
                                          edadminima<=" . $edad . " and
                                          edadmaxima> " . $edad . '  order by id asc;');

    $data_estandar = Mcategoria::find_by_sql("select nombre from mcategorias 
                                          WHERE 
                                          mtipoganaderias_id=" . $t_ganaderia . " and 
                                          status=1 and 
                                          sexo=" . $sexo_categoria . " and 
                                          mfundos_id=0 and
                                          edadminima<=" . $edad . " and
                                          edadmaxima> " . $edad . '  order by id asc;');

    if ($data_propia != null) {

        foreach ($data_propia as $rs) {

            $cat_actual = $rs->nombre;
        }
    } else if ($data_estandar != null) {

        foreach ($data_estandar as $rs) {

            $cat_actual = $rs->nombre;
        }
    }
    return  $cat_actual;
}

function tiempo_t($fecha_nac)
{
    $fecha_f = date('Y-m-d');
    $fecha_i = $fecha_nac;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);
    $meses = ($dias / 30);
    $meses = (int)$meses;

    return $meses;
}
