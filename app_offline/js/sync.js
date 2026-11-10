// Motor de sincronizacion. Recorre los modulos declarados en CONFIG y
// dispara eventos para que app.js actualice la interfaz.

const CAMPOS_INTERNOS_NO_ENVIAR = new Set([
  'uuid_local', 'id', 'version', 'estado_sync', 'created', 'updated', 'mfundos_id',
  'codigo_provisional', 'nacimiento_uuid_local', 'nacimiento_nro_cria',
  'ficha_animal_uuid_local', 'ficha_animal_codigo', 'fichado',
  // Código provisional que este animal tenía antes de que el servidor le
  // asignara el definitivo. Solo existe en el dispositivo (ver
  // mapaCodigosRenombrados) y NO se envía.
  'codigo_anterior',
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

/**
 * Anota en fundos_meta si la cuenta conserva el acceso al fundo en el
 * servidor. NO borra nada: el fundo sigue en el equipo con todos sus
 * datos locales. Solo sirve para no repetir el mismo error de permiso
 * en cada corrida de fondo.
 */
async function marcarFundoSinAcceso(fundo, sinAcceso) {
  const fila = await Db.obtener('fundos_meta', fundo.id).catch(() => null);
  if (!fila) return;
  if (!!fila.sin_acceso === !!sinAcceso) return;

  fila.sin_acceso = !!sinAcceso;
  fila.sin_acceso_desde = sinAcceso ? new Date().toISOString() : null;
  await Db.guardar('fundos_meta', fila);
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
        items: {
          periodo_palpacion_dias: Number(resp.periodo_palpacion_dias || 0),
          // Días de gestación del fundo (Parámetros → Períodos, PGB).
          // Los usa Servicios para calcular la fecha probable de parto:
          // fecha de servicio + estos días. 283 = valor estándar bovino.
          periodo_gestacion_dias: Number(resp.periodo_gestacion_dias || 0) || 283,
          // Períodos de lactancia (PBL) y de producción de leche (PPL), en
          // días. Nacimientos los usa para calcular la fecha de secado
          // (fecha de parto + período) sin esperar al servidor.
          periodo_lactancia_dias: Number(resp.periodo_lactancia_dias || 0),
          periodo_produccion_dias: Number(resp.periodo_produccion_dias || 0),
          // Edad mínima del macho para servicio (Parámetros → Períodos).
          // Si el servidor no la manda se asume 365 días (≈12 meses).
          edad_minima_macho_dias: Number(resp.edad_minima_macho_dias || 0) || 365,
          // Edad mínima de la hembra (Parámetros → Períodos, EHM).
          // En ovinos y caprinos son 210 días (≈7 meses).
          edad_minima_hembra_dias: Number(resp.edad_minima_hembra_dias || 0) || 210,
        },
      });
      await Db.guardarSyncMeta(clave, Date.now());
      window.dispatchEvent(new CustomEvent('bovs:catalogos'));
      return true;
    } catch (e) {
      console.warn('No se pudieron actualizar los catalogos:', e.message);
      return false;
    }
  },

  async enviarPendientes(sesion, fundoId, opciones = {}) {
    const pendientes = await Db.obtenerTodos('outbox');
    if (!pendientes.length) return { enviados: 0, conflictos: 0, errores: 0, modulos_con_error: [] };

    // La corrida de fondo respeta la espera entre reintentos; cuando el
    // operario toca "Sincronizar" se reintenta ya (salvo los registros
    // bloqueados, que necesitan una decisión suya).
    const forzarReintento = opciones.automatico !== true;

    const porModulo = {};
    for (const item of pendientes) {
      const modulo = item.modulo || 'animales';
      (porModulo[modulo] ??= []).push(item);
    }

    const prioridadModulo = { animales: 0, servicios: 1, nacimientos: 2 };
    const modulosEnOrden = Object.keys(porModulo).sort((a, b) =>
      (prioridadModulo[a] ?? 10) - (prioridadModulo[b] ?? 10)
    );
    if (porModulo.animales && porModulo.nacimientos) {
      modulosEnOrden.push('reintentar_fichas_nacimiento');
    }

    let enviados = 0;
    let conflictos = 0;
    let errores = 0;
    const modulosConError = [];

    const dispositivoId = Dispositivo.obtenerId();

    // Códigos provisionales que el servidor YA reemplazó por el
    // definitivo. El mapa de la corrida (mapaCodigos) vive solo en
    // memoria y se pierde al recargar la app, así que se reconstruye
    // desde la copia local del animal (guarda su codigo_anterior al
    // recibir el número nuevo).
    const mapaRenombres = await this.mapaCodigosRenombrados();

    // Índice de animales del equipo para el pre-vuelo de envíos. Se arma
    // una sola vez por corrida: con lotes grandes, buscarlo registro por
    // registro sería muy costoso.
    const indiceAnimales = { porCodigo: null };

    for (const moduloOrden of modulosEnOrden) {
      const esReintentoFichasNacimiento = moduloOrden === 'reintentar_fichas_nacimiento';
      const modulo = esReintentoFichasNacimiento ? 'animales' : moduloOrden;
      // El outbox se vuelve a leer módulo por módulo, JUSTO antes de
      // enviarlo, en vez de usar el snapshot del inicio. Al subir un
      // animal el servidor le asigna el código definitivo y
      // remapearCodigosDeAnimal() corrige la copia guardada — pero los
      // objetos leídos al principio se quedaban con el código viejo, así
      // que un control sanitario o un movimiento creados sin conexión
      // sobre un animal recién numerado viajaban con el provisional y el
      // servidor los rechazaba ("Ninguno de los animales seleccionados
      // está activo en este fundo").
      const items = (await Db.obtenerTodos('outbox'))
        .filter((o) => (o.modulo || 'animales') === modulo
          && (!esReintentoFichasNacimiento || !!o.nacimiento_uuid_local));
      if (!items.length) continue;
      const almacen = modulo;
      // Anulaciones (borrado lógico) dentro de este lote: si alguna
      // choca con un cambio del servidor, la pantalla de conflictos
      // necesita saber que se trataba de un borrado y no de una edición.
      const eliminados = new Set(items.filter((p) => p.eliminado === true).map((p) => p.uuid_local));
      const cambios = [];
      for (const p of items) {
        // Registro bloqueado: se rechazó varias veces y espera una
        // decisión del usuario (reintentar o revertir). No se reenvía
        // en las corridas automáticas.
        if (p.bloqueado === true) continue;

        if (modulo === 'animales' && p.nacimiento_uuid_local) {
          const nacimiento = await Db.obtener('nacimientos', p.nacimiento_uuid_local);
          if (!nacimiento || !nacimiento.id) {
            await this.marcarEnEsperaDeNacimiento(p, almacen, !nacimiento);
            continue;
          }
        }

        // Espera progresiva entre reintentos: 1, 2, 4, 8, 16 y 30 min.
        if (!forzarReintento && this._enEsperaDeReintento(p)) continue;

        let datos = p.datos || {};

        // Reparación de envíos creados con versiones anteriores: al
        // editar un servicio que aún no existía en el servidor, el
        // outbox se reemplazaba por un payload parcial y se perdían los
        // códigos de hembra/macho. Se completan desde la copia local
        // para que el registro pendiente pueda subir.
        if (modulo === 'servicios') {
          const local = await Db.obtener(almacen, p.uuid_local);
          if (local) {
            datos = {
              ...datos,
              codigo_servicio: datos.codigo_servicio || local.codigo_servicio,
              codigo_rgb_id_animal_hembra: datos.codigo_rgb_id_animal_hembra || local.codigo_rgb_id_animal_hembra,
              codigo_rgb_id_animal_macho: datos.codigo_rgb_id_animal_macho || local.codigo_rgb_id_animal_macho,
              codigo_rgb_id_animal_dona: datos.codigo_rgb_id_animal_dona || local.codigo_rgb_id_animal_dona,
            };
          }
        }

        // Reparación general de la identidad del registro:
        //  · si el payload perdió el campo que identifica al registro
        //    (animal, lote, fecha...), se completa desde la copia local;
        //  · si el código de animal que lleva ya fue renombrado por el
        //    servidor, se reemplaza por el definitivo.
        // Sin esto, un registro editado antes de subir quedaba para
        // siempre en error ("Falta el campo requerido ...").
        const camposAnimal = CONFIG.CAMPOS_REFERENCIA_ANIMAL[modulo] || [];
        if (camposAnimal.length || modulo === 'movimiento_rebano') {
          const local = await Db.obtener(almacen, p.uuid_local);
          if (local) {
            datos = { ...datos };
            for (const campo of camposAnimal) {
              const vacio = datos[campo] === undefined
                || datos[campo] === null
                || datos[campo] === '';
              if (vacio && local[campo]) datos[campo] = local[campo];
            }
            if (Array.isArray(local.animales)
              && (!Array.isArray(datos.animales) || !datos.animales.length)) {
              datos.animales = local.animales.slice();
            }
          }
        }
        if (mapaRenombres.size) {
          datos = { ...datos };
          for (const campo of camposAnimal) {
            if (datos[campo] && mapaRenombres.has(datos[campo])) {
              datos[campo] = mapaRenombres.get(datos[campo]);
            }
          }
          if (Array.isArray(datos.animales)) {
            datos.animales = datos.animales.map((codigo) => (
              mapaRenombres.has(codigo) ? mapaRenombres.get(codigo) : codigo
            ));
          }
        }

        // PRE-VUELO DE ANIMALES: si el registro apunta a un animal que
        // todavía no existe en el servidor (alta sin conexión que aún no
        // subió, o código provisional vigente), no se envía: el servidor
        // lo rechazaría y el registro quedaría en error para siempre
        // reintentándose en cada sincronización.
        const animalFaltante = await this.animalesAunNoDisponibles(modulo, datos, indiceAnimales);
        if (animalFaltante) {
          await this.marcarEnEsperaDeAnimal(p, almacen, animalFaltante);
          continue;
        }

        cambios.push({
          uuid_local: p.uuid_local,
          version_base: p.version_base,
          datos,
          eliminado: p.eliminado === true,
          revertir: p.revertir === true,
          motivo_reversion: p.motivo_reversion || null,
          nacimiento_uuid_local: p.nacimiento_uuid_local || null,
          nacimiento_nro_cria: p.nacimiento_nro_cria || null,
        });
      }

      // Todos los registros del módulo quedaron esperando al animal (o
      // bloqueados): no hay nada que enviar en esta pasada.
      if (!cambios.length) continue;

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
              if (r.codigo_anterior) {
                registroLocal.codigo_provisional = false;
                // Se guarda para poder reparar, en cualquier corrida
                // futura, los registros que apunten al código viejo
                // (ver mapaCodigosRenombrados).
                registroLocal.codigo_anterior = r.codigo_anterior;
              }
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
            const mensaje = r.mensaje || 'El servidor rechazó este registro.';
            const bloqueado = await this._registrarFallo(r.uuid_local, mensaje);
            if (registroLocal) {
              registroLocal.estado_sync = 'error';
              // El motivo se guarda también en el registro para poder
              // mostrarlo en la tarjeta (la fila del outbox no se ve).
              registroLocal.ultimo_error = bloqueado
                ? mensaje + ' Este registro necesita tu revisión: no se reintenta solo.'
                : mensaje;
              await Db.guardar(almacen, registroLocal);
            }
          }
        }

        // Renombres de ESTA corrida + los reconstruidos de corridas
        // anteriores (mapaRenombres). Antes solo se aplicaba el mapa de la
        // corrida: si el animal ya se había renumerado en una sincronización
        // previa, un registro local que todavía guardaba el código
        // provisional subía bien (el payload se corrige arriba) pero la copia
        // local quedaba apuntando al código viejo y no aparecía en la ficha.
        // Es idempotente: solo reemplaza valores que están en el mapa.
        if (mapaCodigos.size || mapaRenombres.size) {
          await this.remapearCodigosDeAnimal(
            new Map([...mapaRenombres, ...mapaCodigos])
          );
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

        // Falla de la PETICIÓN completa (sin señal, 500, portal
        // cautivo): no es un rechazo de los datos, así que no se aplica
        // retroceso ni se bloquea nada. Solo se anota para diagnóstico y
        // los registros quedan pendientes para el próximo intento.
        errores += cambios.length;
        modulosConError.push({
          modulo,
          fase: 'push',
          mensaje: e && e.message ? e.message : 'error_desconocido',
        });

        for (const cambio of cambios) {
          const out = await Db.obtener('outbox', cambio.uuid_local);
          if (out) {
            out.intentos = (out.intentos || 0) + 1;
            out.ultimo_error = e && e.message ? e.message : 'error_desconocido';
            out.proximo_intento = null;
            await Db.guardar('outbox', out);
          }
          const reg = await Db.obtener(almacen, cambio.uuid_local);
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

  async marcarEnEsperaDeNacimiento(item, almacen, huerfano) {
    const fila = await Db.obtener('outbox', item.uuid_local);
    const mensaje = huerfano
      ? 'El nacimiento asociado ya no está en este dispositivo. Revisá la ficha de esta cría.'
      : 'Esperando que el nacimiento asociado termine de sincronizarse.';
    if (fila) {
      fila.ultimo_error = mensaje;
      fila.proximo_intento = null;
      if (huerfano) {
        fila.bloqueado = true;
        fila.bloqueado_en = new Date().toISOString();
      }
      await Db.guardar('outbox', fila);
    }
    const registro = await Db.obtener(almacen, item.uuid_local);
    if (registro) {
      if (huerfano) {
        registro.estado_sync = 'error';
        registro.ultimo_error = mensaje;
      } else if (registro.estado_sync === 'error') {
        registro.estado_sync = 'pendiente';
        delete registro.ultimo_error;
      }
      await Db.guardar(almacen, registro);
    }
  },

  /**
   * Mapa "código provisional → código definitivo" reconstruido desde la
   * copia local de los animales. Cada animal guarda en `codigo_anterior`
   * el código que tenía cuando el servidor le asignó el número real, así
   * que el mapa sobrevive a recargas y reinicios de la app.
   *
   * Se usa al preparar cada envío para corregir registros (control
   * sanitario, pesajes, movimientos, servicios…) que quedaron apuntando
   * al código viejo.
   */
  async mapaCodigosRenombrados() {
    const mapa = new Map();
    try {
      const animales = await Db.obtenerTodos('animales');
      for (const animal of animales) {
        if (!animal || !animal.codigo_anterior) continue;
        if (!animal.codigo_rgb_id_animal) continue;
        if (animal.codigo_anterior === animal.codigo_rgb_id_animal) continue;
        mapa.set(animal.codigo_anterior, animal.codigo_rgb_id_animal);
      }
    } catch (e) {
      console.warn('No se pudo reconstruir el mapa de códigos renombrados:', mensajeError(e));
    }
    return mapa;
  },

  /** ¿Todavía tiene que esperar antes de reintentarse? */
  _enEsperaDeReintento(item) {
    if (!item || !item.proximo_intento) return false;
    const cuando = Date.parse(item.proximo_intento);
    if (!Number.isFinite(cuando)) return false;
    return cuando > Date.now();
  },

  /**
   * Revisa los animales que menciona un cambio antes de enviarlo.
   *
   * Devuelve null si todos pueden viajar ya, o
   * { codigo, motivo } con el primer animal que todavía no existe en el
   * servidor:
   *   · 'esperando_alta'      → el alta de ese animal sigue en el outbox,
   *   · 'provisional_huerfano' → el registro guarda un código provisional
   *     y el animal ya no está en el equipo (se revirtió su alta).
   *
   * Enviarlo igual provocaría un rechazo del servidor ("Ninguno de los
   * animales seleccionados está activo en este fundo") que se repetiría
   * en cada sincronización sin solución.
   */
  async animalesAunNoDisponibles(modulo, datos, indice) {
    const campos = CONFIG.CAMPOS_REFERENCIA_ANIMAL[modulo] || [];
    const codigos = [];
    const agregar = (valor) => {
      if (!valor) return;
      const codigo = String(
        typeof valor === 'object'
          ? (valor.codigo_rgb_id_animal || valor.codigo || '')
          : valor
      ).trim().toUpperCase();
      if (codigo) codigos.push(codigo);
    };
    for (const campo of campos) agregar(datos ? datos[campo] : null);
    if (datos && Array.isArray(datos.animales)) datos.animales.forEach(agregar);
    if (!codigos.length) return null;

    if (!indice || !indice.porCodigo) {
      const animales = await Db.obtenerTodos('animales').catch(() => []);
      const porCodigo = new Map();
      for (const animal of animales) {
        const codigo = String(animal.codigo_rgb_id_animal || '').trim().toUpperCase();
        if (codigo) porCodigo.set(codigo, animal);
      }
      if (indice) indice.porCodigo = porCodigo;
      return this._evaluarAnimales(codigos, porCodigo);
    }

    return this._evaluarAnimales(codigos, indice.porCodigo);
  },

  /** Aplica las reglas del pre-vuelo sobre un índice de códigos. */
  _evaluarAnimales(codigos, porCodigo) {
    for (const codigo of codigos) {
      const animal = porCodigo.get(codigo);
      if (codigo.indexOf('-PEND-') !== -1) {
        // Un código provisional no existe en el servidor. Si además el
        // animal ya no está en el equipo, esperar no sirve: hay que
        // revisar el registro.
        return {
          codigo,
          motivo: animal ? 'esperando_alta' : 'provisional_huerfano',
        };
      }
      if (animal && animal.estado_sync !== 'sincronizado') {
        return { codigo, motivo: 'esperando_alta' };
      }
    }
    return null;
  },

  /**
   * Deja el registro "esperando al animal": no se envía, no se marca en
   * rojo y se reintenta solo en cuanto ese animal termine de subir.
   *
   * Si el animal ya no existe en el equipo (se revirtió su alta), esperar
   * no lo va a resolver: el registro pasa a "necesita tu revisión".
   */
  async marcarEnEsperaDeAnimal(item, almacen, faltante) {
    try {
      const codigo = typeof faltante === 'string' ? faltante : faltante.codigo;
      const huerfano = typeof faltante === 'object' && faltante.motivo === 'provisional_huerfano';

      const fila = await Db.obtener('outbox', item.uuid_local);
      if (fila) {
        fila.espera_animal = codigo;
        if (huerfano) {
          fila.bloqueado = true;
          fila.bloqueado_en = new Date().toISOString();
          fila.ultimo_error = 'El animal ' + codigo + ' se creó sin conexión y su alta ya no está en este equipo. '
            + 'Revisá este registro: corregí los animales o borralo.';
        } else {
          fila.ultimo_error = 'Esperando que el animal ' + codigo + ' termine de subir al servidor.';
          fila.proximo_intento = null;
        }
        await Db.guardar('outbox', fila);
      }

      const registro = await Db.obtener(almacen, item.uuid_local);
      if (registro) {
        if (huerfano) {
          registro.estado_sync = 'error';
          registro.ultimo_error = 'El animal ' + codigo + ' ya no está en este equipo. Revisá este registro.';
        } else if (registro.estado_sync === 'error') {
          // No es un error del usuario: vuelve a quedar pendiente normal.
          registro.estado_sync = 'pendiente';
          delete registro.ultimo_error;
        }
        await Db.guardar(almacen, registro);
      }
    } catch (e) {
      console.warn('[Sync] no se pudo marcar la espera del animal:', mensajeError(e));
    }
  },

  /**
   * Anota un rechazo del servidor y aplica retroceso progresivo
   * (1, 2, 4, 8, 16 y 30 minutos). Al llegar a CONFIG.MAX_INTENTOS_SYNC el
   * registro se bloquea: deja de reintentarse solo y pasa a la lista de
   * "requiere tu revisión" del panel de sincronización.
   *
   * Devuelve true si el registro quedó bloqueado.
   */
  async _registrarFallo(uuid, mensaje) {
    const item = await Db.obtener('outbox', uuid);
    if (!item) return false;
    const intentos = (item.intentos || 0) + 1;
    const minutos = Math.min(30, Math.pow(2, Math.min(intentos - 1, 5)));
    item.intentos = intentos;
    item.ultimo_error = mensaje || 'El servidor rechazó este registro.';
    item.espera_animal = null;
    item.proximo_intento = new Date(Date.now() + minutos * 60000).toISOString();
    const bloqueado = intentos >= CONFIG.MAX_INTENTOS_SYNC;
    if (bloqueado) {
      item.bloqueado = true;
      item.bloqueado_en = new Date().toISOString();
    }
    await Db.guardar('outbox', item);
    return bloqueado;
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
        if (modulo === 'animales') {
          // `codigo_anterior` y `codigo_provisional` son datos que solo
          // existen en el equipo: el servidor no los devuelve, así que al
          // reemplazar la copia local se perdían. Sin ellos se rompe el
          // mapa provisional→definitivo y los registros que aún guardan el
          // código viejo vuelven a ser rechazados en el próximo envío.
          const previo = await Db.obtener(almacen, fila.uuid_local).catch(() => null);
          if (previo) {
            if (previo.codigo_anterior) filaLocal.codigo_anterior = previo.codigo_anterior;
            if (previo.codigo_provisional !== undefined && previo.codigo_provisional !== null) {
              filaLocal.codigo_provisional = previo.codigo_provisional;
            }
          }
        }
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
          // Queda en la consola para diagnóstico: la línea de estado de la
          // app muestra el motivo, pero acá se ve el módulo exacto.
          console.warn('[Sync] pull de', modulo, 'falló:', mensajeError(e));
          return {
            modulo,
            error: mensajeError(e),
            codigo: e && e.cuerpo ? e.cuerpo.codigo || null : null,
          };
        }
      }));

      let mantenimiento = false;
      for (const resultado of resultados) {
        if (resultado.error) {
          errores.push({
            modulo: resultado.modulo,
            fase: 'pull',
            mensaje: resultado.error,
            codigo: resultado.codigo || null,
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
      modulos_con_error: [], fundos: [], fundos_sin_acceso: [],
    };

    try {
      for (const fundo of fundos) {
        // Fundo al que el servidor ya le negó el acceso (la cuenta dejó de
        // tenerlo asignado, o el equipo se preparó contra otra base de
        // datos): en las corridas de fondo no se vuelve a intentar. Al
        // tocar "Sincronizar" sí, porque para entonces puede haberse
        // restablecido el acceso.
        if (fundo.sin_acceso && automatico) continue;

        Db.usarFundo(fundo.id);
        const erroresFundo = [];

        try {
          if (!esConsulta) {
            const rp = await this.enviarPendientes(sesion, fundo.id, { automatico });
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

          // Si TODOS los módulos fallaron porque la cuenta no tiene
          // acceso a este fundo, no se muestran nueve errores iguales: se
          // marca el fundo y se resume en un solo aviso. Los datos que ya
          // están en el equipo se conservan.
          const sinAcceso = descargas.errores.length > 0
            && descargas.errores.every((error) => error.codigo === 'fundo_no_permitido');
          if (sinAcceso) {
            await marcarFundoSinAcceso(fundo, true);
            acumulado.fundos_sin_acceso.push(fundo.nombre || ('Fundo ' + fundo.id));
            // Se avisa UNA vez por fundo (no una por módulo) y se corta
            // este fundo: el resto de la corrida sigue con los demás.
            acumulado.modulos_con_error.push({
              modulo: fundo.nombre || ('Fundo ' + fundo.id),
              fase: 'fundo',
              fundo_id: fundo.id,
              mensaje: 'Tu cuenta ya no tiene acceso a este fundo en el servidor. Sus datos siguen guardados en este equipo.',
            });
            acumulado.fundos.push({ fundo_id: fundo.id, nombre: fundo.nombre, ok: false });
            continue;
          }

          if (fundo.sin_acceso) await marcarFundoSinAcceso(fundo, false);

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