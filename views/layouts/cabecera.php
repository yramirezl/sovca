<!DOCTYPE html>
<html>
  <head>
		<style type="text/css">
			.multiselect-container {
				width: 100% !important;
			}
		</style>
	<?php
    //----------------------------------------------------------------
	     echo $constante["tipo_documento"];
    //----------------------------------------------------------------
	 ?>
    <title><?php echo $constante["titulo_pag"];?></title>
    <?php
	//----------------------------------------------------------------
 	     echo $constante["favi_ico"];
	//----------------------------------------------------------------
 	     echo $constante["meta_viewport"];
	//----------------------------------------------------------------
	//--------- Se imprimien los CSS ---------------------------------
	//----------------------------------------------------------------
 	     echo $constante["css_bootstrap"];
 	     echo $constante["css_iconos"];
 	     echo $constante["css_iconos2"];
 	     echo $constante["css_datatable"];
 	     echo $constante["css_tema"];
		  echo $constante["css_skyn"];
		  echo $constante["css_carrusel"];
		  echo $constante["css_tema_bovs"];   // 👈 NUEVA — Capa override BOVS (siempre al final)
		  echo $constante["css_tooltips"];    // Tooltips globales (después del tema)
	//----------------------------------------------------------------
	//-- Se imprimente las etiquetas html de escape para validar el --
	//-- Soporte de IExploer de HTML5                               --
	//----------------------------------------------------------------
    ?>
    <!--[if lt IE 9]>
    <?php
	//----------------------------------------------------------------
 	     echo $constante["js_cb1"];
		 echo $constante["js_cb2"];
	//----------------------------------------------------------------
    ?>
    <![endif]-->


	<?php
	//----------------------------------------------------------------
	//-- SE IMPRIMEN LOS JS PARA ARMAR TODA LA PAGINA               --
	//-- ESTAN DEFINIDOS EN EL ARCHIVO CONSTANTES.PHP               --
	//----------------------------------------------------------------
 	     echo $constante["js_jq"];
		 echo $constante["js_boo"];
	     echo $constante["js_lte"];
	     echo $constante["js_datatable"];
		 echo $constante["js_boo_datatable"];

	//----------------------------------------------------------------
    ?>

		<script Language="JavaScript">
			if(history.forward(1)){
			history.replace(history.forward(1));
			}
		</script>

		<script type="text/javascript">
			(function () {
				var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;

				function applyMobileBehavior() {
					var isSmallScreen = $(window).width() < 768;

					if (isTouch && typeof $.fn.tooltip !== 'undefined' && $.fn.tooltip.Constructor) {
						$.fn.tooltip.Constructor.DEFAULTS.trigger = 'click';
					}

					if (isTouch && typeof $.fn.popover !== 'undefined' && $.fn.popover.Constructor) {
						$.fn.popover.Constructor.DEFAULTS.trigger = 'click';
					}

					$('.dataTables_wrapper').each(function () {
						if (!$(this).parent('.table-responsive').length) {
							$(this).wrap('<div class="table-responsive"></div>');
						}
					});

					$('[data-toggle="tooltip"]').each(function () {
						var $el = $(this);
						if ($el.data('bs.tooltip')) {
							$el.tooltip('destroy');
						}
						$el.tooltip({
							trigger: isTouch ? 'click' : 'hover focus',
							container: 'body',
							placement: 'top',
							html: false,
							viewport: { selector: 'body', padding: 10 },
							delay: { show: 120, hide: 80 }
						});
					});

					$('.modal').each(function () {
						var $modal = $(this);
						var $dialog = $modal.find('.modal-dialog');
						var $body = $modal.find('.modal-body');

						if (isSmallScreen) {
							$dialog.css({
								'margin': '10px auto',
								'width': 'auto',
								'max-width': 'calc(100% - 20px)'
							});
							$body.css({
								'max-height': Math.round($(window).height() * 0.7) + 'px',
								'overflow-y': 'auto',
								'-webkit-overflow-scrolling': 'touch'
							});
						} else {
							$dialog.css({
								'margin': '30px auto',
								'width': '',
								'max-width': ''
							});
							$body.css({
								'max-height': '',
								'overflow-y': '',
								'-webkit-overflow-scrolling': ''
							});
						}
					});

					if (isSmallScreen) {
						$('.form-horizontal .control-label').css('text-align', 'left');
					} else {
						$('.form-horizontal .control-label').css('text-align', '');
					}
				}

				$(function () {
					applyMobileBehavior();
					$(window).on('resize orientationchange', applyMobileBehavior);
				});
			})();
		</script>

  </head>

  <body class="skin-green-light sidebar-mini">
    <div class="wrapper">
	<?php //      <!-- Main Header -->?>
      <header class="main-header">
    <?php
	//----------------------------------------------------------------
	//-- Logotipo superior izquierdo                                --
	//----------------------------------------------------------------
                include_once("logotipo.php");
	//----------------------------------------------------------------
	//-- Barra de Navegacion Superior                               --
	//----------------------------------------------------------------
                include_once("barra_navegacion_superior.php");
	//----------------------------------------------------------------
	
	?>
      </header>
      <aside class="main-sidebar">
        <section class="sidebar">
 <?php
// Multi-fundo switcher — defensivo y silencioso
// Solo se intenta si los modelos ya están cargados por el contexto padre.
if (isset($_SESSION['idusuariox'])) {

    // Cargar helper solo si no está
    if (!function_exists('mf_contar_fundos_usuario')) {
        @include_once(__DIR__ . '/../../lib/multifundo_helper.php');
    }

    // Solo proceder si el helper cargó Y el modelo base está disponible
    if (function_exists('mf_contar_fundos_usuario')
        && class_exists('Mfundosxusuario', false)) {
        try {
            $__total_f = mf_contar_fundos_usuario((int)$_SESSION['idusuariox']);
            if ($__total_f > 1) {
                @include_once(__DIR__ . '/fundo_switcher.php');
            }
        } catch (Exception $e) {
            // Silencioso — el switcher no se muestra si algo falla
        }
    }
}
?>	
			
	<?php
	//----------------------------------------------------------------
	//-- Buscador Superior Izquierdo                                --
	//----------------------------------------------------------------
               // include_once("buscador.php");
	//----------------------------------------------------------------
	//-- Menu Lateral Izquierdo                                     --
	//----------------------------------------------------------------
                include_once("menu_lateral_izquierdo.php");
	//----------------------------------------------------------------
	// include_once("carrusel2.php");

	session_start();

	if ( ( $_SESSION[ 'tipo_ganaderia' ] == 1 ) ) {
	
		 include_once( '../layouts/carrusel_bovinos_p2.php' );
	
	} else if ( ( $_SESSION[ 'tipo_ganaderia' ] == 2 ) ) {
		include_once( '../layouts/carrusel_bovideos_p2.php' );
	
	}else if ( ( $_SESSION[ 'tipo_ganaderia' ] == 0 ) ) {
		include_once( '../layouts/carrusel_bovinos_p2.php' );
	
	}
	?>
        </section>

      </aside>

      <div id="content-wrapper" class="content-wrapper">
	<?php
	    // <!-- Content Header (Page header) -->
		//----------------------------------------------------------------

		
		//-- Barra de Ubicacion de donde esta el Usuario en el Sistema  --
		//----------------------------------------------------------------
        //      include_once("ubicacion_sistema.php");
		//----------------------------------------------------------------
		//<!-- Main content -->
	?>
        <section class="content">