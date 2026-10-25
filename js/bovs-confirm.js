/*
 * bovs-confirm.js  (v1)
 * ---------------------------------------------------------------------------
 * Unifica los diálogos de jquery-confirm:
 *   · el botón que AFIRMA (guardar / registrar / sí / aceptar) va en VERDE,
 *   · el que DENIEGA (cancelar / no / cerrar) va en ROJO.
 *
 * Antes cada vista pasaba sus clases y muchas usaban 'btn-default' (gris) en
 * el cancelar, así que el operario no distinguía la acción afirmativa de la
 * negativa. Se envuelven $.confirm y $.alert para no tener que tocar las ~100
 * llamadas existentes.
 *
 * No cambia el contenido, los textos ni las funciones confirm:/cancel:.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    if (typeof jQuery === 'undefined' || window.__bovsConfirmInstalado) {
        return;
    }
    window.__bovsConfirmInstalado = true;

    var $ = jQuery;
    if (typeof $.confirm !== 'function') {
        return;
    }

    var confirmOriginal = $.confirm;
    var alertOriginal = typeof $.alert === 'function' ? $.alert : null;

    /* El botón de cancelar siempre rojo y el de confirmar siempre verde. */
    function normalizar(opciones, esAlerta) {
        if (!opciones || typeof opciones !== 'object') {
            return opciones;
        }
        /* Se asigna SIEMPRE (aunque la vista no declare los textos) salvo que
           el botón se haya desactivado explícitamente con false. */
        if (opciones.confirmButton !== false) {
            opciones.confirmButtonClass = 'btn-success bovs-btn-afirmar';
            if (opciones.confirmButton === undefined) {
                opciones.confirmButton = esAlerta ? 'Aceptar' : 'Sí';
            }
        }
        if (!esAlerta && opciones.cancelButton !== false) {
            opciones.cancelButtonClass = 'btn-danger bovs-btn-denegar';
            if (opciones.cancelButton === undefined) {
                opciones.cancelButton = 'No';
            }
        }
        return opciones;
    }

    $.confirm = function (opciones) {
        normalizar(opciones, false);
        return confirmOriginal.apply(this, arguments);
    };

    if (alertOriginal) {
        $.alert = function (opciones) {
            normalizar(opciones, true);
            return alertOriginal.apply(this, arguments);
        };
    }

    /* También los $.confirm/$ .alert invocados con (titulo, mensaje) */
    var confirmConTitulo = $.confirm;
    $.confirm = function (titulo, mensaje) {
        if (typeof titulo === 'string') {
            return confirmConTitulo.call(this, {
                title: titulo,
                content: mensaje,
                confirmButtonClass: 'btn-success bovs-btn-afirmar',
                cancelButtonClass: 'btn-danger bovs-btn-denegar'
            });
        }
        return confirmConTitulo.apply(this, arguments);
    };
})();
