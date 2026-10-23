/*!
 * crud-index.js
 * Ubicación: /bovs/js/crud-index.js
 */
(function (window, $) {
    'use strict';

    if (!$) {
        if (window.console) console.error('CrudIndex: jQuery no está disponible.');
        return;
    }

    /* =====================================================================
       RENDERS — helpers para transformar valores en HTML legible
       Cada función recibe (data, type) y devuelve el valor original
       cuando type !== 'display' (para ordenamiento y búsqueda correctos).
       ===================================================================== */
    var renders = {
        status: function (data, type) {
            if (type !== 'display') return data;
            return Number(data) === 1
                ? '<span class="crud-badge crud-badge--active"><i class="fa fa-check"></i> Activo</span>'
                : '<span class="crud-badge crud-badge--inactive"><i class="fa fa-times"></i> Inactivo</span>';
        },
        siNo: function (data, type) {
            if (type !== 'display') return data;
            return Number(data) === 1
                ? '<span class="crud-badge crud-badge--yes">Sí</span>'
                : '<span class="crud-badge crud-badge--no">No</span>';
        },
        sexo: function (data, type) {
            if (type !== 'display') return data;
            var v = String(data || '').toUpperCase();
            if (v === 'HEMBRA' || v === 'H') {
                return '<span class="crud-badge crud-badge--hembra">Hembra</span>';
            }
            if (v === 'MACHO' || v === 'M') {
                return '<span class="crud-badge crud-badge--macho">Macho</span>';
            }
            return data;
        },
        bool: function (data, type) {
            if (type !== 'display') return data;
            return (data === true || data === 1 || data === '1')
                ? '<span class="crud-badge crud-badge--yes">Sí</span>'
                : '<span class="crud-badge crud-badge--no">No</span>';
        },
        mayusculas: function (data, type) {
            if (type !== 'display') return data;
            return data ? String(data).toUpperCase() : '';
        },
        numero: function (data, type) {
            if (type !== 'display') return data;
            var n = parseFloat(data);
            if (isNaN(n)) return data;
            return n.toLocaleString('es-VE');
        }
    };

    var DEFAULTS = {
        tableSelector:   '#tabla',
        messageSelector: '.message',
        countSelector:   '#crud-count',
        searchSelector:  null,

        ajaxUrl:      null,
        controller:   null,
        deleteAction: 'delete',
        deleteRecordField: 'record',

        rol: null,
        acceso: 1,
        auditorRoles: [3],

        buttons: {
            add:      '#add',
            edit:     '#edit',
            view:     '#ver',
            download: '#data',
            import:   '#importar',
            delete:   '#delete'
        },

        routes: {
            add: null, edit: null, view: null,
            import: null, download: null, home: null
        },

        paramName: '%$%',

        columns:          [],
        columnDefs:       [],
        order:            [[0, 'asc']],
        scrollX:          true,
        scrollY:          '400px',
        scrollCollapse:   false,
        rowCallback:      null,

        idField: 'id',
        dblClickEdit: false,
        beforeEdit:   null,
        beforeDelete: null,

        searchPlaceholder: 'Buscar…',
        searchDebounceMs:  200,

        autoSizeMinHeight: 200,
        autoSizeReserveBottom: 70,

        autoRedirect: { enabled: false, ms: 300000, url: null },

        messages: {
            selectOne:     '¡Seleccione un registro!',
            selectDelete:  '¡Seleccione el registro a eliminar!',
            confirmDelete: '¿Desea eliminar este registro?',
            downloadTitle: '¿Descargar la data?',
            maxRecords:    '¡Usted Alcanzó el Máximo de Registros!',
            close: 'Cerrar',
            yes:   'Sí',
            no:    'No',
            ok:    'Ok',
            records: 'registros'
        }
    };

    function mergeConfig(userCfg) {
        return $.extend(true, {}, DEFAULTS, userCfg || {});
    }

    function encodeParam(value) {
        try { return window.btoa(String(value)); }
        catch (e) { return String(value); }
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function dialogInfo(title) {
        $.confirm({
            title: title,
            content: false,
            confirmButton: 'Cerrar',
            cancelButton: false,
            closeIcon: false,
            confirmButtonClass: 'btn-success'
        });
    }

    function measureAvailableHeight($scrollBody, reserveBottom, minHeight) {
        if (!$scrollBody || !$scrollBody.length) return null;

        var vh = window.innerHeight || document.documentElement.clientHeight;
        var rect = $scrollBody[0].getBoundingClientRect();
        var top = Math.max(rect.top, 0);

        var $wrap   = $scrollBody.closest('.crud-table-wrap');
        var $bottom = $wrap.find('.crud-table-bottom');
        var $pag    = $wrap.find('.dataTables_paginate');
        var bottomH = $bottom.length ? $bottom.outerHeight(true) : ($pag.length ? $pag.outerHeight(true) : 0);

        var wrapPadB = parseInt($wrap.css('padding-bottom'), 10) || 18;
        var margin   = 10;

        var below = bottomH + wrapPadB + reserveBottom + margin;
        var avail = vh - top - below;

        return Math.max(avail, minHeight);
    }

    function init(userCfg) {
        var c = mergeConfig(userCfg);
        var $table = $(c.tableSelector);
        if (!$table.length) return null;

        /* ---------- Control de acceso ---------- */
        if (c.auditorRoles.indexOf(Number(c.rol)) !== -1) {
            $(c.buttons.delete).hide();
            $(c.buttons.edit).hide();
        }
        if (Number(c.acceso) !== 1) {
            $(c.buttons.add).hide();
            dialogInfo(c.messages.maxRecords);
        }

        /* ---------- DataTable ---------- */
        var usingExternalSearch = !!(c.searchSelector && $(c.searchSelector).length);

        var dtConfig = {
            dom: usingExternalSearch
                    ? '<"crud-table-top"l>rt<"crud-table-bottom"ip>'
                    : '<"crud-table-top"lf>rt<"crud-table-bottom"ip>',
            ajax: { url: c.ajaxUrl, dataSrc: '' },
            columns: c.columns,
            aoColumnDefs: c.columnDefs,
            order: c.order,
            scrollX: c.scrollX,
            scrollY: c.scrollY,
            scrollCollapse: c.scrollCollapse,

            /* Paginador compacto: Anterior · 1 2 3 … n · Siguiente */
            pagingType: 'simple_numbers',

            language: {
                search: '',
                searchPlaceholder: c.searchPlaceholder,
                lengthMenu: '_MENU_ por página',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Sin registros',
                infoFiltered: '(filtrado de _MAX_ registros)',
                zeroRecords: 'No se encontraron resultados',
                thousands: '.',
                decimal: ',',
                paginate: {
                    first: 'Primera',
                    last: 'Última',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        };

        dtConfig.fnRowCallback = function (nRow, mData, iDisplayIndex) {
            if (c.idField && mData && typeof mData === 'object' && (c.idField in mData)) {
                $(nRow).attr('data-id', mData[c.idField]);
            }
            if (typeof c.rowCallback === 'function') {
                return c.rowCallback(nRow, mData, iDisplayIndex, $);
            }
            return nRow;
        };

        var table = $table.dataTable(dtConfig);

        function applyAutoSize() {
            var $scrollBody = $table.closest('.crud-table-wrap').find('.dataTables_scrollBody');
            var h = measureAvailableHeight($scrollBody, c.autoSizeReserveBottom, c.autoSizeMinHeight);
            if (h === null) return;

            var prevH = parseFloat($scrollBody.css('height'));
            $scrollBody.css({
                'height':    h + 'px',
                'max-height': h + 'px'
            });

            /* IMPORTANTE: no forzar el ancho de .dataTables_scrollHead con el
               scrollWidth del cuerpo. Al hacerlo, el contenedor del encabezado
               queda tan ancho como la tabla completa y pierde su capacidad de
               desplazarse en horizontal; el cuerpo sí se desplaza y las
               etiquetas dejan de coincidir con los registros (bug de columnas
               descuadradas en listados anchos).
               DataTables sincroniza cabecera y cuerpo por sí solo; lo único que
               hace falta es recalcular anchos cuando cambia la altura del área
               con scroll (aparición/desaparición de la barra vertical). */
            if (isNaN(prevH) || Math.abs(prevH - h) > 1) {
                try { table.api().columns.adjust(); } catch (e) { /* noop */ }
            }
        }

        table.api().on('draw.dt', function () {
            applyAutoSize();
            setTimeout(applyAutoSize, 30);
        });

        $(window).on('resize.crudAutoSize', debounce(applyAutoSize, 100));
        setTimeout(applyAutoSize, 60);
        setTimeout(applyAutoSize, 300);

        if (usingExternalSearch) {
            $(c.searchSelector).on('keyup change clear', debounce(function () {
                table.api().search(this.value).draw();
            }, c.searchDebounceMs));
        }

        if (c.countSelector) {
            table.api().on('draw', function () {
                try {
                    var info = table.api().page.info();
                    $(c.countSelector).text(info.recordsTotal + ' ' + c.messages.records);
                } catch (e) { /* noop */ }
            });
        }

        $table.find('tbody').on('click', 'tr', function () {
            if ($(this).hasClass('selected')) {
                $(this).removeClass('selected');
            } else {
                table.$('tr.selected').removeClass('selected');
                $(this).addClass('selected');
            }
        });

        function getSelectedId() {
            var $row = table.$('tr.selected');
            if (!$row.length) return '';

            var idAttr = $row.attr('data-id');
            if (idAttr !== undefined && idAttr !== null && idAttr !== '') {
                return String(idAttr);
            }

            try {
                var rowData = table.api().row($row).data();
                if (rowData && typeof rowData === 'object' && c.idField && (c.idField in rowData)) {
                    return String(rowData[c.idField]);
                }
            } catch (e) { /* noop */ }

            return '';
        }

        function getSelectedRowData() {
            var $row = table.$('tr.selected');
            if (!$row.length) return null;
            try { return table.api().row($row).data(); }
            catch (e) { return null; }
        }

        $(c.buttons.add).on('click', function () {
            if (c.routes.add) window.location.href = c.routes.add;
        });

        $(c.buttons.edit).on('click', function () {
            var id = getSelectedId();
            if (!id) return dialogInfo(c.messages.selectOne);

            var rowData = getSelectedRowData();
            if (typeof c.beforeEdit === 'function') {
                if (c.beforeEdit(id, rowData) === false) return;
            }

            if (c.routes.edit)
                window.location.href = c.routes.edit + '?' + c.paramName + '=' + encodeParam(id);
        });

        $(c.buttons.view).on('click', function () {
            var id = getSelectedId();
            if (!id) return dialogInfo(c.messages.selectOne);
            if (c.routes.view)
                window.location.href = c.routes.view + '?' + c.paramName + '=' + encodeParam(id);
        });

        $(c.buttons.import).on('click', function () {
            if (c.routes.import) window.location.href = c.routes.import;
        });

        $(c.buttons.download).on('click', function () {
            if (!c.routes.download) return;
            $.confirm({
                title: c.messages.downloadTitle,
                content: false,
                confirmButton: c.messages.ok,
                cancelButton: c.messages.close,
                confirmButtonClass: 'btn-success',
                cancelButtonClass: 'btn-default',
                confirm: function () {
                    window.open(c.routes.download, '',
                        'width=320,height=240,top=250,left=500');
                },
                autoClose: 'cancel|5000'
            });
        });

        $(c.buttons.delete).on('click', function () {
            var id = getSelectedId();
            if (!id) return dialogInfo(c.messages.selectDelete);

            var rowData = getSelectedRowData();

            var confirmarEliminar = function () {
                $.confirm({
                    title: c.messages.confirmDelete,
                    content: false,
                    confirmButton: c.messages.yes,
                    cancelButton: c.messages.no,
                    confirmButtonClass: 'btn-danger',
                    cancelButtonClass: 'btn-default',
                    confirm: function () {
                        var payload = { action: c.deleteAction };
                        payload[c.deleteRecordField] = id;

                        // Datos adicionales por módulo (p. ej. motivo de anulación).
                        if (typeof c.deleteExtraData === 'function') {
                            var extra = c.deleteExtraData(id, rowData);
                            if (extra && typeof extra === 'object') {
                                for (var k in extra) {
                                    if (Object.prototype.hasOwnProperty.call(extra, k)) {
                                        payload[k] = extra[k];
                                    }
                                }
                            }
                        }

                        $.post(c.controller, payload)
                         .done(function (data) {
                            $(c.messageSelector).html(data);
                            setTimeout(function () {
                                $(c.messageSelector).find('.alert').alert('close');
                            }, 2500);
                            setTimeout(function () { window.location.reload(); }, 1500);
                         });
                    }
                });
            };

            if (typeof c.beforeDelete === 'function') {
                var resultado = c.beforeDelete(id, rowData);
                if (resultado === false) return;
                // Soporte para validaciones asíncronas (Promesa / jqXHR):
                // si beforeDelete devuelve un "thenable", se espera su resultado.
                if (resultado && typeof resultado.then === 'function') {
                    resultado.then(function (ok) {
                        if (ok === false) return;
                        confirmarEliminar();
                    });
                    return;
                }
            }

            confirmarEliminar();
        });

        if (c.dblClickEdit) {
            $table.find('tbody').on('dblclick', 'tr', function () {
                table.$('tr.selected').removeClass('selected');
                $(this).addClass('selected');
                $(c.buttons.edit).trigger('click');
            });
        }

        if (c.autoRedirect.enabled && c.autoRedirect.url) {
            setTimeout(function () {
                window.location.href = c.autoRedirect.url;
            }, c.autoRedirect.ms);
        }

        return {
            table: table,
            getSelectedId: getSelectedId,
            getSelectedRowData: getSelectedRowData,
            dialogInfo: dialogInfo,
            autoSize: applyAutoSize,
            config: c
        };
    }

    window.CrudIndex = { init: init, renders: renders };
})(window, jQuery);