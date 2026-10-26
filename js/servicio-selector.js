/* ============================================================================
   SELECTOR DE ANIMALES (SOVCA)  — listado filtrable reutilizable
   ----------------------------------------------------------------------------
   Muestra el listado de animales que cumplen la condición pedida y permite
   filtrarlo por nombre, código SOVCA o N° de arete. El filtrado es LOCAL (sin
   consultas por cada tecla), así que responde de inmediato aunque el listado
   tenga cientos de animales.

   Se usa en:
     · views/servicios/frm_registrar.php ........ hembra receptora / donadora / macho
     · views/nacimientos/… y views/animales/… ... madre / donadora / padre (ficha)

   Uso:
       $('#sel_h').bovsSelectorAnimal({
           url:    '../../controllers/mnacimientos_controller',  // opcional
           accion: 'listar_candidatos_nacimiento',               // opcional
           rol:    'madre',                    // lo interpreta cada controlador
           tipo:   function () { return $('#tipo_servicio').val(); },
                                               // opcional: si se indica, el
                                               // listado espera a que haya tipo
           params: function () { return { tipo_s: $('#tipo_servicio').val() }; },
           textoVacio: 'No hay hembras que cumplan la condición.',
           onElegir: function (fila) { ... }   // clic en una fila
       });

   El backend debe responder:
       {resultado:'ok', filas:[{codigo, nombre, nro_arete, condicion,
                                edad_meses, nro_servicio, disponible}]}

   COMPORTAMIENTO COMPACTO (para no ocupar espacio en el formulario, sobre todo
   en móvil):
     · el listado arranca RECOGIDO; sólo se ve el buscador;
     · al hacer foco/clic en el buscador (o en su barra) se despliega COMPLETO,
       con todos los animales que cumplen la condición;
     · al escribir se filtra en local sobre ese listado;
     · al elegir una fila (y al hacer clic fuera, o con "Cambiar") el listado se
       recoge otra vez y deja de mostrarse;
     · SIEMPRE se ofrecen únicamente los animales con `disponible=1` (por eso ya
       no existe el check "Solo disponibles").
     · `maxInicial` / `maxFiltrado` (0 = sin límite, valor por defecto) permiten
       recortar las filas mostradas si algún formulario lo necesita.

   API:  .bovsSelectorAnimal('recargar')   vuelve a pedir el listado
         .bovsSelectorAnimal('marcar', codigo)
         .bovsSelectorAnimal('vaciar')     quita la marca y recoge el listado
         .bovsSelectorAnimal('abrir') / ('cerrar')
   ============================================================================ */

