<?php
/* ============================================================
   SOVCA | Contenido de la pantalla de inicio
   ------------------------------------------------------------
   La estructura (cabecera, menú lateral y pie) la aporta
   views/inicio/index.php. Este archivo NO debe incluir
   cabecera.php ni pie.php: al hacerlo se duplicaban <html>,
   <body> y .content-wrapper, y en móvil quedaba un espacio en
   blanco enorme debajo del banner.
   ============================================================ */

if ( session_status() !== PHP_SESSION_ACTIVE ) {
    session_start();
}

require_once __DIR__ . '/../../lib/mantenimiento_helper.php';
require_once __DIR__ . '/../../lib/texto_helper.php';

/* MODO MANTENIMIENTO: los administradores (1/4) son los únicos que
   pueden estar aquí. Se les recuerda que el resto del sistema está
   bloqueado y cómo entrar a un fundo con Modo soporte. */
$bovsMttoActivo = bovs_mantenimiento_activo();
$bovsMttoRol    = isset( $_SESSION[ 'rolx' ] ) ? (int) $_SESSION[ 'rolx' ] : 0;
$bovsMttoAdmin  = in_array( $bovsMttoRol, array( 1, 4 ), true );
$bovsMttoEstado = $bovsMttoActivo ? bovs_mantenimiento_estado() : null;

/* Recordatorio de validación de correo (solo si el correo no está validado)
   ⚠️ include (no include_once): el layout ya incluyó este archivo para el
   modal y un include_once no volvería a pintar el banner. */
$bovsAvisoModo = 'banner';
include( '../layouts/aviso_validar_correo.php' );

