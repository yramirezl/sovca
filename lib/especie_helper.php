<?php
/**
 * lib/especie_helper.php  (SOVCA)
 * ---------------------------------------------------------------------------
 * Nombre de la ESPECIE según el tipo de ganadería del fundo activo.
 *
 *   mtipoganaderias:  1 = OVINA   · 2 = CAPRINA · 3 = TODAS
 *
 *   OVINA     → oveja / ovejas        · carnero / carneros
 *   CAPRINA   → cabra / cabras        · macho cabrío / machos cabríos
 *   TODAS o sin fundo → hembra / machos (término neutro: no se puede afirmar
 *                       la especie, así que no se dice "vaca" como en BOVS)
 *
 * Uso:
 *   bovs_especie_hembra()                 → 'oveja'
 *   bovs_especie_hembra(true)             → 'ovejas'
 *   bovs_especie_hembra(false, 'title')   → 'Oveja'   (Cabra)
 *   bovs_especie_hembra(true, 'upper')    → 'OVEJAS'  (CABRAS)
 *   bovs_especie_macho(true, 'title')     → 'Carneros'
 *
 * Se conserva el prefijo `bovs_` a propósito: es el mismo nombre que usa el
 * código de origen, de modo que los parches de las vistas se pueden comparar
 * línea a línea con BOVS (SOVCA ya usa nombres así, p. ej. css/tema-bovs.css).
 * ---------------------------------------------------------------------------
 */

if (!function_exists('bovs_especie_tipo_ganaderia')) {
    /**
     * Tipo de ganadería del fundo activo (1 ovina · 2 caprina · 0/3 otros).
     * Reutiliza el helper de catálogos si ya está cargado.
     */
    function bovs_especie_tipo_ganaderia()
    {
        if (function_exists('bovs_catalogo_tipo_ganaderia')) {
            return (int) bovs_catalogo_tipo_ganaderia();
        }
        return isset($_SESSION['tipo_ganaderia']) ? (int) $_SESSION['tipo_ganaderia'] : 0;
    }
}

if (!function_exists('bovs_especie_es_ovina')) {
    /** ¿El fundo activo es de ganadería ovina? */
    function bovs_especie_es_ovina()
    {
        return bovs_especie_tipo_ganaderia() === 1;
    }
}

if (!function_exists('bovs_especie_es_caprina')) {
    /** ¿El fundo activo es de ganadería caprina? */
    function bovs_especie_es_caprina()
    {
        return bovs_especie_tipo_ganaderia() === 2;
    }
}

if (!function_exists('bovs_especie_formatear')) {
    /**
     * Aplica el formato pedido a una palabra.
     * @param string $palabra  palabra en minúsculas y con acentos
     * @param string $formato  'lower' | 'title' | 'upper'
     */
    function bovs_especie_formatear($palabra, $formato = 'lower')
    {
        switch ($formato) {
            case 'upper':
                return function_exists('mb_strtoupper')
                    ? mb_strtoupper($palabra, 'UTF-8')
                    : strtoupper($palabra);
            case 'title':
                return function_exists('mb_strtoupper')
                    ? mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8')
                        . mb_substr($palabra, 1, null, 'UTF-8')
                    : ucfirst($palabra);
            default:
                return $palabra;
        }
    }
}

if (!function_exists('bovs_especie_hembra')) {
    /**
     * Hembra adulta: oveja/ovejas · cabra/cabras · hembra/hembras.
     */
    function bovs_especie_hembra($plural = false, $formato = 'lower')
    {
        if (bovs_especie_es_ovina()) {
            $palabra = $plural ? 'ovejas' : 'oveja';
        } elseif (bovs_especie_es_caprina()) {
            $palabra = $plural ? 'cabras' : 'cabra';
        } else {
            $palabra = $plural ? 'hembras' : 'hembra';
        }
        return bovs_especie_formatear($palabra, $formato);
    }
}

if (!function_exists('bovs_especie_macho')) {
    /**
     * Macho adulto: carnero/carneros · macho cabrío/machos cabríos.
     */
    function bovs_especie_macho($plural = false, $formato = 'lower')
    {
        if (bovs_especie_es_ovina()) {
            $palabra = $plural ? 'carneros' : 'carnero';
        } elseif (bovs_especie_es_caprina()) {
            $palabra = $plural ? 'machos cabríos' : 'macho cabrío';
        } else {
            $palabra = $plural ? 'machos' : 'macho';
        }
        return bovs_especie_formatear($palabra, $formato);
    }
}

if (!function_exists('bovs_especie_hato')) {
    /**
     * Colectivo del rebaño según la especie (para títulos y ayudas).
     */
    function bovs_especie_hato($formato = 'lower')
    {
        return bovs_especie_formatear('rebaño', $formato);
    }
}
