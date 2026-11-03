/* ============================================================================
   categoria-rangos.js  (SOVCA)
   ----------------------------------------------------------------------------
   Mientras se define una categoría (edad mínima / edad máxima) muestra, debajo
   del formulario, las categorías que ya existen para ese sexo con sus rangos y
   avisa cuando el nuevo rango se cruza con otra.

   El análisis lo hace el servidor (lib/categoria_rangos_helper.php) a través de
   la acción `validar_rangos`; aquí solo se pinta. Así el formulario y el
   guardado usan exactamente las mismas reglas.

   Uso:
       bovsCategoriaRangos.iniciar({
           panel:   'cat_rangos',        // contenedor (se muestra al haber datos)
           sexo:    'sexo',
           min:     'edadminima',
           max:     'edadmaxima',
           campoId: 'id_categoria',      // input oculto con el id (opcional)
           url:     '../../controllers/mcategorias_controller'
       });
   ============================================================================ */

(function (window, $) {
    'use strict';

    function escapar(texto) {
        return $('<div>').text(texto === null || texto === undefined ? '' : String(texto)).html();
    }

    var refrescarActual = null;

    function iniciar(cfg) {
        var $sexo  = $('#' + cfg.sexo);
        var $min   = $('#' + cfg.min);
        var $max   = $('#' + cfg.max);
        var $panel = $('#' + cfg.panel);

        if (!$sexo.length || !$min.length || !$max.length || !$panel.length) {
            return { refrescar: function () {} };
        }

        var $lista    = $('#cat_rangos_lista');
        var $recuadro = $('#cat_rangos_aviso');
        var $resumen  = $('#cat_rangos_rango');

        var temporizador = null;

        function idActual() {
            if (!cfg.campoId) { return 0; }
            return parseInt($('#' + cfg.campoId).val(), 10) || 0;
        }

        function pintar(datos) {
            var min = parseInt($min.val(), 10);
            var max = parseInt($max.val(), 10);
            var html = '';

            $.each(datos.cadena || [], function (i, c) {
                var coincide = (c.min === min && c.max === max);
                html += '<li class="cat-rangos__fila' + (coincide ? ' cat-rangos__fila--actual' : '') + '">'
                    + '<span class="cat-rangos__meses">' + c.min + ' – ' + c.max + '</span>'
                    + '<span class="cat-rangos__nombre">' + escapar(c.nombre) + '</span>'
                    + '<span class="cat-rangos__tag' + (c.global ? '' : ' cat-rangos__tag--fundo') + '">'
                    + (c.global ? 'general' : 'fundo') + '</span>'
                    + '</li>';
            });

            $lista.html(html || '<li class="cat-rangos__vacio">Todavía no hay categorías de este sexo.</li>');

            var bloque = '';

            $.each(datos.errores || [], function (i, e) {
                bloque += '<div class="cat-rangos__error"><i class="fa fa-ban"></i> ' + escapar(e) + '</div>';
            });

            $.each(datos.avisos || [], function (i, a) {
                bloque += '<div class="cat-rangos__aviso"><i class="fa fa-warning"></i> ' + escapar(a) + '</div>';
            });

            if (parseInt(datos.sugerencia_min, 10) > 0) {
                bloque += '<button type="button" class="btn btn-default btn-xs cat-rangos__usar" data-usar="'
                    + parseInt(datos.sugerencia_min, 10) + '">'
                    + '<i class="fa fa-magic"></i> Empezar en ' + parseInt(datos.sugerencia_min, 10) + ' meses</button>';
            }

            $recuadro.html(bloque).toggle(bloque !== '');

            if (isNaN(min) || isNaN(max) || max <= 0) {
                $resumen.text('');
            } else {
                $resumen.text('Este rango: ' + min + ' – ' + max + ' meses');
            }
        }

        function consultar() {
            var sexo = $sexo.val();

            if (!sexo || sexo === '*' || sexo === '') {
                $panel.hide();
                return;
            }

            clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                $.post(cfg.url, {
                    action:       'validar_rangos',
                    sexo:         sexo,
                    edadminima:   $min.val(),
                    edadmaxima:   $max.val(),
                    id_categoria: idActual(),
                    /* El nombre importa: si coincide con una categoría general,
                       el alta la guarda como personalización del fundo. */
                    nombre_categoria: (cfg.nombre ? $('#' + cfg.nombre).val() : '')
                }, null, 'json').done(function (datos) {
                    if (!datos || datos.resultado !== 'ok') { return; }
                    pintar(datos);
                    $panel.show();
                });
            }, 250);
        }

        $sexo.on('change', consultar);
        $min.add($max).on('input keyup change', consultar);

        if (cfg.nombre) {
            $('#' + cfg.nombre).on('input keyup change', consultar);
        }

        /* Botón «Empezar en N meses»: corrige la edad mínima sugerida. */
        $recuadro.on('click', '[data-usar]', function () {
            $min.val($(this).attr('data-usar'));
            consultar();
        });

        refrescarActual = consultar;
        consultar();

        return { refrescar: consultar };
    }

    /**
     * Diálogo para guardar un rango que se cruza con otro.
     * El servidor responde `resultado = advertencia` y NO guarda: aquí se
     * pregunta si se corrige o si se guarda igual (reenviando forzar = 1).
     *
     * @param {object}   datos     Respuesta del controlador (con `mensaje`).
     * @param {function} continuar Se llama si el usuario decide guardar igual.
     */
    function dialogoAdvertencia(datos, continuar) {
        $.confirm({
            title: 'Revisa los rangos de edad',
            content: (datos && datos.mensaje) ? datos.mensaje : '',
            icon: 'fa fa-warning',
            type: 'orange',
            confirmButton: 'Guardar de todos modos',
            cancelButton: 'Corregir edades',
            confirmButtonClass: 'btn-warning',
            cancelButtonClass: 'btn-default',
            closeIcon: true,
            confirm: function () {
                if (typeof continuar === 'function') { continuar(); }
            },
            cancel: function () {
                var $min = $('#edadminima');
                if ($min.length) { $min.trigger('focus'); }
            }
        });
    }

    window.bovsCategoriaRangos = { iniciar: iniciar };
    window.bovsDialogoAdvertenciaCategoria = dialogoAdvertencia;

    /* Permite refrescar el panel desde los formularios (p. ej. cuando la carga
       por AJAX termina de rellenar las edades). */
    window.bovsCategoriaRangosRefrescar = function () {
        if (typeof refrescarActual === 'function') { refrescarActual(); }
    };

})(window, jQuery);