if ( $bovsMttoActivo && $bovsMttoAdmin && is_array( $bovsMttoEstado ) ) {

    $mttoMotivo = trim( (string) $bovsMttoEstado[ 'motivo' ] );
    $mttoHasta  = trim( (string) $bovsMttoEstado[ 'hasta' ] );
    $mttoMin    = (int) $bovsMttoEstado[ 'minutos_restantes' ];
    ?>
    <style>
      .bovs-mtto-aviso {
        margin: 0 0 18px;
        padding: 18px 20px;
        border: 1px solid #f1c6a8;
        border-left: 6px solid #b91c1c;
        border-radius: 16px;
        background: linear-gradient(135deg, #fff7ed, #fef2f2);
        box-shadow: 0 6px 18px rgba(127, 29, 29, .08);
        display: flex;
        gap: 16px;
        align-items: flex-start;
        flex-wrap: wrap;
      }
      .bovs-mtto-aviso__icono {
        flex: 0 0 auto;
        width: 46px; height: 46px;
        display: grid; place-items: center;
        border-radius: 12px;
        background: #b91c1c;
        color: #fff;
        font-size: 20px;
      }
      .bovs-mtto-aviso__texto { flex: 1 1 320px; min-width: 0; }
      .bovs-mtto-aviso__titulo {
        margin: 0 0 4px;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: .02em;
        color: #7f1d1d;
        text-transform: uppercase;
      }
      .bovs-mtto-aviso__detalle {
        margin: 0 0 4px;
        color: #7f2d2d;
        font-size: 13px;
        line-height: 1.5;
      }
      .bovs-mtto-aviso__meta { color: #9a3412; font-size: 12px; }
      .bovs-mtto-aviso__acciones {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
      }
      .bovs-mtto-aviso__btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 14px;
        border-radius: 9px;
        border: 1px solid #b91c1c;
        background: #b91c1c;
        color: #fff !important;
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
      }
      .bovs-mtto-aviso__btn--claro {
        background: #fff;
        color: #7f1d1d !important;
      }
      .bovs-mtto-aviso__btn:hover { filter: brightness(.95); text-decoration: none; }
    </style>
    <section class="bovs-mtto-aviso" role="status" aria-live="polite">
      <div class="bovs-mtto-aviso__icono"><i class="fa fa-wrench" aria-hidden="true"></i></div>
      <div class="bovs-mtto-aviso__texto">
        <p class="bovs-mtto-aviso__titulo">Modo mantenimiento activo</p>
        <p class="bovs-mtto-aviso__detalle">
          Solo los administradores del sistema pueden acceder.
          Los productores y ayudantes están bloqueados en la web y en la app sin conexión.
          <?php if ( $mttoMotivo !== '' ): ?>
            <br><strong>Motivo:</strong> <?= htmlspecialchars( $mttoMotivo, ENT_QUOTES, 'UTF-8' ) ?>
          <?php endif; ?>
        </p>
        <div class="bovs-mtto-aviso__meta">
          <?php if ( $mttoHasta !== '' ): ?>
            <i class="fa fa-clock-o"></i> Cierra solo: <?= htmlspecialchars( $mttoHasta, ENT_QUOTES, 'UTF-8' ) ?>
            <?php if ( $mttoMin > 0 ): ?>(<?= (int) $mttoMin ?> min)<?php endif; ?>
          <?php else: ?>
            <i class="fa fa-clock-o"></i> Sin cierre automático: recuerda desactivarlo al terminar.
          <?php endif; ?>
        </div>
      </div>
      <div class="bovs-mtto-aviso__acciones">
        <a class="bovs-mtto-aviso__btn" href="../fundos/?modo=soporte">
          <i class="fa fa-user-secret" aria-hidden="true"></i> Entrar con Modo soporte
        </a>
        <a class="bovs-mtto-aviso__btn bovs-mtto-aviso__btn--claro" href="../mantenimiento/">
          <i class="fa fa-cog" aria-hidden="true"></i> Gestionar mantenimiento
        </a>
      </div>
    </section>
    <?php
}

/* ---------- Datos de la sesión para el saludo ---------- */
$biRol     = isset( $_SESSION[ 'rolx' ] ) ? (int) $_SESSION[ 'rolx' ] : 0;
$biFundo   = isset( $_SESSION[ 'mfundos_id' ] ) ? (int) $_SESSION[ 'mfundos_id' ] : 0;
$biTipo    = isset( $_SESSION[ 'tipo_ganaderia' ] ) ? (int) $_SESSION[ 'tipo_ganaderia' ] : 0;
$biSoporte = function_exists( 'mf_modo_supervisor_activo' ) && mf_modo_supervisor_activo();

$biNombre = isset( $_SESSION[ 'nombresx' ] ) ? trim( (string) $_SESSION[ 'nombresx' ] ) : '';
if ( $biNombre === '' && isset( $_SESSION[ 'usuariox' ] ) ) {
    $biNombre = trim( (string) $_SESSION[ 'usuariox' ] );
}
$biNombre = bovs_nombre_mostrar( $biNombre );

/* Solo el primer nombre: el saludo se lee mejor y no se corta en móvil */
$biPrimerNombre = '';
if ( $biNombre !== '' ) {
    $biPartes       = preg_split( '/\s+/u', $biNombre );
    $biPrimerNombre = $biPartes[ 0 ];
    /* Nombres guardados en MAYÚSCULAS → Título solo para el saludo */
    if ( preg_match( '/^[\p{Lu}\s\.\'-]+$/u', $biPrimerNombre ) ) {
        $biPrimerNombre = mb_convert_case( mb_strtolower( $biPrimerNombre, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
    }
}

$biFundoNombre = isset( $_SESSION[ 'fundo_nombre' ] ) ? trim( (string) $_SESSION[ 'fundo_nombre' ] ) : '';
$biFundoNombre = bovs_nombre_mostrar( $biFundoNombre );

$biInicial = $biPrimerNombre !== ''
    ? mb_strtoupper( mb_substr( $biPrimerNombre, 0, 1, 'UTF-8' ), 'UTF-8' )
    : 'S';

/* ---------- Banners del hero (según el tipo de ganadería) ----------
   SOVCA es ovina y caprina: 1 = OVINA, 2 = CAPRINA, 3 = TODAS. */
$biImgDir = ( $biTipo === 2 ) ? 'img/caprinos/carrusel' : 'img/ovinos/carrusel';
$biSlides = array();
for ( $i = 1; $i <= 4; $i++ ) {
    $biSlides[] = $biImgDir . '/fondo_' . $i . '.jpg';
}

/* ---------- Accesos rápidos según el rol ---------- */
$biAccesos = array();

if ( $biRol === 3 ) {
    /* Encargado: mismas opciones operativas de su menú */
    $biAccesos = array(
        array( '../animales/',         'fa-newspaper-o', 'Animales',     'Fichas y datos',      'verde' ),
        array( '../nacimientos/',      'fa-book',        'Nacimientos',  'Registrar crías',     'verde' ),
        array( '../prodleche/',        'fa-tint',        'Producción',   'Leche del día',       'azul'  ),
        array( '../servicios/',        'fa-calendar',    'Servicios',    'Reproducción',        'rosa'  ),
        array( '../controlsanitario/', 'fa-medkit',      'Sanitario',    'Controles y vacunas', 'rojo'  ),
        array( '../consecutivopesos/', 'fa-line-chart',  'Pesos',        'Consecutivo',         'ambar' ),
        array( '../potreros/',         'fa-table',       'Potreros',     'Rotación y aforo',    'verde' ),
        array( '../movreb/',           'fa-th-list',     'Rebaño',       'Movimientos',         'gris'  ),
    );
} elseif ( $biSoporte || ( $biRol === 2 && $biFundo > 0 ) ) {
    /* Productor con fundo activo y administrador en Modo soporte */
    $biAccesos = array(
        array( '../animales/',           'fa-newspaper-o', 'Animales',     'Fichas y datos',      'verde' ),
        array( '../nacimientos/',        'fa-book',        'Nacimientos',  'Registrar crías',     'verde' ),
        array( '../prodleche/',          'fa-tint',        'Producción',   'Leche del día',       'azul'  ),
        array( '../servicios/',          'fa-calendar',    'Servicios',    'Reproducción',        'rosa'  ),
        array( '../controlsanitario/',   'fa-medkit',      'Sanitario',    'Controles y vacunas', 'rojo'  ),
        array( '../consecutivopesos/',   'fa-line-chart',  'Pesos',        'Consecutivo',         'ambar' ),
        array( '../potreros/',           'fa-table',       'Potreros',     'Rotación y aforo',    'verde' ),
        array( '../dashboard/dashboard', 'fa-bar-chart',   'Estadísticas', 'Indicadores',         'azul'  ),
    );
} elseif ( $biRol === 2 ) {
    /* Productor que todavía no tiene fundo */
    $biAccesos = array(
        array( '../fundos/',         'fa-star',          'Mi fundo',    'Crea tu primer fundo', 'verde' ),
        array( '../../app_offline/', 'fa-cloud-download','App offline', 'Trabaja sin internet', 'azul'  ),
        array( '../inicio/perfil',   'fa-user',          'Mi perfil',   'Datos de la cuenta',   'gris'  ),
    );
} elseif ( $biRol === 1 || $biRol === 4 ) {
    /* Administración del sistema */
    $biAccesos = array(
        array( '../usuarios/',           'fa-users',      'Usuarios',      'Cuentas y permisos', 'verde' ),
        array( '../fundos/',             'fa-star',       'Fundos',        'Gestión de fincas',  'azul'  ),
        /* ../dashboard/dashboard es el tablero de UN fundo (es del productor);
           sin fundo activo el administrador lo ve vacío. Aquí se abre el
           submenú de Estadísticas del administrador. */
        array( '../estadisticas/',       'fa-bar-chart',  'Estadísticas',  'Registros y conexiones', 'ambar' ),
        array( '../dispositivos/',       'fa-mobile',     'Dispositivos',  'App sin conexión',   'gris'  ),
        array( '../mantenimiento/',      'fa-wrench',     'Mantenimiento', 'Modo y respaldos',   'rosa'  ),
        array( '../mensajes/',           'fa-envelope-o', 'Mensajes',      'Comunicación',       'verde' ),
    );
}
?>

<div class="bovs-inicio">

  <!-- ==========================================================
       HERO PUBLICITARIO
       En móvil la imagen se recorta desde la izquierda, que es
       donde está el mensaje, así que el texto queda legible.
       ========================================================== -->
  <section class="bovs-inicio-hero" role="region" aria-roledescription="carrusel"
           aria-label="Novedades SOVCA" data-bovs-slide="1">
    <?php foreach ( $biSlides as $i => $src ): ?>
      <div class="bovs-inicio-hero__slide<?php echo $i === 0 ? ' is-active' : ''; ?>"
           role="group" aria-roledescription="diapositiva"
           aria-label="<?php echo ( $i + 1 ) . ' de ' . count( $biSlides ); ?>"
           aria-hidden="<?php echo $i === 0 ? 'false' : 'true'; ?>">
        <img <?php echo $i === 0
                    ? 'src="' . htmlspecialchars( $src, ENT_QUOTES, 'UTF-8' ) . '" decoding="async" fetchpriority="high"'
                    : 'data-src="' . htmlspecialchars( $src, ENT_QUOTES, 'UTF-8' ) . '" decoding="async"'; ?>
             alt="SOVCA, gestión ovina y caprina"
             draggable="false">
      </div>
    <?php endforeach; ?>

    <div class="bovs-inicio-hero__velo" aria-hidden="true"></div>

    <?php if ( $biFundoNombre !== '' ): ?>
      <div class="bovs-inicio-hero__chip" title="<?php echo htmlspecialchars( $biFundoNombre, ENT_QUOTES, 'UTF-8' ); ?>">
        <i class="fa fa-map-marker" aria-hidden="true"></i>
        <span><?php echo htmlspecialchars( $biFundoNombre, ENT_QUOTES, 'UTF-8' ); ?></span>
      </div>
    <?php endif; ?>

    <div class="bovs-inicio-hero__dots" role="group" aria-label="Elegir banner">
      <?php foreach ( $biSlides as $i => $src ): ?>
        <button type="button"
                class="bovs-inicio-dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
                data-go="<?php echo $i; ?>"
                aria-label="Ver banner <?php echo ( $i + 1 ) . ' de ' . count( $biSlides ); ?>"
                <?php echo $i === 0 ? 'aria-current="true"' : ''; ?>></button>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ==========================================================
       SALUDO
       ========================================================== -->
  <section class="bovs-inicio-hola" aria-label="Sesión actual">
    <div class="bovs-inicio-hola__avatar" aria-hidden="true"><?php echo htmlspecialchars( $biInicial, ENT_QUOTES, 'UTF-8' ); ?></div>
    <div class="bovs-inicio-hola__txt">
      <p class="bovs-inicio-hola__saludo"
         data-bovs-saludo
         data-nombre="<?php echo htmlspecialchars( $biPrimerNombre, ENT_QUOTES, 'UTF-8' ); ?>"><?php
        echo $biPrimerNombre !== ''
            ? 'Hola, ' . htmlspecialchars( $biPrimerNombre, ENT_QUOTES, 'UTF-8' )
            : 'Hola';
      ?></p>
      <p class="bovs-inicio-hola__fecha" data-bovs-fecha><?php echo date( 'd-m-Y' ); ?></p>
    </div>
  </section>

  <?php if ( !empty( $biAccesos ) ): ?>
  <!-- ==========================================================
       ACCESOS RÁPIDOS
       ========================================================== -->
  <section class="bovs-inicio-accesos" aria-labelledby="bovs-inicio-accesos-titulo">
    <div class="bovs-inicio-seccion__cab">
      <h2 class="bovs-inicio-seccion__titulo" id="bovs-inicio-accesos-titulo">Accesos rápidos</h2>
      <span class="bovs-inicio-seccion__linea" aria-hidden="true"></span>
    </div>

    <div class="bovs-inicio-grid">
      <?php foreach ( $biAccesos as $acc ): ?>
        <a class="bovs-acceso bovs-acceso--<?php echo $acc[ 4 ]; ?>"
           href="<?php echo htmlspecialchars( $acc[ 0 ], ENT_QUOTES, 'UTF-8' ); ?>">
          <span class="bovs-acceso__ico" aria-hidden="true"><i class="fa <?php echo $acc[ 1 ]; ?>"></i></span>
          <span class="bovs-acceso__txt">
            <span class="bovs-acceso__titulo"><?php echo htmlspecialchars( $acc[ 2 ], ENT_QUOTES, 'UTF-8' ); ?></span>
            <span class="bovs-acceso__sub"><?php echo htmlspecialchars( $acc[ 3 ], ENT_QUOTES, 'UTF-8' ); ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <p class="bovs-inicio-nota">
    <i class="fa fa-bars" aria-hidden="true"></i>
    <span>Abre el menú para ver todos los módulos</span>
  </p>

</div><!-- /.bovs-inicio -->
