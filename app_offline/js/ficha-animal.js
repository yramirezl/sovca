// Ficha e historial del animal, armada SOLO con los datos que ya están
// en este dispositivo.
//
// En el corral la pregunta típica es "¿qué le pasó a este animal?" y
// hasta ahora había que recorrer cinco módulos distintos buscando su
// código. Esta ficha junta en una sola pantalla: identificación,
// indicadores (peso, ganancia, servicios, partos, controles),
// pesajes, servicios, partos con sus crías, controles sanitarios
// (individuales y por lote), movimientos, producción de leche y la
// baja si la tiene.
//
// Todo se lee de IndexedDB: funciona igual sin conexión. Los registros
// que todavía no se sincronizaron se marcan como "pendiente".

const FichaAnimal = {
  async abrir(uuidLocal, botonOrigen) {
    let animal = null;
    try {
      animal = await Db.obtener('animales', uuidLocal);
    } catch (e) {
      animal = null;
    }

    if (!animal) {
      mostrarToast('No se encontró este animal en el dispositivo.', 'error');
      return;
    }

    let datos = null;
    try {
      datos = await this.recolectar(animal);
    } catch (e) {
      console.error('No se pudo armar la ficha del animal:', e);
      mostrarToast('No se pudo leer el historial de este animal.', 'error');
      return;
    }

    // Foto: copia guardada en el equipo, foto recién tomada o la del
    // servidor. Si no hay ninguna, la ficha muestra el marcador.
    let foto = { url: null, propia: false };
    if (typeof Fotos !== 'undefined') {
      try {
        foto = await Fotos.urlDe(animal);
      } catch (e) {
        foto = { url: null, propia: false };
      }
    }
    this.soltarFotoAnterior();
    this._fotoPropia = foto.propia;
    this._fotoUrl = foto.url;
    datos.foto = foto;
    try {
      datos.fotoPendiente = await Fotos.tienePendiente(animal.uuid_local);
    } catch (e) {
      datos.fotoPendiente = false;
    }

    const partesTitulo = [animal.nombre, animal.codigo_rgb_id_animal].filter(Boolean);
    el('modal-detalle-registro-titulo').textContent = partesTitulo.join(' · ') || 'Ficha del animal';
    el('modal-detalle-registro-contenido').textContent = 'Historial guardado en este dispositivo';

    const grupos = el('modal-detalle-registro-grupos');
    grupos.innerHTML = this.html(animal, datos);

    const botonFoto = grupos.querySelector('[data-foto-animal]');
    if (botonFoto) {
      botonFoto.addEventListener('click', () => this.elegirFoto(animal.uuid_local));
    }

    // Foto que no se puede cargar (archivo ausente en el servidor): se
    // deja el marcador con la inicial, nunca un icono roto.
    const imagenFoto = grupos.querySelector('.ficha-foto__imagen');
    if (imagenFoto) {
      imagenFoto.addEventListener('error', () => {
        const marco = imagenFoto.closest('.ficha-foto__marco');
        if (!marco) return;
        const inicial = (animal.nombre || animal.codigo_rgb_id_animal || '?').trim().charAt(0).toUpperCase();
        marco.innerHTML = '<div class="ficha-foto__vacio" aria-hidden="true">' + escaparHtml(inicial) + '</div>';
        const estado = grupos.querySelector('.ficha-foto__estado');
        if (estado) estado.textContent = 'La foto de este animal no está disponible en el servidor';
      });
    }

    focoAnteriorDetalleRegistro = botonOrigen || document.activeElement;
    el('modal-detalle-registro-overlay').classList.remove('oculto');
    document.body.classList.add('modal-abierto');
    document.addEventListener('keydown', manejarTeclaDetalleRegistro);
    el('modal-detalle-registro-cerrar').focus();
  },

  /** Junta todo lo que el dispositivo sabe del animal. */
  async recolectar(animal) {
    const codigo = animal.codigo_rgb_id_animal || '';

    const [pesos, servicios, nacimientos, controles, controlesLote, movimientos, bajas, produccion,
      razas, aptitudes, categorias, lotes, tiposServicio, estadosFisiologicos,
      causasDescarte, causasMortalidad] = await Promise.all([
      Db.obtenerTodos('pesos'),
      Db.obtenerTodos('servicios'),
      Db.obtenerTodos('nacimientos'),
      Db.obtenerTodos('control_sanitario'),
      Db.obtenerTodos('control_sanitario_lotes'),
      Db.obtenerTodos('movimiento_rebano'),
      Db.obtenerTodos('desincorporacion'),
      Db.obtenerTodos('produccion_leche'),
      Db.obtenerCatalogo('razas'),
      Db.obtenerCatalogo('aptitudes'),
      Db.obtenerCatalogo('categorias'),
      Db.obtenerCatalogo('lotes'),
      Db.obtenerCatalogo('tipos_servicio'),
      Db.obtenerCatalogo('estados_fisiologicos'),
      Db.obtenerCatalogo('causas_descarte'),
      Db.obtenerCatalogo('causas_mortalidad'),
    ]);

    const catalogos = {
      razas, aptitudes, categorias, lotes, tiposServicio, estadosFisiologicos,
      causas_descarte: causasDescarte || [],
      causas_mortalidad: causasMortalidad || [],
    };
    const nombreDe = (lista, id) => {
      if (id === null || id === undefined || id === '') return '';
      const item = (lista || []).find((x) => String(x.id) === String(id));
      return item ? item.nombre : '';
    };

    const masNuevoPrimero = (campo) => (a, b) =>
      String(b[campo] || '').localeCompare(String(a[campo] || ''));

    const pesosAnimal = pesos
      .filter((p) => p.codigo_rgb_id_animal === codigo)
      .sort(masNuevoPrimero('fecha_pesaje'));

    const serviciosAnimal = servicios
      .filter((s) => [s.codigo_rgb_id_animal_hembra, s.codigo_rgb_id_animal_macho, s.codigo_rgb_id_animal_dona]
        .some((cod) => cod && cod === codigo))
      .sort(masNuevoPrimero('f_servicio'));

    const partosAnimal = nacimientos
      .filter((n) => n.codigo_rgb_id_animal_hembra === codigo)
      .sort(masNuevoPrimero('f_parto'));

    const controlesAnimal = controles
      .filter((c) => c.codigo_rgb_id_animal === codigo)
      .sort(masNuevoPrimero('fecha_control'));

    const controlesLoteAnimal = controlesLote
      .filter((c) => Array.isArray(c.animales) && c.animales.some((cod) => FichaAnimal.codigoDeAnimal(cod) === codigo))
      .sort(masNuevoPrimero('fecha_control'));

    const movimientosAnimal = movimientos
      .filter((m) => Array.isArray(m.animales) && m.animales.some((cod) => FichaAnimal.codigoDeAnimal(cod) === codigo))
      .sort(masNuevoPrimero('fecha_movimiento'));

    // Un animal puede tener más de una baja (histórico); se muestra la última.
    const baja = bajas
      .filter((b) => b.codigo_rgb_id_animal === codigo)
      .sort(masNuevoPrimero('f_desincorporacion'))[0] || null;

    const produccionAnimal = produccion
      .filter((p) => p.codigo_rgb_id_animal_hembra === codigo)
      .sort(masNuevoPrimero('fecha_produccion'));

    return {
      codigoAnimal: codigo,
      catalogs: catalogos,
      nombreDe,
      pesos: pesosAnimal,
      servicios: serviciosAnimal,
      partos: partosAnimal,
      controles: controlesAnimal,
      controlesLote: controlesLoteAnimal,
      movimientos: movimientosAnimal,
      baja,
      produccion: produccionAnimal,
    };
  },

  /** Los animales de un movimiento/control pueden ser códigos u objetos. */
  codigoDeAnimal(valor) {
    if (!valor) return '';
    if (typeof valor === 'string') return valor;
    return valor.codigo_rgb_id_animal || '';
  },

  /** HTML completo de la ficha. */
  html(animal, datos) {
    const secciones = [
      this.htmlFoto(animal, datos),
      this.htmlDatosAnimal(animal, datos),
      this.htmlIndicadores(animal, datos),
      this.htmlPesajes(datos),
      this.htmlServicios(datos),
      this.htmlPartos(datos),
      this.htmlControles(datos),
      this.htmlMovimientos(datos),
      this.htmlProduccion(datos),
      this.htmlBaja(animal, datos),
    ].filter(Boolean);

    return secciones.join('');
  },

  // ---- Secciones ---------------------------------------------------

  /* ------------------------------------------------------------------
     Foto del animal
     ------------------------------------------------------------------ */

  _fotoUrl: null,
  _fotoPropia: false,

  /** Libera la URL de objeto de la foto anterior (si era del equipo). */
  soltarFotoAnterior() {
    if (this._fotoUrl && this._fotoPropia) {
      try { URL.revokeObjectURL(this._fotoUrl); } catch (e) { /* nada */ }
    }
    this._fotoUrl = null;
    this._fotoPropia = false;
  },

  /**
   * Sección con la foto. Se puede tomar una nueva desde la cámara del
   * teléfono (o elegirla del carrete) aunque no haya señal: queda en la
   * cola del equipo y sube sola en la próxima sincronización.
   */
  htmlFoto(animal, datos) {
    const foto = (datos && datos.foto) || { url: null };
    const pendiente = datos && datos.fotoPendiente;
    const codigo = animal.codigo_rgb_id_animal || '';
    const iniciales = (animal.nombre || codigo || '?').trim().charAt(0).toUpperCase();

    const marco = foto.url
      ? `<img class="ficha-foto__imagen" src="${escaparHtml(foto.url)}" alt="Foto de ${escaparHtml(animal.nombre || codigo || 'el animal')}">`
      : `<div class="ficha-foto__vacio" aria-hidden="true">${escaparHtml(iniciales)}</div>`;

    return `
      <section class="modal-detalle-registro-grupo ficha-foto">
        <div class="ficha-foto__marco">${marco}</div>
        <div class="ficha-foto__datos">
          <h4>Foto del animal</h4>
          <p class="ficha-foto__estado">${foto.url
            ? (pendiente ? 'Guardada en el equipo · se sube al sincronizar' : 'Guardada')
            : 'Este animal todavía no tiene foto en este equipo'}</p>
          <button class="boton boton--secundario ficha-foto__boton" type="button" data-foto-animal="${escaparHtml(animal.uuid_local)}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"/><circle cx="12" cy="13" r="3.5"/></svg>
            ${foto.url ? 'Cambiar foto' : 'Tomar o subir foto'}
          </button>
        </div>
      </section>
    `;
  },

  /**
   * Abre la cámara (o el selector de archivos) y deja la foto en la cola
   * local. Nunca bloquea nada: si falla, se avisa y la ficha no cambia.
   */
  async elegirFoto(uuidLocal) {
    const animal = await Db.obtener('animales', uuidLocal).catch(() => null);
    if (!animal) {
      mostrarToast('No se encontró este animal en el dispositivo.', 'error');
      return;
    }

    const selector = document.createElement('input');
    selector.type = 'file';
    selector.accept = 'image/*';
    // capture="environment" abre la cámara trasera directo en el celular;
    // en el escritorio simplemente se comporta como un selector normal.
    selector.setAttribute('capture', 'environment');
    selector.style.display = 'none';

    selector.addEventListener('change', async () => {
      const archivo = selector.files && selector.files[0];
      if (selector.parentNode) selector.parentNode.removeChild(selector);
      if (!archivo) return;

      mostrarToast('Preparando la foto…');

      let dataUri = null;
      try {
        dataUri = await Fotos.comprimirArchivo(archivo);
      } catch (error) {
        mostrarToast(error.message || 'No se pudo preparar la foto.', 'error');
        return;
      }
      if (!dataUri) {
        mostrarToast('No se pudo leer esa imagen. Prueba con otra foto.', 'error');
        return;
      }

      try {
        await Fotos.guardarPendiente(uuidLocal, dataUri, animal.codigo_rgb_id_animal);
      } catch (error) {
        mostrarToast('No hay espacio suficiente para guardar la foto.', 'error');
        return;
      }

      mostrarToast('Foto guardada en el equipo.');

      // Con señal se intenta subir de inmediato; si no, queda en cola.
      const sesion = await Db.obtenerSesion().catch(() => null);
      if (sesion && navigator.onLine && sesion.modo_offline !== 'consulta') {
        try {
          const resultado = await Fotos.enviarPendientes(sesion);
          if (resultado.subidas) mostrarToast('Foto subida al servidor.');
          else if (resultado.fallidas) mostrarToast('La foto quedó pendiente para la próxima sincronización.', 'error');
          else mostrarToast('La foto se subirá cuando el animal tenga código definitivo.');
        } catch (error) {
          mostrarToast('La foto quedó pendiente para la próxima sincronización.', 'error');
        }
      }

      this.refrescarFotoEnLista(uuidLocal);
      this.abrir(uuidLocal);
    });

    document.body.appendChild(selector);
    selector.click();
  },

  /** Cambia la miniatura de la tarjeta del hato sin repintar la lista. */
  async refrescarFotoEnLista(uuidLocal) {
    const boton = document.querySelector(`button[data-ficha-animal="${uuidLocal}"]`);
    const tarjeta = boton ? boton.closest('.chapeta') : null;
    if (!tarjeta) return;

    const animal = await Db.obtener('animales', uuidLocal).catch(() => null);
    if (!animal) return;

    let foto = { url: null, propia: false };
    try {
      foto = await Fotos.urlDe(animal);
    } catch (e) {
      foto = { url: null, propia: false };
    }

    const anterior = tarjeta.querySelector('img.chapeta__foto');
    if (!foto.url) {
      if (anterior) anterior.remove();
      tarjeta.classList.remove('chapeta--con-foto');
      return;
    }
    if (anterior) {
      anterior.src = foto.url;
      return;
    }
    const imagen = document.createElement('img');
    imagen.className = 'chapeta__foto';
    imagen.src = foto.url;
    imagen.alt = 'Foto de ' + (animal.nombre || animal.codigo_rgb_id_animal || 'el animal');
    imagen.loading = 'lazy';
    imagen.addEventListener('error', () => {
      imagen.remove();
      tarjeta.classList.remove('chapeta--con-foto');
    });
    tarjeta.classList.add('chapeta--con-foto');
    tarjeta.insertBefore(imagen, tarjeta.firstChild);
  },

  htmlDatosAnimal(animal, datos) {
    const { nombreDe, catalogs } = datos;
    const edad = edadAnimalTexto(animal.f_nacimiento);
    const baja = estadoBajaTexto(animal);

    const campos = [
      fichaCampo('Código', animal.codigo_rgb_id_animal, animal.codigo_provisional ? 'provisional' : ''),
      fichaCampo('Número de arete', animal.nro_arete),
      fichaCampo('Nombre', animal.nombre),
      fichaCampo('Sexo', ({ 1: 'Hembra', 2: 'Macho' })[Number(animal.sexo)] || '—'),
      fichaCampo('Raza', nombreDe(catalogs.razas, animal.mrazas_id)),
      fichaCampo('Aptitud', nombreDe(catalogs.aptitudes, animal.aptitud)),
      fichaCampo('Lote', nombreDe(catalogs.lotes, animal.mlotes_id)),
      fichaCampo('Categoría', nombreDe(catalogs.categorias, animal.categoria_ingreso_id)),
      fichaCampo('Fecha de nacimiento', formatearFecha(animal.f_nacimiento) || '—', edad),
      fichaCampo('Peso al nacer', animal.peso_nacimiento ? animal.peso_nacimiento + ' kg' : ''),
      fichaCampo('Estado', baja ? 'De baja · ' + baja
        : (ESTATUS_ANIMAL_TEXTO[Number(animal.estatus_animal)] || 'Activo')),
    ];

    const origen = [
      animal.rgd_madre || animal.nombre_madre
        ? 'Madre: ' + [animal.rgd_madre, animal.nombre_madre].filter(Boolean).join(' · ')
        : '',
      animal.rgd_padre || animal.nombre_padre
        ? 'Padre: ' + [animal.rgd_padre, animal.nombre_padre].filter(Boolean).join(' · ')
        : '',
      animal.procedencia ? 'Procedencia: ' + animal.procedencia : '',
    ].filter(Boolean);

    return `
      <section class="modal-detalle-registro-grupo">
        <h4>Datos del animal</h4>
        <dl>${campos.join('')}</dl>
        ${origen.length ? `<p class="ficha-origen">${escaparHtml(origen.join(' · '))}</p>` : ''}
      </section>
    `;
  },

  htmlIndicadores(animal, datos) {
    const tarjetas = [];

    // Peso y ganancia diaria
    const pesos = datos.pesos;
    if (pesos.length) {
      const ultimo = pesos[0];
      const ganancia = gananciaDiariaAnimal(pesos);
      const detalle = [formatearFecha(ultimo.fecha_pesaje)];
      if (ganancia) {
        detalle.push((ganancia.kg >= 0 ? '+' : '') + formatoNumero(ganancia.kg) + ' kg en '
          + ganancia.dias + ' día(s)');
      }
      if (pesos.length > 1) detalle.push(pesos.length + ' pesajes');
      tarjetas.push(fichaIndicador(
        formatoNumero(ultimo.peso) + ' kg',
        'Último peso',
        detalle.join(' · '),
        ultimo.estado_sync
      ));
      if (ganancia) {
        tarjetas.push(fichaIndicador(
          formatoNumero(ganancia.gdp, 3) + ' kg/día',
          'Ganancia diaria',
          'Entre el ' + formatearFecha(pesos[1].fecha_pesaje) + ' y el ' + formatearFecha(ultimo.fecha_pesaje)
        ));
      }
    }

    // Servicios
    if (datos.servicios.length) {
      const ultimo = datos.servicios[0];
      const tipo = datos.nombreDe(datos.catalogs.tiposServicio, ultimo.mservicios_id);
      const dias = diasEntreFechas(ultimo.f_servicio, hoyISO());
      const detalle = [tipo || 'Servicio'];
      if (Number.isFinite(dias) && dias >= 0) detalle.push('hace ' + dias + ' día(s)');
      if (datos.servicios.length > 1) detalle.push(datos.servicios.length + ' en total');
      tarjetas.push(fichaIndicador(
        formatearFecha(ultimo.f_servicio),
        'Último servicio',
        detalle.join(' · '),
        ultimo.estado_sync
      ));
    }

    // Partos
    if (datos.partos.length) {
      const ultimo = datos.partos[0];
      const crias = criasDeNacimiento(ultimo);
      tarjetas.push(fichaIndicador(
        String(datos.partos.length),
        datos.partos.length === 1 ? 'Parto registrado' : 'Partos registrados',
        'Último: ' + formatearFecha(ultimo.f_parto)
          + (crias.length ? ' · ' + crias.length + ' cría(s)' : ''),
        ultimo.estado_sync
      ));
    }

    // Controles sanitarios
    const totalControles = datos.controles.length + datos.controlesLote.length;
    if (totalControles) {
      const proximo = proximoControl(datos.controles, datos.controlesLote);
      tarjetas.push(fichaIndicador(
        String(totalControles),
        'Controles sanitarios',
        proximo ? 'Próximo: ' + formatearFecha(proximo) : 'Sin próximo control programado'
      ));
    }

    // Producción de leche (promedio de los últimos 7 registros)
    if (datos.produccion.length) {
      const ultimos = datos.produccion.slice(0, 7);
      const totales = ultimos
        .map((p) => Number(p.cantidad_leche_m || 0) + Number(p.cantidad_leche_t || 0))
        .filter((n) => Number.isFinite(n));
      const promedio = totales.length
        ? totales.reduce((suma, n) => suma + n, 0) / totales.length
        : 0;
      tarjetas.push(fichaIndicador(
        formatoNumero(promedio, 1) + ' L/día',
        'Producción de leche',
        'Promedio de los últimos ' + ultimos.length + ' registro(s)'
      ));
    }

    if (!tarjetas.length) return '';

    return `
      <section class="modal-detalle-registro-grupo">
        <h4>Resumen</h4>
        <div class="ficha-indicadores">${tarjetas.join('')}</div>
      </section>
    `;
  },

  htmlPesajes(datos) {
    const pesos = datos.pesos;
    if (!pesos.length) return seccionVacia('Pesajes', 'Sin pesajes registrados en este dispositivo.');

    const visibles = pesos.slice(0, 6);
    const items = visibles.map((peso, indice) => {
      const anterior = visibles[indice + 1];
      let detalle = '';
      if (anterior) {
        const diferencia = Number(peso.peso) - Number(anterior.peso);
        const dias = diasEntreFechas(anterior.fecha_pesaje, peso.fecha_pesaje);
        const partes = [(diferencia >= 0 ? '+' : '') + formatoNumero(diferencia) + ' kg'];
        if (dias > 0) partes.push(dias + ' día(s)', formatoNumero(diferencia / dias, 3) + ' kg/día');
        detalle = partes.join(' · ');
      }
      return fichaEvento(formatearFecha(peso.fecha_pesaje), formatoNumero(peso.peso) + ' kg', detalle, peso.estado_sync);
    });

    return seccionLista('Pesajes', pesos.length, visibles.length, items);
  },

  htmlServicios(datos) {
    const servicios = datos.servicios;
    if (!servicios.length) return '';

    const visibles = servicios.slice(0, 5);
    const items = visibles.map((servicio) => {
      const tipo = datos.nombreDe(datos.catalogs.tiposServicio, servicio.mservicios_id);
      const titulo = (tipo || 'Servicio') + ' N° ' + (servicio.nro_servicio || '—');
      const partes = [];

      const contraparte = servicio.codigo_rgb_id_animal_hembra === datos.codigoAnimal
        ? servicio.codigo_rgb_id_animal_macho
        : servicio.codigo_rgb_id_animal_hembra;
      if (contraparte) partes.push(contraparte);
      if (servicio.mservicios_id === 3 && servicio.codigo_rgb_id_animal_dona) {
        partes.push('donadora ' + servicio.codigo_rgb_id_animal_dona);
      }

      const estado = datos.nombreDe(datos.catalogs.estadosFisiologicos, servicio.medofisiologicos_id);
      if (estado) partes.push(estado);
      if (Number(servicio.parto) === 1) {
        partes.push('parto ' + (formatearFecha(servicio.fecha_parto) || 'confirmado'));
      } else if (servicio.f_probable_parto) {
        partes.push('parto probable ' + formatearFecha(servicio.f_probable_parto));
      }

      return fichaEvento(formatearFecha(servicio.f_servicio), titulo, partes.join(' · '), servicio.estado_sync);
    });

    return seccionLista('Servicios reproductivos', servicios.length, visibles.length, items);
  },

  htmlPartos(datos) {
    const partos = datos.partos;
    if (!partos.length) return '';

    const visibles = partos.slice(0, 5);
    const items = visibles.map((parto) => {
      const crias = criasDeNacimiento(parto);
      const titulo = crias.length
        ? crias.length + ' cría(s)'
        : 'Parto registrado';
      const detalle = crias.map((cria, indice) => {
        const partes = [
          (indice + 1) + ')',
          Number(cria.sexo) === 2 ? 'Macho' : 'Hembra',
          Number(cria.estatus_nacido) === 2 ? 'muerto' : 'vivo',
        ];
        if (cria.peso_nacimiento) partes.push(cria.peso_nacimiento + ' kg');
        if (cria.id_ternero) partes.push('ID ' + cria.id_ternero);
        return partes.join(' ');
      }).join(' · ');

      return fichaEvento(
        formatearFecha(parto.f_parto),
        titulo,
        detalle + (parto.nro_parto ? ' · parto N° ' + parto.nro_parto : ''),
        parto.estado_sync
      );
    });

    return seccionLista('Partos y crías', partos.length, visibles.length, items);
  },

  htmlControles(datos) {
    const eventos = [];

    datos.controles.forEach((control) => {
      eventos.push({
        fecha: control.fecha_control,
        titulo: control.evento || 'Control sanitario',
        detalle: [
          control.responsable ? 'Responsable: ' + control.responsable : '',
          productosTexto(control.productos_aplicados),
          control.fecha_prox_control ? 'Próximo: ' + formatearFecha(control.fecha_prox_control) : '',
        ].filter(Boolean).join(' · '),
        estado: control.estado_sync,
        porLote: false,
      });
    });

    datos.controlesLote.forEach((control) => {
      eventos.push({
        fecha: control.fecha_control,
        titulo: (control.evento || 'Control sanitario') + ' · por lote',
        detalle: [
          control.lote_nombre ? control.lote_nombre : '',
          productosTexto(control.productos_aplicados),
          control.fecha_prox_control ? 'Próximo: ' + formatearFecha(control.fecha_prox_control) : '',
        ].filter(Boolean).join(' · '),
        estado: control.estado_sync,
        porLote: true,
      });
    });

    if (!eventos.length) return '';

    eventos.sort((a, b) => String(b.fecha || '').localeCompare(String(a.fecha || '')));

    const visibles = eventos.slice(0, 6);
    const items = visibles.map((evento) =>
      fichaEvento(formatearFecha(evento.fecha), evento.titulo, evento.detalle, evento.estado));

    return seccionLista('Controles sanitarios', eventos.length, visibles.length, items);
  },

  htmlMovimientos(datos) {
    const movimientos = datos.movimientos;
    if (!movimientos.length) return '';

    const visibles = movimientos.slice(0, 5);
    const items = visibles.map((movimiento) => {
      const tipo = (typeof MovimientoRebano !== 'undefined' && MovimientoRebano.nombreTipo)
        ? MovimientoRebano.nombreTipo(movimiento.tipo_movimiento)
        : movimiento.tipo_movimiento;

      const origen = movimiento.lote_origen_nombre || movimiento.potrero_origen_nombre
        || (movimiento.mlotes_origen_id ? 'Lote ' + movimiento.mlotes_origen_id : '');
      const destino = movimiento.lote_destino_nombre || movimiento.potrero_destino_nombre
        || (movimiento.mlotes_destino_id ? 'Lote ' + movimiento.mlotes_destino_id : '');

      const detalle = [
        origen && destino ? origen + ' → ' + destino : '',
        movimiento.motivo || '',
      ].filter(Boolean).join(' · ');

      return fichaEvento(formatearFecha(movimiento.fecha_movimiento), tipo || 'Movimiento', detalle, movimiento.estado_sync);
    });

    return seccionLista('Movimientos de rebaño', movimientos.length, visibles.length, items);
  },

  htmlProduccion(datos) {
    const produccion = datos.produccion;
    if (!produccion.length) return '';

    const visibles = produccion.slice(0, 5);
    const items = visibles.map((registro) => {
      const manana = Number(registro.cantidad_leche_m || 0);
      const tarde = Number(registro.cantidad_leche_t || 0);
      const total = manana + tarde;
      return fichaEvento(
        formatearFecha(registro.fecha_produccion),
        formatoNumero(total, 1) + ' L',
        (manana ? 'Mañana ' + formatoNumero(manana, 1) + ' L' : '')
          + (manana && tarde ? ' · ' : '')
          + (tarde ? 'Tarde ' + formatoNumero(tarde, 1) + ' L' : ''),
        registro.estado_sync
      );
    });

    return seccionLista('Producción de leche', produccion.length, visibles.length, items);
  },

  htmlBaja(animal, datos) {
    const baja = datos.baja;
    const bajaTexto = estadoBajaTexto(animal);
    if (!baja && !bajaTexto) return '';

    const campos = [];
    if (baja) {
      campos.push(fichaCampo('Tipo de baja', Desincorporacion.nombreTipo(baja.tipo_desincorporacion) || '—'));
      campos.push(fichaCampo('Fecha', formatearFecha(baja.f_desincorporacion)));
      const causa = causaBajaTexto(baja, datos.catalogs);
      if (causa) campos.push(fichaCampo('Causa', causa));
      if (baja.observaciones) campos.push(fichaCampo('Observaciones', baja.observaciones));
      campos.push(fichaCampo('Sincronización', textoEstadoSync(baja.estado_sync)));
    } else {
      campos.push(fichaCampo('Estado', bajaTexto));
      campos.push(fichaCampo('Detalle', 'La baja se registró en otro dispositivo; el detalle todavía no está descargado.'));
    }

    return `
      <section class="modal-detalle-registro-grupo">
        <h4>Baja del animal</h4>
        <dl>${campos.join('')}</dl>
      </section>
    `;
  },
};

