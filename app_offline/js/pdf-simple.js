// Generador de PDF mínimo, sin librerías externas.
//
// POR QUÉ EXISTE: hasta ahora "Exportar PDF" abría una ventana con el
// reporte y llamaba a window.print(), así que la persona tenía que
// elegir "Guardar como PDF" en el diálogo del sistema. En un celular
// eso es incómodo (y en algunos navegadores dentro de apps no existe).
// Este módulo arma el archivo .pdf de verdad y lo descarga, sin
// conexión y sin dependencias.
//
// ALCANCE: no pretende ser una librería de maquetación. Genera:
//   - una portada de datos (título, usuario, fundo, fecha),
//   - una tabla con encabezado repetido en cada página,
//   - cortes de página automáticos y numeración al pie.
//
// Fuentes: solo las base del PDF (Helvetica / Helvetica-Bold, WinAnsi),
// así que NO hay que incrustar archivos de fuente. Los caracteres
// fuera de Latin-1 se reemplazan por su equivalente sin acento.

const PdfSimple = {
  version: '1.0',

  // A4 en puntos (1 pt = 1/72 pulgada).
  A4: { vertical: { ancho: 595, alto: 842 }, horizontal: { ancho: 842, alto: 595 } },

  // Mapa de caracteres que no existen en WinAnsi/Latin-1.
  _reemplazos: {
    '−': '-', '‒': '-', '–': '-', '—': '-', '―': '-',
    '“': '"', '”': '"', '‘': "'", '’': "'",
    '…': '...', '•': '-', '·': '.', '≥': '>=', '≤': '<=',
    '\u00A0': ' ',
  },

  /** Convierte un texto a Latin-1 escapado para un string PDF. */
  _textoPdf(valor) {
    const crudo = String(valor === null || valor === undefined ? '' : valor);
    let salida = '';

    for (const caracter of crudo) {
      const reemplazo = this._reemplazos[caracter];
      if (reemplazo !== undefined) {
        salida += reemplazo;
        continue;
      }

      const codigo = caracter.codePointAt(0);
      if (codigo === 10 || codigo === 13) {
        salida += ' ';
      } else if (codigo < 256) {
        // Latin-1: el largo del string coincide con los bytes, que es
        // lo que hace falta para calcular los offsets del PDF.
        salida += caracter;
      } else {
        // Fuera de Latin-1: se quita el acento cuando se puede.
        const sinAcento = caracter.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        salida += (sinAcento && sinAcento.codePointAt(0) < 256) ? sinAcento : '?';
      }
    }

    return salida
      .replace(/\\/g, '\\\\')
      .replace(/\(/g, '\\(')
      .replace(/\)/g, '\\)');
  },

  /** Ancho aproximado del texto (Helvetica ~0.5 del tamaño por carácter). */
  _anchoTexto(texto, tamano) {
    return String(texto === null || texto === undefined ? '' : texto).length * tamano * 0.5;
  },

  /** Recorta el texto para que entre en el ancho dado. */
  _recortar(valor, anchoDisponible, tamano) {
    const texto = String(valor === null || valor === undefined ? '' : valor);
    const maximo = Math.max(1, Math.floor(anchoDisponible / (tamano * 0.5)));
    if (texto.length <= maximo) return texto;
    if (maximo <= 3) return texto.slice(0, maximo);
    return texto.slice(0, maximo - 3) + '...';
  },

  /** Nombre de archivo seguro a partir del título del reporte. */
  nombreArchivo(titulo) {
    const base = String(titulo || 'reporte')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-zA-Z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .toLowerCase() || 'reporte';

    const hoy = new Date();
    const sello = hoy.getFullYear()
      + String(hoy.getMonth() + 1).padStart(2, '0')
      + String(hoy.getDate()).padStart(2, '0');

    return base + '-' + sello + '.pdf';
  },

  /**
   * Genera el PDF y devuelve un Blob.
   *
   * @param {Object} opciones
   * @param {string} opciones.titulo
   * @param {Object} opciones.meta      Pares etiqueta/valor para la cabecera.
   * @param {Array}  opciones.columnas  [{ titulo, clave, ancho, alinear }]
   * @param {Array}  opciones.filas     Filas ya formateadas (strings).
   * @param {string} opciones.orientacion 'horizontal' (por defecto) | 'vertical'
   * @param {string} opciones.pie
   */
  generar(opciones) {
    const o = opciones || {};
    const orientacion = o.orientacion === 'vertical' ? 'vertical' : 'horizontal';
    const medidas = this.A4[orientacion];
    const margen = 36;
    const anchoUtil = medidas.ancho - margen * 2;

    const columnas = (Array.isArray(o.columnas) && o.columnas.length)
      ? o.columnas
      : [{ titulo: 'Dato', clave: 'dato', ancho: 1 }];
    const filas = Array.isArray(o.filas) ? o.filas : [];

    const tamanoTexto = 8;
    const tamanoCabecera = 8.5;
    const altoFila = 13;
    const pesoTotal = columnas.reduce((total, col) => total + (Number(col.ancho) || 1), 0);
    const anchos = columnas.map((col) => (anchoUtil * (Number(col.ancho) || 1)) / pesoTotal);

    // Contenido por página: cada página es un arreglo de operadores.
    const paginas = [];
    let actual = [];
    paginas.push(actual);

    const escribir = (operador) => actual.push(operador);
    const texto = (x, y, valor, tamano, negrita, alinear, anchoCaja) => {
      const contenido = this._textoPdf(valor);
      let posicion = x;
      if (alinear === 'right' && anchoCaja) {
        posicion = x + anchoCaja - this._anchoTexto(contenido, tamano) - 3;
      } else if (alinear === 'center' && anchoCaja) {
        posicion = x + (anchoCaja - this._anchoTexto(contenido, tamano)) / 2;
      }
      posicion = Math.max(x + 1, posicion);
      escribir('BT /' + (negrita ? 'F2' : 'F1') + ' ' + tamano + ' Tf '
        + posicion.toFixed(2) + ' ' + y.toFixed(2) + ' Td (' + contenido + ') Tj ET');
    };
    const renglon = (x1, y1, x2, y2, grosor) => {
      escribir((grosor || 0.5) + ' w ' + x1.toFixed(2) + ' ' + y1.toFixed(2)
        + ' m ' + x2.toFixed(2) + ' ' + y2.toFixed(2) + ' l S');
    };

    let y = medidas.alto - margen;

    // ---------- Encabezado del reporte ----------
    texto(margen, y - 10, o.titulo || 'Reporte', 15, true);
    y -= 26;

    const meta = Array.isArray(o.meta) ? o.meta : [];
    for (const lineaMeta of meta) {
      if (!lineaMeta) continue;
      const etiqueta = lineaMeta.etiqueta ? lineaMeta.etiqueta + ': ' : '';
      texto(margen, y, etiqueta + (lineaMeta.valor || ''), 9, false);
      y -= 12;
    }

    y -= 6;
    renglon(margen, y, margen + anchoUtil, y, 0.8);
    y -= 14;

    // ---------- Encabezado de la tabla ----------
    const dibujarCabeceraTabla = () => {
      const inicioX = margen;
      let x = inicioX;
      columnas.forEach((col, indice) => {
        texto(x, y, this._recortar(col.titulo, anchos[indice] - 4, tamanoCabecera),
          tamanoCabecera, true, col.alinear, anchos[indice]);
        x += anchos[indice];
      });
      y -= 4;
      renglon(margen, y, margen + anchoUtil, y, 0.6);
      y -= altoFila - 4;
    };

    dibujarCabeceraTabla();

    // ---------- Filas ----------
    if (!filas.length) {
      texto(margen, y, 'Sin registros para mostrar.', 9, false);
      y -= altoFila;
    }

    for (const fila of filas) {
      if (y < margen + 30) {
        // Nueva página: se repite el encabezado de la tabla arriba.
        actual = [];
        paginas.push(actual);
        y = medidas.alto - margen - 10;
        dibujarCabeceraTabla();
      }

      let x = margen;
      columnas.forEach((col, indice) => {
        const valor = typeof fila === 'object' && fila !== null ? fila[col.clave] : fila;
        texto(x, y, this._recortar(valor, anchos[indice] - 4, tamanoTexto),
          tamanoTexto, false, col.alinear, anchos[indice]);
        x += anchos[indice];
      });

      y -= altoFila;
      renglon(margen, y + 5, margen + anchoUtil, y + 5, 0.25);
    }

    // ---------- Pie con numeración ----------
    const pie = o.pie || 'BOVS Offline - generado en el equipo';
    paginas.forEach((operadores, indice) => {
      const previas = actual;
      actual = operadores;
      texto(margen, margen - 12, pie, 7.5, false);
      const numero = 'Pagina ' + (indice + 1) + ' de ' + paginas.length;
      texto(margen, margen - 12, numero, 7.5, false, 'right', anchoUtil);
      actual = previas;
    });

    return this._armarPdf(paginas, medidas);
  },

  /** Ensambla los objetos del PDF y devuelve el Blob. */
  _armarPdf(paginas, medidas) {
    const objetos = [];
    const agregar = (contenido) => {
      objetos.push(contenido);
      return objetos.length; // número de objeto (1-based)
    };

    const numCatalogo = agregar(null);   // 1
    const numPaginas = agregar(null);    // 2
    const numFuenteNormal = agregar('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    const numFuenteNegrita = agregar('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

    const idsPaginas = [];
    for (const operadores of paginas) {
      const flujo = operadores.join('\n');
      const numContenido = agregar('<< /Length ' + flujo.length + ' >>\nstream\n' + flujo + '\nendstream');
      const numPagina = agregar('<< /Type /Page /Parent ' + numPaginas + ' 0 R'
        + ' /MediaBox [0 0 ' + medidas.ancho + ' ' + medidas.alto + ']'
        + ' /Resources << /Font << /F1 ' + numFuenteNormal + ' 0 R /F2 ' + numFuenteNegrita + ' 0 R >> >>'
        + ' /Contents ' + numContenido + ' 0 R >>');
      idsPaginas.push(numPagina);
    }

    objetos[numCatalogo - 1] = '<< /Type /Catalog /Pages ' + numPaginas + ' 0 R >>';
    objetos[numPaginas - 1] = '<< /Type /Pages /Kids ['
      + idsPaginas.map((id) => id + ' 0 R').join(' ') + '] /Count ' + idsPaginas.length + ' >>';

    let salida = '%PDF-1.4\n';
    const offsets = [];

    objetos.forEach((contenido, indice) => {
      offsets.push(salida.length);
      salida += (indice + 1) + ' 0 obj\n' + contenido + '\nendobj\n';
    });

    const inicioXref = salida.length;
    salida += 'xref\n0 ' + (objetos.length + 1) + '\n';
    salida += '0000000000 65535 f \n';
    offsets.forEach((offset) => {
      salida += String(offset).padStart(10, '0') + ' 00000 n \n';
    });
    salida += 'trailer\n<< /Size ' + (objetos.length + 1) + ' /Root ' + numCatalogo + ' 0 R >>\n';
    salida += 'startxref\n' + inicioXref + '\n%%EOF';

    // Cada carácter es un byte (Latin-1), así que charCodeAt sirve.
    const bytes = new Uint8Array(salida.length);
    for (let i = 0; i < salida.length; i++) {
      bytes[i] = salida.charCodeAt(i) & 0xFF;
    }

    return new Blob([bytes], { type: 'application/pdf' });
  },

  /** Descarga un Blob con el nombre indicado. */
  descargar(blob, nombreArchivo) {
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombreArchivo;
    document.body.appendChild(enlace);
    enlace.click();
    document.body.removeChild(enlace);
    setTimeout(() => URL.revokeObjectURL(url), 5000);
  },

  /**
   * ¿Este equipo puede compartir archivos con el menú del sistema?
   *
   * Web Share con archivos exige **contexto seguro**: HTTPS o
   * localhost. En una IP por HTTP (http://192.168.x.x) el navegador no
   * expone `navigator.share` con archivos, así que hay que seguir
   * bajando el PDF como antes. Por eso se comprueba siempre antes.
   */
  puedeCompartir() {
    return typeof navigator !== 'undefined'
      && typeof navigator.share === 'function'
      && typeof navigator.canShare === 'function'
      && typeof File === 'function';
  },

  /**
   * Comparte el PDF con las apps del teléfono (WhatsApp, correo, Drive…).
   *
   * Devuelve:
   *   'compartido'   → el usuario eligió una app y el archivo salió
   *   'cancelado'    → abrió el menú y lo cerró sin elegir (no es error)
   *   'no_soportado' → este equipo/navegador no puede compartir archivos
   *   'error'        → falló el envío (conviene ofrecer la descarga)
   */
  async compartir(blob, nombreArchivo, titulo) {
    if (!this.puedeCompartir()) return 'no_soportado';

    let archivo;
    try {
      archivo = new File([blob], nombreArchivo, { type: 'application/pdf' });
    } catch (e) {
      return 'no_soportado'; // navegador sin constructor File
    }

    try {
      if (!navigator.canShare({ files: [archivo] })) return 'no_soportado';
    } catch (e) {
      return 'no_soportado';
    }

    try {
      await navigator.share({
        files: [archivo],
        title: titulo || nombreArchivo,
        text: titulo ? titulo + ' (BOVS)' : undefined,
      });
      return 'compartido';
    } catch (error) {
      // Cerrar el menú de compartir lanza AbortError: es normal.
      if (error && error.name === 'AbortError') return 'cancelado';
      return 'error';
    }
  },
};

if (typeof window !== 'undefined') window.PdfSimple = PdfSimple;
if (typeof module !== 'undefined' && module.exports) module.exports = PdfSimple;
