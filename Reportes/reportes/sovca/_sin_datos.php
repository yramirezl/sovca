<?php
/* ============================================================
   SOVCA | Aviso común: reporte sin datos
   ------------------------------------------------------------
   Se incluye desde el bloque "sin datos" de cada wrapper.
   Variables opcionales antes del include:
     $sin_datos_titulo  (string)
     $sin_datos_texto   (string)
   ============================================================ */

$sin_datos_titulo = isset($sin_datos_titulo) && $sin_datos_titulo !== ''
    ? $sin_datos_titulo
    : 'Sin datos disponibles';

$sin_datos_texto = isset($sin_datos_texto) && $sin_datos_texto !== ''
    ? $sin_datos_texto
    : 'No existen registros para los filtros seleccionados.';

$sin_datos_titulo_js = json_encode($sin_datos_titulo, JSON_UNESCAPED_UNICODE);
$sin_datos_texto_js  = json_encode($sin_datos_texto, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($sin_datos_titulo, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="font-family: Arial, sans-serif;">
    <script>
        Swal.fire({
            icon: 'info',
            title: <?php echo $sin_datos_titulo_js; ?>,
            text: <?php echo $sin_datos_texto_js; ?>,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#22B34E'
        }).then(function() {
            window.close();
        });
    </script>
</body>
</html>
