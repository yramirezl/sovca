// Motor de sincronizacion. Recorre los modulos declarados en CONFIG y
// dispara eventos para que app.js actualice la interfaz.

const CAMPOS_INTERNOS_NO_ENVIAR = new Set([
  'uuid_local', 'id', 'version', 'estado_sync', 'created', 'updated', 'mfundos_id',
  'codigo_provisional', 'nacimiento_uuid_local', 'ficha_animal_uuid_local', 'ficha_animal_codigo', 'fichado',
  // Solo existe en el dispositivo: motivo del último error de envío.
  'ultimo_error',
]);

function generarUUID() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function datosParaEnviar(registroLocal) {
  const datos = {};
  for (const campo in registroLocal) {
    if (!CAMPOS_INTERNOS_NO_ENVIAR.has(campo)) datos[campo] = registroLocal[campo];
  }
  return datos;
}

function notificar(resumen) {
  window.dispatchEvent(new CustomEvent('bovs:sync', { detail: resumen }));
}

// Módulos donde un registro con status = 0 significa "anulado".
// En el resto el 0 puede tener otro significado, así que no se toca.
const MODULOS_CON_ANULACION = new Set([
  'produccion_leche',
  'pesos',
  'control_sanitario',
  'servicios',
]);

/** Mensaje legible de cualquier error (o un texto por defecto). */
function mensajeError(e) {
  return e && e.message ? e.message : 'error_desconocido';
}