// ---- Ayudas de la ficha ---------------------------------------------

const ESTATUS_ANIMAL_TEXTO = {
  1: 'Activo',
  2: 'Donador activo',
  3: 'Donador',
  4: 'Donadora activo',
  5: 'Donadora',
};

function fichaCampo(etiqueta, valor, extra) {
  const texto = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
  const sufijo = extra ? ' <span class="ficha-campo__extra">' + escaparHtml(extra) + '</span>' : '';
  return `<div class="modal-detalle-registro-campo"><dt>${escaparHtml(etiqueta)}</dt>`
    + `<dd>${escaparHtml(texto)}${sufijo}</dd></div>`;
}

function fichaIndicador(valor, etiqueta, detalle, estadoSync) {
  return `
    <div class="ficha-indicador">
      <span class="ficha-indicador__valor">${escaparHtml(valor)}</span>
      <span class="ficha-indicador__etiqueta">${escaparHtml(etiqueta)}</span>
      ${detalle ? `<span class="ficha-indicador__detalle">${escaparHtml(detalle)}</span>` : ''}
      ${estadoSync && estadoSync !== 'sincronizado'
        ? `<span class="ficha-pendiente">${escaparHtml(textoEstadoSync(estadoSync))}</span>` : ''}
    </div>
  `;
}

