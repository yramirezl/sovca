/*
 * bovs-validacion.js  (v1)
 * ---------------------------------------------------------------------------
 * Cuando falta un dato obligatorio en un formulario, el sistema lo dice de
 * forma VISIBLE: resalta el campo, escribe el motivo y muestra un resumen con
 * un botón para saltar al primer dato pendiente. Antes el formulario se
 * quedaba "congelado" sin ninguna pista y el operario tenía que adivinar.
 *
 * Cómo funciona (sin tocar los 60+ formularios existentes):
 *   1. Los validadores actuales escriben el mensaje en los contenedores
 *      <div id="ms_xxx">...</div>. Aquí se vigilan esos contenedores: en
 *      cuanto reciben texto, se busca el campo asociado y se resalta.
 *   2. Los campos con required / data-requerido / data-obligatorio se validan
 *      antes de cualquier clic en un botón de guardar.
 *   3. El resaltado se quita en cuanto el usuario corrige el campo.
 *
 * API pública:
 *   BovsValidar.marcar(campo, mensaje)
 *   BovsValidar.limpiar(campo)
 *   BovsValidar.revisar(formulario)  -> devuelve el nº de campos pendientes
 *   BovsValidar.resumen(formulario)
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    if (window.BovsValidar) {
        return;
    }

    /* Algunos contenedores de mensaje no se llaman igual que su campo */
    var ALIAS = {
        ms_nombre: 'nombre_animal',
        ms_raza: 'raza_animal',
        ms_aptitud: 'aptitud_animal',
        ms_cat_ingreso: 'categoria_ingreso',
        ms_cat_futura: 'categoria_futura',
        ms_fech_nac: 'fech_nac',
        ms_fecha_nac: 'fech_nac',
        ms_estatus: 'estatus_animal',
        ms_lote: 'lote',
        ms_servicio: 'servicio_animal',
        ms_peso: 'peso_nac',
        ms_cod_animal: 'cod_animal_h'
    };

    function esCampo(el) {
        if (!el || !el.tagName) return false;
        var t = el.tagName.toUpperCase();
        if (t !== 'INPUT' && t !== 'SELECT' && t !== 'TEXTAREA') return false;
        if (el.type && el.type.toLowerCase() === 'hidden') return false;
        return true;
    }

    function visible(el) {
        if (!el) return false;
        if (el.offsetParent !== null) return true;
        /* Elementos position:fixed no tienen offsetParent */
        var estilo = window.getComputedStyle(el);
        return estilo.position === 'fixed' && estilo.display !== 'none' && estilo.visibility !== 'hidden';
    }

    function habilitado(el) {
        return !!el && !el.disabled && !el.readOnly;
    }

    function estaVacio(el) {
        if (!esCampo(el)) return false;
        var valor = (el.value === undefined || el.value === null) ? '' : String(el.value);
        valor = valor.replace(/\s+/g, ' ').trim();
        if (valor === '' || valor === '*') return true;
        if (el.tagName.toUpperCase() === 'SELECT' && el.selectedIndex >= 0) {
            var opt = el.options[el.selectedIndex];
            var txt = (opt && opt.text ? opt.text : '').trim();
            /* "---", "--", "Seleccione…" cuentan como vacío */
            if (/^[-—–]*$/.test(txt) || /^(seleccione|seleccionar|elija|escoja)\b/i.test(txt)) {
                return true;
            }
        }
        return false;
    }

    function grupoDe(campo) {
        return campo.closest('.form-group, .crud-form__field, .input-group, .sel-animal, td, .form-line')
            || campo.parentNode;
    }

    function etiquetaDe(campo) {
        var grupo = grupoDe(campo);
        var label = grupo ? grupo.querySelector('label, .control-label, .crud-form__label') : null;
        var texto = label ? (label.textContent || '').replace(/\s+/g, ' ').trim() : '';
        texto = texto.replace(/\s*\(obligatorio\)\s*$/i, '');
        if (texto) return texto;
        if (campo.id) return campo.id.replace(/_/g, ' ');
        return 'Campo sin nombre';
    }

    /* ---------- Marcar / limpiar ---------- */

    function marcar(campo) {
        if (!esCampo(campo)) return;
        var yaEstaba = campo.classList.contains('bovs-campo-error');
        campo.classList.add('bovs-campo-error');
        campo.setAttribute('aria-invalid', 'true');
        var grupo = grupoDe(campo);
        if (grupo && grupo.classList) {
            grupo.classList.add('bovs-grupo-error');
        }
        if (!yaEstaba) {
            campo.classList.add('bovs-campo-error--pulso');
            window.setTimeout(function () {
                campo.classList.remove('bovs-campo-error--pulso');
            }, 1000);
        }
    }

    function limpiar(campo) {
        if (!esCampo(campo)) return;
        campo.classList.remove('bovs-campo-error', 'bovs-campo-error--pulso');
        campo.removeAttribute('aria-invalid');
        var grupo = grupoDe(campo);
        if (grupo && grupo.classList) {
            grupo.classList.remove('bovs-grupo-error');
        }
    }

    function enfocar(campo) {
        if (!visible(campo) || !habilitado(campo)) return;
        try {
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            campo.scrollIntoView();
        }
        try {
            campo.focus({ preventScroll: true });
        } catch (e2) {
            try { campo.focus(); } catch (e3) { /* sin foco */ }
        }
    }

    /* ---------- Campo asociado a un contenedor ms_* ---------- */

    function campoDeContenedor(cont) {
        if (!cont || !cont.id) return null;
        var sufijo = cont.id.replace(/^ms_/, '');
        var candidatos = [];
        if (ALIAS[cont.id]) candidatos.push(ALIAS[cont.id]);
        candidatos.push(sufijo, sufijo + '_animal', sufijo + '_id', sufijo + 's');

        for (var i = 0; i < candidatos.length; i++) {
            var el = document.getElementById(candidatos[i]);
            if (esCampo(el)) return el;
        }

        /* Si el id no coincide, se usa el primer campo del mismo grupo */
        var p = cont.parentNode;
        var niveles = 0;
        while (p && niveles < 5) {
            var campos = p.querySelectorAll('input, select, textarea');
            for (var j = 0; j < campos.length; j++) {
                if (esCampo(campos[j])) return campos[j];
            }
            p = p.parentNode;
            niveles++;
        }
        return null;
    }

    /* ---------- Resumen de pendientes ---------- */

    function formularioDe(el) {
        return (el && el.form) ? el.form : (el ? el.closest('form') : null);
    }

    function contenedorResumen(form) {
        if (!form) return document.querySelector('.crud-form') || document.body;
        var existente = form.querySelector('.bovs-resumen-errores');
        if (existente) return existente;

        var div = document.createElement('div');
        div.className = 'bovs-resumen-errores';
        div.setAttribute('role', 'alert');
        div.setAttribute('hidden', 'hidden');
        div.innerHTML = '<span class="bovs-resumen-errores__icono">!</span>'
            + '<span class="bovs-resumen-errores__texto"></span>'
            + '<button type="button" class="bovs-resumen-errores__ir">Ir al primero</button>';
        div.querySelector('.bovs-resumen-errores__ir').addEventListener('click', function () {
            var primero = form.querySelector('.bovs-campo-error');
            if (primero) enfocar(primero);
        });
        form.insertBefore(div, form.firstChild);
        return div;
    }

    function resumen(form) {
        if (!form) return;

        /* La ficha del animal (alta/edición) tiene su propio aviso junto a los
           botones, con la lista completa de lo que falta: no se duplica. */
        if (document.getElementById('bovs_faltantes')) {
            var propio = form.querySelector('.bovs-resumen-errores');
            if (propio) {
                propio.setAttribute('hidden', 'hidden');
                propio.innerHTML = '';
            }
            return;
        }

        var caja = contenedorResumen(form);
        var marcados = form.querySelectorAll('.bovs-campo-error');
        var texto = caja.querySelector('.bovs-resumen-errores__texto');

        if (!marcados.length) {
            caja.setAttribute('hidden', 'hidden');
            texto.innerHTML = '';
            return;
        }

        var nombres = [];
        for (var i = 0; i < marcados.length; i++) {
            var n = etiquetaDe(marcados[i]);
            if (nombres.indexOf(n) === -1) nombres.push(n);
        }
        var listado = nombres.slice(0, 5).join(', ');
        if (nombres.length > 5) listado += '…';

        texto.innerHTML = '<strong>Faltan datos obligatorios</strong>'
            + '<ul class="bovs-resumen-errores__lista"><li>' + listado + '</li></ul>';
        caja.removeAttribute('hidden');
    }

    /* ---------- Observación de los contenedores ms_* ---------- */

    function procesarContenedor(cont) {
        var campo = campoDeContenedor(cont);
        if (!campo) return;
        var texto = (cont.textContent || '').replace(/\s+/g, ' ').trim();
        var estabaMarcado = campo.classList.contains('bovs-campo-error');

        if (texto !== '') {
            marcar(campo);
            if (!estabaMarcado) enfocar(campo);
        } else if (!campo.hasAttribute('required') && !campo.hasAttribute('data-requerido')) {
            /* El validador limpió su mensaje: se quita el resaltado */
            limpiar(campo);
        }

        resumen(formularioDe(campo));
    }

    var observador = new MutationObserver(function (mutaciones) {
        var vistos = [];
        for (var i = 0; i < mutaciones.length; i++) {
            var nodo = mutaciones[i].target;
            if (nodo && nodo.nodeType !== 1) nodo = nodo.parentNode;
            if (!nodo || !nodo.id || nodo.id.indexOf('ms_') !== 0) continue;
            if (vistos.indexOf(nodo) === -1) {
                vistos.push(nodo);
                procesarContenedor(nodo);
            }
        }
    });

    function vigilar() {
        /* Solo cambios de contenido: observar atributos en todo el body era
           demasiado ruidoso (DataTables, animaciones, etc.). Los validadores
           escriben el mensaje con innerHTML, así que childList basta. */
        observador.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    /* ---------- Validación de campos declarados obligatorios ---------- */

    function requeridos() {
        return document.querySelectorAll('[required], [data-requerido], [data-obligatorio]');
    }

    function revisar(form) {
        var campos = (form || document).querySelectorAll('[required], [data-requerido], [data-obligatorio]');
        var pendientes = 0;
        for (var i = 0; i < campos.length; i++) {
            var c = campos[i];
            if (!esCampo(c) || !visible(c) || !habilitado(c)) continue;
            if (estaVacio(c)) {
                marcar(c);
                pendientes++;
            } else {
                limpiar(c);
            }
        }
        resumen(form || (campos.length ? formularioDe(campos[0]) : null));
        return pendientes;
    }

    /* ---------- Interceptor de guardado ---------- */

    var RE_GUARDAR = /guardar|registrar|actualizar|salvar|grabar|aplicar|guardar_|save|submit/i;

    document.addEventListener('click', function (evento) {
        var objetivo = evento.target;
        if (!objetivo || !objetivo.closest) return;
        var boton = objetivo.closest('button, input[type="submit"], a.btn, .crud-btn');
        if (!boton) return;

        var firma = [boton.id || '', boton.className || '', boton.value || '',
                     boton.getAttribute('name') || '', boton.textContent || ''].join(' ');
        if (!RE_GUARDAR.test(firma) && boton.type !== 'submit') return;
        if (boton.disabled) return;

        var form = boton.form || boton.closest('form');
        if (!form) return;

        /* Los formularios que ya declaran contenedores ms_* tienen su propio
           validador: se deja correr para no adelantarse ni bloquear el guardado
           (el observer de ms_* es el que resalta esos campos). */
        if (form.querySelector('[id^="ms_"]')) return;

        var pendientes = revisar(form);
        if (pendientes > 0) {
            evento.preventDefault();
            evento.stopPropagation();
            evento.stopImmediatePropagation();
            var primero = form.querySelector('.bovs-campo-error');
            if (primero) enfocar(primero);
            return false;
        }
    }, true);

    /* ---------- Limpieza al corregir ---------- */

    function contenedorDeCampo(campo) {
        var candidatos = [];
        if (campo.id) {
            candidatos.push('ms_' + campo.id);
            for (var alias in ALIAS) {
                if (Object.prototype.hasOwnProperty.call(ALIAS, alias) && ALIAS[alias] === campo.id) {
                    candidatos.push(alias);
                }
            }
        }
        for (var i = 0; i < candidatos.length; i++) {
            var cont = document.getElementById(candidatos[i]);
            if (cont) return cont;
        }
        var grupo = grupoDe(campo);
        return grupo ? grupo.querySelector('[id^="ms_"]') : null;
    }

    function alCorregir(evento) {
        var campo = evento.target;
        if (!esCampo(campo)) return;

        /* El mensaje del validador deja de tener sentido cuando el campo ya
           tiene valor: se oculta para que no quede el "Valor Obligatorio". */
        if (!estaVacio(campo)) {
            var cont = contenedorDeCampo(campo);
            if (cont && (cont.textContent || '').trim() !== '') {
                cont.innerHTML = '';
                cont.style.display = 'none';
            }
        }

        if (!campo.classList.contains('bovs-campo-error')) return;
        if (estaVacio(campo)) return;
        limpiar(campo);
        resumen(formularioDe(campo));
    }

    document.addEventListener('input', alCorregir, true);
    document.addEventListener('change', alCorregir, true);

    /* ---------- Arranque ---------- */

    function iniciar() {
        vigilar();
        /* Mensajes que ya venían pintados al cargar la página */
        var contenedores = document.querySelectorAll('[id^="ms_"]');
        for (var i = 0; i < contenedores.length; i++) {
            if ((contenedores[i].textContent || '').trim() !== '') {
                procesarContenedor(contenedores[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    window.BovsValidar = {
        marcar: marcar,
        limpiar: limpiar,
        resumen: resumen,
        revisar: revisar,
        enfocar: enfocar,
        requeridos: requeridos
    };
})();
