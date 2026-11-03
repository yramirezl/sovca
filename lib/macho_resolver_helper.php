<?php
/**
 * =============================================================================
 * SOVCA · Resolver del código del padre (macho) de un nacimiento
 * -----------------------------------------------------------------------------
 * PROBLEMA QUE RESUELVE
 *   El alta y la edición de nacimientos solo comprobaban que el campo del
 *   padre no estuviera vacío. Si el operador escribía el NÚMERO DE ARETE y la
 *   búsqueda fallaba (toro dado de baja, fuera de servicio o simplemente no
 *   pulsó «Buscar»), ese número se guardaba tal cual en
 *   `mregnacimientos.codigo_rgb_id_animal_macho`.
 *
 *   Resultado: el listado mostraba un "código fantasma" — en el fundo 063
 *   aparecía «2005», que en realidad es el arete del toro SOVCA-063-078 —
 *   mientras que al editar el registro el padre salía vacío, porque no existe
 *   ningún animal con ese código y la vista devolvía NULL en el nombre.
 *
 * QUÉ HACE
 *   Dado el valor recibido y el fundo, devuelve el CÓDIGO REAL del macho:
 *
 *     1. Si ya es el código de un macho activo  -> se usa tal cual.
 *     2. Si es el nro de arete de UN solo macho -> se convierte a su código.
 *     3. En cualquier otro caso                 -> 'encontrado' = false.
 *
 *   No se resuelve por nombre: dos toros pueden llamarse igual y se
 *   asignaría el padre equivocado.
 * =============================================================================
 */

if (!function_exists('bovs_resolver_codigo_macho')) {

    /**
     * @param  string $codigo     Código o número de arete tal como llegó.
     * @param  int    $mfundos_id Fundo del usuario.
     * @return array{encontrado: bool, codigo: string, original: string,
     *               origen: string, nombre: string, nro_arete: string}
     */
    function bovs_resolver_codigo_macho($codigo, $mfundos_id)
    {
        $res = array(
            'encontrado' => false,
            'codigo'     => '',
            'original'   => trim((string) $codigo),
            'origen'     => '',
            'nombre'     => '',
            'nro_arete'  => '',
        );

        $fundo   = (int) $mfundos_id;
        $buscado = trim((string) $codigo);

        if ($buscado === '' || $fundo <= 0 || !class_exists('Manimale')) {
            return $res;
        }

        /* Comillas simples escapadas: el valor puede llegar de un formulario
           o de la sincronización offline. */
        $esc = str_replace(array('\\', "'"), array('\\\\', "''"), $buscado);

        $asignar = function ($fila, $origen) use (&$res) {
            $res['encontrado'] = true;
            $res['codigo']     = (string) $fila->codigo_rgb_id_animal;
            $res['origen']     = $origen;
            $res['nombre']     = (string) $fila->nombre;
            $res['nro_arete']  = (string) $fila->nro_arete;
            return $res;
        };

        $columnas = "id, codigo_rgb_id_animal,
                     COALESCE(nro_arete, '') AS nro_arete,
                     COALESCE(nombre, '')    AS nombre";

        /* 1) Coincidencia exacta con el código del animal. */
        $filas = @Manimale::find_by_sql("
            SELECT $columnas
              FROM manimales
             WHERE mfundos_id = $fundo
               AND sexo = 2
               AND status = 1
               AND UPPER(BTRIM(codigo_rgb_id_animal)) = UPPER(BTRIM('$esc'))
             ORDER BY id
             LIMIT 1");

        if ($filas && count($filas) > 0) {
            return $asignar($filas[0], 'codigo');
        }

        /* 2) Coincidencia con el número de arete, solo si es inequívoca. */
        $filas = @Manimale::find_by_sql("
            SELECT $columnas
              FROM manimales
             WHERE mfundos_id = $fundo
               AND sexo = 2
               AND status = 1
               AND BTRIM(COALESCE(nro_arete, '')) <> ''
               AND UPPER(BTRIM(nro_arete)) = UPPER(BTRIM('$esc'))
             ORDER BY id
             LIMIT 3");

        if ($filas && count($filas) === 1) {
            return $asignar($filas[0], 'arete');
        }

        return $res;
    }
}

if (!function_exists('bovs_mensaje_macho_no_encontrado')) {

    /**
     * Mensaje HTML reutilizable cuando el padre recibido no existe.
     *
     * @param  array $res        Resultado de bovs_resolver_codigo_macho().
     * @param  int   $ambiguo    >1 cuando el arete lo comparten varios machos.
     * @return string
     */
    function bovs_mensaje_macho_no_encontrado($res, $ambiguo = 0)
    {
        $valor = htmlspecialchars((string) $res['original'], ENT_QUOTES, 'UTF-8');

        if ($ambiguo > 1) {
            return '<div class="alert alert-warning">'
                . '<h4><i class="icon fa fa-warning"></i> Padre ambiguo</h4>'
                . 'El número <b>' . $valor . '</b> coincide con varios machos del fundo.'
                . '<br>Elija el padre desde el listado «Padre / Macho».'
                . '</div>';
        }

        return '<div class="alert alert-warning">'
            . '<h4><i class="icon fa fa-warning"></i> El padre no está registrado</h4>'
            . 'El valor <b>' . $valor . '</b> no corresponde al código ni al nro de arete de un macho activo de este fundo.'
            . '<br>Seleccione el padre desde el listado «Padre / Macho» del formulario.'
            . '</div>';
    }
}
