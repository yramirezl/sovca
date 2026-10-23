/*
 * ajax-unicode.js
 * ---------------------------------------------------------------------------
 * Los controladores responden con json_encode() SIN JSON_UNESCAPED_UNICODE, así
 * que los caracteres no ASCII viajan escapados: "Mañanita" -> "Ma\u00f1anita".
 * Los formularios antiguos NO parsean ese JSON: recortan las comillas del texto
 * crudo (.split('*') + .split('"')) y por eso el escape quedaba a la vista en
 * pantalla (N°, Ñ, acentos).
 *
 * Aquí se decodifican los escapes \uXXXX UNA sola vez, sobre las respuestas de
 * tipo texto, antes de que las reciba cualquier manejador. El texto sigue siendo
 * un JSON válido (una "ñ" suelta dentro de una cadena JSON es válida) y quienes
 * ya hacían $.parseJSON() obtienen exactamente el mismo resultado.
 *
 * No se toca ninguna respuesta sin escapes.
 */
(function ($) {
    if (!$ || !$.ajaxSetup) { return; }

    function decodificar(texto) {
        if (typeof texto !== 'string' || texto.indexOf('\\u') === -1) {
            return texto;
        }

        /* Sólo los escapes SIMPLES (\uXXXX). Un backslash escapado (\\uXXXX,
           o sea el texto literal "\uXXXX" dentro de un JSON) se deja intacto. */
        return texto.replace(/([^\\]|^)\\u([0-9a-fA-F]{4})/g, function (coincidencia, anterior, hex) {
            var codigo = parseInt(hex, 16);

            /* Los sustitutos (pares de emojis) se dejan intactos */
            if (codigo >= 0xD800 && codigo <= 0xDFFF) {
                return coincidencia;
            }

            return anterior + String.fromCharCode(codigo);
        });
    }

    $.ajaxSetup({
        /* OJO: jQuery 2.x llama al dataFilter con el dataType SIN resolver
           (llega undefined), así que NO se puede filtrar por tipo; se decodifican
           sólo las respuestas de texto (los objetos ya vienen parseados). */
        dataFilter: function (data) {
            return decodificar(data);
        }
    });
})(window.jQuery);
