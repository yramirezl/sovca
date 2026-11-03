<?php
/**
 * =============================================================================
 * SOVCA · Categorías de animales: cadena de edades y validación de rangos
 * -----------------------------------------------------------------------------
 * PROBLEMA
 *   El catálogo de categorías se define con dos edades (mínima y máxima en
 *   meses) y NO se validaba nada más allá de que la máxima fuera mayor que la
 *   mínima. Cada fundo pudo crear su propia lista y el resultado, en producción,
 *   es una cadena rota:
 *
 *       fundo 002 · Hembras            fundo 002 · Machos
 *       OVEJAS         0 – 5000        CORDERO  0 – 8
 *       CORDERAS       0 – 8           BORREGO  8 – 24
 *       BORREGAS       7 – 10          BORREGO  8 – 36   ← repetida y cruzada
 *       BORREGA        10 – 14         CARNERO  14 – 70
 *       BORREGA DE VIENTRE 14 – 24     CEBA # 1 25 – 50
 *
 *   Con «OVEJAS 0–5000» cualquier oveja cae en OVEJAS y en BORREGA a la vez, y
 *   la categoría del animal depende del orden en que se consulte.
 *
 * QUÉ HACE ESTE HELPER
 *   · bovs_categorias_cadena()  → categorías que APLICAN al fundo (las globales
 *     que no personalizó + las suyas) para un sexo, ordenadas por edad.
 *   · bovs_analizar_rango_categoria() → errores y avisos de un rango nuevo:
 *       - error: rango inválido (máx ≤ mín) o duplicado exacto;
 *       - aviso: se cruza con otra categoría (indica hasta dónde llega la
 *         anterior y desde qué mes conviene empezar);
 *       - aviso: deja un hueco de meses sin categoría.
 *
 *   El formulario usa el mismo análisis en vivo (acción `validar_rangos`) y el
 *   controlador lo vuelve a aplicar al guardar, así el aviso no se puede
 *   esquivar.
 * =============================================================================
 */

if (!function_exists('bovs_categorias_cadena')) {

    /**
     * Categorías vigentes para un fundo y sexo, ordenadas por edad mínima.
     *
     * Se excluyen las categorías globales que el fundo ya personalizó
     * (`id_padre`), igual que hacen get_categoria_ingreso / get_categoria_futura
     * y el listado del módulo: si no, la global y su copia se cruzarían siempre.
     *
     * @param int $mfundos_id      0 = solo catálogo global
     * @param int $tipo_ganaderia  0 = sin filtrar
     * @param int $sexo            1 hembra · 2 macho
     * @param int $excluir_id      categoría que se está editando
     * @return array
     */
    function bovs_categorias_cadena($mfundos_id, $tipo_ganaderia, $sexo, $excluir_id = 0)
    {
        $fundo   = (int) $mfundos_id;
        $tipo    = (int) $tipo_ganaderia;
        $sexo    = (int) $sexo;
        $excluir = (int) $excluir_id;

        if ($sexo <= 0 || !class_exists('Mcategoria')) {
            return array();
        }

        $sql = "SELECT c.id, c.nombre, c.edadminima, c.edadmaxima, c.sexo, c.mfundos_id
                  FROM mcategorias c
                 WHERE c.status = 1
                   AND c.sexo = " . $sexo;

        if ($tipo > 0) {
            $sql .= " AND c.mtipoganaderias_id = " . $tipo;
        }

        if ($fundo > 0) {
            $sql .= " AND (c.mfundos_id = " . $fundo . " OR c.mfundos_id = 0)";
            $sql .= " AND c.id NOT IN (SELECT b.id_padre FROM mcategorias b
                                        WHERE b.mfundos_id = " . $fundo . " AND b.id_padre > 0)";
        } else {
            $sql .= " AND c.mfundos_id = 0";
        }

        if ($excluir > 0) {
            $sql .= " AND c.id <> " . $excluir;
        }

        $sql .= " ORDER BY c.edadminima ASC, c.edadmaxima ASC, c.id ASC";

        $filas = @Mcategoria::find_by_sql($sql);

        return $filas ? $filas : array();
    }
}

