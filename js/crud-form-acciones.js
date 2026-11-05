/**
 * crud-form-acciones.js — Botones de acción (Editar / Guardar / Regresar) arriba.
 * ---------------------------------------------------------------------------
 * PROBLEMA
 *   En los formularios (.crud-form) la barra de acciones vive al FINAL del
 *   formulario. En el móvil el usuario tenía que bajar toda la pantalla para
 *   poder guardar, editar o salir.
 *
 * QUÉ HACE
 *   Mueve los botones de la barra inferior a la cabecera del formulario, a la
 *   izquierda del botón «Regresar» (#exit_top). No los clona: son los mismos
 *   nodos, así que los manejadores que cada vista les asigna con jQuery
 *   ($('#save').click(...), $('#modificar').click(...), crud-back.js para
 *   #exit/#exit_top) siguen funcionando sin cambios.
 *
 *   Si un formulario no tiene cabecera (.crud-form__header) no se toca nada.
 *
 *   La barra original se oculta cuando queda vacía; el aviso de campos
 *   pendientes (js/bovs-ficha-faltantes.js) se inserta antes de ella y por eso
 *   sigue apareciendo en el mismo lugar.
 * ---------------------------------------------------------------------------
 */
(function (window, document) {
    'use strict';

    var MARCA = 'data-acciones-superior';

    /** ¿El botón se ve en pantalla? (algunos formularios ocultan #exit). */
    function esVisible(elemento) {
        if (!elemento || elemento.hidden) {
            return false;
        }
        var estilo = window.getComputedStyle(elemento);
        if (estilo.display === 'none' || estilo.visibility === 'hidden') {
            return false;
        }
        return elemento.getClientRects().length > 0;
    }

    function moverAcciones() {
        var formularios = document.querySelectorAll('.crud-form');

        for (var i = 0; i < formularios.length; i++) {
            var contenedor = formularios[i];
            var barra = contenedor.querySelector('.crud-form__actions');

            if (!barra || barra.getAttribute(MARCA) === '1') {
                continue;
            }

            var cabecera = contenedor.querySelector('.crud-form__header');
            if (!cabecera || cabecera.parentNode !== contenedor) {
                continue;
            }

            var destino = cabecera.querySelector('.crud-form__header-actions');
            if (!destino) {
                destino = document.createElement('div');
                destino.className = 'crud-form__header-actions';
                cabecera.appendChild(destino);
            }

            /* El «Regresar» de la cabecera se queda al final: EDITAR · GUARDAR · Regresar */
            var referencia = destino.querySelector('#exit_top') || null;
            var botones = barra.querySelectorAll('button, a.btn');
            var movidos = 0;

            /* Se mueven TODOS los botones, estén visibles u ocultos: varios
               formularios arrancan en modo lectura y muestran #modificar /
               #save después de cargar los datos. Al mover el nodo (no una
               copia) conservan su estado y su manejador. */
            for (var j = 0; j < botones.length; j++) {
                var boton = botones[j];
                /* Ya hay un Regresar en la cabecera: no se duplica. */
                if (boton.id === 'exit' && referencia) {
                    continue;
                }
                destino.insertBefore(boton, referencia);
                boton.classList.add('crud-form__accion');
                movidos++;
            }

            barra.setAttribute(MARCA, '1');

            if (movidos === 0) {
                barra.setAttribute(MARCA, '0');
                continue;
            }

            /* Si la barra se quedó solo con botones ocultos, no ocupa espacio. */
            if (!tieneVisibles(barra)) {
                barra.style.display = 'none';
            }

            contenedor.classList.add('crud-form--acciones-superiores');
            /* La cabecera queda pegada arriba: los botones siguen a la vista
               mientras se recorre un formulario largo. */
            cabecera.classList.add('crud-form__header--acciones');
            vigilarMensajes(contenedor);
        }
    }

    function tieneVisibles(barra) {
        var hijos = barra.querySelectorAll('button, a.btn');
        for (var i = 0; i < hijos.length; i++) {
            if (esVisible(hijos[i])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Con la cabecera pegada arriba, el aviso de guardado (.message) puede
     * quedar fuera de la vista. Cuando se escribe un mensaje se lleva al
     * centro de la pantalla (solo si está fuera del área visible).
     */
    function vigilarMensajes(contenedor) {
        var mensaje = contenedor.querySelector('.message');
        if (!mensaje || !window.MutationObserver) {
            return;
        }
        var observador = new MutationObserver(function () {
            if (!(mensaje.textContent || '').replace(/\s/g, '')) {
                return;
            }
            var caja = mensaje.getBoundingClientRect();
            var visible = caja.bottom > 0 && caja.top < (window.innerHeight || 0);
            if (!visible) {
                mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        observador.observe(mensaje, { childList: true, subtree: true, characterData: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', moverAcciones);
    } else {
        moverAcciones();
    }
})(window, document);
