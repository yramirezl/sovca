/* =============================================================================
   SOVCA · Panel lateral de consulta (drawer)
   -----------------------------------------------------------------------------
   Pensado para VER, no para editar: los formularios quedan para registrar y
   editar. Cualquier vista puede cargar aquí una ficha o un resumen.

   Uso:
     BovsDrawer.open({
         title: 'Ficha del animal',
         url:   'frm_ficha?' + encodeURIComponent('%$%') + '=' + btoa(id) + '&embed=1',
         wide:  true,             // 760px en lugar de 520px
         html:  '<p>...</p>'      // alternativa a url
     });

     BovsDrawer.setHtml(html);    // reemplaza el contenido
     BovsDrawer.setLoading();     // vuelve al spinner
     BovsDrawer.close();
     BovsDrawer.isOpen();

   Se cierra con el botón Salir, el botón Cerrar, un clic en el fondo o Esc.
   ============================================================================= */
(function () {
    'use strict';

    var creado = false;
    var $overlay, $panel, $titulo, $cuerpo;

    function crear() {
        if (creado) return;

        $overlay = $('<div class="bovs-drawer-overlay"></div>');
        $panel = $(
            '<aside class="bovs-drawer" role="dialog" aria-modal="true" aria-hidden="true">' +
                '<div class="bovs-drawer__header">' +
                    '<h3 class="bovs-drawer__title"></h3>' +
                    /* Solo una ✕: el pie ya tiene el botón "Cerrar". Dos botones
                       con texto distinto para la misma acción confunden. */
                    '<button type="button" class="bovs-drawer__close" aria-label="Cerrar">' +
                        '<i class="fa fa-times"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="bovs-drawer__body"></div>' +
                '<div class="bovs-drawer__footer">' +
                    '<button type="button" class="btn btn-default bovs-drawer__cerrar">' +
                        '<i class="fa fa-times"></i> Cerrar' +
                    '</button>' +
                '</div>' +
            '</aside>'
        );

        $('body').append($overlay).append($panel);

        $titulo = $panel.find('.bovs-drawer__title');
        $cuerpo = $panel.find('.bovs-drawer__body');

        /* Delegado: el pie se puede reemplazar (navegación entre fichas) sin
           perder el cierre. */
        $overlay.on('click', close);
        $panel.on('click', '.bovs-drawer__close, .bovs-drawer__cerrar', close);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $panel.hasClass('open')) close();
        });

        creado = true;
    }

    function setLoading() {
        $cuerpo.html('<div class="bovs-drawer__loading"><i class="fa fa-spinner fa-spin"></i>Cargando…</div>');
    }

    function setHtml(html) {
        $cuerpo.html(html);
        $cuerpo.scrollTop(0);
    }

    function setTitle(texto) {
        if (creado) $titulo.html(texto || 'Detalle');
    }

    function setFooter(html) {
        if (creado) $panel.find('.bovs-drawer__footer').html(html || '');
    }

    function getBody() {
        return $cuerpo;
    }

    function open(opciones) {
        opciones = opciones || {};
        crear();

        $titulo.html(opciones.title || 'Detalle');

        $panel.toggleClass('bovs-drawer--wide', !!opciones.wide);
        $panel.toggleClass('bovs-drawer--ficha', !!opciones.ficha);
        $cuerpo.toggleClass('bovs-drawer__body--limpio', !!opciones.limpio);

        /* Pie: por defecto solo "Cerrar"; se puede reemplazar para añadir
           navegación entre fichas. */
        var $footer = $panel.find('.bovs-drawer__footer');
        if (opciones.footer) {
            $footer.html(opciones.footer);
        } else {
            $footer.html('<button type="button" class="btn btn-default bovs-drawer__cerrar">' +
                         '<i class="fa fa-times"></i> Cerrar</button>');
        }

        if (opciones.html) {
            setHtml(opciones.html);
        } else {
            setLoading();
        }

        $overlay.addClass('open');
        $panel.addClass('open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');

        if (opciones.url) {
            $.ajax({
                url: opciones.url,
                type: 'GET',
                dataType: 'html',
                cache: false
            }).done(function (respuesta) {
                setHtml(respuesta);
                if (typeof opciones.onLoad === 'function') {
                    opciones.onLoad($cuerpo);
                }
            }).fail(function () {
                setHtml('<div class="alert alert-danger" style="margin:0;">' +
                        'No se pudo cargar la información. Intente de nuevo.</div>');
            });
        } else if (typeof opciones.onLoad === 'function') {
            opciones.onLoad($cuerpo);
        }
    }

    function close() {
        if (!creado) return;
        $overlay.removeClass('open');
        $panel.removeClass('open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    }

    function isOpen() {
        return creado && $panel.hasClass('open');
    }

    window.BovsDrawer = {
        open: open,
        close: close,
        setHtml: setHtml,
        setTitle: setTitle,
        setFooter: setFooter,
        getBody: getBody,
        setLoading: setLoading,
        isOpen: isOpen
    };
})();