if (!function_exists('bovs_analizar_rango_categoria')) {

    /**
     * Revisa un rango de edad contra la cadena de categorías del fundo.
     *
     * @param int    $mfundos_id
     * @param int    $tipo_ganaderia
     * @param int    $sexo
     * @param int    $edadminima
     * @param int    $edadmaxima
     * @param int    $excluir_id     id de la categoría que se edita (0 = nueva)
     * @param string $nombre         nombre propuesto (para avisar de repetidos)
     * @return array{errores:array, avisos:array, sugerencia_min:int, cadena:array}
     */
    function bovs_analizar_rango_categoria($mfundos_id, $tipo_ganaderia, $sexo, $edadminima, $edadmaxima, $excluir_id = 0, $nombre = '')
    {
        $errores  = array();
        $avisos   = array();
        $cadena   = array();
        $sugerida = 0;

        $sexo = (int) $sexo;
        $min  = (int) $edadminima;
        $max  = (int) $edadmaxima;
        $nombre = strtoupper(trim((string) $nombre));

        if ($sexo <= 0) {
            $errores[] = 'Indique el sexo de la categoría.';
        }
        if ($min < 0) {
            $errores[] = 'La edad mínima no puede ser negativa.';
        }
        if ($max <= $min) {
            $errores[] = 'La edad máxima (' . $max . ' meses) debe ser mayor que la mínima (' . $min . ' meses).';
        }

        if (count($errores) === 0) {
            $cadena = bovs_categorias_cadena($mfundos_id, $tipo_ganaderia, $sexo, $excluir_id);

            /* Fin de la categoría inmediatamente anterior (por edad). */
            $fin_anterior = -1;
            $nombre_anterior = '';

            foreach ($cadena as $c) {
                $cmin = (int) $c->edadminima;
                $cmax = (int) $c->edadmaxima;

                if ($cmin === $min && $cmax === $max) {
                    $errores[] = 'Ya existe la categoría «' . $c->nombre . '» con exactamente este rango ('
                        . $cmin . '–' . $cmax . ' meses).';
                    continue;
                }

                /* Solapamiento: dos rangos se cruzan si cada uno empieza antes
                   de que termine el otro. */
                if ($min < $cmax && $cmin < $max) {
                    if ($cmin <= $min && $cmax >= $max) {
                        $avisos[] = '«' . $c->nombre . '» abarca de ' . $cmin . ' a ' . $cmax
                            . ' meses y engloba a esta categoría: conviene corregir primero ese rango.';
                    } else {
                        $avisos[] = '«' . $c->nombre . '» ya cubre de ' . $cmin . ' a ' . $cmax
                            . ' meses: este rango se cruza con ella.';
                    }
                }

                if ($nombre !== '' && $cmin !== $min && strcasecmp(trim((string) $c->nombre), $nombre) === 0) {
                    $avisos[] = 'Ya existe otra categoría llamada «' . $c->nombre . '» ('
                        . $cmin . '–' . $cmax . ' meses) para este sexo.';
                }

                /* «Anterior» = la categoría que termina más cerca del inicio
                   nuevo. Se descartan las que se salen por arriba (una categoría
                   mal definida tipo 0–5000 no sirve de referencia). */
                if ($cmin < $min && $cmax <= $max && $cmax > $fin_anterior) {
                    $fin_anterior    = $cmax;
                    $nombre_anterior = (string) $c->nombre;
                }
            }

            if ($fin_anterior > 0 && $min > $fin_anterior) {
                $avisos[] = 'Queda un hueco de ' . $fin_anterior . ' a ' . $min
                    . ' meses sin ninguna categoría asignada.';
            }

            /* Recomendación: empezar donde termina la anterior. Las categorías
               de este sistema son contiguas (0–7, 7–18, 18–24, 24–…). */
            if ($fin_anterior >= 0 && $min < $fin_anterior) {
                $sugerida = $fin_anterior;
                array_unshift($avisos, 'La categoría anterior («' . $nombre_anterior . '») llega hasta '
                    . $fin_anterior . ' meses; se recomienda que esta empiece en ' . $fin_anterior . ' meses o más.');
            }
        }

        return array(
            'errores'        => $errores,
            'avisos'         => $avisos,
            'sugerencia_min' => $sugerida,
            'cadena'         => $cadena,
        );
    }
}

if (!function_exists('bovs_categoria_cadena_json')) {

    /**
     * La cadena en formato simple, para pintarla en el formulario.
     *
     * @param array $cadena
     * @param int   $id_actual
     * @return array
     */
    function bovs_categoria_cadena_json($cadena, $id_actual = 0)
    {
        $salida = array();
        $id_actual = (int) $id_actual;

        foreach ((array) $cadena as $c) {
            $salida[] = array(
                'id'      => (int) $c->id,
                'nombre'  => (string) $c->nombre,
                'min'     => (int) $c->edadminima,
                'max'     => (int) $c->edadmaxima,
                'propia'  => ((int) $c->id === $id_actual),
                'global'  => ((int) $c->mfundos_id === 0),
            );
        }

        return $salida;
    }
}

