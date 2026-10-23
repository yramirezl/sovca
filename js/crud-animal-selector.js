/* ============================================================================
   crud-animal-selector.js  (BOVS)
   ----------------------------------------------------------------------------
   Conecta el LISTADO de animales (js/servicio-selector.js) con el buscador
   manual heredado (Nro Arete / Código + Buscar) de los formularios CRUD, para
   que el usuario elija el animal de una lista en lugar de teclear el código:

     · al hacer clic en una fila se rellena el campo oculto del código y se
       dispara la búsqueda legacy -> se reutilizan TODAS sus validaciones
       (edad, estado fisiológico, período de gestación, nro de parto…);
     · el botón "Cambiar" del bloque quita la marca del listado;
     · opcionalmente el listado se recarga (y se desmarca) cuando cambia un
       select del formulario, p. ej. el tipo de servicio.

   Uso:
       bovsCrudAnimalSelector({
           lista:         'sel_h',                  // contenedor .sel-animal
           url:           '../../controllers/mnacimientos_controller',
           accion:        'listar_candidatos_nacimiento',
           rol:           'madre',
           campoCodigo:   'cod_animal_h',           // input legacy (oculto)
           botonBuscar:   'buscar',                 // botón legacy de búsqueda
           botonCambiar:  'limpiar',                // botón "Cambiar" visible
           recargarCon:   'tipo_servicio',          // opcional
           tipo:          function () { return $('#tipo_servicio').val(); },
           params:        function () { return { tipo_s: $('#tipo_servicio').val() }; },
           alElegir:      function (fila) { buscarx = 1; }   // fija el modo legacy
       });

   Devuelve el objeto jQuery del listado (para usar
   $(el).bovsSelectorAnimal('recargar') / ('vaciar') / ('marcar', codigo)).
   ============================================================================ */

function bovsCrudAnimalSelector(cfg) {
    var $lista = $('#' + cfg.lista);

    if ($lista.length === 0 || typeof $lista.bovsSelectorAnimal !== 'function') {
        return null;
    }

    $lista.bovsSelectorAnimal({
        url:            cfg.url,
        accion:         cfg.accion,
        rol:            cfg.rol,
        tipo:           cfg.tipo,
        params:         cfg.params,
        textoBloqueado: cfg.textoBloqueado,
        textoVacio:     cfg.textoVacio,
        textoResumen:   cfg.textoResumen,
        onElegir: function (fila) {

            /* 1) El formulario heredado fija su modo (1 = código, 2 = arete) */
            if (typeof cfg.alElegir === 'function') {
                cfg.alElegir(fila);
            }

            /* 2) Se rellena el campo del código y se lanza la búsqueda legacy */
            if (cfg.campoCodigo) {
                $('#' + cfg.campoCodigo).val(fila.codigo).prop('disabled', false);
            }

            if (cfg.botonBuscar) {
                $('#' + cfg.botonBuscar).prop('disabled', false).trigger('click');
            }
        }
    });

    /* El botón "Cambiar"/"Limpiar" del bloque también quita la marca del listado */
    if (cfg.botonCambiar) {
        $('#' + cfg.botonCambiar).on('click', function () {
            $lista.bovsSelectorAnimal('vaciar');
        });
    }

    /* Recarga (y desmarca) cuando cambia el select indicado */
    if (cfg.recargarCon) {
        $('#' + cfg.recargarCon).on('change', function () {
            $lista.bovsSelectorAnimal('vaciar');
            $lista.bovsSelectorAnimal('recargar');
        });
    }

    return $lista;
}
