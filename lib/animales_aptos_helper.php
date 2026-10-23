<?php
/**
 * Listados de animales APTOS para los formularios de eventos.
 * ---------------------------------------------------------------------------
 * Devuelve las filas que espera el componente js/servicio-selector.js:
 *
 *   {codigo, nombre, nro_arete, condicion, edad_meses, nro_servicio, disponible,
 *    etiqueta, etiqueta_clase, detalle}
 *
 * Cada módulo pide sólo los animales que pueden pasar su propia validación,
 * de modo que el buscador nunca ofrece un animal que luego será rechazado:
 *
 *   · controlsanitario  → animales vivos/activos del fundo.
 *   · prodleche         → hembras activas con aptitud LECHE o DOBLE PROPÓSITO,
 *                         con este orden de prioridad:
 *                           1) EN LACTANCIA (según el último parto registrado en
 *                              nacimientos y su fecha de secado);
 *                           2) POST-LACTANCIA (secadas o con parto recién
 *                              vencido, aún podrían estar produciendo);
 *                           3) POR APTITUD (sin parto registrado o con parto
 *                              viejo, por si faltó registrarla en lactancia).
 *   · consecutivo_peso  → animales vivos/activos del fundo.
 *   · desincorporacion  → animales vivos/activos que aún NO están
 *                         desincorporados y que no tienen crías pendientes.
 *
 * Compatible con PHP 7.4 y 8.3. Usa ActiveRecord\ConnectionManager igual que
 * lib/multifundo_helper.php.
 */

if (!function_exists('bovs_aptos_q')) {
    /**
     * Ejecuta un SELECT y devuelve un arreglo de filas asociativas.
     */
    function bovs_aptos_q($sql) {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return array();
        }

        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) {
                return array();
            }

            $sth = $conn->query($sql);
            if (!$sth) {
                return array();
            }

            if (method_exists($sth, 'fetchAll')) {
                $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
                return $rows ? $rows : array();
            }

            if (is_resource($sth)) {
                $rows = array();
                while ($r = pg_fetch_assoc($sth)) {
                    $rows[] = $r;
                }
                return $rows;
            }

            return array();
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('bovs_aptos_ppl_dias')) {
    /**
     * Duración configurada del periodo de producción de leche (PPL).
     * Usa el periodo del fundo si existe; si no, el global (mfundos_id = 0).
     */
    function bovs_aptos_ppl_dias($mfundos_id, $t_ganaderia) {
        $mfundos_id  = intval($mfundos_id);
        $t_ganaderia = intval($t_ganaderia);

        $sql = "select dias_gestacion
                  from mperiodos
                 where status = 1
                   and codper_bov = 'PPL'
                   and mtipoganaderias_id = " . $t_ganaderia . "
                   and mfundos_id in (" . $mfundos_id . ", 0)
                 order by case when mfundos_id = " . $mfundos_id . " then 0 else 1 end, id desc
                 limit 1";

        $rows = bovs_aptos_q($sql);
        $dias = ($rows && isset($rows[0]['dias_gestacion'])) ? intval($rows[0]['dias_gestacion']) : 0;

        return ($dias > 0) ? $dias : 305;
    }
}

if (!function_exists('bovs_aptos_hace_texto')) {
    /**
     * "hace 12 días", "hace 3 meses", "hoy"…
     */
    function bovs_aptos_hace_texto($dias) {
        $dias = intval($dias);

        if ($dias <= 0) {
            return 'hoy';
        }

        if ($dias === 1) {
            return 'hace 1 día';
        }

        if ($dias < 60) {
            return 'hace ' . $dias . ' días';
        }

        $meses = (int)round($dias / 30);

        return 'hace ' . $meses . ($meses === 1 ? ' mes' : ' meses');
    }
}