function fichaEvento(fecha, titulo, detalle, estadoSync) {
  return `
    <li class="ficha-evento">
      <span class="ficha-evento__fecha">${escaparHtml(fecha || '—')}</span>
      <div class="ficha-evento__cuerpo">
        <span class="ficha-evento__titulo">${escaparHtml(titulo)}</span>
        ${detalle ? `<span class="ficha-evento__detalle">${escaparHtml(detalle)}</span>` : ''}
        ${estadoSync && estadoSync !== 'sincronizado'
          ? `<span class="ficha-pendiente">${escaparHtml(textoEstadoSync(estadoSync))}</span>` : ''}
      </div>
    </li>
  `;
}

function seccionLista(titulo, total, mostrados, items) {
  const pie = total > mostrados
    ? `<p class="ficha-vacio">Mostrando los últimos ${mostrados} de ${total}.</p>`
    : '';
  return `
    <section class="modal-detalle-registro-grupo">
      <h4>${escaparHtml(titulo)} (${total})</h4>
      <ul class="ficha-timeline">${items.join('')}</ul>
      ${pie}
    </section>
  `;
}

function seccionVacia(titulo, mensaje) {
  return `
    <section class="modal-detalle-registro-grupo">
      <h4>${escaparHtml(titulo)}</h4>
      <p class="ficha-vacio">${escaparHtml(mensaje)}</p>
    </section>
  `;
}

