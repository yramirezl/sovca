/*
 * bovs-teclado.js
 * ---------------------------------------------------------------------------
 * Varias vistas usan `onkeypress="return ms_direccion(event)"` y
 * `onblur="alsalir(this.id)"`, pero esas DOS funciones nunca se definieron en
 * ningún archivo: en cada tecla el navegador lanzaba
 * "ReferenceError: ms_direccion is not defined" y, como el handler devolvía
 * undefined, el carácter NO se insertaba. El campo parecía bloqueado aunque
 * estuviera habilitado (pasaba en Nacimientos → Observaciones, Fundos, Lotes,
 * Mensajes, etc.).
 *
 * Aquí se definen una sola vez y de forma tolerante:
 *   · ms_direccion() solo bloquea teclas de CONTROL (nunca letras, números,
 *     acentos ni signos): no restringe el texto, solo evita comportamientos
 *     raros del navegador.
 *   · alsalir() limpia el mensaje de validación del campo si existe.
 *
 * Se cargan desde views/layouts/pie.php, así que están disponibles en todas
 * las vistas sin tocar cada formulario.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    if (typeof window.ms_direccion !== 'function') {
        window.ms_direccion = function (e) {
            if (!e) {
                return true;
            }
            var k = (typeof e.which === 'number') ? e.which : e.keyCode;

            /* Nada, backspace, tab, enter y escape siempre se permiten */
            if (k === 0 || k === 8 || k === 9 || k === 13 || k === 27) {
                return true;
            }
            /* Atajos del navegador (Ctrl+C, Ctrl+V, Cmd+V…) */
            if (e.ctrlKey || e.metaKey) {
                return true;
            }
            /* El resto de teclas de control no aportan texto */
            if (k < 32) {
                return false;
            }

            /* Letras, números, acentos, espacios y signos: todo permitido */
            return true;
        };
    }

    if (typeof window.alsalir !== 'function') {
        window.alsalir = function (id) {
            var ms = document.getElementById('ms_' + id);
            if (ms) {
                ms.innerHTML = '';
                ms.style.display = 'none';
            }
            return true;
        };
    }
})();
