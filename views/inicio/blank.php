<?php
include( '../layouts/constantes.php' );
include_once( '../layouts/cabecera.php' );
session_start();

/* Recordatorio de validación de correo (solo si el correo no está validado)
   ⚠️ include (no include_once): el layout ya incluyó este archivo para el
   modal y un include_once no volvería a pintar el banner. */
$bovsAvisoModo = 'banner';
include( '../layouts/aviso_validar_correo.php' );

if ( ( $_SESSION[ 'tipo_ganaderia' ] == 1 )||( $_SESSION[ 'tipo_ganaderia' ] == 0 ) ) {
    include_once( '../layouts/carrusel_bovinos.php' );

} else if ( ( $_SESSION[ 'tipo_ganaderia' ] == 2 ) ) {
    include_once( '../layouts/carrusel_bovideos.php' );

}

include_once( '../layouts/pie.php' );

?>