function textoEstadoSync(estado) {
  return ({
    pendiente: 'pendiente de sincronizar',
    error: 'error al sincronizar',
    conflicto: 'en conflicto',
    sincronizado: 'sincronizado',
  })[estado] || 'pendiente de sincronizar';
}

function formatoNumero(valor, decimales = 0) {
  const numero = Number(valor);
  if (!Number.isFinite(numero)) return '—';
  return numero.toFixed(decimales);
}

function hoyISO() {
  const hoy = new Date();
  return [hoy.getFullYear(), String(hoy.getMonth() + 1).padStart(2, '0'), String(hoy.getDate()).padStart(2, '0')].join('-');
}

/** "3 años y 2 meses" (o días si es muy chico). */
function edadAnimalTexto(fechaNacimiento) {
  const iso = comoFechaInput(fechaNacimiento);
  const partes = iso.split('-').map(Number);
  if (partes.length !== 3 || !partes[0] || !partes[1] || !partes[2]) return '';

  const nacimiento = new Date(partes[0], partes[1] - 1, partes[2]);
  if (Number.isNaN(nacimiento.getTime())) return '';

  const hoy = new Date();
  let meses = (hoy.getFullYear() - nacimiento.getFullYear()) * 12
    + (hoy.getMonth() - nacimiento.getMonth());
  if (hoy.getDate() < nacimiento.getDate()) meses -= 1;
  if (meses < 0) return '';

  if (meses === 0) {
    const dias = Math.max(0, Math.round((hoy - nacimiento) / 86400000));
    return dias + ' día' + (dias === 1 ? '' : 's');
  }

  const anios = Math.floor(meses / 12);
  const restoMeses = meses % 12;
  if (anios === 0) return restoMeses + ' mes' + (restoMeses === 1 ? '' : 'es');

  const textoAnios = anios + ' año' + (anios === 1 ? '' : 's');
  return restoMeses
    ? textoAnios + ' y ' + restoMeses + ' mes' + (restoMeses === 1 ? '' : 'es')
    : textoAnios;
}

