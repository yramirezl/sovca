# Migración de FPDF 1.7 a FPDF 1.9 en los reportes

Estado: **motor 1.9 instalado y validado, pero NO activo por defecto.**

---

## 1. Qué se hizo

`Reportes/lib/general/fpdf/fpdf.php` era a la vez la librería (FPDF 1.7 +
capa de tablas del proyecto) y el punto de entrada que requieren los 30
y pico reportes. Ahora **todo vive en una sola carpeta**, `lib/general/fpdf/`,
que internamente tiene la versión nueva como motor principal:

| Archivo | Contenido |
|---|---|
| `fpdf/fpdf.php` | **Despachador**. Solo elige motor y carga el archivo correspondiente. Ya no define clases. |
| `fpdf/fpdf19.php` | FPDF 1.9 oficial, **sin modificar**. |
| `fpdf/generated/fpdf19_base.php` | Copia de la 1.9 con la clase renombrada a `FPDF19Base`. **Archivo generado**. |
| `fpdf/fpdf_compat.php` | `class FPDF extends FPDF19Base` con la capa de compatibilidad. |
| `fpdf/font/` | Fuentes de la 1.9 (formato `.json`). |
| `fpdf/doc`, `tutorial`, `makefont`, `license.txt`, ... | Documentación y utilidades de la 1.9. |
| `fpdf/fpdf17.php` | **Transitorio.** La FPDF 1.7 + capa de tablas de siempre. |
| `fpdf/font17/` | **Transitorio.** Fuentes de la 1.7 (formato `.php`). |
| `pdf_engine.php` | Configuración: `'17'` (actual) o `'19'`. |

> La carpeta `fpdf19/` ya no existe: se fusionó dentro de `fpdf/` para que el
> proyecto tenga una sola librería PDF. Los dos archivos marcados como
> *transitorios* (`fpdf17.php` y `font17/`) existen solo hasta que estén
> validados todos los reportes; después se borran y el despachador se reduce
> a cargar `fpdf_compat.php`.

La razón de separar la 1.7 en su propio archivo es técnica: PHP registra las
clases de un archivo **al compilarlo**, antes de ejecutar la primera línea. Si
el despachador estuviera en el mismo archivo que `class FPDF` (1.7), al
intentar cargar la 1.9 PHP ya tendría la clase 1.7 declarada y el `require`
del motor nuevo se descartaría sin avisar.

### Capa de compatibilidad (`fpdf/fpdf_compat.php`)

Replica lo que la 1.9 no trae y que los reportes usan:

- **Tablas**: `SetWidths`, `SetAligns`, `SetBorder`, `SetJump`, `SetFillTable`,
  `RowM`, `RowMFillColor`, `NbLines`, `MultiCellBlt`, `CheckPageBreak`.
- **Rotación**: `Rotate`.
- **`FPDF()`**: los reportes inicializan con `$this->FPDF('L','mm','Legal')`;
  en 1.9 el constructor es `__construct`, así que se conserva el método.
- **`Output($nombre)`**: firma histórica que descarga el PDF.
- **`_pdfNormalizeText()`** aplicada en `Cell`, `MultiCell`, `Write` y `Text`
  (UTF-8 → ISO-8859-1). Sin esto, los acentos saldrían como `Ã³`.
- **`Open()`**: no-op, por compatibilidad con scripts antiguos.
- Ruta de fuentes apuntando a `fpdf/font/` (la base generada está en
  `generated/`, y 1.9 busca las fuentes junto a su propio archivo).

---

## 2. Cómo se activa

Por orden de prioridad:

1. Constante `SOVCA_PDF_ENGINE` definida por el script.
2. Parámetro de URL: `.../reportes/sovca/rla.php?pdf_engine=19`
   (pensado para comparar reporte por reporte; quitarlo al terminar).
3. Valor de `Reportes/lib/general/pdf_engine.php` → `'17'` por defecto.

Para pasar un reporte concreto al motor nuevo, basta abrir su URL con
`?pdf_engine=19`. Cuando todos estén validados, cambiar `pdf_engine.php` a
`'19'` y eliminar el soporte del parámetro.

