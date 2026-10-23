<?php
require_once '../models/Mregservicio.php';
require_once '../models/Vdesincorporacione.php';
require_once '../models/Manimale.php';
require_once '../models/Vregservicio.php';
require_once '../models/Mserviciosxfundo.php';
require_once '../models/Mregnacimiento.php';
require_once '../models/Vcontrolservicio.php';


include '../lib/sanitizar.php';
require_once '../lib/estatus_animal_helper.php';
require_once '../lib/fecha_secado_helper.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);
@$id = ($_POST['id_regservicio']);

@$record = ($_POST['record']);
/***********************************/
@$cod_animal_h = ($_POST['cod_animal_h']);
@$cod_animal_dona = ($_POST['cod_animal_dona']);
@$cod_animal_m = ($_POST['cod_animal_m']);
@$fecha_servicio = ($_POST['fecha_servicio']);
@$fecha_fup = ($_POST['fecha_fup']);
@$tipo_servicio = ($_POST['tipo_servicio']);
@$nro_servicio = ($_POST['nro_servicio']);

@$aptitud_animal = ($_POST['aptitud_animal']);
@$raza_animal = ($_POST['raza_animal']);

@$fecha_fpp = ($_POST['fecha_fpp']);
@$fecha_fp = ($_POST['fecha_fp']);

@$estado_fisiologico = ($_POST['estado_fisiologico']);

@$fecha_fppt = ($_POST['fecha_fppt']);
@$pario = ($_POST['pario']);
@$nro_terneros = ($_POST['nro_terneros']);
@$observaciones = ($_POST['observaciones']);
@$estado_fisiologico = ($_POST['estado_fisiologico']);

/// Nuevas Variables
@$rgb_animal_h = ($_POST['rgb_animal_h']);
@$rgb_animal_m = ($_POST['rgb_animal_m']);
@$id_regservicio = ($_POST['id_regservicio']);
@$dnp = ($_POST['dnp']);
@$fecha_parto = ($_POST['fecha_parto']);
@$fecha_secado = ($_POST['fecha_secado']);

/**********************************/