if (!function_exists('bovs_animales_aptos_listar')) {
    /**
     * @param string $modulo controlsanitario | prodleche | consecutivo_peso | desincorporacion
     * @return array
     */
    function bovs_animales_aptos_listar($modulo) {
        $mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        if ($mfundos_id <= 0) {
            return array();
        }

        $es_prodleche = ($modulo === 'prodleche');
        $t_ganaderia  = isset($_SESSION['tipo_ganaderia']) ? intval($_SESSION['tipo_ganaderia']) : 0;

        $edad_sql = "case when a.f_nacimiento is null then 0
                           else (extract(year from age(current_date, a.f_nacimiento)) * 12
                                 + extract(month from age(current_date, a.f_nacimiento)))::int
                      end";

        /* Para producción de leche: último parto registrado en nacimientos y la
           fecha de secado de ese mismo parto. */
        $campos_lactancia = '';
        $join_lactancia   = '';

        if ($es_prodleche) {
            $campos_lactancia = ",
                       lp.f_parto::date                          as f_parto,
                       lp.f_secado::date                         as f_secado,
                       case when lp.f_parto is null then null
                            else (current_date - lp.f_parto::date) end as dias_parto,
                       case when lp.f_secado is null then null
                            else (current_date - lp.f_secado::date) end as dias_secado";

            $join_lactancia = "
                  left join lateral (
                        select r.f_parto, r.f_secado
                          from mregnacimientos r
                         where r.mfundos_id = a.mfundos_id
                           and r.codigo_rgb_id_animal_hembra = a.codigo_rgb_id_animal
                           and r.status = 1
                           and r.f_parto is not null
                         order by r.f_parto desc, r.id desc
                         limit 1
                  ) lp on true";
        }

        $sql = "select a.codigo_rgb_id_animal            as codigo,
                       coalesce(a.nro_arete, '')          as nro_arete,
                       coalesce(a.nombre, '')             as nombre,
                       $edad_sql                          as edad_meses,
                       a.aptitud                          as aptitud
                       $campos_lactancia
                  from manimales a
                  $join_lactancia
                 where a.mfundos_id = " . $mfundos_id . "
                   and a.status = 1
                   and a.mestatus_id = 1";

        switch ($modulo) {

            case 'prodleche':
                /* Misma condición de mprodleche_controller (get_buscar_rgb_h):
                   hembras con aptitud de producción de leche. */
                $sql .= " and a.sexo = 1
                          and a.aptitud in (2, 3)";
                break;

            case 'desincorporacion':
                $sql .= " and a.mtipoganaderias_id = " . $t_ganaderia;

                /* Todavía no desincorporado ACTIVO (la tabla guarda un registro
                   por evento; los anulados con status = 0 no cuentan). */
                $sql .= " and not exists (
                                select 1
                                  from mdesincorporacionanimales d
                                 where d.mfundos_id = a.mfundos_id
                                   and d.codigo_rgb_id_animal = a.codigo_rgb_id_animal
                                   and d.status = 1
                          )";

                /* Misma regla de mdesincorporacion_controller (case add): una
                   hembra con crías vivas sin ficha no se puede desincorporar. */
                $sql .= " and not exists (
                                select 1
                                  from mregnacimientos r
                                 where r.mfundos_id = a.mfundos_id
                                   and r.codigo_rgb_id_animal_hembra = a.codigo_rgb_id_animal
                                   and r.status = 1
                                   and r.f_parto is not null
                                   and r.estatus_nacido = 1
                                   and r.mestatus_id = 1
                                   and r.fichado = false
                          )";
                break;

            case 'controlsanitario':
            case 'consecutivo_peso':
            default:
                /* Animales vivos/activos del fundo (regla de sus get_buscar_rgb) */
                break;
        }

        $sql .= " order by a.nombre, a.codigo_rgb_id_animal;";

        $ppl_dias    = $es_prodleche ? bovs_aptos_ppl_dias($mfundos_id, $t_ganaderia) : 305;
        $gracia_dias = 90; /* "acaba de terminar la lactancia": hasta 90 días después */

        $filas = array();

        foreach (bovs_aptos_q($sql) as $r) {
            $aptitud = intval($r['aptitud']);

            $fila = array(
                'codigo'       => $r['codigo'],
                'nro_arete'    => ($r['nro_arete'] === null) ? '' : $r['nro_arete'],
                'nombre'       => ($r['nombre'] === null) ? '' : $r['nombre'],
                'edad_meses'   => ($r['edad_meses'] === null) ? '' : intval($r['edad_meses']),
                'condicion'    => 'Activo',
                'disponible'   => 1,
                'nro_servicio' => 0
            );

            if (!$es_prodleche) {
                $filas[] = $fila;
                continue;
            }

            /* ---------------- Clasificación por lactancia ---------------- */
            $dias_parto  = ($r['dias_parto'] === null) ? null : intval($r['dias_parto']);
            $dias_secado = ($r['dias_secado'] === null) ? null : intval($r['dias_secado']);

            $tiene_parto = ($dias_parto !== null);
            $secada      = ($tiene_parto && $dias_secado !== null
                            && $dias_secado >= 0 && $dias_secado <= $dias_parto);

            $aptitud_txt = ($aptitud === 2) ? 'Aptitud leche' : 'Doble propósito';

            if (!$tiene_parto) {
                $grupo    = 2;
                $etiqueta = $aptitud_txt;
                $clase    = 'is-apta';
                $detalle  = 'Sin parto registrado';
            } elseif ($secada) {
                if ($dias_secado <= $gracia_dias) {
                    $grupo    = 1;
                    $etiqueta = 'Post-lactancia';
                    $clase    = 'is-post';
                    $detalle  = 'Secada ' . bovs_aptos_hace_texto($dias_secado);
                } else {
                    $grupo    = 2;
                    $etiqueta = $aptitud_txt;
                    $clase    = 'is-apta';
                    $detalle  = 'Secada ' . bovs_aptos_hace_texto($dias_secado);
                }
            } elseif ($dias_parto <= $ppl_dias) {
                $grupo    = 0;
                $etiqueta = 'En lactancia';
                $clase    = 'is-ok';
                $detalle  = 'Parto ' . bovs_aptos_hace_texto($dias_parto);
            } elseif ($dias_parto <= ($ppl_dias + $gracia_dias)) {
                $grupo    = 1;
                $etiqueta = 'Post-lactancia';
                $clase    = 'is-post';
                $detalle  = 'Parto ' . bovs_aptos_hace_texto($dias_parto);
            } else {
                $grupo    = 2;
                $etiqueta = $aptitud_txt;
                $clase    = 'is-apta';
                $detalle  = 'Parto ' . bovs_aptos_hace_texto($dias_parto);
            }

            $fila['condicion']      = $etiqueta;
            $fila['etiqueta']       = $etiqueta;
            $fila['etiqueta_clase'] = $clase;
            $fila['detalle']        = $detalle;
            $fila['_grupo']         = $grupo;
            $fila['_f_parto']       = ($r['f_parto'] === null) ? '' : $r['f_parto'];

            $filas[] = $fila;
        }

        /* Prioridad: en lactancia → post-lactancia → por aptitud.
           Dentro de cada grupo, el parto más reciente primero. */
        if ($es_prodleche) {
            usort($filas, function ($a, $b) {
                $ga = isset($a['_grupo']) ? $a['_grupo'] : 2;
                $gb = isset($b['_grupo']) ? $b['_grupo'] : 2;

                if ($ga !== $gb) {
                    return ($ga < $gb) ? -1 : 1;
                }

                $fa = isset($a['_f_parto']) ? $a['_f_parto'] : '';
                $fb = isset($b['_f_parto']) ? $b['_f_parto'] : '';
                $cmp = strcmp($fb, $fa);

                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a['nombre'], $b['nombre']);
            });

            foreach ($filas as $i => $f) {
                unset($filas[$i]['_grupo'], $filas[$i]['_f_parto']);
            }
        }

        return $filas;
    }
}
