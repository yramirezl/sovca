<?php
/* Guardia: evita el listado de la carpeta. El estado real del modo
   mantenimiento se consulta desde lib/mantenimiento_helper.php. */
http_response_code(404);
exit;