/** Diferencia entre los dos pesajes más recientes. */
function gananciaDiariaAnimal(pesosOrdenados) {
  if (pesosOrdenados.length < 2) return null;
  const ultimo = pesosOrdenados[0];
  const anterior = pesosOrdenados[1];

  const pesoUltimo = Number(ultimo.peso);
  const pesoAnterior = Number(anterior.peso);
  if (!Number.isFinite(pesoUltimo) || !Number.isFinite(pesoAnterior)) return null;

  const dias = diasEntreFechas(anterior.fecha_pesaje, ultimo.fecha_pesaje);
  if (!dias || dias <= 0) return null;

  const kg = pesoUltimo - pesoAnterior;
  return { kg, dias, gdp: kg / dias };
}

/** Las crías de un nacimiento, vengan como lista o como campos planos. */
function criasDeNacimiento(nacimiento) {
  if (Array.isArray(nacimiento.terneros) && nacimiento.terneros.length) {
    return nacimiento.terneros;
  }
  return [{
    sexo: nacimiento.sexo,
    estatus_nacido: nacimiento.estatus_nacido,
    peso_nacimiento: nacimiento.peso_nacimiento,
    id_ternero: nacimiento.id_ternero,
  }];
}

/** Próximo control programado (individual o por lote), si lo hay. */
function proximoControl(controles, controlesLote) {
  const fechas = [...controles, ...controlesLote]
    .map((control) => control.fecha_prox_control)
    .filter((fecha) => !!fecha)
    .sort();
  const proxima = fechas.find((fecha) => comoFechaInput(fecha) >= hoyISO());
  return proxima || null;
}

/** "Vacuna A 2 ml · Vacuna B" a partir de productos_aplicados. */
function productosTexto(productos) {
  if (!Array.isArray(productos) || !productos.length) return '';
  return productos.map((producto) => {
    const nombre = producto.producto_nombre || producto.nombre_producto
      || ('Producto ' + (producto.mproductos_id || ''));
    return producto.dosis ? nombre + ' (' + producto.dosis + ')' : nombre;
  }).join(', ');
}
