<?php
include( '../layouts/constantes.php' );
include_once( '../layouts/cabecera.php' );
session_start();

require_once __DIR__ . '/../../lib/mantenimiento_helper.php';

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

if ( ( $_SESSION[ 'tipo_ganaderia' ] == 1 )||( $_SESSION[ 'tipo_ganaderia' ] == 0 ) ) {
    include_once( '../layouts/carrusel_bovinos.php' );

} else if ( ( $_SESSION[ 'tipo_ganaderia' ] == 2 ) ) {
    include_once( '../layouts/carrusel_bovideos.php' );

}

include_once( '../layouts/pie.php' );

?>