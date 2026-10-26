/*!
 * servicios-agrupado.js
 * Ubicación: /sovca/js/servicios-agrupado.js
 *
 * Vista agrupada del listado de Servicios (mismo patrón visual que
 * Producción de Leche): un bloque por hembra y, dentro, sus servicios
 * ordenados del más reciente al más antiguo.
 *
 * El botón se declara en views/servicios/index.php (#vista_agrupada).
 * Los datos salen del mismo endpoint del listado (data_mservicios), así
 * no se duplica la lógica de "condición" que ya vive allí.
 *
 * Edición: cada fila enlaza al formulario frm_editar. No se edita en
 * línea porque el formulario maneja 10 campos, tres de ellos selects
 * dependientes del fundo (tipo de servicio, raza, aptitud), dos fechas
 * con validaciones cruzadas y el recálculo de estado fisiológico /
 * fecha de secado; replicarlo aquí duplicaría esa lógica y podría
 * desincronizarse del formulario oficial.
 */
(function (window, document, $) {
    'use strict';

    if (!$) return;

    var URL_DATOS = '../../data_json/data_mservicios';
    var URL_EDIT  = 'frm_editar';
    var PARAM     = '%$%';

    // El módulo oculta "Editar" para el rol ENCARGADO (3); la vista agrupada
    // debe respetar la misma regla.
    var PUEDE_EDITAR = (window.SV_PUEDE_EDITAR !== false);

    var grupos   = [];     // grupos calculados en cada carga (sin caché)
    var xhr      = null;   // petición en curso
    var activa   = false;
    var filtro   = '';

    var $btn, $cont, $lista, $buscador, $resumen, $btnTodo, $wrap;

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    function esc(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function num(valor, dec) {
        dec = (dec === undefined) ? 0 : dec;
        var partes = Number(valor || 0).toFixed(dec).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    function btoaSeguro(valor) {
        try { return window.btoa(String(valor)); }
        catch (e) { return String(valor); }
    }

    function urlEditar(id) {
        return URL_EDIT + '?' + encodeURIComponent(PARAM) + '=' + encodeURIComponent(btoaSeguro(id));
    }

    function normaliza(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    /* ------------------------------------------------------------------ */
    /* Agrupación                                                          */
    /* ------------------------------------------------------------------ */

    function agrupar(filas) {
        var mapa  = {};
        var orden = [];

        filas.forEach(function (r) {
            var codigo = String(r.codigo_rgb_id_animal_hembra || '').trim();
            if (!codigo) return;

            if (!mapa[codigo]) {
                mapa[codigo] = { codigo: codigo, nombre: '', arete: '', servicios: [] };
                orden.push(codigo);
            }
            mapa[codigo].servicios.push(r);
        });

        var lista = orden.map(function (c) { return mapa[c]; });

        lista.forEach(function (g) {
            // Más reciente primero dentro de cada animal (desempate por id, para
            // que el "último registro" sea siempre el mismo).
            g.servicios.sort(function (a, b) {
                var fa = String(a.f_servicio_orden || '');
                var fb = String(b.f_servicio_orden || '');
                if (fa !== fb) return fb.localeCompare(fa);
                return Number(b.id || 0) - Number(a.id || 0);
            });

            // El más reciente por fecha encabeza la lista del animal…
            var ult = g.servicios[0] || {};

            // …pero el estatus reproductivo se toma del registro VIGENTE
            // (el que el endpoint marca con vigente = 1), porque es el único
            // que representa la situación actual de la hembra.
            var vigente = ult;
            for (var k = 0; k < g.servicios.length; k++) {
                if (Number(g.servicios[k].vigente) === 1) { vigente = g.servicios[k]; break; }
            }

            g.nombre            = String(ult.nombre_h || '').trim();
            g.arete             = String(ult.nro_arete || '').trim();
            g.ultimo            = ult.f_servicio || '';
            g.ultimo_orden      = String(ult.f_servicio_orden || '');
            g.estado_fisio      = String(vigente.estado_fisiologico || '').trim();
            g.estatus           = String(vigente.estatus_servicio || '').trim();
            g.condicion         = String(vigente.condicion || '').trim();
            g.estatus_animal    = String(vigente.estatus_animal_nombre || '').trim();
            // La condición puede venir marcada como "[* ... *]": esos signos
            // son señalización interna del listado, no se muestran.
            g.condicion_limpia  = g.condicion.replace(/^\[\*\s*/, '').replace(/\s*\*\]$/, '');
            g.total             = g.servicios.length;
            g.busqueda          = normaliza([g.codigo, g.nombre, g.arete].join(' '));
        });

        // Animales con el servicio más reciente primero.
        lista.sort(function (a, b) {
            return String(b.ultimo_orden || '').localeCompare(String(a.ultimo_orden || ''));
        });

        return lista;
    }

    /* ------------------------------------------------------------------ */
    /* Render                                                             */
    /* ------------------------------------------------------------------ */

    function chip(estatus) {
        var valor = String(estatus || '').toUpperCase();
        if (valor === 'ABIERTO') return '<span class="sv-chip sv-chip--abierto">Abierto</span>';
        if (valor === 'CERRADO') return '<span class="sv-chip sv-chip--cerrado">Cerrado</span>';
        return valor ? '<span class="sv-chip sv-chip--neutro">' + esc(valor) + '</span>' : '—';
    }

    /* Estatus del animal (ACTIVO, VENDIDO, MUERTO, …) */
    function chipEstatus(estatus) {
        var valor = String(estatus || '').toUpperCase();
        if (!valor) return '—';

        var clase = 'sv-chip--neutro';
        if (valor === 'ACTIVO') clase = 'sv-chip--abierto';
        else if (valor === 'MUERTO' || valor === 'DESCARTE' || valor === 'EXTRAVIADO') clase = 'sv-chip--cerrado';

        return '<span class="sv-chip ' + clase + '">' + esc(valor) + '</span>';
    }

    function filasHtml(g) {
        var html = '';

        g.servicios.forEach(function (s, i) {
            var condicion = String(s.condicion || '').replace(/^\[\*\s*/, '').replace(/\s*\*\]$/, '');
            var fisio     = String(s.estado_fisiologico || '').trim();

            /* El endpoint marca con 'vigente' el último registro de la hembra
               (max id dentro del fundo). Ese es el único que representa su
               estatus reproductivo actual; los demás son ciclos ya cerrados. */
            var esVigente  = (s.vigente === undefined || s.vigente === null)
                           ? (i === 0)
                           : (Number(s.vigente) === 1);
            var permitido  = (Number(s.editable) === 1);
            var accion;

            if (!esVigente) {
                accion = '<span class="sv-ref" title="Registro de referencia: solo el último servicio es editable">Referencia</span>';
            } else if (!PUEDE_EDITAR) {
                accion = '—';
            } else if (permitido) {
                accion = '<a class="crud-btn crud-btn--primary sv-btn-mini" href="' + urlEditar(s.id) + '"'
                       + ' title="Editar el último servicio de la hembra">'
                       + '<i class="fa fa-pencil"></i><span>Editar</span></a>';
            } else {
                accion = '<span class="sv-ref sv-ref--bloqueado"'
                       + ' title="Ciclo cerrado o servicio no vigente: el formulario se abre en modo solo lectura">'
                       + '<i class="fa fa-lock"></i> Solo lectura</span>';
            }

            html += '<tr' + (esVigente ? ' class="sv-fila-vigente"' : '') + '>'
                 +    '<td data-l="Fecha" class="sv-num"><strong>' + esc(s.f_servicio || '—') + '</strong></td>'
                 +    '<td data-l="Nro servicio" class="sv-num">' + esc(s.nro_servicio || '—') + '</td>'
                 +    '<td data-l="Servicio">'
                 +      (esVigente
                         ? chip(s.estatus_servicio)
                         : '<span class="sv-chip sv-chip--neutro" title="Servicio de un ciclo ya finalizado">Histórico</span>')
                 +    '</td>'
                 +    '<td data-l="Est. fisiológico">' + esc(fisio || '—') + '</td>'
                 +    '<td data-l="Estatus reproductivo">'
                 +      (esVigente
                         ? esc(condicion || '—')
                         : '<span class="sv-muted">' + esc(condicion || 'Ciclo cerrado') + '</span>')
                 +    '</td>'
                 +    '<td data-l="Macho" class="sv-macho">' + esc(s.nombre_m || '—') + '</td>'
                 +    '<td data-l="Acción" class="sv-acc">' + accion + '</td>'
                 +  '</tr>';
        });

        return html;
    }

    function bloqueHtml(g, indice) {
        var meta = [];
        if (g.arete) meta.push('Arete ' + g.arete);

        return '<article class="sv-animal" data-indice="' + indice + '" data-codigo="' + esc(g.codigo) + '">'
             +   '<div class="sv-animal__cab" role="button" tabindex="0" aria-expanded="false"'
             +        ' aria-label="Ver los servicios de ' + esc(g.codigo) + '">'
             +     '<span class="sv-animal__chevron"><i class="fa fa-chevron-right"></i></span>'
             +     '<div class="sv-animal__id">'
             +       '<span class="sv-dato__label">Animal</span>'
             +       '<span class="sv-animal__linea">'
             +         '<strong class="sv-cod" title="' + esc(g.codigo) + '">' + esc(g.codigo) + '</strong>'
             +         (g.nombre ? '<span class="sv-animal__nombre" title="' + esc(g.nombre) + '">' + esc(g.nombre) + '</span>' : '')
             +       '</span>'
             +       (meta.length ? '<span class="sv-animal__meta" title="' + esc(meta.join(' · ')) + '">' + esc(meta.join(' · ')) + '</span>' : '')
             +     '</div>'
             +     '<div class="sv-animal__datos">'
             +       dato('Servicios', num(g.total))
             +       dato('Último', esc(g.ultimo || '—'))
             +       dato('Servicio', chip(g.estatus))
             +       dato('Est. fisiológico', esc(g.estado_fisio || '—'), g.estado_fisio)
             +       dato('Estatus reproductivo', esc(g.condicion_limpia || '—'), g.condicion_limpia)
             +       dato('Estatus del animal', chipEstatus(g.estatus_animal))
             +     '</div>'
             +   '</div>'
             +   '<div class="sv-animal__detalle" hidden></div>'
             + '</article>';
    }

    function dato(label, valor, titulo) {
        return '<div class="sv-dato">'
             +     '<span class="sv-dato__label">' + esc(label) + '</span>'
             +     '<span class="sv-dato__valor"'
             +       (titulo ? ' title="' + esc(titulo) + '"' : '') + '>' + valor + '</span>'
             +   '</div>';
    }

    function visibles() {
        if (!filtro) return grupos;
        var q = normaliza(filtro);
        return grupos.filter(function (g) { return g.busqueda.indexOf(q) !== -1; });
    }

    function render() {
        var lista = visibles();

        if (!grupos.length) {
            $lista.innerHTML = '<div class="sv-vacio"><i class="fa fa-inbox"></i>'
                             + 'No hay servicios registrados en este fundo.</div>';
            $resumen.textContent = 'Sin servicios';
            return;
        }

        if (!lista.length) {
            $lista.innerHTML = '<div class="sv-vacio"><i class="fa fa-search"></i>'
                             + 'No se encontraron animales para «' + esc(filtro) + '».</div>';
            $resumen.textContent = 'Sin resultados';
            return;
        }

        var html = '';
        var totalServicios = 0;
        lista.forEach(function (g, i) {
            totalServicios += g.total;
            html += bloqueHtml(g, i);
        });

        $lista.innerHTML = html;

        var etAnimales = (lista.length === 1) ? 'animal' : 'animales';
        var etServicios = (totalServicios === 1) ? 'servicio' : 'servicios';
        $resumen.textContent = num(lista.length) + ' ' + etAnimales + ' · '
                             + num(totalServicios) + ' ' + etServicios;
    }

    /* ------------------------------------------------------------------ */
    /* Carga                                                              */
    /* ------------------------------------------------------------------ */

    /*
     * Sin caché: cada vez que se abre la vista se piden los datos frescos
     * (el endpoint ya no hace consultas N+1, así que es barato). Si había
     * una petición en curso se cancela para no pintar datos viejos.
     */
    function cargar() {
        if (xhr && xhr.readyState !== 4) {
            try { xhr.abort(); } catch (e) { /* noop */ }
        }

        if ($lista.querySelector('.sv-animal')) {
            $lista.classList.add('is-cargando');
        } else {
            $lista.innerHTML = '<div class="sv-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando servicios…</div>';
        }

        xhr = $.ajax({
                url: URL_DATOS,
                dataType: 'json',
                cache: false   // fuerza una lectura nueva en cada apertura
            })
            .done(function (resp) {
                xhr = null;
                $lista.classList.remove('is-cargando');

                if (!resp || !resp.length) {
                    grupos = [];
                    render();
                    return;
                }

                grupos = agrupar(resp);
                render();
            })
            .fail(function (jqXHR, estado) {
                if (estado === 'abort') return;

                xhr = null;
                $lista.classList.remove('is-cargando');
                $lista.innerHTML = '<div class="sv-vacio"><i class="fa fa-exclamation-triangle"></i>'
                                 + 'No se pudo cargar la vista agrupada. Recargue la página.</div>';
            });
    }

    /* ------------------------------------------------------------------ */
    /* Detalle por animal (se construye la primera vez)                   */
    /* ------------------------------------------------------------------ */

    function abrir(articulo) {
        var indice = parseInt(articulo.getAttribute('data-indice'), 10);
        var grupo  = visibles()[indice];
        if (!grupo) return;

        var detalle = articulo.querySelector('.sv-animal__detalle');

        if (!detalle.getAttribute('data-render')) {
            detalle.innerHTML = '<table class="sv-tabla">'
                + '<thead><tr>'
                +   '<th>Fecha</th><th>Nro</th><th>Servicio</th>'
                +   '<th>Est. fisiológico</th><th>Estatus reproductivo</th><th>Macho</th><th></th>'
                + '</tr></thead>'
                + '<tbody>' + filasHtml(grupo) + '</tbody>'
                + '</table>';
            detalle.setAttribute('data-render', '1');
        }

        detalle.hidden = false;
        articulo.classList.add('is-open');
        articulo.querySelector('.sv-animal__cab').setAttribute('aria-expanded', 'true');
    }

    function cerrar(articulo) {
        articulo.querySelector('.sv-animal__detalle').hidden = true;
        articulo.classList.remove('is-open');
        articulo.querySelector('.sv-animal__cab').setAttribute('aria-expanded', 'false');
    }

    function alternar(articulo) {
        if (articulo.classList.contains('is-open')) cerrar(articulo);
        else abrir(articulo);
    }

    /* ------------------------------------------------------------------ */
    /* Activación / desactivación de la vista                             */
    /* ------------------------------------------------------------------ */

    function guardarYOcultar(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('data-sv-prev', el.style.display || '');
        el.style.display = 'none';
    }

    function restaurar(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var prev = el.getAttribute('data-sv-prev');
        el.style.display = (prev === null) ? '' : prev;
        el.removeAttribute('data-sv-prev');
    }

    function activar() {
        activa = true;
        $cont.hidden = false;
        if ($wrap) $wrap.style.display = 'none';

        guardarYOcultar('edit');
        guardarYOcultar('delete');
        guardarYOcultar('control');

        $btn.innerHTML = '<i class="fa fa-table"></i> <span>Vista lista</span>';
        $btn.classList.remove('crud-btn--info');
        $btn.classList.add('crud-btn--success');
        $btn.setAttribute('aria-pressed', 'true');

        cargar();
    }

    function desactivar() {
        activa = false;
        $cont.hidden = true;
        if ($wrap) $wrap.style.display = '';

        restaurar('edit');
        restaurar('delete');
        restaurar('control');

        $btn.innerHTML = '<i class="fa fa-sitemap"></i> <span>Vista agrupada</span>';
        $btn.classList.remove('crud-btn--success');
        $btn.classList.add('crud-btn--info');
        $btn.setAttribute('aria-pressed', 'false');

        // DataTables necesita recalcular anchos al volver a mostrarse.
        try {
            if (window.SV_CRUD && window.SV_CRUD.table) {
                window.SV_CRUD.table.api().columns.adjust();
                if (window.SV_CRUD.autoSize) window.SV_CRUD.autoSize();
            }
        } catch (e) { /* noop */ }
    }

    /* ------------------------------------------------------------------ */
    /* Montaje                                                            */
    /* ------------------------------------------------------------------ */

    function montar() {
        $btn = document.getElementById('vista_agrupada');
        if (!$btn) return false;

        var card = document.querySelector('.crud-page .crud-card');
        $wrap = card ? card.querySelector('.crud-table-wrap') : null;
        if (!card || !$wrap) return false;

        $cont = document.createElement('div');
        $cont.className = 'sv-agrupada';
        $cont.id = 'sv-agrupada';
        $cont.hidden = true;
        $cont.innerHTML =
              '<div class="sv-barra">'
            +   '<div class="sv-buscador">'
            +     '<i class="fa fa-search"></i>'
            +     '<input type="text" id="sv-buscar" autocomplete="off"'
            +            ' placeholder="Buscar por código, nombre o arete…">'
            +   '</div>'
            +   '<span class="sv-resumen" id="sv-resumen">—</span>'
            +   '<button type="button" class="crud-btn crud-btn--ghost" id="sv-todo">'
            +     '<i class="fa fa-expand"></i><span>Expandir todo</span>'
            +   '</button>'
            + '</div>'
            + '<div class="sv-lista" id="sv-lista"></div>';

        $wrap.parentNode.insertBefore($cont, $wrap.nextSibling);

        $lista    = document.getElementById('sv-lista');
        $buscador = document.getElementById('sv-buscar');
        $resumen  = document.getElementById('sv-resumen');
        $btnTodo  = document.getElementById('sv-todo');

        /* --- Eventos --- */
        $btn.addEventListener('click', function () {
            if (activa) desactivar();
            else activar();
        });

        $buscador.addEventListener('input', function () {
            filtro = this.value;
            render();
        });

        $btnTodo.addEventListener('click', function () {
            var articulos = $lista.querySelectorAll('.sv-animal');
            var todosAbiertos = articulos.length > 0
                && Array.prototype.every.call(articulos, function (a) {
                    return a.classList.contains('is-open');
                });

            Array.prototype.forEach.call(articulos, function (a) {
                if (todosAbiertos) cerrar(a);
                else abrir(a);
            });

            $btnTodo.innerHTML = todosAbiertos
                ? '<i class="fa fa-expand"></i><span>Expandir todo</span>'
                : '<i class="fa fa-compress"></i><span>Contraer todo</span>';
        });

        $lista.addEventListener('click', function (evento) {
            // Los enlaces y botones de acción no deben plegar el bloque.
            if (evento.target.closest('a, button')) return;

            var cab = evento.target.closest('.sv-animal__cab');
            if (!cab) return;

            alternar(cab.parentNode);
        });

        $lista.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' && evento.key !== ' ') return;

            var cab = evento.target.closest('.sv-animal__cab');
            if (!cab) return;

            evento.preventDefault();
            alternar(cab.parentNode);
        });

        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', montar);
    } else {
        montar();
    }

})(window, document, window.jQuery);