if (!function_exists('bovs_mensaje_errores_categoria')) {

    /**
     * HTML con los errores que impiden guardar la categoría.
     *
     * @param  array $errores
     * @return string  Cadena vacía cuando no hay errores.
     */
    function bovs_mensaje_errores_categoria($errores)
    {
        if (empty($errores)) {
            return '';
        }

        $items = '';
        foreach ($errores as $e) {
            $items .= '<li>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        return '<div class="alert alert-danger alert-dismissable">'
            . '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
            . '<h4><i class="icon fa fa-ban"></i> No se puede guardar la categoría</h4>'
            . '<ul class="cat-rangos__mensaje">' . $items . '</ul>'
            . '</div>';
    }
}

if (!function_exists('bovs_mensaje_avisos_categoria')) {

    /**
     * HTML con las advertencias de una categoría ya guardada.
     *
     * @param  array $avisos
     * @return string  Cadena vacía cuando no hay avisos.
     */
    function bovs_mensaje_avisos_categoria($avisos)
    {
        if (empty($avisos)) {
            return '';
        }

        $items = '';
        foreach ($avisos as $a) {
            $items .= '<li>' . htmlspecialchars((string) $a, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        return '<div class="alert alert-warning alert-dismissable">'
            . '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
            . '<h4><i class="icon fa fa-warning"></i> Revisa los rangos de edad</h4>'
            . '<ul class="cat-rangos__mensaje">' . $items . '</ul>'
            . '<p class="cat-rangos__pie">Puedes corregir la edad mínima y volver a guardar.</p>'
            . '</div>';
    }
}

if (!function_exists('bovs_respuesta_advertencia_categoria')) {

    /**
     * Respuesta para que el formulario pregunte ANTES de guardar.
     *
     * El controlador no bloquea: devuelve `resultado = advertencia` con el
     * detalle y el formulario muestra un diálogo con dos salidas:
     *   · «Corregir»  → vuelve a las edades;
     *   · «Guardar de todos modos» → reenvía la petición con forzar = 1.
     *
     * @param  array $avisos
     * @return array
     */
    function bovs_respuesta_advertencia_categoria($avisos)
    {
        $items = '';
        foreach ((array) $avisos as $a) {
            $items .= '<li>' . htmlspecialchars((string) $a, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        return array(
            'resultado' => 'advertencia',
            'avisos'    => array_values((array) $avisos),
            'mensaje'   => '<div class="cat-rangos__dialogo">'
                . '<p>Este rango de edad se cruza con otras categorías del mismo sexo:</p>'
                . '<ul class="cat-rangos__mensaje">' . $items . '</ul>'
                . '<p class="cat-rangos__pie">Si guardas así, un animal puede caer en dos categorías '
                . 'a la vez. Puedes corregir las edades o guardar de todos modos.</p>'
                . '</div>',
        );
    }
}

if (!function_exists('bovs_categoria_padre_global')) {

    /**
     * Id de la categoría GENERAL con el mismo nombre, sexo y ganadería.
     *
     * Cuando el productor crea una categoría con un nombre que ya existe en el
     * catálogo general, el registro se guarda como personalización del fundo
     * (`id_padre`): la general deja de aplicarle y no quedan dos categorías con
     * el mismo nombre. La validación debe excluir esa general para no avisar de
     * un cruce con la categoría que se está reemplazando.
     *
     * @param int    $mfundos_id 0 = no aplica (el admin crea en el global)
     * @param string $nombre
     * @param int    $sexo
     * @param int    $tipo_ganaderia
     * @return int  0 si no hay coincidencia
     */
    function bovs_categoria_padre_global($mfundos_id, $nombre, $sexo, $tipo_ganaderia)
    {
        $fundo  = (int) $mfundos_id;
        $sexo   = (int) $sexo;
        $tipo   = (int) $tipo_ganaderia;
        $nombre = strtoupper(trim((string) $nombre));

        if ($fundo <= 0 || $sexo <= 0 || $nombre === '' || !class_exists('Mcategoria')) {
            return 0;
        }

        $esc = str_replace("'", "''", $nombre);

        $filas = @Mcategoria::find_by_sql(
            "SELECT id FROM mcategorias
              WHERE mfundos_id = 0
                AND status = 1
                AND sexo = $sexo"
                . ($tipo > 0 ? " AND mtipoganaderias_id = $tipo" : '')
                . " AND UPPER(BTRIM(nombre)) = UPPER(BTRIM('$esc'))
              ORDER BY id
              LIMIT 1"
        );

        return ($filas && count($filas) > 0) ? (int) $filas[0]->id : 0;
    }
}
