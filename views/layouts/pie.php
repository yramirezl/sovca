<?php
// ══════════════════════════════════════════════════════════════
// Footer con detección de tipo de ganadería
// ══════════════════════════════════════════════════════════════

$__fid = isset($_SESSION['mfundos_id']) ? (int)$_SESSION['mfundos_id'] : 0;
$__uid = isset($_SESSION['idusuariox']) ? (int)$_SESSION['idusuariox'] : 0;

$__tipo_g = 0;
$__debug  = "fid=$__fid uid=$__uid";

if (class_exists('ActiveRecord\\ConnectionManager')) {
    try {
        $__conn = ActiveRecord\ConnectionManager::get_connection();

        if ($__fid > 0) {
            $__sth = $__conn->query("
                SELECT id, nombre, mtipoganaderias_id
                  FROM mfundos WHERE id = $__fid AND status = 1 LIMIT 1
            ");
            if ($__sth) {
                $__row = $__sth->fetch(PDO::FETCH_ASSOC);
                if ($__row && isset($__row['mtipoganaderias_id'])) {
                    $__tipo_g = (int)$__row['mtipoganaderias_id'];
                    $_SESSION['tipo_ganaderia'] = $__tipo_g;
                    $__debug .= " OK={$__row['nombre']}/tipo=$__tipo_g";
                } else {
                    $__debug .= " fundo_no_existe";
                }
            }
        }

        if ($__tipo_g === 0 && $__uid > 0) {
            $__sth2 = $__conn->query("
                SELECT f.id, f.mtipoganaderias_id, f.nombre
                  FROM mfundosxusuarios fxu
            INNER JOIN mfundos f ON f.id = fxu.mfundos_id
                 WHERE fxu.musuarios_id = $__uid
                   AND fxu.status = 1 AND f.status = 1
              ORDER BY f.id ASC LIMIT 1
            ");
            if ($__sth2) {
                $__row2 = $__sth2->fetch(PDO::FETCH_ASSOC);
                if ($__row2) {
                    $__tipo_g = (int)$__row2['mtipoganaderias_id'];
                    $_SESSION['mfundos_id']     = (int)$__row2['id'];
                    $_SESSION['tipo_ganaderia'] = $__tipo_g;
                    $__debug .= " CORREGIDO={$__row2['nombre']}/tipo=$__tipo_g";
                }
            }
        }

        if ($__tipo_g === 0 && isset($_SESSION['tipo_ganaderia'])) {
            $__tipo_g = (int)$_SESSION['tipo_ganaderia'];
            $__debug .= " FALLBACK=$__tipo_g";
        }
    } catch (Exception $e) {
        $__debug .= ' EX=' . $e->getMessage();
    }
} else {
    $__debug .= ' sin_ConnManager';
}

if ($__tipo_g === 1) {
    $__footer_tipo = 'OVINA';
} elseif ($__tipo_g === 2) {
    $__footer_tipo = 'CAPRINA';
} else {
    $__footer_tipo = 'OVINA Y CAPRINA';
}
?>
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->

      <!-- ================================================================
           FOOTER RESPONSIVO
           El desplazamiento izquierdo usa la MISMA variable que el
           .content-wrapper (--bovs-sidebar-w), así que acompaña al menú
           lateral al abrirse, colapsarse o en móvil, sin valores fijos
           que se descuadren.
           ================================================================ -->
      <style>
      /* ---- Base: desktop con sidebar visible ---- */
      .pie-footer {
          background: #008d4c;
          color: #ffffff;
          padding: 14px 24px;
          font-family: Arial, sans-serif;
          font-size: 13px;
          line-height: 1.5;
          margin-left: var(--bovs-sidebar-w, 230px);
          box-sizing: border-box;
          transition: margin-left .3s ease;
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: space-between;
          gap: 6px 18px;
      }
      .pie-footer a,
      .pie-footer strong { color: #ffffff; }
      .pie-footer a { text-decoration: underline; }
      .pie-footer strong { font-weight: 700; }
      .pie-footer__left,
      .pie-footer__right {
          display: inline-flex;
          align-items: center;
          flex-wrap: wrap;
          gap: 4px 6px;
          min-width: 0;
          max-width: 100%;
      }
      .pie-footer__left  { flex: 1 1 auto; }
      .pie-footer__right { flex: 0 1 auto; }
      .pie-footer__right > span { overflow-wrap: anywhere; }

      /* ---- Menú colapsado (mini): se alinea con el content-wrapper ---- */
      @media (min-width: 768px) {
          body.sidebar-collapse .pie-footer {
              margin-left: var(--bovs-sidebar-mini-w, 50px);
          }
      }

      /* ---- Móvil: el menú va oculto, el pie ocupa todo el ancho ---- */
      @media (max-width: 767px) {
          .pie-footer {
              margin-left: 0;
              padding: 12px 14px;
              font-size: 12px;
              flex-direction: column;
              align-items: center;
              text-align: center;
              gap: 4px;
          }
          .pie-footer__left,
          .pie-footer__right {
              justify-content: center;
              width: 100%;
              flex-basis: auto;
          }
          .pie-footer__left { line-height: 1.45; }
      }

      /* ---- Móvil muy angosto ---- */
      @media (max-width: 480px) {
          .pie-footer {
              font-size: 11.5px;
              padding: 10px 12px;
              line-height: 1.5;
          }
      }
      </style>

      <footer class="pie-footer" role="contentinfo">
          <div class="pie-footer__left">
              <strong>Copyright &copy; <?php echo date('Y'); ?></strong>
              <a href="#" title="RUTED">RUTED</a>.
              <span>Todos los Derechos Reservados.</span>
          </div>
          <div class="pie-footer__right">
              <span>Sistema de Gestión <strong><?php echo htmlspecialchars($__footer_tipo); ?></strong></span>
          </div>
      </footer>

    </div><!-- /.wrapper -->

    <!-- Navegación estandarizada del botón Regresar (sin confirmación) -->
    <script type="text/javascript" src="../../js/crud-back.js?v=1"></script>
    <!-- Decodifica los escapes \uXXXX de las respuestas AJAX (Ñ, acentos, N°) -->
    <script type="text/javascript" src="../../js/ajax-unicode.js?v=2"></script>
    <!-- Quita los <p></p> de relleno de los contenedores de mensajes (barras rojas
         vacías). La franja que cierra cada bloque la dibuja css/crud-form.css -->
    <script type="text/javascript" src="../../js/crud-mensajes.js?v=3"></script>
  </body>
</html>