<?php
/* ============================================================
   SOVCA | Wrapper del Reporte de Nacimientos
   ------------------------------------------------------------
   - Instancia pdfreg_nacimientos (versión optimizada).
   - Valida sesión y disponibilidad de datos.
   - Sin datos: aviso consistente y cierre de la ventana.
   ============================================================ */

require_once('pdfreg_nacimientos.php');
require_once('../../lib/reporte_contexto_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$obj        = new pdfreporte();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

/* Nomenclatura de salida: reporte + animal/lote/rango + fecha de generado */
$ctx = bovs_reporte_contexto($mfundos_id);
$obj->contexto_detalle = $ctx['detalle'];
$obj->SetTitle(bovs_reporte_titulo('INFORME DE NACIMIENTOS', $ctx));
$name = bovs_reporte_nombre_archivo('Reporte_de_Nacimientos', $ctx);

if ($mfundos_id > 0) {

    if ($obj->arrp && count($obj->arrp) > 0) {

        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();
        $obj->Output($name, 'I');

    } else {
        $sin_datos_texto = 'No existen registros de nacimientos para los filtros seleccionados.';
        include '_sin_datos.php';
    }

} else {
    // Sesión caducada
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sesión caducada</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body style="font-family: Arial, sans-serif;">
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Su sesión ha caducado',
                text: 'Por favor, vuelva a iniciar sesión.',
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#dd4b39'
            }).then(function() {
                window.close();
            });
        </script>
    </body>
    </html>
    <?php
}

