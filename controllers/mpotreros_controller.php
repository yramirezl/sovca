<?php
require_once '../models/Mpotrero.php';
require_once '../models/Mlote.php';
date_default_timezone_set('America/Caracas');

@$action              = ($_POST['action']);
@$id                  = ($_POST['id_potrero']);
@$record              = ($_POST['record']);
@$nombre_potrero      = strtoupper($_POST['nombre_potrero']);
@$descripcion         = ($_POST['descripcion']);
@$hectareas           = ($_POST['hectareas']);
@$capacidad_maxima    = ($_POST['capacidad_maxima']);
@$tipo_pasto          = ($_POST['tipo_pasto']);

switch ($action) {

    /* ============================================================
       ALTA
       ============================================================ */
    case 'add':

        if ($nombre_potrero == '') {
            $respuesta = array('resultado' => 'error', 'mensaje' => 'Ingrese el nombre del potrero.');
        } elseif ($hectareas != '' && !is_numeric($hectareas)) {
            $respuesta = array('resultado' => 'error', 'mensaje' => 'Las hectáreas deben ser un valor numérico.');
        } elseif ($capacidad_maxima != '' && !is_numeric($capacidad_maxima)) {
            $respuesta = array('resultado' => 'error', 'mensaje' => 'La capacidad máxima debe ser un número entero.');
        } else {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $hoy = date('d-m-Y');

            $existe = Mpotrero::find('all', array(
                'conditions' => array('nombre = ? AND mfundos_id = ?', $nombre_potrero, $mfundos_id)
            ));

            if ($existe != null) {
                $respuesta = array('resultado' => 'error', 'mensaje' => 'Ya existe un potrero con ese nombre en este fundo.');
            } else {

                $potrero = new Mpotrero();
                $potrero->nombre            = $nombre_potrero;
                $potrero->descripcion       = $descripcion;
                $potrero->hectareas         = $hectareas !== '' ? floatval($hectareas) : null;
                $potrero->capacidad_maxima  = $capacidad_maxima !== '' ? intval($capacidad_maxima) : null;
                $potrero->tipo_pasto        = $tipo_pasto;
                $potrero->mfundos_id        = $mfundos_id;
                $potrero->status            = 1;
                $potrero->user_create       = $usuario_id;
                $potrero->created           = $hoy;

                if ($potrero->save()) {
                    $respuesta = array('resultado' => 'ok', 'mensaje' => 'Potrero registrado exitosamente.');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => 'Error al registrar el potrero.');
                }
            }
        }
        echo json_encode($respuesta);
        break;

    /* ============================================================
       EDICION
       ============================================================ */
    case 'edit':

        if ($nombre_potrero == '') {
            $respuesta = array('resultado' => 'error', 'mensaje' => 'Ingrese el nombre del potrero.');
        } else {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $hoy = date('d-m-Y');

            $existe = Mpotrero::find('all', array(
                'conditions' => array('nombre = ? AND mfundos_id = ? AND id != ?', $nombre_potrero, $mfundos_id, $id)
            ));

            if ($existe != null) {
                $respuesta = array('resultado' => 'error', 'mensaje' => 'Ya existe otro potrero con ese nombre.');
            } else {

                $potrero = Mpotrero::find($id);
                $potrero->nombre            = $nombre_potrero;
                $potrero->descripcion       = $descripcion;
                $potrero->hectareas         = $hectareas !== '' ? floatval($hectareas) : null;
                $potrero->capacidad_maxima  = $capacidad_maxima !== '' ? intval($capacidad_maxima) : null;
                $potrero->tipo_pasto        = $tipo_pasto;
                $potrero->user_modify       = $usuario_id;
                $potrero->updated           = $hoy;

                if ($potrero->save()) {
                    $respuesta = array('resultado' => 'ok', 'mensaje' => 'Potrero actualizado exitosamente.');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => 'Error al actualizar el potrero.');
                }
            }
        }
        echo json_encode($respuesta);
        break;

    /* ============================================================
       ELIMINACION (logica)
       ============================================================ */
    case 'delete':

        if ($record != null) {
            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');

            if ($usuario_id == '' || !in_array(intval($rol), array(1, 2, 4))) {
                echo 'No tiene permisos para eliminar este registro.';
                break;
            }

            /* Verificar que no haya animales asignados */
            require_once '../models/Manimale.php';
            $animales = Manimale::find_by_sql("
                SELECT COUNT(*) AS c FROM manimales
                WHERE status = 1 AND mfundos_id = {$mfundos_id} AND mpotreros_id = {$record}
            ");
            $cant = 0;
            if (is_array($animales) && count($animales) > 0) {
                $cant = intval($animales[0]->c);
            }

            if ($cant > 0) {
                echo 'No se puede eliminar: hay ' . $cant . ' animal(es) asignados a este potrero.';
                break;
            }

            $potrero = Mpotrero::find($record);
            $potrero->status      = 0;
            $potrero->user_modify = $usuario_id;
            $potrero->updated     = $hoy;

            if ($potrero->save()) {
                echo 'Potrero eliminado exitosamente.';
            } else {
                echo 'Error al eliminar el potrero.';
            }
        }
        break;

    /* ============================================================
       BUSCAR UNO (para editar)
       ============================================================ */
    case 'search':

        if ($id != null) {
            $data = Mpotrero::find('all', array('conditions' => array('id = ?', $id)));
            if ($data != null) {
                foreach ($data as $rs) {
                    $resp = array(
                        'id'               => $rs->id,
                        'nombre'           => $rs->nombre,
                        'descripcion'      => $rs->descripcion,
                        'hectareas'        => $rs->hectareas,
                        'capacidad_maxima' => $rs->capacidad_maxima,
                        'tipo_pasto'       => $rs->tipo_pasto
                    );
                }
                echo json_encode($resp);
            }
        }
        break;

    /* ============================================================
       LISTAR PARA SELECTS
       ============================================================ */
    case 'get_potreros':

        @session_start();
        @$mfundos_id = $_SESSION['mfundos_id'];
        $mfundos_id = intval($mfundos_id);

        $data = Mpotrero::find_by_sql("
            SELECT id, nombre FROM mpotreros
            WHERE status = 1 AND mfundos_id = {$mfundos_id}
            ORDER BY nombre ASC
        ");

        if ($data != null) {
            $resp = '<option value="">--</option>';
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
            }
        } else {
            $resp = '<option value="">No hay potreros registrados</option>';
        }

        echo $resp;
        break;

    /* ============================================================
       ELIMINAR ANIMAL DE POTRERO (para desasignar)
       ============================================================ */
    case 'desasignar_animal':

        if ($record != null) {
            require_once '../models/Manimale.php';
            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $hoy = date('d-m-Y');

            $animal = Manimale::find($record);
            if ($animal && $animal->mfundos_id == $mfundos_id) {
                $animal->mpotreros_id = null;
                $animal->user_modify  = $usuario_id;
                $animal->updated      = $hoy;
                if ($animal->save()) {
                    echo 'Animal desasignado del potrero.';
                } else {
                    echo 'Error al desasignar el animal.';
                }
            }
        }
        break;
}
?>