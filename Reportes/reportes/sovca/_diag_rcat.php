<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Polyfills (por si algún include los necesita)
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() { return false; }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($v) { return true; }
}

echo "<h2 style='font-family:Arial'>Diagnóstico rcat.php</h2>";

// 1. Verificar existencia de archivos
echo "<h3 style='font-family:Arial'>1. Archivos requeridos</h3><ul style='font-family:monospace'>";
$archivos = [
    "../../lib/general/fpdf/fpdf.php",
    "../../lib/bd/basedatosAdoIbase.php",
    "../../lib/bd/conexionAdo.php",
    "../../lib/bd/baseClases.class.php",
    "../../lib/general/cabecera.php",
    "../../lib/general/Herramientas.class.php",
    "../../lib/utiles.php",
    "../../lib/funciones.php",
    "../../adodb/adodb.inc.php",
];
foreach ($archivos as $f) {
    $ruta = __DIR__ . "/" . $f;
    $existe = file_exists($ruta) ? "<span style='color:green'>OK</span>" : "<span style='color:red'>NO EXISTE</span>";
    echo "<li>$f → $existe</li>";
}
echo "</ul>";

// 2. Cargar includes
echo "<h3 style='font-family:Arial'>2. Cargando includes...</h3>";
require_once("../../lib/general/fpdf/fpdf.php");
require_once("../../lib/bd/basedatosAdoIbase.php");
require_once("../../lib/bd/conexionAdo.php");
require_once("../../lib/bd/baseClases.class.php");
require_once("../../lib/general/cabecera.php");
require_once("../../lib/general/Herramientas.class.php");
include_once("../../lib/utiles.php");
include_once("../../lib/funciones.php");
echo "<p style='font-family:Arial'>Todos los includes cargados sin errores fatales.</p>";

// 3. Verificar clases
echo "<h3 style='font-family:Arial'>3. Clases cargadas</h3><ul style='font-family:monospace'>";
foreach (['baseClases', 'basedatosAdoIbase', 'conexionAdo', 'Cabecera', 'Herramientas'] as $c) {
    echo "<li>$c: " . (class_exists($c) ? "<span style='color:green'>OK</span>" : "<span style='color:red'>FALTA</span>") . "</li>";
}
echo "</ul>";

// 4. Verificar existencia de __construct
echo "<h3 style='font-family:Arial'>4. Constructores PHP 8</h3><ul style='font-family:monospace'>";
foreach (['basedatosAdoIbase', 'conexionAdo'] as $c) {
    $rc = new ReflectionClass($c);
    $ok = $rc->hasMethod('__construct') ? "<span style='color:green'>SÍ</span>" : "<span style='color:red'>NO (PROBLEMA)</span>";
    echo "<li>$c::__construct → $ok</li>";
}
echo "</ul>";

// 5. Probar instanciar basedatosAdoIbase y ver si la conexión se crea
echo "<h3 style='font-family:Arial'>5. Prueba de conexión AdoDB</h3>";
try {
    $bd = new basedatosAdoIbase();
    echo "<p style='color:green'>✔ Instancia basedatosAdoIbase creada.</p>";

    $ref = new ReflectionObject($bd);
    $connProp = $ref->getProperty('conn');
    $connProp->setAccessible(true);
    $conn = $connProp->getValue($bd);

    if ($conn === null) {
        echo "<p style='color:red'><b>✘ \$conn es NULL</b> — la conexión no se estableció.</p>";
    } else {
        echo "<p style='color:green'><b>✔ \$conn es " . get_class($conn) . "</b></p>";
        try {
            $rs = $bd->select("SELECT 1 AS n");
            echo "<p style='color:green'>✔ SELECT 1 → OK</p>";
        } catch (Throwable $e) {
            echo "<p style='color:red'>✘ SELECT 1 falló: " . $e->getMessage() . "</p>";
        }
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>✘ Error al crear basedatosAdoIbase: <b>" . $e->getMessage() . "</b><br>Archivo: " . $e->getFile() . " línea " . $e->getLine() . "</p>";
}

echo "<hr><p style='font-family:Arial'><b>Si ves todo en verde → el problema está en el SQL del rcat.php, no en la conexión.</b></p>";