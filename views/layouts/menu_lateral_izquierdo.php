<?php

@session_start();
@$id_sesion   = $_SESSION['idsessionx'];
@$mfundos_id  = $_SESSION['mfundos_id'];
@$t_ganaderia = $_SESSION['tipo_ganaderia'];
@$rol         = $_SESSION['rolx'];

// El helper trae el estado del MODO SOPORTE (mf_modo_supervisor_activo /
// mf_soporte_estado). Se incluye de forma defensiva: si no carga, el menú
// se comporta exactamente como antes.
if (!function_exists('mf_modo_supervisor_activo')) {
    @include_once(__DIR__ . '/../../lib/multifundo_helper.php');
}

// ============================================================
// SELECTOR DE FUNDOS — usa la conexión ya abierta
// NO carga modelos (evita el problema de rutas relativas)
// ============================================================
if (!function_exists('render_selector_fundos')) {
    function render_selector_fundos($uid, $mfundos_activo) {
        if (!$uid) return '';
        if (!class_exists('ActiveRecord\\ConnectionManager')) return '';

        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            $uid  = (int)$uid;

            $sql = "SELECT f.id AS mfundos_id, f.nombre, f.cid_rif,
                           (SELECT COUNT(*) FROM manimales a
                             WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales
                      FROM mfundosxusuarios fxu
                INNER JOIN mfundos f ON f.id = fxu.mfundos_id
                     WHERE fxu.musuarios_id = $uid
                       AND fxu.status = 1
                       AND f.status = 1
                  ORDER BY f.nombre ASC";

            $fundos = $conn->query_and_fetch_all($sql);
        } catch (Exception $e) {
            return '';
        }

        if (!$fundos || count($fundos) === 0) return '';

        $activo = null;
        foreach ($fundos as $f) {
            if ((int)$f['mfundos_id'] === (int)$mfundos_activo) { $activo = $f; break; }
        }
        if (!$activo) $activo = $fundos[0];

        $html  = '<li class="mf-side-block">';
        $html .= '  <div class="mf-side-label">';
        $html .= '    <i class="fa fa-building-o"></i> <span>MI FUNDO ACTIVO</span>';
        $html .= '  </div>';
        $html .= '  <div class="mf-side-current" id="mf_side_current">';
        $html .= '    <div class="mf-side-avatar">' . htmlspecialchars(mb_strtoupper(mb_substr($activo['nombre'], 0, 1))) . '</div>';
        $html .= '    <div class="mf-side-info">';
        $html .= '      <div class="mf-side-name">' . htmlspecialchars($activo['nombre']) . '</div>';
        $html .= '      <div class="mf-side-meta">' . (int)$activo['total_animales'] . ' animales</div>';
        $html .= '    </div>';
        $html .= '    <i class="fa fa-angle-down mf-side-caret"></i>';
        $html .= '  </div>';

        $html .= '  <ul class="mf-side-list" id="mf_side_list">';
        foreach ($fundos as $f) {
            $is_active = ((int)$f['mfundos_id'] === (int)$mfundos_activo);
            $html .= '    <li class="' . ($is_active ? 'active' : '') . '" data-fundo-id="' . (int)$f['mfundos_id'] . '">';
            $html .= '      <div class="mf-side-avatar sm">' . htmlspecialchars(mb_strtoupper(mb_substr($f['nombre'], 0, 1))) . '</div>';
            $html .= '      <div class="mf-side-info">';
            $html .= '        <div class="mf-side-name">' . htmlspecialchars($f['nombre']) . '</div>';
            $html .= '        <div class="mf-side-meta">' . (int)$f['total_animales'] . ' animales</div>';
            $html .= '      </div>';
            if ($is_active) $html .= '      <i class="fa fa-check mf-side-check"></i>';
            $html .= '    </li>';
        }
        $html .= '  </ul>';

        $html .= '  <a href="../fundos/" class="mf-side-manage">';
        $html .= '    <i class="fa fa-cog"></i> Gestionar fundos';
        $html .= '  </a>';
        $html .= '</li>';

        return $html;
    }
}