;(function ($) {
    'use strict';

    var URL_DEFECTO = '../../controllers/mservicios_controller';

    /* Normaliza el texto para comparar sin acentos ni mayúsculas */
    function normalizar(txt) {
        var s = (txt === null || txt === undefined) ? '' : String(txt);

        s = s.toUpperCase();
        s = s.replace(/Á|À|Ä|Â/g, 'A').replace(/É|È|Ë|Ê/g, 'E')
             .replace(/Í|Ì|Ï|Î/g, 'I').replace(/Ó|Ò|Ö|Ô/g, 'O')
             .replace(/Ú|Ù|Ü|Û/g, 'U').replace(/Ñ/g, 'N');

        return s.replace(/\s+/g, ' ');
    }

    function recortar(txt) {
        return normalizar(txt).replace(/^ +| +$/g, '');
    }

    function escapar(txt) {
        return String((txt === null || txt === undefined) ? '' : txt)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function Selector(raiz, cfg) {
        this.$raiz = $(raiz);
        this.cfg = $.extend({
            rol: 'receptora',
            url: URL_DEFECTO,
            accion: 'listar_candidatos_servicio',
            tipo: null,               // función que devuelve el tipo (null = no aplica)
            params: null,             // función que devuelve parámetros extra del POST
            maxInicial: 0,            // 0 = al desplegar el buscador se ven TODOS
            maxFiltrado: 0,           // 0 = al escribir se ven TODOS los que coinciden
            textoBloqueado: 'Indique primero el tipo de servicio.',
            textoVacio: 'No hay animales que cumplan la condición para este tipo de servicio.',
            textoResumen: null,
            onElegir: null,
            /* Callbacks opcionales para explicar por qué un animal escrito a
               mano no aparece en el listado (ver frm_registrar). */
            onSinResultados: null,
            onConResultados: null
        }, cfg || {});

        /* Sólo espera a que haya un tipo elegido si se indicó la función `tipo` */
        this.requiereTipo = (typeof this.cfg.tipo === 'function');

        this.filas = [];
        this.cargado = false;
        this.tipoCargado = null;
        this.codigoElegido = '';
        this.abierto = false;
        this.peticion = null;

        this.$lista = this.$raiz.find('[data-sel="lista"]');
        this.$filtro = this.$raiz.find('[data-sel="filtro"]');
        this.$contador = this.$raiz.find('[data-sel="contador"]');
        this.$resumen = this.$raiz.find('[data-sel="resumen"]');

        /* Cabecera de columnas (se crea una sola vez, antes del listado) */
        this.$head = this.$raiz.find('[data-sel="head"]');

        if (this.$head.length === 0) {
            this.$head = $('<div class="sel-animal__head" data-sel="head"></div>');
            this.$lista.before(this.$head);
        }

        this.cerrar();
        this.enlazar();
        this.cargar();
    }

    Selector.prototype.enlazar = function () {
        var self = this;

        /* Al escribir se filtra en local y se amplía el listado */
        this.$filtro.on('input keyup', function () {
            self.abrir();
            self.pintar();
        });

        /* El buscador es el que despliega el listado (ver aplicarEstado):
           sirve el foco (teclado), el clic en el campo y el clic en la barra */
        this.$filtro.on('focus focusin', function () {
            self.abrir();
        });

        this.$raiz.find('.sel-animal__bar').on('click', function () {
            self.abrir();
        });

        this.$raiz.find('[data-sel="recargar"]').on('click', function () {
            self.abrir();
            self.cargar(true);
        });

        this.$lista.on('click', '.sel-animal__item', function () {
            var codigo = $(this).attr('data-codigo');
            var fila = self.buscar(codigo);

            if (!fila) {
                return;
            }

            self.codigoElegido = codigo;
            self.pintar();

            /* Al elegir se recoge el listado: ya no hace falta ocupar espacio */
            self.cerrar();

            if (typeof self.cfg.onElegir === 'function') {
                self.cfg.onElegir(fila);
            }
        });

        /* Un clic fuera del bloque también lo recoge */
        $(document).on('click', function (e) {
            if (!$.contains(self.$raiz[0], e.target) && (e.target !== self.$raiz[0])) {
                self.cerrar();
            }
        });
    };

    /* ------------------------- abrir / cerrar listado ------------------------- */

    Selector.prototype.abrir = function () {
        this.abierto = true;
        this.aplicarEstado();
    };

    Selector.prototype.cerrar = function () {
        this.abierto = false;
        this.aplicarEstado();
    };

    /* El listado se mantiene RECOGIDO mientras no se use el buscador, para no
       ocupar espacio en el formulario (sobre todo en móvil). Sólo se muestra al
       abrir el buscador o cuando no hay nada que listar: ahí el mensaje
       ("Indique primero el tipo de servicio…", "No hay hembras…") sí debe verse. */
    Selector.prototype.aplicarEstado = function () {
        var mostrar = this.abierto || (this.filas.length === 0);

        this.$raiz.toggleClass('is-cerrado', !mostrar);
    };

    Selector.prototype.buscar = function (codigo) {
        var encontrada = null;

        $.each(this.filas, function (i, fila) {
            if (String(fila.codigo) === String(codigo)) {
                encontrada = fila;
                return false;
            }
        });

        return encontrada;
    };

    Selector.prototype.vaciar = function () {
        this.codigoElegido = '';
        this.$filtro.val('');
        this.pintar();
        this.cerrar();
    };

    Selector.prototype.marcar = function (codigo) {
        this.codigoElegido = (codigo === null || codigo === undefined) ? '' : String(codigo);
        this.pintar();
    };

    Selector.prototype.recargar = function () {
        this.cargar(true);
    };

    Selector.prototype.obtenerTipo = function () {
        if (typeof this.cfg.tipo !== 'function') {
            return '';
        }

        var t = this.cfg.tipo();

        return (t === null || t === undefined) ? '' : String(t);
    };

    Selector.prototype.cargar = function (forzar) {
        var self = this;
        var tipo = this.obtenerTipo();
        var clave = (tipo === '') ? '(sin-tipo)' : tipo;

        if (this.requiereTipo && tipo === '') {
            this.filas = [];
            this.cargado = false;
            this.tipoCargado = null;
            this.$raiz.addClass('is-bloqueado');
            this.$raiz.removeClass('is-con-detalle');
            this.$head.html('');
            this.$lista.html('<p class="sel-animal__vacio">' + this.cfg.textoBloqueado + '</p>');
            this.$contador.text('');
            this.$resumen.text('');
            this.aplicarEstado();
            return;
        }

        if ((forzar !== true) && this.cargado && (this.tipoCargado === clave)) {
            return;
        }

        this.tipoCargado = clave;
        this.$raiz.removeClass('is-bloqueado');
        this.$raiz.addClass('is-cargando');
        this.$head.html('');
        this.$lista.html('<p class="sel-animal__vacio"><i class="fa fa-spinner fa-spin"></i> Cargando listado…</p>');

        if (this.peticion && this.peticion.abort) {
            this.peticion.abort();
        }

        var datos = { action: this.cfg.accion, rol: this.cfg.rol };

        if (this.requiereTipo) {
            datos.tipo_s = tipo;
        }

        if (typeof this.cfg.params === 'function') {
            $.extend(datos, this.cfg.params() || {});
        }

        this.peticion = $.post(this.cfg.url, datos).done(function (data) {
            var json = null;

            try {
                json = (typeof data === 'string') ? $.parseJSON(data) : data;
            } catch (e) {
                json = null;
            }

            self.$raiz.removeClass('is-cargando');

            if (!json || json.resultado !== 'ok') {
                self.filas = [];
                self.cargado = false;
                self.$raiz.removeClass('is-con-detalle');
                self.$head.html('');
                self.$lista.html('<p class="sel-animal__vacio">No se pudo cargar el listado de animales.</p>');
                self.$contador.text('');
                self.$resumen.text('');
                self.aplicarEstado();
                return;
            }

            self.filas = json.filas || [];
            self.cargado = true;
            self.pintar();
        }).fail(function () {
            self.$raiz.removeClass('is-cargando');
            self.filas = [];
            self.cargado = false;
            self.$raiz.removeClass('is-con-detalle');
            self.$head.html('');
            self.$lista.html('<p class="sel-animal__vacio">No se pudo cargar el listado de animales.</p>');
            self.$contador.text('');
            self.$resumen.text('');
            self.aplicarEstado();
        });
    };

    Selector.prototype.coincide = function (fila, partes) {
        /* join() convierte null/undefined en cadena vacía, así los campos
           opcionales (etiqueta, detalle…) no ensucian la búsqueda */
        var cadena = recortar([
            fila.codigo, fila.nombre, fila.nro_arete, fila.condicion,
            fila.nro_servicio, fila.etiqueta, fila.detalle
        ].join(' '));
        var j;

        for (j = 0; j < partes.length; j++) {
            if (partes[j] !== '' && cadena.indexOf(partes[j]) === -1) {
                return false;
            }
        }

        return true;
    };

    Selector.prototype.pintar = function () {
        var self = this;
        var texto = recortar(this.$filtro.val());
        var partes = (texto === '') ? [] : texto.split(' ');

        /* 0 = sin límite: se listan todos los que cumplen la condición */
        var limite = (texto === '') ? this.cfg.maxInicial : this.cfg.maxFiltrado;

        var visibles = 0;
        var coinciden = 0;
        var disponibles = 0;
        var ocupados = 0;
        var html = '';
        var tieneDetalle = false;
        var k;

        for (k = 0; k < this.filas.length; k++) {
            if (this.filas[k].detalle !== undefined && this.filas[k].detalle !== null &&
                String(this.filas[k].detalle) !== '') {
                tieneDetalle = true;
                break;
            }
        }

        this.$raiz.toggleClass('is-con-detalle', tieneDetalle);

        this.$head.html(this.filas.length > 0
            ? ('<span class="sel-animal__h-codigo">Código</span>' +
               '<span class="sel-animal__h-arete">N° Arete</span>' +
               '<span class="sel-animal__h-nombre">Nombre</span>' +
               (tieneDetalle ? '<span class="sel-animal__h-parte">Parto</span>' : '') +
               '<span class="sel-animal__h-estatus">Estatus</span>')
            : '');

        $.each(this.filas, function (i, fila) {
            if (String(fila.disponible) === '1') {
                disponibles++;
            } else {
                ocupados++;
            }
        });

        $.each(this.filas, function (i, fila) {

            /* El listado muestra SÓLO los animales que cumplen la condición
               (los que no la cumplen no se ofrecen, así ya no hace falta el
               filtro "Solo disponibles"). */
            if (String(fila.disponible) !== '1') {
                return;
            }

            if (!self.coincide(fila, partes)) {
                return;
            }

            coinciden++;

            if ((limite > 0) && (visibles >= limite)) {
                return;
            }

            visibles++;
            html += self.filaHtml(fila);
        });

        if (visibles === 0) {
            html = '<p class="sel-animal__vacio">' +
                   ((disponibles === 0)
                        ? this.cfg.textoVacio
                        : 'Ningún animal coincide con el filtro indicado.') +
                   '</p>';
        } else if (coinciden > visibles) {
            html += '<p class="sel-animal__mas">y ' + (coinciden - visibles) +
                    ' más… siga escribiendo para filtrar</p>';
        }

        /* Aviso de diagnóstico: permite explicar por qué el código escrito no
           aparece (no existe, es de otro fundo, tiene ciclo abierto, etc.). */
        if (visibles === 0 && texto !== '') {
            if (typeof this.cfg.onSinResultados === 'function') {
                this.cfg.onSinResultados(texto);
            }
        } else if (typeof this.cfg.onConResultados === 'function') {
            this.cfg.onConResultados();
        }

        this.$lista.html(html);
        this.$contador.text('Mostrando ' + visibles + ' de ' + coinciden + ' registros');
        this.$resumen.text(typeof this.cfg.textoResumen === 'function'
            ? this.cfg.textoResumen(disponibles, ocupados)
            : disponibles + ' disponibles · ' + ocupados + ' no disponibles');

        this.aplicarEstado();
    };

    Selector.prototype.filaHtml = function (fila) {
        var disponible = (String(fila.disponible) === '1');
        var nro_servicio = parseInt(fila.nro_servicio, 10);
        var tieneEtiqueta = (fila.etiqueta !== undefined && fila.etiqueta !== null && String(fila.etiqueta) !== '');
        var etiqueta = '';

        var nro_arete = (fila.nro_arete === null || fila.nro_arete === undefined)
            ? '' : String(fila.nro_arete).replace(/\s+/g, ' ').trim();
        var sin_arete = (nro_arete === '');
        var nombre_crudo = (fila.nombre === null || fila.nombre === undefined)
            ? '' : String(fila.nombre).replace(/\s+/g, ' ').trim();
        var nombre = (nombre_crudo === '') ? '(sin nombre)' : nombre_crudo;
        var codigo = (fila.codigo === null || fila.codigo === undefined)
            ? '' : String(fila.codigo).trim();

        var detalle = (fila.detalle !== undefined && fila.detalle !== null && String(fila.detalle) !== '')
            ? String(fila.detalle) : '';

        if (disponible) {
            etiqueta = tieneEtiqueta ? String(fila.etiqueta) : 'Disponible';

            if (nro_servicio > 0) {
                etiqueta += ' · serv. ' + nro_servicio;
            }
        } else {
            etiqueta = (fila.condicion === '') ? 'En servicio' : fila.condicion;
        }

        var claseTag = disponible ? 'is-ok' : 'is-ocupado';

        if (disponible && fila.etiqueta_clase) {
            claseTag = String(fila.etiqueta_clase);
        }

        var titulo = (sin_arete ? 'Sin arete' : 'Arete ' + nro_arete) +
                     ' · ' + nombre +
                     ' · ' + codigo +
                     (detalle !== '' ? ' · ' + detalle : '');

        return '<button type="button" class="sel-animal__item' +
                    ((String(fila.codigo) === this.codigoElegido) ? ' is-elegido' : '') + '"' +
                    ' data-codigo="' + escapar(fila.codigo) + '"' +
                    ' title="' + escapar(titulo) + '"' +
                    (disponible ? '' : ' data-ocupado="1"') + '>' +
                    '<span class="sel-animal__codigo" title="Código SOVCA">' + escapar(codigo) + '</span>' +
                    '<span class="sel-animal__arete">' +
                        '<strong>' + (sin_arete ? '—' : escapar(nro_arete)) + '</strong>' +
                    '</span>' +
                    '<span class="sel-animal__nombre">' + escapar(nombre) + '</span>' +
                    (detalle !== '' ? '<span class="sel-animal__detalle">' + escapar(detalle) + '</span>' : '') +
                    '<span class="sel-animal__tag ' + claseTag + '">' + escapar(etiqueta) + '</span>' +
                '</button>';
    };

    $.fn.bovsSelectorAnimal = function (opciones) {
        var argumentos = Array.prototype.slice.call(arguments, 1);

        return this.each(function () {
            var $el = $(this);
            var instancia = $el.data('bovsSelectorAnimal');

            if (!instancia && (typeof opciones === 'string')) {
                return;
            }

            if (!instancia) {
                $el.data('bovsSelectorAnimal', new Selector(this, opciones));
                return;
            }

            if (typeof opciones === 'string' && typeof instancia[opciones] === 'function') {
                instancia[opciones].apply(instancia, argumentos);
            }
        });
    };

}(jQuery));
