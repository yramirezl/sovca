<?php
/**
 * lib/general/pdf_engine.php
 * ---------------------------------------------------------------------------
 * Motor con el que se generan los PDF de los reportes.
 *
 *   '17'  →  lib/general/fpdf/fpdf.php
 *            FPDF 1.7 + capa de tablas del proyecto.  ← valor actual
 *
 *   '19'  →  lib/general/fpdf/fpdf_compat.php
 *            FPDF 1.9 + la misma capa de tablas (compatibilidad completa).
 *
 * Para probar un reporte concreto sin cambiar este archivo, añadir a la URL:
 *
 *   .../reportes/sovca/rla.php?pdf_engine=19
 *
 * Cuando los 16 reportes que usan tablas estén comparados y validados,
 * cambiar el valor a '19' y eliminar el soporte del parámetro.
 */

return '17';