// ============================================================
// BOTÓN DE INICIO — primer ítem del menú lateral
// Apunta a la pantalla que se muestra al ingresar al sistema
// ============================================================
if (!function_exists('render_boton_inicio')) {
    function render_boton_inicio() {
        $activo = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $activo = (bool)preg_match('#/views/inicio(/|[?]|$)#i', $_SERVER['REQUEST_URI']);
        }

        return '<li class="bovs-menu-inicio' . ($activo ? ' is-active' : '') . '">'
             . '  <a href="../inicio/" title="Ir a la pantalla de inicio">'
             . '    <span class="bovs-menu-inicio-ic"><i class="fa fa-home"></i></span>'
             . '    <span>INICIO</span>'
             . '  </a>'
             . '</li>';
    }
}


// ============================================================
// MENÚ OPERATIVO DEL FUNDO
// Se usa en el menú del PRODUCTOR y también en MODO SOPORTE
// (cuando un administrador entra a un fundo), para que no haya
// dos versiones del mismo menú.
// ============================================================
if (!function_exists('render_menu_gestion_fundo')) {
    function render_menu_gestion_fundo() {
        $html  = '';

        $html .= ' <li class="treeview"><a href="#"><i class="fa fa-book"></i> <span>Maestros</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        $html .= '<li><a href="../razas/"><i class="fa fa-certificate"></i> <span>Razas</span></a></li>';
        $html .= '<li><a href="../lotes/"><i class="fa fa-table"></i> <span>Lotes</span></a></li>';
        $html .= '<li><a href="../productos/"><i class="fa fa-flask"></i> <span>Productos</span></a></li>';
        $html .= '</ul></li>';

        $html .= ' <li class="treeview"><a href="#"><i class="fa fa-area-chart"></i> <span>Parámetros</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        $html .= '<li><a href="../categorias/"><i class="fa fa-certificate"></i> <span>Categorías</span></a></li>';
        $html .= '<li><a href="../periodos/"><i class="fa fa-calendar"></i> <span>Períodos</span></a></li>';
        $html .= '</ul></li>';

        $html .= ' <li class="treeview"><a href="#"><i class="fa fa-database"></i> <span>Gestión</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        $html .= '<li><a href="../animales/"><i class="fa fa-newspaper-o"></i> <span>Ficha de Animales</span></a></li>';
        $html .= '<li><a href="../nacimientos/"><i class="fa fa-book"></i> <span>Registro de Nacimientos</span></a></li>';
        $html .= '<li><a href="../servicios/"><i class="fa fa-calendar"></i> <span>Servicio del Animal</span></a></li>';
        $html .= '<li><a href="../prodleche/"><i class="fa fa-area-chart"></i> <span>Producción de Leche</span></a></li>';
        $html .= '<li><a href="../consecutivopesos/"><i class="fa fa-line-chart"></i> <span>Consecutivo de Pesos</span></a></li>';
        $html .= '<li><a href="../controlsanitario/"><i class="fa fa-medkit"></i> <span>Control Sanitario</span></a></li>';
        $html .= '<li><a href="../desincorporacion/"><i class="fa fa-toggle-on"></i> <span>Desincorporaciones</span></a></li>';
        $html .= '<li><a href="../potreros/"><i class="fa fa-table"></i> <span>Potreros</span></a></li>';
        $html .= '<li><a href="../movreb/"><i class="fa fa-th-list"></i> <span>Movimiento de Rebaño</span></a></li>';
        $html .= '</ul></li>';

        $html .= ' <li class="treeview"><a href="#"><i class="fa fa-bar-chart"></i> <span>Reportes</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        $html .= '<li><a href="../dashboard/dashboard"><i class="fa fa-bar-chart"></i> <span>Estadísticas</span></a></li>';
        $html .= '</ul></li>';

        return $html;
    }
}


// ============================================================
// MENÚS POR ROL
// ============================================================

