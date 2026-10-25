<?php
/**
 * SOVCA | Normalización de códigos de animal (para los reportes)
 * ---------------------------------------------------------------------------
 * Los códigos se guardan como `SOVCA-0{fundo}-0{número}`:
 *
 *     fundo  5 → SOVCA-05-0199
 *     fundo  8 → SOVCA-08-08
 *
 * Al escribir el código a mano es fácil teclear ceros de más o de menos
 * (`SOVCA-005-199`, `SOVCA-5-199`, `SOVCA-05-199`…). Las búsquedas de los
 * formularios reconstruyen el código a partir del fundo y del número, pero los
 * reportes comparaban el texto EXACTO, así que un código con otro relleno
 * devolvía "sin datos" aunque el animal existiera en el hato.
 *
 * Estas funciones permiten localizar el animal sin depender del relleno.
 * Se incluyen desde Reportes/reportes/sovca/*.php como
 * `require_once('../../lib/animal_codigo_helper.php');`
 *
 * Se acepta también el prefijo histórico `BOVS-` (códigos migrados desde la
 * versión anterior del sistema).
 * ---------------------------------------------------------------------------
 */

if (!function_exists('bovs_codigos_candidatos')) {
    /**
     * Variantes del código que se deben probar (la primera es la que escribió
     * el usuario, tal cual en mayúsculas).
     *
     * @param string $codigo     Código o número escrito por el usuario.
     * @param int    $mfundos_id Fundo activo.
     * @return string[]
     */
    function bovs_codigos_candidatos($codigo, $mfundos_id)
    {
        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return array();
        }

        $lista = array($codigo);

        /* Último grupo numérico del código: SOVCA-005-199 → 199 */
        $nro = bovs_numero_de_codigo($codigo);
        $fundo = intval($mfundos_id);

        if ($nro > 0 && $fundo > 0) {
            /* Se respeta el prefijo que escribió el usuario; si no trae
               ninguno, se prueban SOVCA (actual) y BOVS (migrados). */
            $prefijos = array();
            if (strpos($codigo, 'BOVS') === 0) {
                $prefijos[] = 'BOVS';
            } elseif (strpos($codigo, 'SOVCA') === 0) {
                $prefijos[] = 'SOVCA';
            } else {
                $prefijos = array('SOVCA', 'BOVS');
            }

            foreach ($prefijos as $prefijo) {
                $lista[] = $prefijo . '-0' . $fundo . '-0' . $nro;
                $lista[] = $prefijo . '-0' . $fundo . '-' . $nro;
                $lista[] = $prefijo . '-' . $fundo . '-0' . $nro;
                $lista[] = $prefijo . '-' . $fundo . '-' . $nro;
            }
        }

        return array_values(array_unique($lista));
    }
}

if (!function_exists('bovs_numero_de_codigo')) {
    /**
     * Número final del código (8 para SOVCA-08-08, 255 para SOVCA-02-0255).
     * 0 cuando no aplica.
     */
    function bovs_numero_de_codigo($codigo)
    {
        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return 0;
        }

        if (preg_match('/^(?:SOVCA|BOVS)[-\s]*\d*[-\s]*0*(\d+)$/', $codigo, $m)) {
            return intval($m[1]);
        }
        if (ctype_digit($codigo)) {
            return intval($codigo);
        }

        return 0;
    }
}
