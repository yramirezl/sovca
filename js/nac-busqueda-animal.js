/* ============================================================================
   nac-busqueda-animal.js  (SOVCA)
   ----------------------------------------------------------------------------
   Las acciones get_buscar_rgb_h / get_buscar_rgb_m / get_buscar_rgb_h_dona
   responden de dos formas distintas:

     · Éxito → una cadena con los datos separados por «*»:
                 CODIGO * NOMBRE * NRO_ARETE * NRO_PARTO   (madre)
                 CODIGO * NOMBRE * NRO_ARETE                (padre, donadora)
     · Fallo  → un objeto {resultado:"error", mensaje:"<div …>"}.

   En los formularios de nacimientos ese parseo se hacía SIEMPRE, dentro y
   fuera del `else`, sobre el valor del propio campo de texto. Con una
   respuesta de error el parseo devolvía `undefined` y la cadena

       $("#campo").html(undefined).val(undefined)

   reventaba con un TypeError: `.html(undefined)` es un *getter* y devuelve
   una cadena, que no tiene método `.val`. El efecto era doble y silencioso:

     1. el mensaje de error quedaba a medias, y
     2. el campo CONSERVABA el texto que el operador había tecleado — por
        ejemplo el número de arete "2005" — y ese texto se guardaba como si
        fuera el código del animal. En el listado aparecía un "código
        fantasma" que después no coincidía con ningún animal.

   Estas funciones separan la respuesta en código / nombre / arete / parto y
   dejan los campos limpios cuando la búsqueda no devolvió nada.

   El animal elegido se muestra en una ficha con tres columnas —Nombre, Nro de
   arete y Código SOVCA— y cada columna se oculta si el animal no tiene ese dato
   (un animal sin arete no muestra «Nro arete»). Ver bovsPintarFichaAnimal().
   ============================================================================ */

(function (window, $) {
    'use strict';

    /**
     * Pinta la ficha del animal seleccionado.
     *
     * @param {string} idFicha id del contenedor .sel-ficha.
     * @param {object} datos   {codigo, nombre, nro_arete}.
     */
    function bovsPintarFichaAnimal(idFicha, datos) {
        var $ficha = $('#' + idFicha);
        if (!$ficha.length) { return; }

        datos = datos || {};

        var valores = {
            nombre: $.trim(datos.nombre || ''),
            arete:  $.trim(datos.nro_arete || ''),
            codigo: $.trim(datos.codigo || '')
        };

        var hayDatos = false;

        $ficha.find('[data-campo]').each(function () {
            var $item  = $(this);
            var campo  = $item.attr('data-campo');
            var valor  = valores[campo] || '';

            $item.find('[data-valor]').text(valor);
            /* Sin dato no se muestra ni la etiqueta ni el valor. */
            $item.prop('hidden', valor === '');

            if (valor !== '') { hayDatos = true; }
        });

        $ficha.find('.sel-ficha__vacia').prop('hidden', hayDatos);
        $ficha.toggleClass('sel-ficha--vacia', !hayDatos);
    }

    /**
     * Separa la respuesta de get_buscar_rgb_*.
     *
     * @param  {mixed} respuesta Texto crudo de la respuesta AJAX.
     * @return {{codigo: string, nombre: string, nro_arete: string,
     *           nro_parto: string, ok: boolean}}
     */
    function bovsParsearBusquedaAnimal(respuesta) {
        var salida = {
            codigo: '', nombre: '', nro_arete: '', nro_parto: '', ok: false
        };

        if (typeof respuesta !== 'string') {
            return salida;
        }

        var texto = respuesta.trim();
        /* json_encode() de una cadena produce algo como "COD*NOMBRE":
           las comillas iniciales distinguen el éxito del objeto de error. */
        if (texto === '' || texto.charAt(0) !== '"') {
            return salida;
        }

        try {
            texto = JSON.parse(texto);
        } catch (e) {
            return salida;
        }

        if (typeof texto !== 'string' || texto.indexOf('*') === -1) {
            return salida;
        }

        var partes = texto.split('*');
        salida.codigo    = $.trim(partes[0] || '');
        salida.nombre    = $.trim(partes[1] || '');
        salida.nro_arete = $.trim(partes[2] || '');
        salida.nro_parto = $.trim(partes[3] || '');
        salida.ok        = salida.codigo !== '';

        return salida;
    }

    /**
     * Vuelca la respuesta en los campos del formulario. Si la búsqueda falló
     * los deja vacíos, para que nunca se envíe a guardar lo que se tecleó.
     *
     * @param {mixed}  respuesta  Texto crudo de la respuesta AJAX.
     * @param {string} idCodigo   id del input del código (oculto).
     * @param {string} idNombre   id del input del nombre (oculto).
     * @param {string} idNroParto id del input del nro de parto (opcional).
     * @param {string} idFicha    id de la ficha visible (opcional).
     * @return {{codigo: string, nombre: string, nro_arete: string,
     *           nro_parto: string, ok: boolean}}
     */
    function bovsAplicarBusquedaAnimal(respuesta, idCodigo, idNombre, idNroParto, idFicha) {
        var datos = bovsParsearBusquedaAnimal(respuesta);

        if (idCodigo)   { $('#' + idCodigo).val(datos.codigo); }
        if (idNombre)   { $('#' + idNombre).val(datos.nombre); }
        if (idNroParto) { $('#' + idNroParto).val(datos.nro_parto); }
        if (idFicha)    { bovsPintarFichaAnimal(idFicha, datos); }

        return datos;
    }

    window.bovsPintarFichaAnimal      = bovsPintarFichaAnimal;
    window.bovsParsearBusquedaAnimal = bovsParsearBusquedaAnimal;
    window.bovsAplicarBusquedaAnimal = bovsAplicarBusquedaAnimal;

})(window, jQuery);