// MODO SOPORTE (solo web): un administrador (rol 1/4) está "dentro"
// de un fundo. Se muestra el menú operativo del fundo MÁS los accesos
// de administración, y el rol se conserva intacto.
if (!empty($id_sesion) && function_exists('mf_modo_supervisor_activo') && mf_modo_supervisor_activo()) {

    $soporte      = function_exists('mf_soporte_estado') ? mf_soporte_estado() : null;
    $sop_nombre   = $soporte ? $soporte['fundo'] : (isset($_SESSION['fundo_nombre']) ? $_SESSION['fundo_nombre'] : '');
    $sop_inicio   = ($soporte && $soporte['inicio'] > 0) ? date('d-m-Y H:i', $soporte['inicio']) : '';
    $sop_expira   = ($soporte && $soporte['expira'] > 0) ? date('d-m-Y H:i', $soporte['expira']) : '';

    echo '<ul class="sidebar-menu nav">';

    echo '<li class="bovs-soporte-bloque">';
    echo '  <div class="bovs-soporte-titulo"><i class="fa fa-user-secret"></i> MODO SOPORTE</div>';
    echo '  <div class="bovs-soporte-fundo">' . htmlspecialchars($sop_nombre) . '</div>';
    if ($sop_inicio !== '') {
        echo '  <div class="bovs-soporte-meta">Desde ' . $sop_inicio . '</div>';
    }
    if ($sop_expira !== '') {
        echo '  <div class="bovs-soporte-meta">Cierra solo: ' . $sop_expira . '</div>';
    }
    echo '  <button type="button" class="bovs-soporte-salir js-salir-soporte">';
    echo '    <i class="fa fa-sign-out"></i> Salir del modo soporte';
    echo '  </button>';
    echo '</li>';

    echo render_boton_inicio();
    echo render_menu_gestion_fundo();

    // Accesos de administración: permiten volver a la gestión del
    // sistema sin salir del modo soporte.
    echo ' <li class="treeview bovs-soporte-admin"><a href="#"><i class="fa fa-users fa-lg"></i> <span>Administración</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../usuarios/"><i class="fa fa-users"></i> <span>Usuarios</span></a></li>';
    echo '<li><a href="../fundos/"><i class="fa fa-star"></i> <span>Todos los Fundos</span></a></li>';
    echo '<li><a href="../dispositivos/"><i class="fa fa-mobile"></i> <span>Dispositivos Offline</span></a></li>';
    echo '<li><a href="../conexiones/"><i class="fa fa-flag"></i> <span>Conexiones</span></a></li>';
    echo '</ul></li>';

    echo '</ul>';

} else if (!empty($id_sesion) && ($rol == 4)) { // SUPER USUARIO
    echo '<ul class="sidebar-menu nav">';
    echo render_boton_inicio();
    echo ' <li class="treeview"><a href="#"><i class="fa fa-cloud fa-lg"></i> <span>Modo Offline</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../../app_offline/" target="_blank"><i class="fa fa-cloud-download"></i> <span>App Offline (PWA)</span></a></li>';
    echo '<li><a href="../dispositivos/"><i class="fa fa-mobile"></i> <span>Dispositivos Offline</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-certificate fa-lg"></i> <span>Parámetros</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../aptitudes/"><i class="fa fa-truck"></i> <span>Aptitudes del Animal</span></a></li>';
    echo '<li><a href="../causadescartes/"><i class="fa fa-bookmark"></i> <span>Causas de Descartes</span></a></li>';
    echo '<li><a href="../causademortalidad/"><i class="fa fa-bookmark"></i> <span>Causas de Mortalidad</span></a></li>';
    echo '<li><a href="../estadosfisiologicos/"><i class="fa fa-bookmark"></i> <span>Estados Fisiológicos</span></a></li>';
    echo '<li><a href="../estatusganados/"><i class="fa fa-bookmark"></i> <span>Estatus Ganados</span></a></li>';
    echo '<li><a href="../tiposervicios/"><i class="fa fa-bookmark"></i> <span>Tipo de Servicios</span></a></li>';
    echo '<li><a href="../razas/"><i class="fa fa-certificate"></i> <span>Razas</span></a></li>';
    echo '<li><a href="../periodos/"><i class="fa fa-certificate"></i> <span>Períodos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-users fa-lg"></i> <span>Administrador</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../usuarios/"><i class="fa fa-users"></i> <span>Usuarios</span></a></li>';
    echo '<li><a href="../tipousuarios/"><i class="fa fa-link"></i> <span>Tipos de Usuario</span></a></li>';
    echo '<li><a href="../roles/"><i class="fa fa-flag"></i> <span>Roles</span></a></li>';
    echo '<li><a href="../fundos/"><i class="fa fa-star"></i> <span>Todos los Fundos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-envelope-o fa-lg"></i> <span>Mensajería</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../mensajes/"><i class="fa fa-envelope-o"></i> <span>Mensajes Internos</span></a></li>';
    echo '<li><a href="../mensajesext/"><i class="fa fa-envelope"></i> <span>Mensajes Externos</span></a></li>';
    echo '<li><a href="../mcontactosext/"><i class="fa fa-users"></i> <span>Contactos Externos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-bar-chart"></i> <span>Estadísticas</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../dataxfundos/"><i class="fa fa-certificate"></i> <span>Registros por Fundos</span></a></li>';
    echo '<li><a href="../conexiones/"><i class="fa fa-flag"></i> <span>Conexiones</span></a></li>';
    echo '</ul></li>';
    echo '</ul>';

} else if (!empty($id_sesion) && ($rol == 1)) { // ADMINISTRADOR
    echo '<ul class="sidebar-menu nav">';
    echo render_boton_inicio();
    echo ' <li class="treeview"><a href="#"><i class="fa fa-cloud fa-lg"></i> <span>Modo Offline</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../../app_offline/" target="_blank"><i class="fa fa-cloud-download"></i> <span>App Offline (PWA)</span></a></li>';
    echo '<li><a href="../dispositivos/"><i class="fa fa-mobile"></i> <span>Dispositivos Offline</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-wrench fa-lg"></i> <span>Parámetros</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../aptitudes/"><i class="fa fa-truck"></i> <span>Aptitudes del Animal</span></a></li>';
    echo '<li><a href="../causadescartes/"><i class="fa fa-bookmark"></i> <span>Causas de Descartes</span></a></li>';
    echo '<li><a href="../causademortalidad/"><i class="fa fa-bookmark"></i> <span>Causas de Mortalidad</span></a></li>';
    echo '<li><a href="../estadosfisiologicos/"><i class="fa fa-bookmark"></i> <span>Estados Fisiológicos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-envelope-o fa-lg"></i> <span>Mensajería</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../mensajes/"><i class="fa fa-envelope-o"></i> <span>Mensajes Internos</span></a></li>';
    echo '<li><a href="../mensajesext/"><i class="fa fa-envelope"></i> <span>Mensajes Externos</span></a></li>';
    echo '<li><a href="../mcontactosext/"><i class="fa fa-users"></i> <span>Contactos Externos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-users fa-lg"></i> <span>Administrador</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../usuarios/"><i class="fa fa-users"></i> <span>Usuarios</span></a></li>';
    echo '<li><a href="../fundos/"><i class="fa fa-star"></i> <span>Todos los Fundos</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-bar-chart"></i> <span>Estadísticas</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../dataxfundos/"><i class="fa fa-certificate"></i> <span>Registros por Fundos</span></a></li>';
    echo '<li><a href="../conexiones/"><i class="fa fa-flag"></i> <span>Conexiones</span></a></li>';
    echo '</ul></li>';
    echo '</ul>';

} else if (!empty($id_sesion) && ($rol == 2)) { // PROPIETARIO

    if (($mfundos_id != Null) && ($t_ganaderia > 0)) {

        echo '<ul class="sidebar-menu nav">';

        // ==========================================================
        // SELECTOR DE FUNDOS — arriba del menú
        // ==========================================================
        echo render_selector_fundos((int)$_SESSION['idusuariox'], (int)$mfundos_id);

        // Botón de INICIO (debajo del selector, sobre las acciones del perfil)
        echo render_boton_inicio();

        echo ' <li class="treeview"><a href="#"><i class="fa fa-cloud fa-lg"></i> <span>Modo Offline</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        echo '<li><a href="../../app_offline/" target="_blank"><i class="fa fa-cloud-download"></i> <span>App Offline (PWA)</span></a></li>';
        echo '<li><a href="../dispositivos/"><i class="fa fa-mobile"></i> <span>Dispositivos Offline</span></a></li>';
        echo '</ul></li>';

        echo ' <li class="treeview"><a href="#"><i class="fa fa-book"></i> <span>Fundos</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        echo '<li><a href="../fundos/"><i class="fa fa-cog"></i> <span>Gestionar Fundos</span></a></li>';
        echo '<li><a href="../migracion/"><i class="fa fa-table"></i> <span>Migrar Animales</span></a></li>';
        echo '</ul></li>';
   


        // Menú operativo del fundo (compartido con el MODO SOPORTE)
        echo render_menu_gestion_fundo();

        echo '</ul>';

    } else {
        // Productor sin fundo aún
        echo '<ul class="sidebar-menu nav">';
        echo render_boton_inicio();
        echo ' <li class="treeview"><a href="#"><i class="fa fa-cloud fa-lg"></i> <span>Modo Offline</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        echo '<li><a href="../../app_offline/" target="_blank"><i class="fa fa-cloud-download"></i> <span>App Offline (PWA)</span></a></li>';
        echo '</ul></li>';

        echo ' <li class="treeview"><a href="#"><i class="fa fa-link"></i> <span>Registro de Fundo</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
        echo '<li><a href="../fundos/"><i class="fa fa-bookmark"></i> <span>Crear mi primer fundo</span></a></li>';
        echo '</ul></li>';
        echo '</ul>';
    }

} else if (!empty($id_sesion) && ($rol == 3)) { // ENCARGADO
    echo '<ul class="sidebar-menu nav">';
    echo render_boton_inicio();
    echo ' <li class="treeview"><a href="#"><i class="fa fa-cloud fa-lg"></i> <span>Modo Offline</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../../app_offline/" target="_blank"><i class="fa fa-cloud-download"></i> <span>App Offline (PWA)</span></a></li>';
    echo '</ul></li>';

    echo ' <li class="treeview"><a href="#"><i class="fa fa-database"></i> <span>Gestión</span> <i class="fa fa-angle-left pull-right"></i></a><ul class="treeview-menu">';
    echo '<li><a href="../animales/"><i class="fa fa-newspaper-o"></i> <span>Ficha de Animales</span></a></li>';
    echo '<li><a href="../nacimientos/"><i class="fa fa-book"></i> <span>Registro de Nacimientos</span></a></li>';
    echo '<li><a href="../servicios/"><i class="fa fa-calendar"></i> <span>Servicio del Animal</span></a></li>';
    echo '<li><a href="../prodleche/"><i class="fa fa-area-chart"></i> <span>Producción de Leche</span></a></li>';
    echo '<li><a href="../consecutivopesos/"><i class="fa fa-line-chart"></i> <span>Consecutivo de Pesos</span></a></li>';
    echo '<li><a href="../controlsanitario/"><i class="fa fa-medkit"></i> <span>Control Sanitario</span></a></li>';
    echo '<li><a href="../desincorporacion/"><i class="fa fa-toggle-on"></i> <span>Desincorporaciones</span></a></li>';
    echo '<li><a href="../potreros/"><i class="fa fa-table"></i> <span>Potreros</span></a></li>';
    echo '<li><a href="../movreb/"><i class="fa fa-th-list"></i> <span>Movimiento de Rebaño</span></a></li>';
    echo '</ul></li>';
    echo '</ul>';

} else {
    exit;
}
?>