switch ($action) {
    //// **************************

    /* ============================================================
       AVISO DE CICLOS ABIERTOS
       Una hembra debe tener un solo servicio vigente. Si al elegir la
       hembra en el registro de servicio detectamos varios ciclos abiertos
       (o uno muy antiguo), se devuelve un aviso para mostrarlo en el
       formulario. No bloquea: solo orienta al productor.
       ============================================================ */
    case 'avisos_ciclo_hembra':

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $mfundos_id = (int)$_SESSION['mfundos_id'];
        $cod_hembra = trim((string)(isset($_POST['cod_animal_h']) ? $_POST['cod_animal_h'] : ''));

        if ($cod_hembra === '') {
            echo json_encode(array('ok' => false, 'total' => 0, 'html' => ''));
            break;
        }

        $abiertos = Vregservicio::find_by_sql(
            "select id, f_servicio, nro_servicio
               from Vregservicios
              where mfundos_id = ? and codigo_rgb_id_animal_hembra = ? and estatus_ciclo = 1
              order by f_servicio asc nulls last, id asc",
            array($mfundos_id, $cod_hembra)
        );

        $total = $abiertos ? count($abiertos) : 0;
        $html  = '';

        if ($total > 1) {
            $fechas = array();
            foreach ($abiertos as $fila) {
                if ($fila->f_servicio instanceof DateTime) {
                    $fechas[] = $fila->f_servicio->format('d-m-Y');
                }
            }
            $lista_fechas = $fechas ? ' (' . implode(', ', $fechas) . ')' : '';

            $html = '<div class="bovs-aviso-ciclo bovs-aviso-ciclo--alerta">'
                  . '<i class="fa fa-exclamation-triangle"></i>'
                  . '<div><strong>Esta hembra tiene ' . $total . ' servicios abiertos' . htmlspecialchars($lista_fechas, ENT_QUOTES, 'UTF-8') . '.</strong> '
                  . 'Solo el más reciente debe quedar vigente. Antes de continuar, registra en '
                  . '<strong>Control</strong> la palpación, el parto o el aborto de los anteriores para cerrarlos.'
                  . '</div>'
                  . '</div>';
        } elseif ($total === 1) {
            $ref = $abiertos[0]->f_servicio;

            if ($ref instanceof DateTime) {
                $hoy = new DateTime('now');
                $dif = $ref->diff($hoy);
                $meses = ($dif->y * 12) + $dif->m;

                if ($meses >= 12) {
                    $html = '<div class="bovs-aviso-ciclo bovs-aviso-ciclo--info">'
                          . '<i class="fa fa-clock-o"></i>'
                          . '<div>Su ciclo abierto más antiguo es del <strong>' . $ref->format('d-m-Y') . '</strong>'
                          . ' (hace ' . $meses . ' meses). Si ese ciclo ya terminó, regístralo en '
                          . '<strong>Control</strong> para que su estatus reproductivo quede al día.'
                          . '</div>'
                          . '</div>';
                }
            }
        }

        echo json_encode(array(
            'ok'    => true,
            'total' => $total,
            'html'  => $html
        ));
        break;

    //// **************************

    /* ============================================================
       DIAGNÓSTICO DE HEMBRA PARA EL REGISTRO DE SERVICIO

       Cuando el productor escribe un código/arete que no aparece en el
       listado, se explica POR QUÉ no está disponible: no existe, es de
       otro fundo, es macho, no está activa, es menor de 12 meses o tiene
       un servicio abierto pendiente de cerrar.
       ============================================================ */
    case 'diagnostico_hembra':

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $mfundos_id = (int)$_SESSION['mfundos_id'];
        $texto = trim((string)(isset($_POST['texto']) ? $_POST['texto'] : ''));

        if ($texto === '') {
            echo json_encode(array('ok' => false, 'html' => ''));
            break;
        }

        $html = '';

        /* ---------- 1) ¿Existe en este fundo? ---------- */
        $animales = Manimale::find_by_sql(
            "select a.id, a.codigo_rgb_id_animal, a.nombre, a.nro_arete, a.sexo,
                    a.mestatus_id, a.f_nacimiento, coalesce(sg.nombre, '') as estatus_nombre
               from manimales a
               left join mestatusganados sg on sg.id = a.mestatus_id
              where a.mfundos_id = ?
                and a.status = 1
                and (upper(a.codigo_rgb_id_animal) = upper(?)
                     or a.nro_arete = ?
                     or upper(a.nombre) = upper(?))
              order by a.id desc
              limit 1",
            array($mfundos_id, $texto, $texto, $texto)
        );

        if (!$animales) {
            /* ¿El código pertenece a otro fundo? (SOVCA-032-0125) */
            $otro_fundo = 0;
            if (preg_match('/^(?:SOVCA|BOVS)-0*(\d+)-/i', $texto, $partes)) {
                $otro_fundo = (int)$partes[1];
            }

            if ($otro_fundo > 0 && $otro_fundo !== $mfundos_id) {
                $html = aviso_diagnostico(
                    'alerta',
                    'fa-exclamation-triangle',
                    'El código <strong>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</strong> pertenece al fundo <strong>' . $otro_fundo . '</strong>, '
                    . 'no a este fundo. Cambia de fundo activo o verifica el código.'
                );
            } else {
                $html = aviso_diagnostico(
                    'alerta',
                    'fa-search',
                    'No existe un animal activo con el código, arete o nombre <strong>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</strong> en este fundo.<br>'
                    . 'Revisa que el código esté completo (ej.: <em>SOVCA-0' . $mfundos_id . '-0123</em>).'
                );
            }

            echo json_encode(array('ok' => true, 'html' => $html));
            break;
        }

        $animal = $animales[0];
        $codigo = (string)$animal->codigo_rgb_id_animal;
        $nombre = trim((string)$animal->nombre);
        $ref = '<strong>' . htmlspecialchars($codigo . ($nombre !== '' ? ' · ' . $nombre : ''), ENT_QUOTES, 'UTF-8') . '</strong>';

        /* ---------- 2) Sexo ---------- */
        if ((int)$animal->sexo !== 1) {
            $html = aviso_diagnostico('alerta', 'fa-mars',
                $ref . ' es un <strong>macho</strong>. El servicio reproductivo se registra sobre hembras.');
            echo json_encode(array('ok' => true, 'html' => $html));
            break;
        }

        /* ---------- 3) Estatus del animal ---------- */
        if ((int)$animal->mestatus_id !== 1) {
            $estatus = trim((string)$animal->estatus_nombre);
            $html = aviso_diagnostico('alerta', 'fa-ban',
                $ref . ' está en estado <strong>' . htmlspecialchars($estatus !== '' ? $estatus : ('#' . $animal->mestatus_id), ENT_QUOTES, 'UTF-8') . '</strong>. '
                . 'Solo se les registra servicio a animales <strong>ACTIVOS</strong>.');
            echo json_encode(array('ok' => true, 'html' => $html));
            break;
        }

        /* ---------- 4) Edad mínima ---------- */
        $meses = null;
        if ($animal->f_nacimiento instanceof DateTime) {
            $dif = $animal->f_nacimiento->diff(new DateTime('now'));
            $meses = ($dif->y * 12) + $dif->m;
        }

        if ($meses !== null && $meses < 12) {
            $html = aviso_diagnostico('alerta', 'fa-clock-o',
                $ref . ' tiene <strong>' . $meses . ' meses</strong>. La edad mínima para registrar servicio es de <strong>12 meses</strong>.');
            echo json_encode(array('ok' => true, 'html' => $html));
            break;
        }

        /* ---------- 5) ¿Tiene un ciclo abierto? ---------- */
        $abiertos = Vregservicio::find_by_sql(
            "select s.id, s.f_servicio, s.nro_servicio, s.medofisiologicos_id,
                    coalesce(ef.nombre, '') as estado
               from mregservicios s
               left join mestadofisiologicos ef on ef.id = s.medofisiologicos_id
              where s.mfundos_id = ? and s.codigo_rgb_id_animal_hembra = ?
                and s.status = 1 and s.estatus_ciclo = 1
              order by s.f_servicio desc nulls last, s.id desc
              limit 1",
            array($mfundos_id, $codigo)
        );

        if ($abiertos) {
            $abierto = $abiertos[0];
            $fecha = ($abierto->f_servicio instanceof DateTime) ? $abierto->f_servicio->format('d-m-Y') : '';
            $estado = trim((string)$abierto->estado);
            $fisio = (int)$abierto->medofisiologicos_id;

            if (in_array($fisio, array(1, 2, 3), true)) {
                $html = aviso_diagnostico('info', 'fa-lock',
                    $ref . ' tiene un <strong>servicio abierto</strong>'
                    . ($fecha !== '' ? ' del <strong>' . $fecha . '</strong>' : '')
                    . ($estado !== '' ? ' en estado <strong>' . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') . '</strong>' : '')
                    . '.<br>Para registrar un servicio nuevo, primero carga el <strong>Control</strong> '
                    . '(palpación, parto o aborto) y cierra ese ciclo.');
            } else {
                $html = aviso_diagnostico('info', 'fa-info-circle',
                    $ref . ' figura con un ciclo abierto del '
                    . ($fecha !== '' ? '<strong>' . $fecha . '</strong>' : 'ciclo anterior')
                    . ' pero sin gestación activa. Debería aparecer en el listado: '
                    . 'pulsa el botón <strong>recargar</strong> del buscador o escríbela completa.');
            }

            echo json_encode(array('ok' => true, 'html' => $html));
            break;
        }

        /* ---------- 6) Existe y no tiene ciclos abiertos ---------- */
        $html = aviso_diagnostico('info', 'fa-info-circle',
            $ref . ' está activa y sin servicios abiertos, así que sí puede recibir servicio.<br>'
            . 'Escríbela completa o selecciónala de la lista para continuar.');

        echo json_encode(array('ok' => true, 'html' => $html));
        break;

    //// **************************
    case 'get_buscar_rgb_h_dona':

        $buscar = intval($_POST['buscarx_dona']);
        $nro_arete_codigo = ($_POST['rgb_animal_h_dona']);

        $cadena = ' ';
        $cadena_2 = '';

        if ($nro_arete_codigo == '') {

            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Debe Ingresar el Código o Número de Arete de la HEMBRA.
            </div>');
        } else {

            session_start();
            $mfundos_id = $_SESSION['mfundos_id'];

            if ($buscar == 1) {

                $rgb_animal = $nro_arete_codigo;
                $partes = explode('-', $rgb_animal);
                $prefijo = $partes[0];
                $nro = 0;
                if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
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

            /* OJO: la versión anterior de esta búsqueda consultaba Vregservicios
               y leía $rs->estatus (columna que NO existe en la vista), por lo
               que SIEMPRE respondía "El Código del Animal NO EXISTE" y nunca se
               podía registrar una Transferencia de Embriones. Ahora se valida
               igual que la receptora, exigiendo que sea donadora (estatus
               animal 2 o 3). */
            /* La DONADORA es una hembra con estatus propio de donante
               (4 DONADORA ACTIVO / 5 DONADORA): antes se buscaba con los
               valores de macho (2/3), por lo que nunca coincidía. */
            @$data_animal = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h,f_nacimiento from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 and sexo=1 and " . bovs_estatus_animal_donante_sql(1) . " $cadena;");

            if ($data_animal != null) {

                foreach ($data_animal as $rs_animal) {

                    $fecha_nac = $rs_animal->f_nacimiento;
                    @$fecha_nac = ($fecha_nac != null) ? (string) $fecha_nac->format('Y-m-d') : '';
                    $edad = ($fecha_nac != '') ? tiempo_t($fecha_nac) : 0;

                    if ($edad < 12) {
                        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                 La Edad de la HEMBRA DONADORA no puede ser Menor de 12 Meses.. EDAD ACTUAL  [ ' . $edad . ' ] meses !!.
                  </div>');

                        break;
                    }

                    $data_control_servicio = Vcontrolservicio::find_by_sql("select  id, medofisiologicos_id, nro_servicio
                                                                             from Vcontrolservicios
                                                                            where mfundos_id=" . $mfundos_id . " and estatus_ciclo=1 $cadena_2 ;");

                    $nro_servicio_dona = 1;

                    /* OJO: a la DONADORA no se le aplica la regla de "ya está en
                       servicio": en la transferencia de embriones ella se
                       insemina para producir el embrión, por lo que su estado
                       reproductivo es parte del procedimiento y no un impedimento. */
                    if ($data_control_servicio != null) {

                        foreach ($data_control_servicio as $rs_control_servicio) {
                            $nro_servicio_dona = $rs_control_servicio->nro_servicio;
                        }

                        $nro_servicio_dona = $nro_servicio_dona + 1;
                    }

                    /* Mismo formato de respuesta que la receptora: codigo*nombre*nro_servicio */
                    $resp = $rs_animal->cod_animal_h . '*' . $rs_animal->nombre_h . '*' . $nro_servicio_dona;

                    break;
                }
            } else {
                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-ban"></i>
                    Alerta!
                    </h4>
                    No existe una HEMBRA DONADORA (estatus ' . bovs_estatus_animal_donante_descripcion(1) . ') con el ' . $criterio . ' [' . $nro_arete_codigo . '] !!.
                    </div>');

                /* OJO: este break sale del switch (no de un bucle), por eso la
                   respuesta se imprime antes de salir. */
                echo json_encode($resp);

                break;
            }
        }
        echo json_encode($resp);
        break;

    //// **************************

    #*******************************************************************************
    # LISTADOS DE CANDIDATOS PARA EL REGISTRO DE SERVICIO
    #   rol = receptora | donadora | macho
    # Devuelve, en UNA sola consulta, los animales del fundo que cumplen la
    # condición del tipo de servicio en curso, con el estado reproductivo, para
    # que el formulario arme el listado filtrable (nombre, código o arete).
    # Las reglas son las MISMAS que validan las búsquedas get_buscar_rgb_*:
    #   - hembra receptora : activa, >= 12 meses y sin ciclo abierto (o con el
    #                        último control en Aborto/Vacía)
    #   - hembra donadora  : donadora (estatus_animal 2/3) con las mismas reglas
    #   - macho            : MONTA -> toro activo | INSEMINACION -> donador
    #*******************************************************************************
    case 'listar_candidatos_servicio':

        session_start();
        $mfundos_id = intval($_SESSION['mfundos_id']);

        $rol    = isset($_POST['rol'])    ? $_POST['rol'] : '';
        $tipo_s = isset($_POST['tipo_s']) ? intval($_POST['tipo_s']) : 0;

        $filas = array();

        /* ---------- MACHO (monta / donador) ---------- */
        if ($rol == 'macho') {

            if ($tipo_s == 2) {
                $cad_estatus = 'and ' . bovs_estatus_animal_donante_sql(2);
                $condicion   = 'Donador';
            } else {
                $cad_estatus = 'and estatus_animal=1';
                $condicion   = 'Toro de monta';
            }

            $datos = Manimale::find_by_sql("select codigo_rgb_id_animal, nro_arete, nombre, estatus_animal,
                                                   (extract(year from age(current_date, f_nacimiento)) * 12
                                                    + extract(month from age(current_date, f_nacimiento)))::int as edad_meses
                                              from manimales
                                             where mfundos_id=" . $mfundos_id . " and mestatus_id=1 and sexo=2
                                                   $cad_estatus
                                             order by nombre, codigo_rgb_id_animal;");

            foreach ($datos as $rs) {
                $filas[] = array(
                    'codigo'       => $rs->codigo_rgb_id_animal,
                    'nro_arete'    => ($rs->nro_arete === null) ? '' : $rs->nro_arete,
                    'nombre'       => ($rs->nombre === null) ? '' : $rs->nombre,
                    'edad_meses'   => ($rs->edad_meses === null) ? '' : intval($rs->edad_meses),
                    'condicion'    => $condicion,
                    'disponible'   => 1,
                    'nro_servicio' => 0
                );
            }
        } else {

            /* ---------- HEMBRA receptora / donadora ---------- */
            $cad_estatus = '';
            $es_donadora = ($rol == 'donadora');

            if ($es_donadora) {
                $cad_estatus = 'and (' . bovs_estatus_animal_donante_sql(1, 'a') . ')';
            }

            $edad_sql = '(extract(year from age(current_date, a.f_nacimiento)) * 12
                         + extract(month from age(current_date, a.f_nacimiento)))';

            $sql = "select distinct on (a.codigo_rgb_id_animal)
                           a.codigo_rgb_id_animal                  as codigo,
                           coalesce(a.nro_arete, '')                as nro_arete,
                           coalesce(a.nombre, '')                   as nombre,
                           " . $edad_sql . "::int                    as edad_meses,
                           coalesce(c.medofisiologicos_id, 0)       as medofisiologicos_id,
                           coalesce(ef.nombre, '')                  as estado,
                           coalesce(c.nro_servicio, 0)              as nro_servicio,
                           case when c.id is null then 0 else 1 end as en_servicio
                      from manimales a
                      left join mregservicios c on c.codigo_rgb_id_animal_hembra = a.codigo_rgb_id_animal
                                               and c.mfundos_id = a.mfundos_id
                                               and c.status = 1
                                               and c.estatus_ciclo = 1
                      left join mestadofisiologicos ef on ef.id = c.medofisiologicos_id
                     where a.mfundos_id = " . $mfundos_id . "
                       and a.mestatus_id = 1
                       and a.sexo = 1
                       and a.f_nacimiento is not null
                       $cad_estatus
                       and " . $edad_sql . " >= 12
                     order by a.codigo_rgb_id_animal, c.id desc nulls last;";

            foreach (Manimale::find_by_sql($sql) as $rs) {

                $en_servicio = intval($rs->en_servicio);
                $medofisio   = intval($rs->medofisiologicos_id);
                $nro_ctl     = intval($rs->nro_servicio);

                if ($es_donadora) {
                    /* La DONADORA se insemina justamente para producir el embrión:
                       su estado reproductivo NO la inhabilita (a diferencia de la
                       hembra que va a recibir el servicio). */
                    $disponible = 1;
                } else {
                    /* Misma regla de la validación: puede recibir servicio si no
                       tiene control abierto o si el último quedó en Aborto (4) o
                       Vacía (5). En el resto de los casos ya está en servicio. */
                    $disponible = (($en_servicio == 0) || ($medofisio == 4) || ($medofisio == 5)) ? 1 : 0;
                }

                $estado = ($rs->estado === null) ? '' : $rs->estado;

                $filas[] = array(
                    'codigo'       => $rs->codigo,
                    'nro_arete'    => ($rs->nro_arete === null) ? '' : $rs->nro_arete,
                    'nombre'       => ($rs->nombre === null) ? '' : $rs->nombre,
                    'edad_meses'   => intval($rs->edad_meses),
                    'condicion'    => $es_donadora ? 'Donadora' : (($disponible == 1) ? 'Hembra' : $estado),
                    'disponible'   => $disponible,
                    'nro_servicio' => ($en_servicio == 0) ? 1 : ($nro_ctl + 1)
                );
            }
        }

        echo json_encode(array(
            'resultado' => 'ok',
            'rol'       => $rol,
            'tipo_s'    => $tipo_s,
            'filas'     => $filas,
            'total'     => count($filas)
        ));

        break;

    //// **************************

    case 'add':

        if ($cod_animal_h == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>');
        } else if (($tipo_servicio == 3) && ($cod_animal_dona == '')) {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra Donadora.
        </div>');
        } else if (($tipo_servicio != 3) && ($cod_animal_m == '')) {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código del Macho.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $hoy = date('d-m-Y');

            /// GENERAMOS EL CODIGO PARA EL ANIMAL
            //***************** */
            $data_genera_codigo = Mserviciosxfundo::find_by_sql("SELECT count(id) as cant_animal
            FROM mserviciosxfundos
            WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

            foreach ($data_genera_codigo as $fund_ani) {
                $cant_animal = $fund_ani->cant_animal;
            }
            if ($cant_animal > 0) {
                $contados = $cant_animal + 1;
            } else {
                $contados = 1;
            }
            $cod_rsa = 'RSA-0' . $mfundos_id . '-0' . $contados;



            $consulta_ciclo_activo = Mregservicio::find_by_sql("select  ciclo_reproductivo
                                            from Mregservicios where codigo_rgb_id_animal_hembra='$cod_animal_h' 
                                            and estatus_ciclo=1  ;");

            $consulta_max_ciclo_activo = Mregservicio::find_by_sql("select  max(ciclo_reproductivo) as max_ciclo
                                            from Mregservicios where codigo_rgb_id_animal_hembra='$cod_animal_h' ;");

            $ciclo_activo = 0;

            if ($consulta_ciclo_activo != null) {

                foreach ($consulta_ciclo_activo as $rs_ciclo_activo) {
                    $ciclo_activo = $rs_ciclo_activo->ciclo_reproductivo;
                }
            }

            $new_ciclo = 0;
            if ($consulta_max_ciclo_activo != null) {
                foreach ($consulta_max_ciclo_activo as $rs_max_ciclo_activo) {
                    $new_ciclo = $rs_max_ciclo_activo->max_ciclo + 1;
                }
            }
            $ciclo_new_activo = 1;

            if ($ciclo_activo > 0) {

                $ciclo_new_activo = $ciclo_activo;
            } else   if ($new_ciclo  > 0) {

                $ciclo_new_activo = $new_ciclo;
            }


            $reg_servicio = new Mregservicio();
            $reg_servicio->codigo_servicio = $cod_rsa;
            $reg_servicio->codigo_rgb_id_animal_hembra = $cod_animal_h;
            $reg_servicio->codigo_rgb_id_animal_macho = $cod_animal_m;
            $reg_servicio->codigo_rgb_id_animal_dona = $cod_animal_dona;
            $reg_servicio->f_servicio = $fecha_servicio;
            $reg_servicio->f_probable_palpacion = $fecha_fpp;
            $reg_servicio->nro_servicio = $nro_servicio;
            $reg_servicio->mservicios_id = $tipo_servicio;
            $reg_servicio->mfundos_id = $mfundos_id;
            $reg_servicio->aptitud = $aptitud_animal;
            $reg_servicio->mrazas_id = $raza_animal;
            $reg_servicio->user_create = $usuario_id;
            $reg_servicio->created = $hoy;
            $reg_servicio->ciclo_reproductivo = $ciclo_new_activo;




            if ($reg_servicio->save()) {
                // da el mensaje de guardado...

                $data_search = Mregservicio::find_by_sql("SELECT max(id) as cod_servicio
                        FROM Mregservicios
                        WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                foreach ($data_search as $reg_serv_f) {
                    $mregservicios = $reg_serv_f->cod_servicio;
                }

                $reg_serv_fundo = new Mserviciosxfundo();
                $reg_serv_fundo->mregservicios_id = $mregservicios;
                $reg_serv_fundo->mfundos_id = $mfundos_id;
                $reg_serv_fundo->user_create = $usuario_id;

                if ($reg_serv_fundo->save()) {

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                }
            } else {
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
            }
        }

        echo json_encode($respuesta);

        break;

    //// **************************

    case 'edit':
        session_start();
        $usuario_id = $_SESSION['idusuariox'];

        $activa_edit = $_POST['activa_edit'];



        if ($fecha_servicio == '' && $usuario_id) {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La fecha de Servicio.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            $id_regservicio = intval($id_regservicio);
            $consulta = Mregservicio::find('all', array('conditions' => array('id=?', $id_regservicio)));

            if ($consulta != null) {

                foreach ($consulta as $rs) {

                    if ($rs->estado_servicio == 2 || $rs->estatus_ciclo == 2) {
                        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                                        <h4>
                                        <i class="icon fa fa-warning"></i>
                                        Alerta!
                                        </h4>
                                        El Servicio no puede ser Modificado, esta en "Modo Solo Lectura". Consulte al Administrador del SIstema.
                                        </div>');
                        echo json_encode($respuesta);

                        break;
                    }
                }
                $hoy = date('d-m-Y');
                $reg_servicio = Mregservicio::find($id_regservicio);

                $reg_servicio->f_servicio = $fecha_servicio;
                $reg_servicio->f_probable_palpacion = $fecha_fpp;
                $reg_servicio->mservicios_id = $tipo_servicio;

                if ($activa_edit) {
                    $reg_servicio->nro_servicio = $nro_servicio;
                }
                $reg_servicio->aptitud = $aptitud_animal;
                $reg_servicio->mrazas_id = $raza_animal;
                $reg_servicio->observaciones = $observaciones;
                $reg_servicio->user_modify = $usuario_id;
                $reg_servicio->updated = $hoy;



                if ($reg_servicio->save()) {
                    // da el mensaje de guardado...

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
                }

                //echo json_encode( $respuesta );

            } else {
                //( B ) si exite el nombre verifica el status del registro...

                foreach ($consulta as $st) {

                    $estado_1 = $st->status;
                    $ide = $st->id;
                }

                if ($estado_1 == 1) {
                    //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                    exit();

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
              <div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>

              Registro inactivo . ¿Desea incluir [' . $cod_animal_h . '] ?

                  <div class="modal-footer" >
                    <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                    <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                  </div>
              </div>');
                } else {

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              El Tipo [' . $cod_animal_h . '] ya se encuentra registrado.
              </div>');
                }
                // echo json_encode( $respuesta );
            }
        }
        echo json_encode($respuesta);

        break;

    case 'edit_control':

        $fecha_servicio_date = strtotime($fecha_servicio);
        $fecha_fup_date       = strtotime($fecha_fup);

        if ($fecha_servicio_date < $fecha_fup_date) {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Error en Fechas. La fecha del Servicio  no Puede ser menor a la del último parto
        </div>');
        } else
    if ($fecha_fp == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La fecha de Palpación.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            $id_regservicio = intval($id_regservicio);
            $consulta = Mregservicio::find('all', array('conditions' => array('id=?', $id_regservicio)));

            if ($consulta != null) {


                session_start();
                $usuario_id = $_SESSION['idusuariox'];
                $mfundos_id = isset($_SESSION['mfundos_id']) ? (int)$_SESSION['mfundos_id'] : 0;
                $t_ganaderia = isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0;
                $hoy = date('d-m-Y');

                if ($estado_fisiologico == 1) {
                    if ($pario == 1) {


                        foreach ($consulta as $rs_ciclo) {


                            $cod_hembra = $rs_ciclo->codigo_rgb_id_animal_hembra;

                            $data_control_servicio = Mregservicio::find_by_sql("select  id as id_hembra
                                                                            from Mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra'
                                                                            and estatus_ciclo=1  ;");

                            foreach ($data_control_servicio as $rs_cerrar_ciclo) {

                                $id_hembra = $rs_cerrar_ciclo->id_hembra;

                                $reg_cerrar_servicio = Mregservicio::find($id_hembra);
                                $reg_cerrar_servicio->estatus_ciclo = 2;
                                $reg_cerrar_servicio->estado_servicio = 2;
                                $reg_cerrar_servicio->user_modify = $usuario_id;
                                $reg_cerrar_servicio->updated = $hoy;
                                $reg_cerrar_servicio->save();
                            }
                        }
                    }
                }


                $reg_servicio = Mregservicio::find($id_regservicio);

                // Si el usuario no fijó una fecha de secado, se calcula con la
                // regla única. Si ya viene una fecha (registrada o editada a
                // mano), se respeta: el dato no se recalcula.
                if (trim((string)$fecha_secado) === '' && $estado_fisiologico == 1 && trim((string)$fecha_fup) !== '') {
                    $aptitud_hembra = (int)$reg_servicio->aptitud;
                    $calculo_secado = bovs_calcular_secado_servicio(
                        $reg_servicio->f_servicio,
                        $fecha_fup,
                        $aptitud_hembra,
                        $mfundos_id,
                        $t_ganaderia
                    );
                    $fecha_secado = $calculo_secado['fecha'] ? $calculo_secado['fecha']->format('d-m-Y') : '';
                }

                $reg_servicio->f_ultimo_parto = $fecha_fup;
                $reg_servicio->f_secado = ($fecha_secado !== '') ? $fecha_secado : null;
                $reg_servicio->f_palpacion = $fecha_fp;
                $reg_servicio->medofisiologicos_id = $estado_fisiologico;
                $reg_servicio->f_probable_parto = $fecha_fppt;
                $reg_servicio->parto = $pario;
                $reg_servicio->fecha_parto = $fecha_parto;

                $reg_servicio->nro_terneros = $nro_terneros;
                $reg_servicio->dnp = $dnp;
                $reg_servicio->observaciones = $observaciones;
                $reg_servicio->user_modify = $usuario_id;
                $reg_servicio->updated = $hoy;

                ////////////////7 VALIDANDO LAS ACCIONES PARA CONTROLAR EL CICLO DEL SERVICIO /////////////////////////////

                if ($estado_fisiologico == 4 || $estado_fisiologico == 5) {
                    $reg_servicio->estado_servicio = 2;
                }
                /////////////////////////////////////////////////////////////////////////////////////////////////////////////

                if ($reg_servicio->save()) {
                    // da el mensaje de guardado...

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
                }
            }
        }
        echo json_encode($respuesta);

        break;

    /// BUSCA LOS DATOS DEL MACHO PARA SER COLOCADA EN SERVICIO

    case 'get_buscar_rgb_m':

        $buscar = intval($_POST['buscarx_m']);
        $nro_arete_codigo = ($_POST['rgb_animal_m']);
        $tipo_s = ($_POST['tipo_s']);

        $cadena = ' ';

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
                if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
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
            $cad_estatus = '';
            $nombre_servicio = "";

            if ($tipo_s == 2) {
                $cad_estatus = 'and ' . bovs_estatus_animal_donante_sql(2);
                $nombre_servicio = "INSEMINACION ARTIFICIAL";
            } else  if ($tipo_s == 1) {
                $cad_estatus = 'and estatus_animal=1';
                $nombre_servicio = "MONTA";
            } else  if ($tipo_s == 3) {
                $cad_estatus = 'and ' . bovs_estatus_animal_donante_sql(2);
                $nombre_servicio = "TRANSFERENCIA DE EMBRIONES";
            }

            $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

            // @$data = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre_m,codigo_rgb_id_animal as cod_animal_m from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 and sexo=2 $cadena;");
            @$data = Manimale::find_by_sql("select  id, mestatus_id as estatus, nombre as nombre_m,codigo_rgb_id_animal as cod_animal_m from Manimales where mfundos_id=" . $mfundos_id . " and mestatus_id=1 and sexo=2 $cadena $cad_estatus;");

            if ($data != null) {

                foreach ($data as $rs) {

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
                El Código del Animal NO EXISTE para un Macho Reproductor !.
                </div>');
                    }
                }
            } else {
                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                No existe el MACHO con el ' . $criterio . ' [' . $cod_rgb_animal . '] para el tipo de Servicio [' . $nombre_servicio . ']!!.
                </div>');
            }
        }
        echo json_encode($resp);
        break;

    /// BUSCA LOS DATOS DE LA HEMBRA PARA SER COLOCADA EN SERVICIO

    case 'get_buscar_rgb_h':

        $buscar = intval($_POST['buscarx']);
        $nro_arete_codigo = ($_POST['rgb_animal_h']);

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
                if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
                    $nro = intval($partes[2]);
                } else {
                    $nro = intval($rgb_animal);
                }

                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

                $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
                $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";
                $cadena_3 = "and b.codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

                $criterio = 'Código';
            } else   if ($buscar == 2) {

                $nro_arete = $nro_arete_codigo;

                $cadena = "and nro_arete='$nro_arete' ";
                $cadena_2 = "and nro_arete='$nro_arete' ";
                $cadena_3 = "and b.nro_arete='$nro_arete' ";

                $criterio = 'Nro de Arete';
            }

            $nro_servicio = 0;
            $calcular_nro_servicio = false;
            $validar_servicio = false;
            $nombre_animal = "";

            @$data_animal = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h,f_nacimiento from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 and sexo=1 $cadena;");


            if ($data_animal != null) {

                foreach ($data_animal as $rs_animal) {


                    $fecha_nac = $rs_animal->f_nacimiento;
                    @$fecha_nac = (string)$fecha_nac->format('Y-m-d');
                    $edad = tiempo_t($fecha_nac);

                    if ($edad < 12) {
                        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                 La Edad de la VACA no puede ser Menor de 12 Meses.. EDAD ACTUAL  [ ' . $edad . ' ] meses !!.
                  </div>');
                        echo json_encode($resp);

                        break;
                    } else {

                        $estatus = $rs_animal->estatus;

                        if ($estatus == 1) {
                            $validar_servicio = true;
                            $nombre_animal = $rs_animal->cod_animal_h . '*' . $rs_animal->nombre_h;


                            if ($validar_servicio) {

                                $data_control_servicio = Vcontrolservicio::find_by_sql("select  id, medofisiologicos_id,ciclo_reproductivo,estatus_ciclo,estado_servicio,nro_servicio
                                                                         from Vcontrolservicios where mfundos_id=" . $mfundos_id . "  and estatus_ciclo=1  $cadena_2   ;");


                                if ($data_control_servicio != null) {

                                    foreach ($data_control_servicio as $rs_control_servicio) {

                                        $medofisiologicos_id = $rs_control_servicio->medofisiologicos_id;

                                        $ciclo_reproductivo = $rs_control_servicio->ciclo_reproductivo;

                                        $estado_servicio = $rs_control_servicio->estado_servicio;

                                        $nro_servicio = $rs_control_servicio->nro_servicio;
                                    }

                                    if ($medofisiologicos_id == 4 || $medofisiologicos_id == 5) {

                                        $nro_servicio = $nro_servicio + 1;

                                        $nombre_animal =  $nombre_animal . '*' . $nro_servicio;
                                        $resp = $nombre_animal;
                                        echo json_encode($resp);

                                        break;
                                    } else {
                                        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                                        <h4>
                                        <i class="icon fa fa-ban"></i>
                                        Alerta!
                                        </h4>
                                        El Animal Ya se encuentra en SERVICIO. Debe Dirigirse al Módulo de CONTROL.
                                        </div>');
                                        echo json_encode($resp);

                                        break;
                                    }
                                } else {


                                    $nro_servicio = 1;
                                    $calcular_nro_servicio = false;
                                    $nombre_animal =  $nombre_animal . '*' . $nro_servicio;

                                    echo json_encode($nombre_animal);

                                    break;
                                }
                            }
                        } else {
                            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                                        <h4>
                                        <i class="icon fa fa-ban"></i>
                                        Alerta!
                                        </h4>
                                        El Código del Animal NO SE ENCUENTRA ACTIVO!!.
                                        </div>');
                            echo json_encode($resp);

                            break;
                        }
                    }
                }
            } else {
                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  No existe una HEMBRA con el codigo [' . $cod_rgb_animal . '] !!.
                  </div>');
                echo json_encode($resp);

                break;
            }
        }


        // echo json_encode($resp);

        // break;

        #*******************************************************************************
    case 'search':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

        if ($id_regservicio != null) {

            @$data = Vregservicio::find_by_sql("select id,codigo_servicio,codigo_rgb_id_animal_hembra,nombre_h,
                                                    codigo_rgb_id_animal_macho,nombre_m,f_servicio,mservicios_id,aptitud,raza,nro_servicio,f_probable_palpacion,
                                                    observaciones,estatus_ciclo,estado_servicio,medofisiologicos_id
        from Vregservicios 
        where id=" . $id_regservicio . " and mfundos_id=" . $mfundos_id . " ;");


            if ($data != null) {

                foreach ($data as $rs) {

                    if ($rs->f_probable_palpacion != null) {

                        $fecha_fpp = (string)$rs->f_probable_palpacion->format('d-m-Y');
                    } else {
                        $fecha_fpp = '';
                    }
                    if ($rs->f_servicio != null) {

                        $f_servicio = (string)$rs->f_servicio->format('d-m-Y');
                    } else {
                        $f_servicio = '';
                    }

                    ///////////////////////////////////////////////////////////////////////////

                    $bloquear = 0;

                    if ($rs->estado_servicio == 2 || $rs->estatus_ciclo == 2 || $rs->medofisiologicos_id != 3) {
                        $bloquear = 1;
                    }


                    $data_cuenta_servicios_reg = Vregservicio::find_by_sql("select count (codigo_rgb_id_animal_hembra) as cantidad 
                                                                                    from Vregservicios 
                                                                                            where codigo_rgb_id_animal_hembra='$rs->codigo_rgb_id_animal_hembra' 
                                                                                                and mfundos_id=" . $mfundos_id . " ; ");


                    $activar_edit_nro_servicio = false;
                    if ($data_cuenta_servicios_reg != null) {


                        foreach ($data_cuenta_servicios_reg as $rs_contar) {

                            if ($rs_contar->cantidad == 1) {
                                $activar_edit_nro_servicio = true;
                            }
                        }
                    }


                    $resp = array(
                        'id_regservicio' => $rs->id,
                        'codigo_servicio' => $rs->codigo_servicio,
                        'cod_animal_h' => $rs->codigo_rgb_id_animal_hembra,
                        'nombre_animal_h' => $rs->nombre_h,
                        'cod_animal_m' => $rs->codigo_rgb_id_animal_macho,
                        'nombre_animal_m' => $rs->nombre_m,
                        'fecha_servicio' => $f_servicio,
                        'fecha_fpp' => $fecha_fpp,
                        'tipo_servicio' => $rs->mservicios_id,
                        'aptitud_animal' => $rs->aptitud,
                        'raza_animal' => $rs->raza,
                        'nro_servicio' => $rs->nro_servicio,
                        'observaciones' => $rs->observaciones,
                        'activa_edit' => $activar_edit_nro_servicio,
                        'bloquear' => $bloquear
                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    case 'search_reg_animal_servicio_h':

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
            if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
                $nro = intval($partes[2]);
            } else {
                $nro = intval($rgb_animal);
            }

            $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

            $cadena = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

            $criterio = 'Código';
        } else   if ($buscar == 2) {

            $nro_arete = $nro_arete_codigo;

            $cadena = "and nro_arete='$nro_arete' ";
            $criterio = 'Nro de Arete';
        }

        @$data = Vregservicio::find_by_sql('select id as cod_animal from Vregservicios where mfundos_id=' . $mfundos_id . "  $cadena;");

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

    #*******************************************************************************
    case 'search_control':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        $t_ganaderia = isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0;
        $hoy = date('d-m-Y');

        if ($id_regservicio != null) {

            //  @$data = Vregservicio::find('all', array('conditions' => array('id=?', $id_regservicio)));

            @$data = Vregservicio::find_by_sql('select  id,estado_servicio,estatus_ciclo,dnp,f_ultimo_parto,codigo_rgb_id_animal_hembra,
                                         f_servicio,f_probable_palpacion,fecha_parto,f_palpacion,medofisiologicos_id,aptitud_hembra,
                                         codigo_servicio,nombre_h,codigo_rgb_id_animal_macho,aptitud_h,nombre_m,parto,
                                         nro_terneros,observaciones,f_probable_parto
                                    from Vregservicios where id=' . $id_regservicio .' ;');



            if ($data != null) {

                foreach ($data as $rs) {

                    ///////////////////////////////////////////////////////////////////////////
                    $bloquear = 0;

                    if ($rs->estado_servicio == 2 || $rs->estatus_ciclo == 2) {
                        $bloquear = 1;
                    }


                    if ($rs->dnp != null) {
                        $dnp = $rs->dnp;
                    } else {
                        $dnp = 0;
                    }
                    ////////
                    // Fecha de último parto: se usa la guardada en el servicio
                    // (texto fijo, la imagen pide que no se recalcule); si no
                    // hay, se toma el parto más reciente de nacimientos o del
                    // cierre de ciclo de servicios.
                    $f_ultimo_parto = '';
                    if ($rs->f_ultimo_parto != null) {

                        $f_ultimo_parto = (string)$rs->f_ultimo_parto->format('d-m-Y');
                    } else {

                        $cod_hembra = $rs->codigo_rgb_id_animal_hembra;
                        $buscar_ultimo_parto = Mregnacimiento::find_by_sql("
                                select 
                                    a.f_parto  from Mregnacimientos a 
                                    where 
                                    mfundos_id=" . $mfundos_id . " and
                                    a.codigo_rgb_id_animal_hembra='$cod_hembra' and
                                    a.id=(select max(b.id) 
                                    from Mregnacimientos b
                                    where b.codigo_rgb_id_animal_hembra= '$cod_hembra' )

                                    union 

                                 select 
                                    a.fecha_parto as f_parto  from mregservicios a 
                                    where 
                                    mfundos_id=" . $mfundos_id . " and
                                    a.codigo_rgb_id_animal_hembra='$cod_hembra' and
                                    a.id=(select max(b.id) 
                                    from mregservicios b
                                    where b.codigo_rgb_id_animal_hembra= '$cod_hembra' and estatus_ciclo=2) ;");

                        // Del UNION nos quedamos con el parto MÁS RECIENTE (antes
                        // el último recorrido pisaba el valor y podía quedar uno viejo).
                        $fecha_parto_candidata = null;
                        if ($buscar_ultimo_parto != null) {
                            foreach ($buscar_ultimo_parto as $rs_buscar) {
                                $posible_parto = bovs_fecha_a_objeto($rs_buscar->f_parto);
                                if ($posible_parto && ($fecha_parto_candidata === null || $posible_parto > $fecha_parto_candidata)) {
                                    $fecha_parto_candidata = $posible_parto;
                                }
                            }
                        }
                        if ($fecha_parto_candidata) {
                            $f_ultimo_parto = $fecha_parto_candidata->format('d-m-Y');
                        }
                    }
                    ///////
                    if ($rs->f_servicio != null) {

                        $f_servicio = (string)$rs->f_servicio->format('d-m-Y');
                    } else {
                        $f_servicio = '';
                    }
                    ///////
                    if ($rs->f_probable_palpacion != null) {

                        $fecha_fpp = (string)$rs->f_probable_palpacion->format('d-m-Y');
                    } else {
                        $fecha_fpp = '';
                    }

                    ///////
                    if ($rs->f_probable_parto != null) {

                        $fecha_fppt = (string)$rs->f_probable_parto->format('d-m-Y');
                    } else {
                        $fecha_fppt = '';
                    }
                    ///////
                    if ($rs->fecha_parto != null) {

                        $fecha_parto = (string)$rs->fecha_parto->format('d-m-Y');
                    } else {
                        $fecha_parto = '';
                    }
                    ///////
                    if ($rs->f_palpacion != null) {

                        $fecha_fp = (string)$rs->f_palpacion->format('d-m-Y');
                    } else {
                        $fecha_fp = '';
                    }
                    $control = false;

                    $tiempo = tiempo_t2($f_servicio, $fecha_fppt);

                    if ($fecha_fppt >= $hoy && $rs->medofisiologicos_id == 1) {
                        $control = true;
                    } else  if ($tiempo >= 9 && $rs->medofisiologicos_id == 1) {
                        $control = true;
                    }

                    // Fecha de secado/destete:
                    //  - Si ya está guardada en el servicio se respeta tal cual
                    //    (dato fijo; la imagen pide no recalcular lo registrado).
                    //  - Si no, se aplica la regla única: solo leche/doble
                    //    propósito y, si el servicio fue antes de 180 días del
                    //    parto, secado a los 210 días del servicio.
                    $fecha_destete = '';
                    $secado_guardado = '';
                    $data_secado = Mregservicio::find_by_sql(
                        "SELECT f_secado FROM mregservicios WHERE id = ?",
                        array((int)$rs->id)
                    );
                    if ($data_secado && isset($data_secado[0]->f_secado) && $data_secado[0]->f_secado != null) {
                        $secado_guardado = (string)$data_secado[0]->f_secado->format('d-m-Y');
                    }

                    $calculo_secado = bovs_calcular_secado_servicio(
                        $rs->f_servicio,
                        $f_ultimo_parto,
                        $rs->aptitud_hembra,
                        $mfundos_id,
                        $t_ganaderia
                    );
                    $secado_calculado = $calculo_secado['fecha'] ? $calculo_secado['fecha']->format('d-m-Y') : '';
                    $fecha_destete = ($secado_guardado !== '') ? $secado_guardado : $secado_calculado;

                    $resp = array(
                        'id_regservicio' => $rs->id,
                        'codigo_servicio' => $rs->codigo_servicio,
                        'cod_animal_h' => $rs->codigo_rgb_id_animal_hembra,
                        'nombre_animal_h' => $rs->nombre_h,
                        'cod_animal_m' => $rs->codigo_rgb_id_animal_macho,
                        'aptitud_h' => $rs->aptitud_h,
                        'nombre_animal_m' => $rs->nombre_m,
                        'fecha_servicio' => $f_servicio,
                        'fecha_fup' => $f_ultimo_parto,
                        'fecha_fpp' => $fecha_fpp,
                        'fecha_fp' =>  $fecha_fp,
                        'pario' => $rs->parto,
                        'fecha_parto' => $fecha_parto,
                        'nro_terneros' => $rs->nro_terneros,
                        'estado_fisiologico' => $rs->medofisiologicos_id,
                        'fecha_fppt' => $fecha_fppt,
                        'fecha_destete' => $fecha_destete,
                        'secado_regla' => ($secado_guardado !== '') ? 'GUARDADO' : $calculo_secado['regla'],
                        'secado_mensaje' => ($secado_guardado !== '') ? 'Fecha de secado registrada manualmente.' : $calculo_secado['mensaje'],
                        'dnp' => $dnp,
                        'observaciones' => $rs->observaciones,
                        'control' => $control,
                        'bloquear' => $bloquear



                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    case 'delete':

        if ($record != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];

            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');

            if ($usuario_id != '' && ($rol = 2 || $rol = 1 || $rol = 4)) {

                @$data_buscar = Vregservicio::find_by_sql("select id	  
                                                                from Vregservicios 
                                                                where 
                                                                id=" . $record . " and medofisiologicos_id=1 and parto=1 
                                                                and mfundos_id=" . $mfundos_id . ' ;');

                if ($data_buscar == null) {
                    $regserv = Mregservicio::find($record);
                    $regserv->user_modify = $usuario_id;
                    $regserv->updated = $hoy;
                    $regserv->status = 0;

                    if ($regserv->save()) {

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

    case 'comparar_fechas':

        $f_servicio = ($_POST['fecha_servicio']);
        $f_ultimo_parto = ($_POST['fecha_fup']);
        $fecha_servicio_date  = strtotime($f_servicio);
        $fecha_fup_date       = strtotime($f_ultimo_parto);

        if (($fecha_servicio_date != '') && ($fecha_fup_date != '')) {

            if ($fecha_servicio_date > $fecha_fup_date) {

                $resp = array(
                    'mifecha' => 'Mayor'

                );
            } else {
                $resp = array(
                    'mifecha' => 'Menor'

                );
            }
        } else {

            $resp = array(
                'mifecha' => 'Null'

            );
        }

        echo json_encode($resp);

        break;

    case 'calcula_fecha_secado':

        @$f_servicio = ($_POST['fecha_servicio']);
        @$f_ultimo_parto = ($_POST['fecha_parto']);
        @$aptitud_hembra = ($_POST['aptitud_h']);

        session_start();
        $mfundos_id = isset($_SESSION['mfundos_id']) ? (int)$_SESSION['mfundos_id'] : 0;
        $t_ganaderia = isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0;

        // Misma regla única que el resto del sistema (lib/fecha_secado_helper.php).
        $calculo_secado = bovs_calcular_secado_servicio(
            $f_servicio,
            $f_ultimo_parto,
            $aptitud_hembra,
            $mfundos_id,
            $t_ganaderia
        );

        $resp = array(
            'mifecha' => $calculo_secado['fecha'] ? $calculo_secado['fecha']->format('d-m-Y') : '',
            'regla' => $calculo_secado['regla'],
            'mensaje' => $calculo_secado['mensaje']
        );

        echo json_encode($resp);

        break;

        /// Nuevas Busquedas

}
//end switch

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

function tiempo_t2($f_servicio, $fecha_fppt)
{
    $fecha_f = $fecha_fppt;
    $fecha_i = $f_servicio;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);
    $meses = ($dias / 30);
    $meses = (int)$meses;

    return $meses;
}

function tiempo_t3($f_servicio, $f_ultimo_parto)
{
    $fecha_f = $f_ultimo_parto;
    $fecha_i = $f_servicio;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);

    return $dias;
}

/**
 * Caja de aviso usada por el diagnóstico de hembras del registro de servicio.
 * Los estilos (.bovs-aviso-ciclo) viven en views/servicios/frm_registrar.php.
 */
function aviso_diagnostico($tipo, $icono, $texto)
{
    $tipos = array('alerta', 'info');
    $tipo = in_array($tipo, $tipos, true) ? $tipo : 'info';

    return '<div class="bovs-aviso-ciclo bovs-aviso-ciclo--' . $tipo . '">'
        . '<i class="fa ' . $icono . '"></i>'
        . '<div>' . $texto . '</div>'
        . '</div>';
}