const Sync = {
  sincronizando: false,
  reintentar: false,
  reintentarForzandoCatalogos: false,

  async asegurarRango(sesion, fundoId) {
    if (!navigator.onLine) return;

    for (const [modulo, infoModulo] of Object.entries(CONFIG.MODULOS_CON_RANGO_LOCAL || {})) {
      const rango = await Db.obtener('rangos', modulo);
      const disponibles = rango ? Math.max(0, rango.hasta - rango.actual + 1) : 0;

      if (disponibles >= CONFIG.UMBRAL_RESERVA_BAJA) continue;

      try {
        const resp = await Api.reservarRango(sesion.token, modulo, CONFIG.CANTIDAD_A_RESERVAR, fundoId);
        await Db.guardar('rangos', {
          modulo,
          actual: resp.desde,
          hasta: resp.hasta,
          prefijo_codigo: resp.prefijo_codigo,
        });
      } catch (e) {
        console.warn('No se pudo reservar rango de codigos (' + modulo + '):', e.message);
      }
    }
  },

  async actualizarCatalogos(sesion, fundoId, forzar = false) {
    // Los catálogos cambian poco: se refrescan cada
    // CONFIG.HORAS_REFRESCO_CATALOGOS horas (o cuando se sincroniza a
    // mano) para no gastar una petición y 9 escrituras en cada sync.
    const clave = 'catalogos_actualizados';
    if (!forzar) {
      const ultima = Number(await Db.obtenerSyncMeta(clave)) || 0;
      const horas = Number(CONFIG.HORAS_REFRESCO_CATALOGOS) || 0;
      if (ultima > 0 && horas > 0 && (Date.now() - ultima) < horas * 3600000) return false;
    }

    try {
      const resp = await Api.catalogos(sesion.token, fundoId);
      await Db.guardar('catalogos', { tipo: 'aptitudes', items: resp.aptitudes });
      await Db.guardar('catalogos', { tipo: 'lotes', items: resp.lotes });
      await Db.guardar('catalogos', { tipo: 'potreros', items: resp.potreros || [] });
      await Db.guardar('catalogos', { tipo: 'razas', items: resp.razas });
      await Db.guardar('catalogos', { tipo: 'categorias', items: resp.categorias || [] });
      await Db.guardar('catalogos', { tipo: 'tipos_servicio', items: resp.tipos_servicio || [] });
      await Db.guardar('catalogos', { tipo: 'estados_fisiologicos', items: resp.estados_fisiologicos || [] });
      await Db.guardar('catalogos', { tipo: 'categorias_productos', items: resp.categorias_productos || [] });
      await Db.guardar('catalogos', { tipo: 'productos_control', items: resp.productos_control || [] });
      await Db.guardar('catalogos', { tipo: 'causas_descarte', items: resp.causas_descarte || [] });
      await Db.guardar('catalogos', { tipo: 'causas_mortalidad', items: resp.causas_mortalidad || [] });
      await Db.guardar('catalogos', {
        tipo: 'parametros_servicios',
        items: { periodo_palpacion_dias: Number(resp.periodo_palpacion_dias || 0) },
      });
      await Db.guardarSyncMeta(clave, Date.now());
      window.dispatchEvent(new CustomEvent('bovs:catalogos'));
      return true;
    } catch (e) {
      console.warn('No se pudieron actualizar los catalogos:', e.message);
      return false;
    }
  },

  async enviarPendientes(sesion, fundoId) {
    const pendientes = await Db.obtenerTodos('outbox');
    if (!pendientes.length) return { enviados: 0, conflictos: 0, errores: 0, modulos_con_error: [] };

    const porModulo = {};
    for (const item of pendientes) {
      const modulo = item.modulo || 'animales';
      (porModulo[modulo] ??= []).push(item);
    }

    const prioridadModulo = { animales: 0, servicios: 1, nacimientos: 2 };
    const modulosEnOrden = Object.keys(porModulo).sort((a, b) =>
      (prioridadModulo[a] ?? 10) - (prioridadModulo[b] ?? 10)
    );

    let enviados = 0;
    let conflictos = 0;
    let errores = 0;
    const modulosConError = [];

    const dispositivoId = Dispositivo.obtenerId();

    for (const modulo of modulosEnOrden) {
      const items = porModulo[modulo];
      const almacen = modulo;
      // Anulaciones (borrado lógico) dentro de este lote: si alguna
      // choca con un cambio del servidor, la pantalla de conflictos
      // necesita saber que se trataba de un borrado y no de una edición.
      const eliminados = new Set(items.filter((p) => p.eliminado === true).map((p) => p.uuid_local));
      const cambios = items.map((p) => ({
        uuid_local: p.uuid_local,
        version_base: p.version_base,
        datos: p.datos,
        eliminado: p.eliminado === true,
        revertir: p.revertir === true,
        motivo_reversion: p.motivo_reversion || null,
        nacimiento_uuid_local: p.nacimiento_uuid_local || null,
      }));

      try {
        const resp = await Api.push(sesion.token, modulo, cambios, dispositivoId, fundoId);
        const mapaCodigos = new Map();

        for (const r of resp.resultados) {
          const registroLocal = await Db.obtener(almacen, r.uuid_local);

          if (r.resultado === 'eliminado') {
            // Anulación confirmada por el servidor: el registro ya no
            // hace falta en el equipo. Se borra junto con su fila del
            // outbox y cualquier conflicto que hubiera quedado.
            enviados++;
            await Db.eliminar(almacen, r.uuid_local);
            await Db.eliminar('outbox', r.uuid_local);
            await Db.eliminar('conflictos', r.uuid_local);
            continue;
          }

          if (r.resultado === 'revertido') {
            // Movimiento deshecho en el servidor: los animales ya
            // volvieron a su lote/potrero de origen y la auditoría
            // quedó guardada allá. Se refleja en la copia local.
            enviados++;
            await Db.eliminar('outbox', r.uuid_local);
            await Db.eliminar('conflictos', r.uuid_local);
            if (registroLocal) {
              registroLocal.revertido = true;
              registroLocal.fecha_reversion = r.fecha_reversion || new Date().toISOString();
              registroLocal.version = r.version ?? registroLocal.version;
              registroLocal.updated = r.updated || registroLocal.updated;
              registroLocal.estado_sync = 'sincronizado';
              delete registroLocal.ultimo_error;
              await Db.guardar(almacen, registroLocal);
            }
            continue;
          }

          if (r.resultado === 'creado' || r.resultado === 'actualizado') {
            enviados++;
            if (registroLocal) {
              registroLocal.id = r.id ?? registroLocal.id;
              registroLocal.version = r.version;
              registroLocal.updated = r.updated;
              registroLocal.estado_sync = 'sincronizado';
              registroLocal.ultimo_error = null;
              if (r.campos_resueltos) Object.assign(registroLocal, r.campos_resueltos);
              // El servidor devuelve el detalle de crías ya guardado
              // (con sus códigos y datos definitivos).
              if (modulo === 'nacimientos' && Array.isArray(r.terneros) && r.terneros.length) {
                registroLocal.terneros = r.terneros;
              }
              if (r.codigo_actualizado) registroLocal.codigo_rgb_id_animal = r.codigo_actualizado;
              if (r.codigo_anterior) registroLocal.codigo_provisional = false;
              await Db.guardar(almacen, registroLocal);
            }
            if (modulo === 'nacimientos' && r.ficha_animal_actualizada) {
              const animalFicha = await Db.obtener('animales', r.ficha_animal_actualizada.uuid_local);
              if (animalFicha) {
                animalFicha.codigo_ternero = r.ficha_animal_actualizada.codigo_ternero;
                animalFicha.version = r.ficha_animal_actualizada.version;
                animalFicha.updated = r.ficha_animal_actualizada.updated;
                animalFicha.estado_sync = 'sincronizado';
                await Db.guardar('animales', animalFicha);
              }
            }
            if (modulo === 'animales' && r.nacimiento_fichado) {
              const nacimiento = await Db.obtener('nacimientos', r.nacimiento_fichado.uuid_local);
              if (nacimiento) {
                nacimiento.fichado = true;
                nacimiento.ficha_animal_uuid_local = registroLocal?.uuid_local || null;
                nacimiento.ficha_animal_codigo = registroLocal?.codigo_rgb_id_animal || null;
                nacimiento.version = r.nacimiento_fichado.version;
                nacimiento.updated = r.nacimiento_fichado.updated;
                await Db.guardar('nacimientos', nacimiento);
              }
            }
            await Db.eliminar('outbox', r.uuid_local);

            if (r.codigo_anterior && r.campos_resueltos) {
              const campoCodigo = Object.keys(r.campos_resueltos)[0];
              mapaCodigos.set(r.codigo_anterior, r.campos_resueltos[campoCodigo]);
            }
          } else if (r.resultado === 'sin_cambios') {
            await Db.eliminar('outbox', r.uuid_local);
            if (registroLocal) {
              registroLocal.version = r.version;
              registroLocal.estado_sync = 'sincronizado';
              registroLocal.ultimo_error = null;
              await Db.guardar(almacen, registroLocal);
            }
          } else if (r.resultado === 'conflicto') {
            conflictos++;
            await Db.guardar('conflictos', {
              uuid_local: r.uuid_local,
              modulo,
              tipo: r.tipo || 'version',
              eliminado: eliminados.has(r.uuid_local),
              servidor: r.servidor,
              tuyo: r.tuyo,
              detectado_en: new Date().toISOString(),
            });
            await Db.eliminar('outbox', r.uuid_local);
            if (registroLocal) {
              registroLocal.estado_sync = 'conflicto';
              await Db.guardar(almacen, registroLocal);
            }
          } else {
            errores++;
            const item = await Db.obtener('outbox', r.uuid_local);
            if (item) {
              item.intentos = (item.intentos || 0) + 1;
              item.ultimo_error = r.mensaje;
              await Db.guardar('outbox', item);
            }
            if (registroLocal) {
              registroLocal.estado_sync = 'error';
              // El motivo se guarda también en el registro para poder
              // mostrarlo en la tarjeta (la fila del outbox no se ve).
              registroLocal.ultimo_error = r.mensaje || 'El servidor rechazó este registro.';
              await Db.guardar(almacen, registroLocal);
            }
          }
        }

        if (mapaCodigos.size) {
          await this.remapearCodigosDeAnimal(mapaCodigos);
        }
      } catch (e) {
        // Mantenimiento del servidor: NO es un rechazo de los registros.
        // Se dejan tal cual (pendientes) y se corta el ciclo completo para
        // que la app lo informe sin manchar las tarjetas con errores.
        if (e && e.cuerpo && e.cuerpo.codigo === 'mantenimiento') {
          modulosConError.push({ modulo, fase: 'push', mensaje: mensajeError(e), mantenimiento: true });
          return {
            enviados,
            conflictos,
            errores,
            modulos_con_error: modulosConError,
            mantenimiento: true,
          };
        }

        errores += items.length;
        modulosConError.push({
          modulo,
          fase: 'push',
          mensaje: e && e.message ? e.message : 'error_desconocido',
        });

        for (const item of items) {
          const out = await Db.obtener('outbox', item.uuid_local);
          if (out) {
            out.intentos = (out.intentos || 0) + 1;
            out.ultimo_error = e && e.message ? e.message : 'error_desconocido';
            await Db.guardar('outbox', out);
          }
          const reg = await Db.obtener(almacen, item.uuid_local);
          if (reg) {
            reg.estado_sync = 'error';
            reg.ultimo_error = e && e.message ? e.message : 'No se pudo contactar al servidor.';
            await Db.guardar(almacen, reg);
          }
        }
      }
    }

    return { enviados, conflictos, errores, modulos_con_error: modulosConError };
  },

  async remapearCodigosDeAnimal(mapaCodigos) {
    for (const modulo of CONFIG.MODULOS) {
      const campos = CONFIG.CAMPOS_REFERENCIA_ANIMAL[modulo];
      if (!campos) continue;

      const registros = await Db.obtenerTodos(modulo);
      for (const reg of registros) {
        let cambio = false;
        for (const campo of campos) {
          if (reg[campo] && mapaCodigos.has(reg[campo])) {
            reg[campo] = mapaCodigos.get(reg[campo]);
            cambio = true;
          }
        }
        // Algunos registros guardan la lista de animales en un array
        // (control sanitario por lote).
        if (Array.isArray(reg.animales)) {
          reg.animales = reg.animales.map((codigo) => {
            if (!mapaCodigos.has(codigo)) return codigo;
            cambio = true;
            return mapaCodigos.get(codigo);
          });
        }
        if (cambio) await Db.guardar(modulo, reg);
      }

      const outboxDelModulo = (await Db.obtenerTodos('outbox')).filter((o) => o.modulo === modulo);
      for (const item of outboxDelModulo) {
        let cambio = false;
        for (const campo of campos) {
          const codigo = item.datos && item.datos[campo];
          if (codigo && mapaCodigos.has(codigo)) {
            item.datos[campo] = mapaCodigos.get(codigo);
            cambio = true;
          }
        }
        if (item.datos && Array.isArray(item.datos.animales)) {
          item.datos.animales = item.datos.animales.map((codigo) => {
            if (!mapaCodigos.has(codigo)) return codigo;
            cambio = true;
            return mapaCodigos.get(codigo);
          });
        }
        if (cambio) await Db.guardar('outbox', item);
      }
    }
  },

  async traerCambios(sesion, modulo, fundoId) {
    const almacen = modulo;
    // El cursor vive en el sync_meta DEL FUNDO (cada fundo tiene su base
    // de datos), así que cambiar de fundo no mezcla ni salta registros.
    // Su formato es "marca|id" (cursor compuesto): el servidor lo
    // devuelve listo en "siguiente_desde" y acá se guarda tal cual.
    const claveCursor = 'ultima_sync_' + modulo;
    let desde = (await Db.obtenerSyncMeta(claveCursor)) || '';
    let total = 0;

    // Registros con cambios propios sin subir (o con conflicto abierto):
    // NO se sobrescriben con la copia del servidor. Si el servidor ya
    // tiene una versión más nueva, el push posterior lo detecta como
    // conflicto y la persona decide en la pantalla de conflictos; así no
    // se pierde ni el dato local ni el del servidor.
    const uuidPendientes = new Set(
      (await Db.obtenerTodos('outbox')).map((item) => item.uuid_local)
    );

    const serviciosPorId = modulo === 'nacimientos'
      ? new Map((await Db.obtenerTodos('servicios'))
        .filter((servicio) => servicio.id)
        .map((servicio) => [Number(servicio.id), servicio.uuid_local]))
      : null;

    const dispositivoId = Dispositivo.obtenerId();

    while (true) {
      const resp = await Api.pull(sesion.token, modulo, desde, dispositivoId, fundoId);

      const registros = Array.isArray(resp.registros) ? resp.registros : [];
      const registrosValidos = [];

      for (const fila of registros) {
        if (!fila || !fila.uuid_local) {
          console.warn('Registro omitido en pull de', modulo, 'por uuid_local faltante:', fila);
          continue;
        }
        // Hay cambios locales sin enviar de este registro: se deja la
        // copia local intacta y se resolverá al subir.
        if (uuidPendientes.has(fila.uuid_local)) continue;

        // Registro ANULADO en el servidor (status = 0, ya sea porque se
        // eliminó desde la web o desde otro dispositivo): se borra del
        // equipo para que no siga apareciendo en los listados. Solo se
        // aplica a los módulos que se pueden anular; en el resto el
        // status puede significar otra cosa.
        if (MODULOS_CON_ANULACION.has(modulo) && fila.status !== undefined && Number(fila.status) === 0) {
          await Db.eliminar(almacen, fila.uuid_local);
          await Db.eliminar('conflictos', fila.uuid_local);
          continue;
        }

        const filaLocal = {
          ...fila,
          estado_sync: 'sincronizado',
        };
        if (modulo === 'nacimientos' && fila.mregservicios_id) {
          const uuidServicio = serviciosPorId.get(Number(fila.mregservicios_id));
          if (uuidServicio) filaLocal.servicio_uuid_local = uuidServicio;
        }
        registrosValidos.push(filaLocal);
      }

      try {
        await Db.guardarVarios(almacen, registrosValidos);
      } catch (e) {
        for (const fila of registrosValidos) {
          try {
            await Db.guardar(almacen, fila);
          } catch (err) {
            console.warn('No se pudo guardar un registro del pull de', modulo, err && err.message ? err.message : err);
          }
        }
      }

      total += registrosValidos.length;

      desde = resp.siguiente_desde;
      await Db.guardarSyncMeta(claveCursor, desde);

      if (!resp.hay_mas) break;
    }

    return { traidos: total };
  },

  async asegurarProductosControlSanitario(sesion, fundoId) {
    const claveMigracion = 'productos_control_sanitario_v1';
    if (await Db.obtenerSyncMeta(claveMigracion)) return 0;

    let desde = '';
    let procesados = 0;
    const dispositivoId = Dispositivo.obtenerId();

    while (true) {
      const respuesta = await Api.pull(sesion.token, 'control_sanitario', desde, dispositivoId, fundoId);
      const registros = Array.isArray(respuesta.registros) ? respuesta.registros : [];

      for (const registroServidor of registros) {
        if (!registroServidor || !registroServidor.uuid_local) continue;

        const local = await Db.obtener('control_sanitario', registroServidor.uuid_local);
        const pendiente = await Db.obtener('outbox', registroServidor.uuid_local);
        if (pendiente || (local && local.estado_sync && local.estado_sync !== 'sincronizado')) continue;

        if (local) {
          local.productos_aplicados = Array.isArray(registroServidor.productos_aplicados)
            ? registroServidor.productos_aplicados
            : [];
          await Db.guardar('control_sanitario', local);
        } else {
          await Db.guardar('control_sanitario', {
            ...registroServidor,
            estado_sync: 'sincronizado',
          });
        }
        procesados++;
      }

      desde = respuesta.siguiente_desde || desde;
      if (!respuesta.hay_mas) break;
    }

    await Db.guardarSyncMeta(claveMigracion, new Date().toISOString());
    return procesados;
  },

  /**
   * Descarga los cambios de todos los módulos del fundo activo en
   * tandas paralelas (CONFIG.DESCARGAS_EN_PARALELO). Cada módulo tiene
   * su propio cursor de sincronización, así que no se pisan entre sí.
   */
  async traerCambiosEnParalelo(sesion, fundoId) {
    const modulos = [...CONFIG.MODULOS];
    const tamano = Math.max(1, Number(CONFIG.DESCARGAS_EN_PARALELO) || 1);
    let traidos = 0;
    const errores = [];

    for (let indice = 0; indice < modulos.length; indice += tamano) {
      const lote = modulos.slice(indice, indice + tamano);
      const resultados = await Promise.all(lote.map(async (modulo) => {
        try {
          const resultado = await this.traerCambios(sesion, modulo, fundoId);
          return { modulo, traidos: resultado.traidos };
        } catch (e) {
          if (e && e.cuerpo && e.cuerpo.codigo === 'mantenimiento') {
            return { modulo, mantenimiento: true, error: mensajeError(e) };
          }
          return { modulo, error: mensajeError(e) };
        }
      }));

      let mantenimiento = false;
      for (const resultado of resultados) {
        if (resultado.error) {
          errores.push({
            modulo: resultado.modulo,
            fase: 'pull',
            mensaje: resultado.error,
            mantenimiento: resultado.mantenimiento === true,
          });
          if (resultado.mantenimiento) mantenimiento = true;
        } else {
          traidos += resultado.traidos;
        }
      }

      // El servidor está cerrado: no tiene sentido pedir los demás lotes.
      if (mantenimiento) {
        return { traidos, errores, mantenimiento: true };
      }
    }

    return { traidos, errores };
  },

  async sincronizar(opciones = {}) {
    // "automatico" = corrida de fondo (temporizador o al volver la red).
    // La interfaz la trata en silencio: no escribe "Sincronizando…" ni
    // repinta listas si al final no hubo nada nuevo que mostrar.
    const automatico = opciones.automatico === true;

    if (this.sincronizando) {
      // Si el usuario guarda algo mientras se está sincronizando, no se
      // pierde: se marca para repetir al terminar en vez de encolar
      // varias corridas simultáneas.
      this.reintentar = true;
      if (opciones.forzarCatalogos) this.reintentarForzandoCatalogos = true;
      return;
    }
    if (!navigator.onLine) {
      notificar({ ok: false, motivo: 'sin_conexion', automatico });
      return;
    }

    const sesion = await Db.obtenerSesion();
    if (!sesion) return;

    if (!sesion.token) {
      notificar({ ok: false, motivo: 'sesion_local', automatico });
      return;
    }

    // MULTIFUNDO: se sincronizan TODOS los fundos descargados en este
    // equipo, uno por uno. Cada fundo tiene su propia base de datos, así
    // que basta con ir cambiando el "fundo activo" y reutilizar el mismo
    // motor de siempre (pull, push, rangos y catálogos por fundo).
    const fundos = await Db.listarFundosMeta();
    if (!fundos.length) {
      notificar({ ok: false, motivo: 'sin_fundos', automatico });
      return;
    }

    this.sincronizando = true;
    notificar({ ok: true, en_curso: true, automatico });

    const fundoPrevio = Db.fundoActivo();
    const esConsulta = sesion.modo_offline === 'consulta';
    const acumulado = {
      enviados: 0, conflictos: 0, errores: 0, traidos: 0,
      modulos_con_error: [], fundos: [],
    };

    try {
      for (const fundo of fundos) {
        Db.usarFundo(fundo.id);
        const erroresFundo = [];

        try {
          if (!esConsulta) {
            const rp = await this.enviarPendientes(sesion, fundo.id);
            acumulado.enviados += rp.enviados;
            acumulado.conflictos += rp.conflictos;
            acumulado.errores += rp.errores;
            erroresFundo.push(...(rp.modulos_con_error || []));

            // Servidor en mantenimiento: se deja todo pendiente y se corta
            // el ciclo (no se cuenta como error de sincronización).
            if (rp.mantenimiento) {
              acumulado.mantenimiento = true;
              acumulado.motivo_mantenimiento = (rp.modulos_con_error || [])
                .filter((m) => m.mantenimiento)
                .map((m) => m.mensaje)
                .join(' ') || 'El sistema está en mantenimiento.';
              break;
            }
          }

          const descargas = await this.traerCambiosEnParalelo(sesion, fundo.id);
          acumulado.traidos += descargas.traidos;
          erroresFundo.push(...descargas.errores);

          if (descargas.mantenimiento) {
            acumulado.mantenimiento = true;
            acumulado.motivo_mantenimiento = (descargas.errores || [])
              .filter((x) => x.mantenimiento)
              .map((x) => x.mensaje)
              .join(' ') || 'El sistema está en mantenimiento.';
            break;
          }
          try {
            await this.asegurarProductosControlSanitario(sesion, fundo.id);
          } catch (e) {
            erroresFundo.push({ modulo: 'control_sanitario', fase: 'productos', mensaje: mensajeError(e) });
          }

          await Promise.all([
            this.asegurarRango(sesion, fundo.id),
            this.actualizarCatalogos(sesion, fundo.id, !!opciones.forzarCatalogos),
          ]);

          // Fotos: van al final y aparte. Primero sube las que se tomaron
          // en el corral (solo en modo gestión) y después baja unas pocas
          // de las que faltan en el equipo. Si algo falla acá NO afecta a
          // los datos del hato: se reintenta en la próxima pasada.
          try {
            const fotos = await Fotos.procesar({ ...sesion, fundo_id: fundo.id }, !esConsulta);
            if (fotos.subidas) acumulado.fotos_subidas = (acumulado.fotos_subidas || 0) + fotos.subidas;
            if (fotos.bajadas) acumulado.fotos_bajadas = (acumulado.fotos_bajadas || 0) + fotos.bajadas;
          } catch (e) {
            console.warn('[Sync] fotos:', e);
          }

          await Db.descargarFundo({ ...fundo, ultima_sync: new Date().toISOString() });
          acumulado.fundos.push({ fundo_id: fundo.id, nombre: fundo.nombre, ok: erroresFundo.length === 0 });
        } catch (e) {
          erroresFundo.push({ mensaje: mensajeError(e) });
          acumulado.fundos.push({ fundo_id: fundo.id, nombre: fundo.nombre, ok: false });
        }

        acumulado.modulos_con_error.push(...erroresFundo.map((x) => ({ ...x, fundo_id: fundo.id })));
      }

      notificar({
        ok: true,
        en_curso: false,
        automatico,
        ...acumulado,
        cuando: new Date().toISOString(),
      });
    } catch (e) {
      notificar({ ok: false, en_curso: false, automatico, motivo: mensajeError(e) });
    } finally {
      Db.usarFundo(fundoPrevio);
      this.sincronizando = false;
    }

    // Hubo guardados mientras se sincronizaba: se corre otra vez para
    // que salgan en esta misma conexión (manteniendo el modo: si la
    // corrida era de fondo, la repetición también se trata en silencio).
    if (this.reintentar) {
      const forzarCatalogos = !!this.reintentarForzandoCatalogos;
      this.reintentar = false;
      this.reintentarForzandoCatalogos = false;
      setTimeout(() => this.sincronizar({ forzarCatalogos, automatico }), 0);
    }
  },
};

// Corridas de fondo: silenciosas. Solo tocan el indicador de pendientes
// y avisan cuando de verdad se envió o recibió algo.
window.addEventListener('online', () => Sync.sincronizar({ automatico: true }));
setInterval(() => {
  if (navigator.onLine) Sync.sincronizar({ automatico: true });
}, CONFIG.INTERVALO_AUTO_SYNC_MS);