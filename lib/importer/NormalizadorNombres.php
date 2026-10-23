<?php
/* ============================================================
   Normaliza y compara nombres en lenguaje natural.
   Resuelve: acentos, mayúsculas, espacios múltiples, guiones.
   Ej: "Brahman-Rojo " == "BRAHMAN ROJO" == "brahman  rojo"
   ============================================================ */

class NormalizadorNombres {

    public static function normalizar($texto) {
        if ($texto === null || $texto === '') return '';

        $texto = trim((string)$texto);

        /* Quitar acentos */
        $reemplazos = array(
            'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U',
            'à'=>'A','è'=>'E','ì'=>'I','ò'=>'O','ù'=>'U',
            'ä'=>'A','ë'=>'E','ï'=>'I','ö'=>'O',
            'ñ'=>'N','Ñ'=>'N','ç'=>'C','Ç'=>'C',
        );
        $texto = strtr($texto, $reemplazos);

        $texto = mb_strtoupper($texto, 'UTF-8');

        /* Todo lo que no sea A-Z0-9 se convierte en espacio */
        $texto = preg_replace('/[^A-Z0-9]+/u', ' ', $texto);

        /* Colapsar espacios múltiples */
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    public static function similitud($a, $b) {
        $a = self::normalizar($a);
        $b = self::normalizar($b);
        if ($a === '' || $b === '') return 0;
        similar_text($a, $b, $pct);
        return $pct;
    }

    /**
     * Busca coincidencia exacta normalizada + coincidencias similares.
     * Defensivo contra arrays u objetos en el catalogo.
     *
     * @param string $texto    Valor ingresado por el usuario.
     * @param array  $items    Array de objetos o arrays con 'id' y 'nombre'.
     * @param int    $umbral   Porcentaje mínimo de similitud (default 80).
     * @return array  ['match' => obj|null, 'similares' => [['item'=>obj,'similitud'=>float], ...]]
     */
    public static function buscarConSimilares($texto, $items, $umbral = 80) {
        $texto_norm = self::normalizar($texto);
        if ($texto_norm === '') return array('match' => null, 'similares' => array());

        $match = null;
        $similares = array();

        if (!is_array($items)) return array('match' => null, 'similares' => array());

        foreach ($items as $item) {
            /* Acepta tanto arrays como objetos */
            if (is_array($item)) {
                $nombre_item = isset($item['nombre']) ? $item['nombre'] : '';
                $id_item     = isset($item['id'])     ? $item['id']     : 0;
            } else {
                $nombre_item = isset($item->nombre) ? $item->nombre : '';
                $id_item     = isset($item->id)     ? $item->id     : 0;
            }

            $item_norm = self::normalizar($nombre_item);

            if ($item_norm === $texto_norm) {
                $match = (object)array('id' => $id_item, 'nombre' => $nombre_item);
                break;
            }

            $sim = self::similitud($texto_norm, $item_norm);
            if ($sim >= $umbral) {
                $similares[] = array(
                    'item' => (object)array('id' => $id_item, 'nombre' => $nombre_item),
                    'similitud' => round($sim, 1)
                );
            }
        }

        usort($similares, function($a, $b) {
            return $b['similitud'] - $a['similitud'];
        });

        return array('match' => $match, 'similares' => $similares);
    }

    public static function parsearFecha($valor) {
        if ($valor === null || trim($valor) === '') return null;
        $valor = trim($valor);

        $formatos = array('Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y');
        foreach ($formatos as $fmt) {
            $d = DateTime::createFromFormat($fmt, $valor);
            if ($d && $d->format($fmt) === $valor) return $d->format('Y-m-d');
        }
        /* Fallback: intento por strtotime */
        $ts = strtotime($valor);
        if ($ts !== false) return date('Y-m-d', $ts);
        return null;
    }
}