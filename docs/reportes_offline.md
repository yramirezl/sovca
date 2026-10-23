# Reportes de la app offline y PDF generado en el equipo

Versión: **4.38** (`bovs-shell-v86`)

## 1. Qué se agregó en P2

Seis reportes nuevos, todos calculados **con los datos que ya están en el
dispositivo** (IndexedDB, un almacén por fundo):

| Reporte | Code | Fuente local | Qué muestra |
|---|---|---|---|
| Consecutivo de peso | `consecutivo_peso` | `pesos` | Cada pesaje con el peso anterior del mismo animal, la diferencia, los días y la ganancia media diaria |
| Movimiento de rebaño | `movimiento_rebano` | `movimiento_rebano` | Traslados con tipo, origen, destino, animales, motivo y si fue revertido |
| Grupos etarios | `grupos_etarios` | `animales` + `categorias` | Animales activos por tramo de edad (meses) con su categoría y sexo |
| Hembras servidas | `hembras_servidas` | `servicios` + `tipos_servicio` | Servicios por hembra con tipo, macho/donadora, días transcurridos y si terminó en parto |
| Productoras de leche | `productoras_leche` | `produccion_leche` | Litros por hembra: días con registro, totales y promedio diario |
| Animales por lote | `animales_por_lote` | `animales` + `lotes` | Resumen por lote (hembras/machos) y listado de cada animal |

Detalles de implementación que conviene no romper:

- **Consecutivo de peso**: el "peso anterior" se toma del historial COMPLETO del
  animal; después el rango de fechas decide qué filas se muestran (mismo criterio
  que el IEP de nacimientos).
- **Grupos etarios**: `mesesCumplidos()` cuenta meses calendario (descuenta uno si
  todavía no llegó el día del mes) y `grupoEtario()` corta en 0-3, 4-8, 9-12,
  13-24, 25-36, 37-48, 49-72 y más de 72 meses. Los animales sin `f_nacimiento`
  caen en "Sin fecha de nacimiento".
- **sexo**: en este sistema **1 = HEMBRA y 2 = MACHO**. Todos los reportes nuevos
  usan `etiquetaSexo()`; el reporte "Animales activos" tenía el mapeo invertido y
  se corrigió.

## 2. PDF real en el equipo

`js/pdf-simple.js` es un generador de PDF mínimo, **sin librerías ni CDN**, que
arma el archivo y lo descarga. Reemplaza al flujo anterior (`window.print()` +
"Guardar como PDF" del diálogo del sistema).

- Solo usa las fuentes base del PDF (`Helvetica` y `Helvetica-Bold`, WinAnsi):
  no hay que incrustar archivos de fuente.
- Texto en **Latin-1**: cada carácter es un byte, así que los offsets del `xref`
  coinciden con `string.length` y el archivo queda válido. Lo que no entra en
  Latin-1 se convierte a su equivalente sin acento.
- Tabla con **encabezado repetido** en cada página, corte automático y pie con
  "Página N de M".
- Orientación: horizontal si el reporte tiene más de 6 columnas (las tablas anchas
  no entran en A4 vertical).

`Reportes.descargarPdf(code, filtros)` y `Reportes.compartirPdf(code, filtros)`
comparten el mismo armado (`Reportes.armarPdf()`), que:

1. llama a `prepararReporte()` (misma consulta que la vista previa),
2. arma las columnas con `tablaPdf()` a partir de las claves de las filas
   (no hace falta una definición por reporte; se saltean las claves internas:
   `sexoValor`, `loteId`, `partoValor`, `vivo`, …),
3. formatea fechas ISO a `dd-mm-aaaa` y vacíos a `—`,
4. genera el Blob y guarda el reporte en `reportes_cache`.

Después cada uno hace lo suyo:

| Método | Qué hace | Devuelve `accion` |
|---|---|---|
| `descargarPdf()` | Descarga `<titulo>-AAAAMMDD.pdf` a la carpeta del equipo | `descargado` |
| `compartirPdf()` | Abre el menú de compartir del teléfono con el PDF adjunto | `compartido` \| `cancelado` \| `descargado` |

La **vista previa** sigue existiendo: abre el HTML y desde ahí se puede imprimir
(el botón de esa ventana dice "Imprimir", no "Exportar PDF", para no confundir).

## 2.1 Compartir el PDF (WhatsApp / correo)

`PdfSimple.compartir(blob, nombre, titulo)` usa la **Web Share API con archivos**
y devuelve un resultado explícito:

| Resultado | Significado | Qué hace la app |
|---|---|---|
| `compartido` | El usuario eligió una app y el archivo salió | Avisa "PDF compartido" |
| `cancelado` | Abrió el menú y lo cerró (`AbortError`) | Avisa y sugiere el botón Descargar |
| `no_soportado` | El equipo no puede compartir archivos | **Descarga el PDF** |
| `error` | Falló el envío | **Descarga el PDF** |

⚠️ **Requiere contexto seguro** (`https://` o `localhost`): en una IP por HTTP
`navigator.share` con archivos no existe y el botón vuelve a ser "Exportar PDF".
La detección se hace con `PdfSimple.puedeCompartir()`
(`navigator.share` + `navigator.canShare` + `File`) **al pintar la tarjeta**:
- con soporte → botones **"Compartir PDF"** y **"Descargar"**;
- sin soporte → el botón de siempre, **"Exportar PDF"**.

El archivo que se comparte es un `File` real (`application/pdf`) construido desde
el mismo Blob que se descarga, así que WhatsApp lo abre como PDF y no como una
imagen ni un enlace.

### Verificación del PDF

```bash
node -e "..."   # ver /tmp/test_pdf.js durante el desarrollo
pdfinfo archivo.pdf      # Pages / Page size
pdftotext -layout archivo.pdf -   # contenido legible
```

Comprobado: cabecera `%PDF-1.4`, `%%EOF`, `xref` con offsets correctos, paginación
(120 filas → 4 páginas), acentos Latin-1 (`Finca Mañanita`) y texto extraíble con
`pdftotext`.

## 3. Pendiente (P2)

**Fotos del animal offline** → ya implementado en la versión **4.39**. Ver
`docs/fotos_animales.md`. Lo que sigue pendiente en P2 es únicamente el envío de
los reportes por correo desde el equipo.
