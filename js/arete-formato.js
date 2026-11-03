/*!
 * arete-formato.js — FORMATO CANÓNICO del N° de arete.
 *
 * Misma regla que lib/arete_helper.php (servidor):
 *   - MAYÚSCULAS (los acentos se convierten a su letra: Ñ→N, Á→A)
 *   - cada símbolo o espacio se reemplaza por un guion
 *   - nunca dos guiones seguidos
 *   - sin guion al inicio (al final sí: es el ajuste de duplicados)
 *   - máximo 15 caracteres
 *
 * El productor elige la combinación (9321A, A9321, 322-JR…); esto solo la
 * deja en la forma canónica. Se aplica al salir del campo y otra vez en el
 * servidor al guardar (ahí manda la regla de PHP).
 *
 * Uso: incluir el archivo; actúa sobre <input id="numero_arete"> y sobre
 * cualquier <input data-arete>. Para formatear a mano: BovsArete.formatear(x).
 */
(function (window, document, $) {
    'use strict';

    var MAX = 15;

    var ACENTOS = {
        'á': 'A', 'à': 'A', 'ä': 'A', 'â': 'A', 'ã': 'A',
        'é': 'E', 'è': 'E', 'ë': 'E', 'ê': 'E',
        'í': 'I', 'ì': 'I', 'ï': 'I', 'î': 'I',
        'ó': 'O', 'ò': 'O', 'ö': 'O', 'ô': 'O', 'õ': 'O',
        'ú': 'U', 'ù': 'U', 'ü': 'U', 'û': 'U',
        'ñ': 'N', 'ç': 'C',
        'Á': 'A', 'À': 'A', 'Ä': 'A', 'Â': 'A', 'Ã': 'A',
        'É': 'E', 'È': 'E', 'Ë': 'E', 'Ê': 'E',
        'Í': 'I', 'Ì': 'I', 'Ï': 'I', 'Î': 'I',
        'Ó': 'O', 'Ò': 'O', 'Ö': 'O', 'Ô': 'O', 'Õ': 'O',
        'Ú': 'U', 'Ù': 'U', 'Ü': 'U', 'Û': 'U',
        'Ñ': 'N', 'Ç': 'C'
    };

    function formatear(valor) {
        var texto = String(valor == null ? '' : valor).replace(/^\s+|\s+$/g, '');
        if (texto === '') return '';

        texto = texto.replace(/[áàäâãéèëêíìïîóòöôõúùüûñçÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ]/g, function (c) {
            return ACENTOS[c] || c;
        });
        texto = texto.toUpperCase();
        texto = texto.replace(/[^0-9A-Z]+/g, '-');
        texto = texto.replace(/^-+/, '');

        if (texto.length > MAX) {
            texto = texto.slice(0, MAX).replace(/-+$/, '');
        }

        return texto;
    }

    function esCampoArete(nodo) {
        if (!nodo || nodo.nodeType !== 1) return false;
        if (nodo.id === 'numero_arete') return true;
        return nodo.hasAttribute && nodo.hasAttribute('data-arete');
    }

    function aplicarMaximo(nodo) {
        if (!nodo || !nodo.setAttribute) return;
        // No se pisa un maxlength menor configurado a propósito.
        if (!nodo.getAttribute('maxlength')) {
            nodo.setAttribute('maxlength', String(MAX));
        }
        nodo.setAttribute('autocapitalize', 'characters');
    }

    /* Se normaliza al salir del campo. OJO: `focusout` se dispara DESPUÉS de
       los handlers `blur` del propio campo, así que cada formulario que
       consulta el duplicado por AJAX formatea primero (ver los `blur` de
       frm_registrar.php y frm_editar.php). Esto es la red de seguridad para
       cualquier otro punto que use el campo. */
    document.addEventListener('focusout', function (evento) {
        var nodo = evento.target;
        if (!esCampoArete(nodo)) return;
        var nuevo = formatear(nodo.value);
        if (nuevo !== nodo.value) {
            nodo.value = nuevo;
        }
    }, true);

    $(function () {
        $('#numero_arete, input[data-arete]').each(function () {
            aplicarMaximo(this);
        });
    });

    window.BovsArete = {
        formatear: formatear,
        MAX: MAX
    };
})(window, document, window.jQuery);