---

## 3. Verificación

### Comparación automática

```bash
php scripts/verificar_pdf_engines.php
```

Genera el mismo documento (tabla con `RowMFillColor`/`RowM`, celdas
multilínea con acentos, texto rotado y pie `{nb}`) con los dos motores y
compara el texto extraído.

Resultado actual:

```
Motor 17: exit=0  bytes=1912
Motor 19: exit=0  bytes=2426
Texto extraído: IDÉNTICO en ambos motores.
```

### Comparación con reportes reales

Con sesión iniciada, se pidió cada reporte con los dos motores y se comparó
el PDF resultante:

| Reporte | Texto extraído | Páginas | Imagen (MD5) |
|---|---|---|---|
| `rla` (Animales Activos, 2 págs.) | idéntico | 2 / 2 | idéntica |
| `rlpa` | idéntico | 1 / 1 | idéntica |
| `rge` | idéntico | 1 / 1 | idéntica |
| `rcat` | idéntico | 1 / 1 | idéntica |

Las imágenes renderizadas (`pdftoppm -png`) de `rla` tienen **el mismo hash
MD5** en ambas páginas con los dos motores: la salida es visualmente
idéntica.

### Nota sobre el *polyfill* de magic quotes

Los reportes definen al principio:

```php
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() { return false; }
}
```

Sin ese *polyfill*, la FPDF 1.7 lanza un fatal en PHP 8 (`Call to undefined
function`). La 1.9 no lo necesita. La herramienta de verificación lo replica
para poder comparar con el motor 1.7.

---

## 4. Regenerar la base de FPDF 1.9

Si se actualiza `fpdf/fpdf19.php` con una versión nueva de la librería:

```bash
php scripts/generar_fpdf19_base.php
```

Vuelve a crear `fpdf/generated/fpdf19_base.php` con la clase renombrada a
`FPDF19Base`. Después, repetir `php scripts/verificar_pdf_engines.php`.

---

## 5. Reportes que usan la capa de tablas

16 de los 35 archivos de `Reportes/reportes/sovca`:

```
pdfrlisanimales_activos.php        pdfmovimiento.php
pdfservicio_animal.php             pdfconsecutivo_peso.php
pdfreg_nacimientos_legacy.php      pdfprodlechera.php
pdfrlisanimales_activos_x_lotes.php  rcat.php
pdfprodlechera_legacy.php          pdfservicio_animal_legacy.php
pdfrlisanimales_servidas_act.php   pdfrlisanimales_gestantes_act.php
pdfrlisanimales_prodleche_act.php  pdfgruposetarios.php
pdfcontrol_animal.php              pdfreg_nacimientos.php
```

Uso de métodos de tabla: `SetWidths` 228, `SetAligns` 222, `SetBorder` 173,
`SetJump` 170, `RowMFillColor` 134, `RowM` 96, `SetFillTable` 22,
`Rotate` 15, `NbLines` 3, `MultiCellBlt` 1.

---

## 6. Sobre `fpdfbase.class.php`

Es FPDF **1.53** sin las extensiones de tabla y **no lo referencia ningún
archivo del proyecto** (verificado con búsqueda global). Se puede:

- dejar como está (no molesta), o
- marcarlo como obsoleto / eliminarlo.

Actualizarlo a 1.9 no aporta nada: el motor moderno ya está en
`fpdf/fpdf_compat.php`.

---

## 7. Cómo eliminar definitivamente la 1.7

Cuando los 16 reportes con tablas estén validados con `?pdf_engine=19`:

1. Cambiar `Reportes/lib/general/pdf_engine.php` a `'19'` y comprobar en QA.
2. Borrar `fpdf/fpdf17.php` y `fpdf/font17/`.
3. Simplificar `fpdf/fpdf.php` para que solo haga:

   ```php
   require_once __DIR__ . '/fpdf_compat.php';
   ```

   junto con la definición de `SOVCA_PDF_ENGINE` (ya no haría falta), y
   quitar el parámetro `?pdf_engine`.
4. Actualizar `scripts/verificar_pdf_engines.php` o retirarlo.
