<?php
/**
 * =====================================================================
 * lib/catalogo_fundo_helper.php — CATÁLOGOS SIEMPRE DEL FUNDO ACTIVO
 * ---------------------------------------------------------------------
 * Los formularios piden sus catálogos (razas, lotes, categorías,
 * productos, presentaciones…) a endpoints que ANTES ramificaban así:
 *
 *      rol 1/4  →  "todo el universo" (sin filtro de fundo)
 *      rol 2/3  →  fundo de la sesión (mfundos_id) + globales
 *
 * Eso rompía el MODO SOPORTE: el administrador entra a un fundo
 * (mf_activar_supervisor() deja ese fundo en $_SESSION['mfundos_id']),
 * pero los endpoints lo trataban como "administrador suelto" y le
 * devolvían las razas/lotes/categorías de TODOS los fundos. Resultado:
 *   · listas enormes con nombres repetidos (varias veces "CARORA"),
 *   · al guardar se podía elegir el id de OTRO fundo,
 *   · y en los endpoints que solo miraban `rol == 1`, el SUPER USUARIO
 *     (rol 4, la cuenta ADMIN) no veía NINGÚN lote.
 *
 * Regla única (la que usan estos helpers):
 *   · Con un fundo activo en la sesión  → filas de ese fundo + globales.
 *   · Sin fundo activo (admin fuera de soporte) → solo las globales
 *     (mfundos_id = 0), que es lo único que tiene sentido editar.
 *
 * El MODO SOPORTE ya deja en la sesión:
 *   $_SESSION['mfundos_id']     = fundo visitado
 *   $_SESSION['tipo_ganaderia'] = tipo de ganadería del fundo
 * así que no hay que tocar nada más: basta con dejar de ramificar por rol.
 *
 * Este archivo SOLO lee la sesión (no incluye modelos ni abre la BD),
 * de modo que se puede requerir desde cualquier controlador.
 * =====================================================================
 */

/**
 * Fundo activo para los catálogos (0 = ninguno ⇒ solo globales).
 * En modo soporte es el fundo visitado.
 */
if (!function_exists('bovs_catalogo_fundo_id')) {
    function bovs_catalogo_fundo_id()
    {
        return isset($_SESSION['mfundos_id']) ? (int) $_SESSION['mfundos_id'] : 0;
    }
}

/**
 * Tipo de ganadería del fundo activo (0 = sin filtrar).
 */
if (!function_exists('bovs_catalogo_tipo_ganaderia')) {
    function bovs_catalogo_tipo_ganaderia()
    {
        return isset($_SESSION['tipo_ganaderia']) ? (int) $_SESSION['tipo_ganaderia'] : 0;
    }
}

/**
 * ¿La sesión está dentro de un fundo por MODO SOPORTE?
 * (Se usa para mensajes/ayudas; los filtros no dependen de esto.)
 */
if (!function_exists('bovs_catalogo_en_soporte')) {
    function bovs_catalogo_en_soporte()
    {
        return !empty($_SESSION['supervisor_mode']);
    }
}

/**
 * Filtro SQL estándar: filas del fundo activo + globales.
 * $alias es el alias de la tabla en la consulta (puede ir vacío).
 */
if (!function_exists('bovs_catalogo_sql_fundo')) {
    function bovs_catalogo_sql_fundo($alias = '')
    {
        $p   = ($alias !== '') ? $alias . '.' : '';
        $fid = bovs_catalogo_fundo_id();

        if ($fid > 0) {
            return " AND ({$p}mfundos_id = {$fid} OR {$p}mfundos_id = 0) ";
        }

        return " AND {$p}mfundos_id = 0 ";
    }
}

/**
 * Filtro SQL por tipo de ganadería (opcional).
 * $alias es el alias de la tabla; $columna permite usar otra columna.
 */
if (!function_exists('bovs_catalogo_sql_tipo')) {
    function bovs_catalogo_sql_tipo($alias = '', $columna = 'mtipoganaderias_id')
    {
        $tipo = bovs_catalogo_tipo_ganaderia();
        if ($tipo <= 0) {
            return '';
        }
        $p = ($alias !== '') ? $alias . '.' : '';
        return " AND {$p}{$columna} = {$tipo} ";
    }
}
