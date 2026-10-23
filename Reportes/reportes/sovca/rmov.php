<?php
/* ============================================================
   BOVS | Wrapper del Reporte de Movimiento de Rebaño
   ============================================================ */

require_once('pdfmovimiento.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$obj = new pdfreporte();

$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$name = 'Movimiento_Rebano.pdf';

if ($mfundos_id > 0) {

    if (is_array($obj->arrp_mov) && count($obj->arrp_mov) > 0) {

        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();

        /* FPDF legacy: Output($name, $dest) — nombre primero */
        $obj->Output($name, 'I');

    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Sin datos</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body style="font-family: Arial, sans-serif;">
            <script>
                Swal.fire({
                    icon: 'info',
                    title: 'Sin datos',
                    text: 'El movimiento solicitado no existe o no pertenece a este fundo.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#22B34E'
                }).then(function() {
                    window.close();
                });
            </script>
        </body>
        </html>
        <?php
    }

} else {
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
?>