<style>
/* ============================================================
   SELECTOR DE FUNDOS EN EL SIDEBAR
   ============================================================ */
.mf-side-block {
    margin: 8px 8px 12px;
    list-style: none;
}
.mf-side-label {
    font-size: 10px;
    font-weight: 700;
    color: #8895a4;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 6px 8px 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mf-side-label i { color: #234e35; }

.mf-side-current {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #234e35, #2c6e46);
    color: #fff;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all .15s ease;
    user-select: none;
}
.mf-side-current:hover {
    box-shadow: 0 4px 12px rgba(35,78,53,.25);
    transform: translateY(-1px);
}
.mf-side-current .mf-side-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,.22); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; flex-shrink: 0;
}
.mf-side-current .mf-side-info { flex: 1; min-width: 0; }
.mf-side-current .mf-side-name {
    font-size: 13px; font-weight: 600;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mf-side-current .mf-side-meta {
    font-size: 10px; opacity: .8; margin-top: 1px;
}
.mf-side-current .mf-side-caret {
    font-size: 12px; opacity: .9;
    transition: transform .2s ease;
}
.mf-side-block.open .mf-side-current .mf-side-caret {
    transform: rotate(180deg);
}

.mf-side-list {
    list-style: none;
    margin: 0; padding: 0;
    max-height: 0; overflow: hidden;
    transition: max-height .25s ease;
}
.mf-side-block.open .mf-side-list {
    max-height: 320px; overflow-y: auto;
    margin-top: 6px;
    background: #f8fafc;
    border-radius: 8px;
    padding: 6px;
    border: 1px solid #e5e8eb;
}
.mf-side-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background .12s ease;
    font-size: 12.5px;
}
.mf-side-list li:hover { background: #fff; }
.mf-side-list li.active { background: #e8f5e9; }
.mf-side-list li.active .mf-side-name { color: #1b5e20; font-weight: 600; }

.mf-side-list .mf-side-avatar.sm {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #234e35, #2c6e46); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.mf-side-list li.active .mf-side-avatar.sm {
    background: linear-gradient(135deg, #2f8f4a, #4caf50);
}
.mf-side-list .mf-side-info { flex: 1; min-width: 0; }
.mf-side-list .mf-side-name {
    color: #233e2d; font-size: 12.5px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mf-side-list .mf-side-meta {
    color: #8895a4; font-size: 10px; margin-top: 1px;
}
.mf-side-list .mf-side-check { color: #2f8f4a; font-size: 12px; }

.mf-side-manage {
    display: block;
    text-align: center;
    padding: 8px 10px;
    margin-top: 6px;
    background: #f4f6f9;
    border-radius: 6px;
    color: #5a6874;
    font-size: 11.5px;
    text-decoration: none;
    transition: all .12s ease;
}
.mf-side-manage:hover {
    background: #e8f0f7;
    color: #234e35;
    text-decoration: none;
}
.mf-side-manage i { margin-right: 4px; }

/* ============================================================
   BOTÓN DE INICIO DEL MENÚ LATERAL
   ============================================================ */
.sidebar-menu > li.bovs-menu-inicio {
    margin: 10px 8px 6px !important;
    list-style: none;
}
.sidebar-menu > li.bovs-menu-inicio > a {
    display: flex !important;
    align-items: center;
    gap: 10px;
    padding: 10px 12px !important;
    border: 0 !important;
    border-radius: 8px !important;
    background: linear-gradient(135deg, #234e35, #2c6e46) !important;
    color: #ffffff !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    letter-spacing: .6px;
    text-transform: uppercase;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(35, 78, 53, .22);
    transition: background .15s ease, box-shadow .15s ease, transform .15s ease;
}
.sidebar-menu > li.bovs-menu-inicio > a:hover,
.sidebar-menu > li.bovs-menu-inicio > a:focus {
    background: linear-gradient(135deg, #2c6e46, #3a8a58) !important;
    color: #ffffff !important;
    text-decoration: none !important;
    box-shadow: 0 4px 12px rgba(35, 78, 53, .3);
    transform: translateY(-1px);
}
.bovs-menu-inicio-ic {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .2);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.sidebar-menu > li.bovs-menu-inicio.is-active > a {
    background: linear-gradient(135deg, #2f8f4a, #4caf50) !important;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .35), 0 2px 6px rgba(35, 78, 53, .25);
}

/* ============================================================
   BLOQUE DE MODO SOPORTE (administrador dentro de un fundo)
   ============================================================ */
.sidebar-menu > li.bovs-soporte-bloque {
    margin: 10px 8px 8px !important;
    padding: 10px 12px !important;
    list-style: none;
    border-radius: 10px !important;
    background: linear-gradient(135deg, #7a3b12, #a8561d) !important;
    color: #fff !important;
    box-shadow: 0 3px 10px rgba(122, 59, 18, .28);
}
.bovs-soporte-titulo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11.5px !important;
    font-weight: 800 !important;
    letter-spacing: .8px !important;
    text-transform: uppercase !important;
    color: #fff3e4 !important;
}
.bovs-soporte-fundo {
    margin-top: 4px;
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1.25 !important;
    color: #ffffff !important;
    overflow-wrap: anywhere;
}
.bovs-soporte-meta {
    margin-top: 2px;
    font-size: 10.5px !important;
    color: #ffe6cc !important;
    line-height: 1.35 !important;
}
.sidebar-menu > li.bovs-soporte-bloque .bovs-soporte-salir {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    margin-top: 9px;
    padding: 8px 10px !important;
    border: 1px solid rgba(255, 255, 255, .55) !important;
    border-radius: 7px !important;
    background: rgba(255, 255, 255, .14) !important;
    color: #ffffff !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    letter-spacing: .3px;
    cursor: pointer;
    transition: background .15s ease;
}
.sidebar-menu > li.bovs-soporte-bloque .bovs-soporte-salir:hover {
    background: rgba(255, 255, 255, .28) !important;
}
</style>

<script type="text/javascript">
var rol = <?php echo (int)$_SESSION['rolx']; ?>;
var act_email = <?php echo (int)(isset($_SESSION['act_email']) ? $_SESSION['act_email'] : 0); ?>;
var accion = <?php echo (int)(isset($_SESSION['accion']) ? $_SESSION['accion'] : 0); ?>;

$(document).ready(function() {

    // =========================================================
    // SELECTOR DE FUNDOS EN SIDEBAR
    // =========================================================
    $(document).on('click', '#mf_side_current', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('.mf-side-block').toggleClass('open');
    });

    // Click en un fundo → cambiar
    $(document).on('click', '.mf-side-list li', function(e) {
        e.preventDefault();
        var $li = $(this);
        if ($li.hasClass('active')) {
            $('.mf-side-block').removeClass('open');
            return;
        }
        var id = $li.data('fundo-id');
        if (!id) return;

        $li.css('opacity', '0.5').css('pointer-events', 'none');

        $.post("../../controllers/mfundos_controller.php", {
            action: "cambiar_fundo",
            fundo_id: id
        }, function(resp) {
            if (resp && resp.ok) {
                window.location.href = "../inicio/";
            } else {
                alert((resp && resp.msg) ? resp.msg : 'Error al cambiar de fundo');
                $li.css('opacity', '1').css('pointer-events', 'auto');
            }
        }, "json").fail(function() {
            alert('Error de conexión');
            $li.css('opacity', '1').css('pointer-events', 'auto');
        });
    });

    // Cerrar el dropdown al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mf-side-block').length) {
            $('.mf-side-block').removeClass('open');
        }
    });

    // Marcado de item activo en el menú
    $('li').click(function() {
        $("li.active").removeClass("active");
        $(this).addClass('active');
    });

    (function() {
        var current_page_URL = location.href;
        $("a").each(function() {
            if ($(this).attr("href") !== "#") {
                var target_URL = $(this).prop("href");
                if (target_URL == current_page_URL) {
                    $('li.active').removeClass('active');
                    $(this).parent('li').addClass('active');
                    return false;
                }
            }
        });
    })();

    // Aviso de cierre del MODO SOPORTE (lo deja lib/validar_session.php)
    <?php
    $soporteAviso = isset($_SESSION['soporte_aviso']) ? $_SESSION['soporte_aviso'] : '';
    if ($soporteAviso !== '') {
        unset($_SESSION['soporte_aviso']);
    }
    ?>
    (function() {
        var aviso = <?php echo json_encode((string)$soporteAviso, JSON_UNESCAPED_UNICODE); ?>;
        if (!aviso) return;

        var texto = 'El modo soporte finalizó: el tiempo de acceso al fundo se cumplió.';
        if ($.alert) {
            $.alert({ title: 'Modo soporte', content: texto });
        } else {
            alert(texto);
        }
    })();
    
});
</script>

<?php
/* Aviso de validación de correo electrónico (diseño propio).
   Se imprime FUERA del <script>: trae su propio CSS y su JS. */
$bovsAvisoModo = 'modal';
include(__DIR__ . '/aviso_validar_correo.php');
?>