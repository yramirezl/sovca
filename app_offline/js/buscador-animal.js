// Combo de "animal buscable": convierte un input de texto + input
// oculto en un selector con autocompletado por código, nombre o
// arete — mismo criterio y estilo que ya usaba Reportes
// (inicializarBuscadorAnimales en app.js), generalizado para poder
// reemplazar los <select> de animal de Control Sanitario, Producción
// de Leche, Pesos, Servicios y Nacimientos.
//
// Contrato importante: el input OCULTO conserva el mismo id que antes
// tenía el <select> (ej. "cs-animal", "servicios-hembra"). Así, todo
// el código existente en app.js que hace el('cs-animal').value,
// el('cs-animal').disabled, etc. sigue funcionando sin cambios — el
// combo solo reemplaza cómo se elige ese valor, no cómo se consume.
//
// Estructura HTML esperada por cada campo (idBase = "cs-animal"):
//   <div class="buscador-animal" id="cs-animal-combo">
//     <input type="text" id="cs-animal-buscar" class="buscador-animal__input" ...>
//     <input type="hidden" id="cs-animal">
//     <div id="cs-animal-opciones" class="buscador-animal__opciones oculto"></div>
//   </div>

const BuscadorAnimal = (() => {
  const registro = {};

  function textoOpcion(animal) {
    return animal.codigo_rgb_id_animal + ' - ' + (animal.nombre || 'Sin nombre');
  }

  function textoBusqueda(animal) {
    return [animal.codigo_rgb_id_animal, animal.nombre, animal.nro_arete]
      .filter(Boolean).join(' ').toLowerCase();
  }

  function marcarActiva(items, indice) {
    items.forEach((item, i) => item.classList.toggle('buscador-animal__item--activa', i === indice));
  }

  /** Da de alta el combo una sola vez: engancha los listeners. */
  function crear(idBase) {
    const inputBuscar = el(idBase + '-buscar');
    const inputOculto = el(idBase);
    const opciones = el(idBase + '-opciones');
    const existente = registro[idBase];
    if (existente
      && existente.inputBuscar === inputBuscar
      && existente.inputOculto === inputOculto
      && existente.opciones === opciones) return;

    if (existente) delete registro[idBase];
    if (!inputBuscar || !inputOculto || !opciones) return;

    registro[idBase] = { candidatos: [], textoActual: '', inputBuscar, inputOculto, opciones };

    let temporizador = null;
    let indiceActivo = -1;

    const cerrar = () => opciones.classList.add('oculto');

    const abrirYRenderizar = () => {
      indiceActivo = -1;
      renderizarOpciones(idBase);
    };

    inputBuscar.addEventListener('input', () => {
      // Si lo escrito ya no coincide con lo que había seleccionado,
      // se limpia el valor real hasta que se elija de nuevo — así
      // nunca se manda un código que el usuario no confirmó de la
      // lista (igual de estricto que un <select> nativo).
      if (inputOculto.value && inputBuscar.value !== registro[idBase].textoActual) {
        inputOculto.value = '';
      }
      clearTimeout(temporizador);
      temporizador = setTimeout(abrirYRenderizar, 120);
    });

    inputBuscar.addEventListener('focus', abrirYRenderizar);

    inputBuscar.addEventListener('keydown', (evento) => {
      const items = [...opciones.querySelectorAll('[data-codigo]')];
      if (!items.length) return;

      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        indiceActivo = indiceActivo < items.length - 1 ? indiceActivo + 1 : 0;
        marcarActiva(items, indiceActivo);
        items[indiceActivo].scrollIntoView({ block: 'nearest' });
      } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        indiceActivo = indiceActivo > 0 ? indiceActivo - 1 : items.length - 1;
        marcarActiva(items, indiceActivo);
        items[indiceActivo].scrollIntoView({ block: 'nearest' });
      } else if (evento.key === 'Enter') {
        const elegido = indiceActivo >= 0 ? items[indiceActivo] : items[0];
        if (elegido) {
          evento.preventDefault();
          seleccionar(idBase, elegido.dataset.codigo);
          cerrar();
        }
      } else if (evento.key === 'Escape') {
        cerrar();
      }
    });

    inputBuscar.addEventListener('blur', () => {
      // Retraso corto para que el click sobre una opción alcance a
      // dispararse antes de que "blur" cierre la lista.
      setTimeout(cerrar, 150);
    });

    opciones.addEventListener('mousedown', (evento) => {
      // Evita que el "blur" del input se dispare antes que el click.
      evento.preventDefault();
    });
  }

  /**
   * Carga la lista de animales candidatos para este campo (ya
   * filtrada por sexo si corresponde, igual que antes). Conserva la
   * selección previa si el animal elegido sigue entre los candidatos
   * — mismo comportamiento que tenía el <select>.
   */
  function poblar(idBase, animales, textoVacio) {
    crear(idBase);
    if (!registro[idBase]) return;

    const inputOculto = el(idBase);
    const inputBuscar = el(idBase + '-buscar');
    if (!inputOculto || !inputBuscar) return;
    const valorPrevio = inputOculto.value;

    registro[idBase].candidatos = animales || [];
    registro[idBase].textoVacio = textoVacio || 'Selecciona...';

    if (valorPrevio) {
      const animal = registro[idBase].candidatos.find((a) => a.codigo_rgb_id_animal === valorPrevio);
      if (animal) {
        registro[idBase].textoActual = textoOpcion(animal);
        inputBuscar.value = registro[idBase].textoActual;
      }
      // Si ya no está entre los candidatos, se deja tal cual estaba
      // mostrándose (no se pisa lo que el registro ya tenía elegido).
    }
  }

  function renderizarOpciones(idBase) {
    const datos = registro[idBase];
    const inputBuscar = el(idBase + '-buscar');
    const opciones = el(idBase + '-opciones');
    if (!datos || !inputBuscar || !opciones) return;

    const termino = inputBuscar.value.trim().toLowerCase();
    const LIMITE = 40;
    let candidatos = datos.candidatos;
    let coincidencias = termino
      ? candidatos.filter((a) => textoBusqueda(a).includes(termino))
      : candidatos.slice(0, 8);

    const truncado = termino && coincidencias.length > LIMITE;
    if (termino) coincidencias = coincidencias.slice(0, LIMITE);

    opciones.innerHTML = '';

    if (!candidatos.length) {
      opciones.innerHTML = '<div class="buscador-animal__vacio">'
        + (navigator.onLine ? 'Sin animales disponibles todavía.' : 'Sin conexión: aún no hay animales guardados en este equipo.')
        + '</div>';
      opciones.classList.remove('oculto');
      return;
    }

    if (!coincidencias.length) {
      opciones.innerHTML = '<div class="buscador-animal__vacio">Sin coincidencias.</div>';
      opciones.classList.remove('oculto');
      return;
    }

    for (const animal of coincidencias) {
      const item = document.createElement('div');
      item.className = 'buscador-animal__item';
      item.dataset.codigo = animal.codigo_rgb_id_animal;
      item.innerHTML = '<span class="buscador-animal__codigo">' + escaparHtml(animal.codigo_rgb_id_animal || 'Sin código') + '</span>'
        + '<span class="buscador-animal__detalle">' + escaparHtml(animal.nombre || 'Sin nombre')
        + (animal.nro_arete ? ' · Arete ' + escaparHtml(animal.nro_arete) : '') + '</span>';
      opciones.appendChild(item);
    }

    if (truncado) {
      const aviso = document.createElement('div');
      aviso.className = 'buscador-animal__vacio';
      aviso.textContent = 'Sigue escribiendo para afinar la búsqueda…';
      opciones.appendChild(aviso);
    } else if (!termino && candidatos.length > 8) {
      const aviso = document.createElement('div');
      aviso.className = 'buscador-animal__vacio';
      aviso.textContent = 'Escribe para buscar entre los ' + candidatos.length + ' animales disponibles…';
      opciones.appendChild(aviso);
    }

    opciones.querySelectorAll('[data-codigo]').forEach((item) => {
      item.addEventListener('click', () => {
        seleccionar(idBase, item.dataset.codigo);
        opciones.classList.add('oculto');
      });
    });

    opciones.classList.remove('oculto');
  }

  /**
   * Selecciona un animal por código — se usa tanto al hacer click en
   * una sugerencia como al abrir una edición (reemplaza a lo que
   * antes hacía asegurarOpcionSeleccionada con un <select>).
   *
   * Si el código no está entre los candidatos cargados en este
   * momento (por ejemplo, una hembra que ya no cumple el filtro
   * actual), igual se muestra: el campo siempre refleja el valor real
   * del registro, exista o no ese animal en la lista ahora mismo.
   */
  function seleccionar(idBase, valor) {
    crear(idBase);
    const inputOculto = el(idBase);
    const inputBuscar = el(idBase + '-buscar');
    if (!inputOculto || !inputBuscar) return;

    if (!valor) {
      inputOculto.value = '';
      inputBuscar.value = '';
      if (registro[idBase]) registro[idBase].textoActual = '';
      window.dispatchEvent(new CustomEvent('bovs:animal_seleccionado', {
        detail: { idBase, codigo: '' },
      }));
      return;
    }

    const datos = registro[idBase];
    const animal = datos && datos.candidatos.find((a) => a.codigo_rgb_id_animal === valor);
    const texto = animal ? textoOpcion(animal) : valor;

    inputOculto.value = valor;
    inputBuscar.value = texto;
    if (datos) datos.textoActual = texto;
    window.dispatchEvent(new CustomEvent('bovs:animal_seleccionado', {
      detail: { idBase, codigo: valor },
    }));
  }

  /** Bloquea (modo edición, animal ya no se puede cambiar) o desbloquea el combo. */
  function bloquear(idBase, idMostrar, bloquear) {
    const combo = el(idBase + '-combo');
    const mostrarEl = el(idMostrar);
    const inputBuscar = el(idBase + '-buscar');
    if (!combo || !mostrarEl) return;

    if (bloquear) {
      mostrarEl.textContent = inputBuscar && inputBuscar.value ? inputBuscar.value : '—';
      combo.classList.add('oculto');
      mostrarEl.classList.remove('oculto');
    } else {
      combo.classList.remove('oculto');
      mostrarEl.classList.add('oculto');
    }
  }

  return { crear, poblar, seleccionar, bloquear };
})();