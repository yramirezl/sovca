/*!
 * controlsanitario-agrupado.js
 * Ubicación: /sovca/js/controlsanitario-agrupado.js
 *
 * Vista agrupada del listado de Control Sanitario (mismo patrón que
 * Servicios y Producción de Leche): un bloque por animal y, dentro, sus
 * controles ordenados del más reciente al más antiguo.
 *
 * El botón se declara en views/controlsanitario/index.php (#cs_vista_agrupada)
 * y los datos salen del mismo endpoint del listado (data_mcontrolsanitario),
 * así no se duplica ninguna lógica.
 *
 * No se edita en línea: cada fila enlaza al formulario frm_editar (el
 * formulario tiene evento dependiente del tipo, fechas y validaciones que
 * conviene no replicar aquí).
 */
(function (window, document, $) {
    'use strict';

    if (!$) return;

    var URL_DATOS = '../../data_json/data_mcontrolsanitario';
    var URL_EDIT  = 'frm_editar';
    var PARAM     = '%$%';

    // El rol ENCARGADO (3) no edita en este módulo.
    var PUEDE_EDITAR = (window.CS_PUEDE_EDITAR !== false);

    var grupos = [];      // se recalculan en cada carga (sin caché)
    var xhr    = null;
    var activa = false;
    var filtro = '';

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

    function num(valor) {
        return String(Number(valor || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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

    function hoyIso() {
        var d = new Date();
        var m = String(d.getMonth() + 1);
        var dia = String(d.getDate());
        if (m.length < 2) m = '0' + m;
        if (dia.length < 2) dia = '0' + dia;
        return d.getFullYear() + '-' + m + '-' + dia;
    }

    /** Diferencia en días entre dos fechas ISO (Y-m-d). */
    function diasHasta(iso) {
        if (!iso) return null;
        var a = iso.split('-'), b = hoyIso().split('-');
        var fa = Date.UTC(+a[0], +a[1] - 1, +a[2]);
        var fb = Date.UTC(+b[0], +b[1] - 1, +b[2]);
        return Math.round((fa - fb) / 86400000);
    }

    /* ------------------------------------------------------------------ */
    /* Agrupación                                                          */
    /* ------------------------------------------------------------------ */

    function agrupar(filas) {
        var mapa  = {};
        var orden = [];

        filas.forEach(function (r) {
            var codigo = String(r.codigo_rgb_id_animal || '').trim();
            if (!codigo) return;

            if (!mapa[codigo]) {
                mapa[codigo] = { codigo: codigo, nombre: '', arete: '', controles: [] };
                orden.push(codigo);
            }
            mapa[codigo].controles.push(r);
        });

        var lista = orden.map(function (c) { return mapa[c]; });

        lista.forEach(function (g) {
            // Más reciente primero; desempate por id para que el "último"
            // sea siempre el mismo registro.
            g.controles.sort(function (a, b) {
                var fa = String(a.fecha_control_orden || '');
                var fb = String(b.fecha_control_orden || '');
                if (fa !== fb) return fb.localeCompare(fa);
                return Number(b.id || 0) - Number(a.id || 0);
            });

            var ult = g.controles[0] || {};

            g.nombre           = String(ult.nombre_animal || '').trim();
            g.arete            = String(ult.nro_arete || '').trim();
            g.ultimo           = String(ult.fecha_control || '').trim();
            g.ultimo_orden     = String(ult.fecha_control_orden || '');
            g.proximo          = String(ult.fecha_prox_control || '').trim();
            g.proximo_orden    = String(ult.fecha_prox_orden || '');
            g.evento           = String(ult.evento || '').trim();
            g.estatus_animal   = String(ult.estatus_animal_nombre || '').trim();
            g.total            = g.controles.length;
            g.busqueda         = normaliza([
                g.codigo, g.nombre, g.arete,
                g.controles.map(function (c) { return c.evento || ''; }).join(' ')
            ].join(' '));
        });

        // Animales con el control más reciente primero.
        lista.sort(function (a, b) {
            return String(b.ultimo_orden || '').localeCompare(String(a.ultimo_orden || ''));
        });

        return lista;
    }

    /* ------------------------------------------------------------------ */
    /* Render                                                             */
    /* ------------------------------------------------------------------ */

    /** Estado del próximo control: vencido, por vencer o programado. */
    function chipProximo(iso, texto) {
        if (!iso) {
            return texto ? '<span class="ag-muted">' + esc(texto) + '</span>' : '—';
        }

        var dias = diasHasta(iso);
        var clase = 'ag-chip--ok';
        var etiqueta = 'Programado';

        if (dias < 0) {
            clase = 'ag-chip--bad';
            etiqueta = 'Vencido';
        } else if (dias <= 7) {
            clase = 'ag-chip--warn';
            etiqueta = 'Por vencer';
        }

        return '<span class="ag-chip ' + clase + '" title="' + esc(etiqueta + ': ' + (texto || iso)) + '">'
             + esc(texto || iso) + '</span>';
    }

    /** Estatus del animal (ACTIVO, DONADORA, VENDIDO, …). */
    function chipEstatus(estatus) {
        var valor = String(estatus || '').toUpperCase();
        if (!valor) return '—';

        var clase = 'ag-chip--neutro';
        if (valor === 'ACTIVO' || valor.indexOf('DONADOR') === 0) clase = 'ag-chip--ok';
        else if (valor === 'MUERTO' || valor === 'DESCARTE' || valor === 'EXTRAVIADO') clase = 'ag-chip--bad';

        return '<span class="ag-chip ' + clase + '">' + esc(valor) + '</span>';
    }

    function filasHtml(g) {
        var html = '';

        g.controles.forEach(function (c) {
            var observaciones = String(c.observaciones || '').trim();
            var accion = PUEDE_EDITAR
                ? '<a class="crud-btn crud-btn--primary ag-btn-mini" href="' + urlEditar(c.id) + '"'
                  + ' title="Editar este control"><i class="fa fa-pencil"></i><span>Editar</span></a>'
                : '—';

            html += '<tr>'
                 +    '<td data-l="Fecha control" class="ag-num"><strong>' + esc(c.fecha_control || '—') + '</strong></td>'
                 +    '<td data-l="Evento">'
                 +      esc(c.evento || '—')
                 +      (observaciones ? '<br><small class="ag-muted" title="' + esc(observaciones) + '">'
                                          + esc(observaciones) + '</small>' : '')
                 +    '</td>'
                 +    '<td data-l="Próximo control" class="ag-num">'
                 +      (c.fecha_prox_orden
                          ? chipProximo(c.fecha_prox_orden, c.fecha_prox_control)
                          : esc(c.fecha_prox_control || '—'))
                 +    '</td>'
                 +    '<td data-l="Responsable">' + esc(c.responsable || '—') + '</td>'
                 +    '<td data-l="Acción" class="ag-acc">' + accion + '</td>'
                 +  '</tr>';
        });

        return html;
    }

    function dato(label, valor, titulo) {
        return '<div class="ag-dato">'
             +     '<span class="ag-dato__label">' + esc(label) + '</span>'
             +     '<span class="ag-dato__valor"'
             +       (titulo ? ' title="' + esc(titulo) + '"' : '') + '>' + valor + '</span>'
             +   '</div>';
    }

    function bloqueHtml(g, indice) {
        var meta = [];
        if (g.arete) meta.push('Arete ' + g.arete);

        return '<article class="ag-animal" data-indice="' + indice + '" data-codigo="' + esc(g.codigo) + '">'
             +   '<div class="ag-animal__cab" role="button" tabindex="0" aria-expanded="false"'
             +        ' aria-label="Ver los controles de ' + esc(g.codigo) + '">'
             +     '<span class="ag-animal__chevron"><i class="fa fa-chevron-right"></i></span>'
             +     '<div class="ag-animal__id">'
             +       '<span class="ag-dato__label">Animal</span>'
             +       '<span class="ag-animal__linea">'
             +         '<strong class="ag-cod" title="' + esc(g.codigo) + '">' + esc(g.codigo) + '</strong>'
             +         (g.nombre ? '<span class="ag-animal__nombre" title="' + esc(g.nombre) + '">' + esc(g.nombre) + '</span>' : '')
             +       '</span>'
             +       (meta.length ? '<span class="ag-animal__meta" title="' + esc(meta.join(' · ')) + '">' + esc(meta.join(' · ')) + '</span>' : '')
             +     '</div>'
             +     '<div class="ag-animal__datos">'
             +       dato('Controles', num(g.total))
             +       dato('Último control', esc(g.ultimo || '—'))
             +       dato('Próximo control', g.proximo_orden
                        ? chipProximo(g.proximo_orden, g.proximo)
                        : esc(g.proximo || '—'))
             +       dato('Último evento', esc(g.evento || '—'), g.evento)
             +       dato('Estatus del animal', chipEstatus(g.estatus_animal))
             +     '</div>'
             +   '</div>'
             +   '<div class="ag-animal__detalle" hidden></div>'
             + '</article>';
    }

    function visibles() {
        if (!filtro) return grupos;
        var q = normaliza(filtro);
        return grupos.filter(function (g) { return g.busqueda.indexOf(q) !== -1; });
    }

    function render() {
        var lista = visibles();

        if (!grupos.length) {
            $lista.innerHTML = '<div class="ag-vacio"><i class="fa fa-inbox"></i>'
                             + 'No hay controles sanitarios registrados en este fundo.</div>';
            $resumen.textContent = 'Sin controles';
            return;
        }

        if (!lista.length) {
            $lista.innerHTML = '<div class="ag-vacio"><i class="fa fa-search"></i>'
                             + 'No se encontraron animales para «' + esc(filtro) + '».</div>';
            $resumen.textContent = 'Sin resultados';
            return;
        }

        var html = '';
        var totalControles = 0;
        lista.forEach(function (g, i) {
            totalControles += g.total;
            html += bloqueHtml(g, i);
        });

        $lista.innerHTML = html;

        $resumen.textContent = num(lista.length) + (lista.length === 1 ? ' animal · ' : ' animales · ')
                             + num(totalControles) + (totalControles === 1 ? ' control' : ' controles');
    }

    /* ------------------------------------------------------------------ */
    /* Carga                                                              */
    /* ------------------------------------------------------------------ */

    function cargar() {
        if (xhr && xhr.readyState !== 4) {
            try { xhr.abort(); } catch (e) { /* noop */ }
        }

        if ($lista.querySelector('.ag-animal')) {
            $lista.classList.add('is-cargando');
        } else {
            $lista.innerHTML = '<div class="ag-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando controles…</div>';
        }

        xhr = $.ajax({
                url: URL_DATOS,
                dataType: 'json',
                cache: false
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
                $lista.innerHTML = '<div class="ag-vacio"><i class="fa fa-exclamation-triangle"></i>'
                                 + 'No se pudo cargar la vista agrupada. Recargue la página.</div>';
            });
    }

    /* ------------------------------------------------------------------ */
    /* Detalle por animal                                                 */
    /* ------------------------------------------------------------------ */

    function abrir(articulo) {
        var indice = parseInt(articulo.getAttribute('data-indice'), 10);
        var grupo  = visibles()[indice];
        if (!grupo) return;

        var detalle = articulo.querySelector('.ag-animal__detalle');

        if (!detalle.getAttribute('data-render')) {
            detalle.innerHTML = '<table class="ag-tabla">'
                + '<thead><tr>'
                +   '<th>Fecha control</th><th>Evento</th><th>Próximo control</th>'
                +   '<th>Responsable</th><th></th>'
                + '</tr></thead>'
                + '<tbody>' + filasHtml(grupo) + '</tbody>'
                + '</table>';
            detalle.setAttribute('data-render', '1');
        }

        detalle.hidden = false;
        articulo.classList.add('is-open');
        articulo.querySelector('.ag-animal__cab').setAttribute('aria-expanded', 'true');
    }

    function cerrar(articulo) {
        articulo.querySelector('.ag-animal__detalle').hidden = true;
        articulo.classList.remove('is-open');
        articulo.querySelector('.ag-animal__cab').setAttribute('aria-expanded', 'false');
    }

    function alternar(articulo) {
        if (articulo.classList.contains('is-open')) cerrar(articulo);
        else abrir(articulo);
    }

    /* ------------------------------------------------------------------ */
    /* Activación / desactivación                                          */
    /* ------------------------------------------------------------------ */

    function guardarYOcultar(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('data-cs-prev', el.style.display || '');
        el.style.display = 'none';
    }

    function restaurar(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var prev = el.getAttribute('data-cs-prev');
        el.style.display = (prev === null) ? '' : prev;
        el.removeAttribute('data-cs-prev');
    }

    function activar() {
        activa = true;
        $cont.hidden = false;
        if ($wrap) $wrap.style.display = 'none';

        // En la vista agrupada la edición/eliminación se hace desde cada
        // control; los botones del listado plano no aplican.
        guardarYOcultar('edit');
        guardarYOcultar('delete');

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

        $btn.innerHTML = '<i class="fa fa-sitemap"></i> <span>Vista agrupada</span>';
        $btn.classList.remove('crud-btn--success');
        $btn.classList.add('crud-btn--info');
        $btn.setAttribute('aria-pressed', 'false');

        // DataTables necesita recalcular anchos al volver a mostrarse.
        try {
            if (window.CS_CRUD && window.CS_CRUD.table) {
                window.CS_CRUD.table.api().columns.adjust();
                if (window.CS_CRUD.autoSize) window.CS_CRUD.autoSize();
            }
        } catch (e) { /* noop */ }
    }

    /* ------------------------------------------------------------------ */
    /* Montaje                                                            */
    /* ------------------------------------------------------------------ */

    function montar() {
        $btn = document.getElementById('cs_vista_agrupada');
        if (!$btn) return false;

        var card = document.querySelector('.crud-page .crud-card');
        $wrap = card ? card.querySelector('.crud-table-wrap') : null;
        if (!card || !$wrap) return false;

        $cont = document.createElement('div');
        $cont.className = 'ag-agrupada';
        $cont.id = 'cs-agrupada';
        $cont.hidden = true;
        $cont.innerHTML =
              '<div class="ag-barra">'
            +   '<div class="ag-buscador">'
            +     '<i class="fa fa-search"></i>'
            +     '<input type="text" id="cs-buscar" autocomplete="off"'
            +            ' placeholder="Buscar por código, nombre, arete o evento…">'
            +   '</div>'
            +   '<span class="ag-resumen" id="cs-resumen">—</span>'
            +   '<button type="button" class="crud-btn crud-btn--ghost ag-todo" id="cs-todo">'
            +     '<i class="fa fa-expand"></i><span>Expandir todo</span>'
            +   '</button>'
            + '</div>'
            + '<div class="ag-lista" id="cs-lista"></div>';

        $wrap.parentNode.insertBefore($cont, $wrap.nextSibling);

        $lista    = document.getElementById('cs-lista');
        $buscador = document.getElementById('cs-buscar');
        $resumen  = document.getElementById('cs-resumen');
        $btnTodo  = document.getElementById('cs-todo');

        $btn.addEventListener('click', function () {
            if (activa) desactivar();
            else activar();
        });

        $buscador.addEventListener('input', function () {
            filtro = this.value;
            render();
        });

        $btnTodo.addEventListener('click', function () {
            var articulos = $lista.querySelectorAll('.ag-animal');
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
            if (evento.target.closest('a, button')) return;

            var cab = evento.target.closest('.ag-animal__cab');
            if (!cab) return;

            alternar(cab.parentNode);
        });

        $lista.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' && evento.key !== ' ') return;

            var cab = evento.target.closest('.ag-animal__cab');
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
