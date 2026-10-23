<?php
/**
 * Aviso común para reportes cuando la sesión ya no está disponible.
 * Fuerza UTF-8 para que “Sesión” no se muestre como “SesiÃ³n”.
 */
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesión caducada</title>
</head>
<body>
    <script>
        alert('Su Sesión ha Caducado');
        window.close();
    </script>
</body>
</html>
