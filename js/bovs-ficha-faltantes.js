/*
 * bovs-ficha-faltantes.js  (v1)
 * ---------------------------------------------------------------------------
 * Dos problemas de los formularios de la ficha del animal (alta y edición):
 *
 * 1) MADRE / PADRE EXTERNOS
 *    Un animal comprado (procedencia externa) tiene padres que NO están en el
 *    hato. Antes había que escribir un código, pulsar "Buscar", esperar a que
 *    fallara la búsqueda y recién entonces un diálogo habilitaba el nombre; el
 *    campo del código bloqueaba el pegado y llamaba a una función de teclado
 *    inexistente (`es_codigo_nro_arete`), así que cada tecla lanzaba un error.
 *
 *    Ahora la casilla "…externa (no registrada)" habilita de una vez el código
 *    y el nombre, sin búsqueda previa y permitiendo pegar el dato.
 *
 * 2) CAMPOS OBLIGATORIOS AL GUARDAR
 *    Si faltaba más de un dato, el formulario sólo avisaba del primero (dentro
 *    de una tarjeta que podía estar fuera de la vista) y dejaba el botón
 *    Guardar deshabilitado, así que se percibía como "congelado".
 *
 *    Ahora se revisan TODOS los obligatorios, se marca cada campo, se muestra
 *    un aviso junto a los botones con la lista de lo que falta y un enlace para
 *    saltar al primero. El botón Guardar nunca queda bloqueado.
 *
 * API pública (window.BovsFicha):
 *   BovsFicha.validar()          -> true si no falta nada (y pinta el aviso)
 *   BovsFicha.faltantes()        -> [{campo, ms, etiqueta}, …]
 *   BovsFicha.manual(rol, on)    -> activa/desactiva la carga manual de madre|padre
 *   BovsFicha.bloquear(bool)     -> refleja el estado bloqueado/desbloqueado
 *   BovsFicha.marcarExternos()   -> deduce del contenido si el padre/madre viene
 *                                   de fuera y activa la carga manual
 *   BovsFicha.ocultarAviso()
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    if (window.BovsFicha) {
        return;
    }

    /* Datos mínimos para guardar una ficha (alta y edición). El id del campo y
       el contenedor del mensaje se corresponden con los del formulario. */
    var REQUERIDOS = [
        { campo: 'nombre_animal',     ms: 'ms_nombre',      etiqueta: 'Nombre del Animal' },
        { campo: 'raza_animal',       ms: 'ms_raza',        etiqueta: 'Raza del Animal' },
        { campo: 'sexo',              ms: 'ms_sexo',        etiqueta: 'Sexo' },
        { campo: 'fech_nac',          ms: 'ms_fech_nac',    etiqueta: 'Fecha de Nacimiento' },
        { campo: 'aptitud_animal',    ms: 'ms_aptitud',     etiqueta: 'Aptitud del Animal' },
        { campo: 'categoria_ingreso', ms: 'ms_cat_ingreso', etiqueta: 'Categoría de Ingreso' },
        { campo: 'categoria_futura',  ms: 'ms_cat_futura',  etiqueta: 'Categoría Futura' }
    ];

    /* Piezas de cada rol dentro del formulario */
    var ROLES = {
        madre: {
            check:      'madre_manual',
            caja:       'manual_madre',
            link:       'manual_madre_link',
            codigo:     'rgd_madre',
            nombre:     'nombre_madre',
            buscar:     'buscar',
            chkCodigo:  'b_codigo',
            chkArete:   'b_nro_arete',
            etiqueta:   'lbl_madre',
            texto:      'Madre seleccionada',
            textoManual: 'Madre externa (carga manual)',
            etiquetaItem: 'Nombre de la Madre (externa)',
            msNombre:   'ms_nombre_madre'
        },
        padre: {
            check:      'padre_manual',
            caja:       'manual_padre',
            link:       'manual_padre_link',
            codigo:     'rgd_padre',
            nombre:     'nombre_padre',
            buscar:     'buscar_m',
            chkCodigo:  'macho_codigo',
            chkArete:   'macho_nro_arete',
            etiqueta:   'lbl_padre',
            texto:      'Padre seleccionado',
            textoManual: 'Padre externo (carga manual)',
            etiquetaItem: 'Nombre del Padre (externo)',
            msNombre:   'ms_nombre_padre'
        }
    };

    var bloqueado = false;

    /* ---------- utilidades ---------- */

    function el(id) {
        return id ? document.getElementById(id) : null;
    }

    /* Muestra / oculta el bloque de captura (la clase .sel-animal-oculto lo
       esconde por defecto; se fuerza con !important por si alguna hoja de
       estilos del tema lo vuelve a ocultar). */
    function mostrar(id) {
        var caja = el(id);
        if (!caja) return;
        caja.style.setProperty('display', 'block', 'important');
    }

    function val(id) {
        var e = el(id);
        if (!e) return '';
        var v = (e.value === null || e.value === undefined) ? '' : String(e.value);
        return v.replace(/\s+/g, ' ').trim();
    }

    /* Vacío real: sin texto, el comodín "*" que devuelven algunos catálogos
       legacy o el id 0 (p. ej. una aptitud que quedó sin asignar). */
    function esVacio(v) {
        return v === '' || v === '*' || v === '0';
    }

    function esCodigoBovs(codigo) {
        return /^BOVS-\d+-\d+$/i.test(String(codigo || '').trim());
    }

    /* ---------- madre / padre externos ---------- */

    function manualActivo(rol) {
        var c = el(ROLES[rol].check);
        return !!(c && c.checked);
    }

    function aplicarRol(rol) {

        var r = ROLES[rol];
        var on = manualActivo(rol);

        var caja   = el(r.caja);
        var nombre = el(r.nombre);
        var codigo = el(r.codigo);
        var buscar = el(r.buscar);
        var chkC   = el(r.chkCodigo);
        var chkA   = el(r.chkArete);
        var chkM   = el(r.check);
        var lbl    = el(r.etiqueta);

        /* Al activar el modo manual se muestra el bloque de captura */
        if (on && caja) {
            mostrar(r.caja);
        }

        if (bloqueado) {
            /* Ficha en modo lectura: nada editable, pero la casilla conserva su
               estado para cuando se pulse EDITAR. */
            if (chkM) chkM.disabled = true;
            if (nombre) nombre.disabled = true;
            if (codigo) codigo.disabled = true;
            if (lbl) lbl.textContent = on ? r.textoManual : r.texto;
            return;
        }

        if (chkM) chkM.disabled = false;

        if (on) {
            /* Carga manual: se escribe el código y el nombre directamente */
            if (nombre) nombre.disabled = false;
            if (codigo) codigo.disabled = false;
            if (buscar) buscar.disabled = true;
            if (chkC) chkC.disabled = true;
            if (chkA) chkA.disabled = true;
        } else {
            /* Búsqueda en el hato (listado amigable o buscador por código) */
            if (nombre) nombre.disabled = true;
            if (chkC) chkC.disabled = false;
            if (chkA) chkA.disabled = false;
        }

        if (lbl) {
            lbl.textContent = on ? r.textoManual : r.texto;
        }
    }

    function aplicarManual() {
        aplicarRol('madre');
        aplicarRol('padre');
    }

    /* ---------- aviso de campos pendientes ---------- */

    function cajaAviso() {
        var caja = el('bovs_faltantes');
        if (caja) return caja;

        caja = document.createElement('div');
        caja.id = 'bovs_faltantes';
        caja.className = 'bovs-faltantes';
        caja.setAttribute('role', 'alert');
        caja.setAttribute('hidden', 'hidden');

        var acciones = document.querySelector('.crud-form .crud-form__actions');
        if (acciones && acciones.parentNode) {
            acciones.parentNode.insertBefore(caja, acciones);
        } else {
            var form = document.querySelector('.crud-form form, .crud-form');
            if (form) form.insertBefore(caja, form.firstChild);
        }
        return caja;
    }

    function ocultarAviso() {
        var caja = el('bovs_faltantes');
        if (caja) {
            caja.setAttribute('hidden', 'hidden');
            caja.innerHTML = '';
        }
    }

    function mostrarAviso(lista) {
        var caja = cajaAviso();
        if (!caja) return;

        var items = '';
        for (var i = 0; i < lista.length; i++) {
            items += '<li>' + lista[i].etiqueta + '</li>';
        }

        caja.innerHTML = '<div class="bovs-faltantes__titulo">'
            + '<i class="fa fa-exclamation-triangle"></i> Faltan campos por llenar</div>'
            + '<ul class="bovs-faltantes__lista">' + items + '</ul>'
            + '<button type="button" class="bovs-faltantes__ir">Ir al primer campo</button>';

        var boton = caja.querySelector('.bovs-faltantes__ir');
        if (boton) {
            boton.addEventListener('click', function () {
                enfocar(lista.length ? lista[0].campo : null);
            });
        }
        caja.removeAttribute('hidden');
    }

    /* ---------- resaltado de campos ---------- */

    function marcarCampo(id) {
        var campo = el(id);
        if (!campo) return;
        campo.classList.add('bovs-campo-error');
        campo.setAttribute('aria-invalid', 'true');
    }

    function limpiarCampo(id) {
        var campo = el(id);
        if (!campo) return;
        campo.classList.remove('bovs-campo-error', 'bovs-campo-error--pulso');
        campo.removeAttribute('aria-invalid');
    }

    function pintarMensaje(idMs, texto) {
        var caja = el(idMs);
        if (!caja) return;
        caja.innerHTML = texto || '';
        caja.style.display = texto ? 'block' : 'none';
    }

    function enfocar(id) {
        var campo = el(id);
        if (!campo) return;
        try {
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            try { campo.scrollIntoView(); } catch (e2) { /* sin scroll */ }
        }
        try { campo.focus({ preventScroll: true }); } catch (e3) {
            try { campo.focus(); } catch (e4) { /* sin foco */ }
        }
    }

    /* ---------- revisión ---------- */

    function faltantes() {

        var lista = [];
        var i;

        for (i = 0; i < REQUERIDOS.length; i++) {
            if (esVacio(val(REQUERIDOS[i].campo))) {
                lista.push(REQUERIDOS[i]);
            }
        }

        /* El nombre del padre/madre externo pasa a ser obligatorio cuando la
           carga es manual: es el único dato que queda del animal. */
        for (var rol in ROLES) {
            if (!Object.prototype.hasOwnProperty.call(ROLES, rol)) continue;
            if (!manualActivo(rol)) continue;

            var r = ROLES[rol];
            if (esVacio(val(r.nombre))) {
                lista.push({ campo: r.nombre, ms: r.msNombre, etiqueta: r.etiquetaItem });
            }
        }

        return lista;
    }

    function validar() {

        /* Se limpian primero todos los avisos para no dejar mensajes viejos */
        var i;
        for (i = 0; i < REQUERIDOS.length; i++) {
            pintarMensaje(REQUERIDOS[i].ms, '');
            limpiarCampo(REQUERIDOS[i].campo);
        }
        pintarMensaje(ROLES.madre.msNombre, '');
        pintarMensaje(ROLES.padre.msNombre, '');
        limpiarCampo(ROLES.madre.nombre);
        limpiarCampo(ROLES.padre.nombre);

        var lista = faltantes();

        if (!lista.length) {
            ocultarAviso();
            return true;
        }

        for (i = 0; i < lista.length; i++) {
            pintarMensaje(lista[i].ms, 'Valor Obligatorio');
            marcarCampo(lista[i].campo);
        }

        mostrarAviso(lista);
        enfocar(lista[0].campo);

        return false;
    }

    /* ---------- deducción de padres externos ---------- */

    function marcarExternos() {

        for (var rol in ROLES) {
            if (!Object.prototype.hasOwnProperty.call(ROLES, rol)) continue;

            var r = ROLES[rol];
            var codigo = val(r.codigo);
            var nombre = val(r.nombre);
            var chk = el(r.check);

            if (!chk) continue;

            /* Un animal del hato se identifica con el código BOVS-0NN-0NNN; si
               el código guardado no tiene ese formato se trata como externo. */
            if (codigo !== '' && !esCodigoBovs(codigo)) {
                chk.checked = true;
            } else if (codigo === '' && nombre !== '') {
                chk.checked = true;
            }
        }

        aplicarManual();
    }

    /* ---------- arranque ---------- */

    function conectar() {

        /* Estado inicial: si el formulario arranca bloqueado (la ficha en modo
           lectura hasta pulsar EDITAR), la carga manual de madre/padre también
           queda bloqueada. */
        var referencia = el('nombre_animal');
        if (referencia && referencia.disabled) {
            bloqueado = true;
        }

        for (var rol in ROLES) {
            if (!Object.prototype.hasOwnProperty.call(ROLES, rol)) continue;

            (function (nombreRol) {
                var r = ROLES[nombreRol];

                var chk = el(r.check);
                if (chk) {
                    chk.addEventListener('change', function () {
                        if (manualActivo(nombreRol)) {
                            mostrar(r.caja);
                        }
                        aplicarRol(nombreRol);
                        if (!faltantes().length) ocultarAviso();
                    });
                }

                var link = el(r.link);
                if (link) {
                    link.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        var c = el(r.check);
                        if (c) {
                            c.checked = true;
                            c.disabled = false;
                        }
                        mostrar(r.caja);
                        aplicarRol(nombreRol);
                        enfocar(r.nombre);
                    });
                }

                /* Al corregir, el aviso desaparece solo */
                var nombre = el(r.nombre);
                var codigo = el(r.codigo);
                [nombre, codigo].forEach(function (campo) {
                    if (!campo) return;
                    campo.addEventListener('input', function () {
                        if (!faltantes().length) ocultarAviso();
                    });
                });
            })(rol);
        }

        /* Cualquier cambio en los obligatorios actualiza el aviso */
        var form = document.querySelector('.crud-form form') || document.querySelector('.crud-form');
        if (form) {
            form.addEventListener('change', function () {
                var caja = el('bovs_faltantes');
                if (!caja || caja.hasAttribute('hidden')) return;

                var lista = faltantes();
                if (!lista.length) {
                    ocultarAviso();
                } else {
                    mostrarAviso(lista);
                }
            });
        }

        aplicarManual();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', conectar);
    } else {
        conectar();
    }

    window.BovsFicha = {
        requeridos: REQUERIDOS,
        faltantes: faltantes,
        validar: validar,
        manual: function (rol, on) {
            var c = el(ROLES[rol].check);
            if (c) {
                c.checked = !!on;
                c.disabled = false;
            }
            if (on) mostrar(ROLES[rol].caja);
            aplicarRol(rol);
        },
        aplicarManual: aplicarManual,
        bloquear: function (estado) {
            bloqueado = !!estado;
            aplicarManual();
        },
        marcarExternos: marcarExternos,
        ocultarAviso: ocultarAviso
    };
})();
