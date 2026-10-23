<?php
// ══════════════════════════════════════════════════════════════
// Footer dinámico — determina OVINA / CAPRINA por el fundo activo
// ══════════════════════════════════════════════════════════════

$__tipo_g = isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0;
$__fid    = isset($_SESSION['mfundos_id'])      ? (int)$_SESSION['mfundos_id']      : 0;

// Si hay fundo activo, verificar que el tipo coincida
if ($__fid > 0) {
    try {
        if (class_exists('ActiveRecord\\ConnectionManager')) {
            $__conn = ActiveRecord\ConnectionManager::get_connection();
            $__sth  = $__conn->query("SELECT mtipoganaderias_id FROM mfundos WHERE id = $__fid LIMIT 1");
            if ($__sth) {
                $__row = $__sth->fetch(PDO::FETCH_ASSOC);
                if ($__row && isset($__row['mtipoganaderias_id'])) {
                    $__tipo_db = (int)$__row['mtipoganaderias_id'];

                    // Si no coincide, sobreescribir la sesión con la verdad del fundo activo
                    if ($__tipo_db !== $__tipo_g) {
                        $__tipo_g = $__tipo_db;
                        $_SESSION['tipo_ganaderia'] = $__tipo_g;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silencioso
    }
}

if ($__tipo_g === 1) {
    $__footer_tipo = 'OVINA';
} elseif ($__tipo_g === 2) {
    $__footer_tipo = 'CAPRINA';
} else {
    $__footer_tipo = 'OVINA / CAPRINA';
}

// Plan (por si se usa más adelante)
$__cantidad = isset($_SESSION['maximo_registros']) ? (int)$_SESSION['maximo_registros'] : 0;
$__plan     = '';
$__cambiar  = ($__cantidad > 0 && $__cantidad <= 150);
if ($__cambiar) {
    $__plan = 'Plan de ' . $__cantidad . ' Registros ';
}
?>
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->

      <footer class="main-footer oculto-impresion">
          <div class="pull-right hidden-xs oculto-impresion">
              <?php echo $__plan; ?>Sistema de Gestión <?php echo $__footer_tipo; ?>
          </div>
          <strong class="oculto-impresion">Copyright &copy; <?php echo date('Y'); ?> <a href="#">RUTED</a>.</strong>
          Todos los Derechos Reservados.
      </footer>

    </div><!-- /.wrapper -->
  </body>
</html>