/**
 * js/bovs-sesion.js
 * ---------------------------------------------------------------------------
 * Aviso de CIERRE REMOTO de la sesión.
 *
 * El administrador puede pedir desde «Conexiones» que esta sesión se cierre.
 * En vez de expulsar al usuario de golpe, se le muestra un aviso fijo con
 * cuenta atrás de 60 segundos para que guarde lo que esté haciendo:
 *
 *   «Su sesión ha expirado. En 1 minuto el sistema se cerrará de forma
 *    automática. Si tiene un cambio importante, guárdelo de inmediato.»
 *
 * Al llegar a cero se cierra la sesión y se vuelve al login. Si el usuario
 * cierra el navegador, el servidor cumple el cierre igualmente al vencer el
 * plazo (lib/validar_session.php).
 *
 * Lo incluye views/layouts/pie.php en todas las páginas.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    if (window.__bovsSesionInit) { return; }
    window.__bovsSesionInit = true;

    var URL_CTRL   = '../../controllers/mconexiones_controller';
    var URL_LOGIN  = '../../views/login/?stat=session_closed';
    var INTERVALO  = 15000;   /* consulta cada 15 s */
    var cerrando   = false;
    var cuenta     = null;
    var intervalo  = null;
    var restante   = 0;

    function post(datos, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', URL_CTRL, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var r = null;
            try { r = JSON.parse(xhr.responseText); } catch (e) { r = null; }
            if (typeof cb === 'function') { cb(r); }
        };
        var partes = [];
        for (var k in datos) {
            if (Object.prototype.hasOwnProperty.call(datos, k)) {
                partes.push(encodeURIComponent(k) + '=' + encodeURIComponent(datos[k]));
            }
        }
        xhr.send(partes.join('&'));
    }

    /* ------------------------------------------------------------------
     * Estilos del aviso (se inyectan una sola vez)
     * ------------------------------------------------------------------ */
    function inyectarEstilos() {
        if (document.getElementById('bovs-sesion-css')) { return; }
        var css = document.createElement('style');
        css.id = 'bovs-sesion-css';
        css.textContent = ''
          + '#bovs-aviso-sesion{position:fixed;right:18px;bottom:18px;z-index:999999;'
          + 'width:340px;max-width:calc(100vw - 24px);background:#fff;border-radius:14px;'
          + 'box-shadow:0 18px 50px rgba(0,0,0,.28);border:1px solid #f0d9d7;'
          + 'font-family:inherit;overflow:hidden;animation:bovsSube .22s ease-out}'
          + '@keyframes bovsSube{from{transform:translateY(14px);opacity:0}to{transform:translateY(0);opacity:1}}'
          + '#bovs-aviso-sesion .bs-cab{display:flex;align-items:center;gap:10px;'
          + 'padding:13px 16px;background:#b3261e;color:#fff}'
          + '#bovs-aviso-sesion .bs-cab i{font-size:18px}'
          + '#bovs-aviso-sesion .bs-cab strong{font-size:14px;font-weight:800;letter-spacing:.2px}'
          + '#bovs-aviso-sesion .bs-cuerpo{padding:14px 16px 8px}'
          + '#bovs-aviso-sesion .bs-texto{margin:0;font-size:13px;line-height:1.5;color:#3d4b44}'
          + '#bovs-aviso-sesion .bs-texto b{color:#b3261e}'
          + '#bovs-aviso-sesion .bs-reloj{margin:12px 0 0;text-align:center;font-size:36px;'
          + 'font-weight:800;font-variant-numeric:tabular-nums;color:#b3261e;line-height:1}'
          + '#bovs-aviso-sesion .bs-reloj span{display:block;font-size:10.5px;font-weight:700;'
          + 'color:#8a95a1;letter-spacing:.8px;text-transform:uppercase;margin-top:6px}'
          + '#bovs-aviso-sesion .bs-pie{display:flex;gap:8px;padding:12px 16px 16px}'
          + '#bovs-aviso-sesion .bs-btn{flex:1;border:0;border-radius:9px;padding:10px 12px;'
          + 'font-size:13px;font-weight:800;cursor:pointer;background:#b3261e;color:#fff}'
          + '#bovs-aviso-sesion .bs-btn:hover{background:#8f1d17}'
          + '#bovs-aviso-sesion .bs-btn--ghost{background:#eef1f3;color:#3d4b44}'
          + '#bovs-aviso-sesion .bs-btn--ghost:hover{background:#e2e7ea}'
          + '#bovs-aviso-sesion.bs-urgente .bs-cab{background:#7f1d1d}'
          + '#bovs-sesion-barra{position:fixed;top:0;left:0;height:3px;background:#b3261e;'
          + 'z-index:999999;transition:width .9s linear}';
        document.head.appendChild(css);
    }

    /* ------------------------------------------------------------------
     * Construir el aviso
     * ------------------------------------------------------------------ */
    function mostrarAviso(segundos) {
        if (document.getElementById('bovs-aviso-sesion')) { return; }
        inyectarEstilos();

        var caja = document.createElement('div');
        caja.id = 'bovs-aviso-sesion';
        caja.setAttribute('role', 'alertdialog');
        caja.innerHTML = ''
          + '<div class="bs-cab"><i class="fa fa-clock-o"></i><strong>Su sesión ha expirado</strong></div>'
          + '<div class="bs-cuerpo">'
          +   '<p class="bs-texto">En <b>1 minuto</b> el sistema se cerrará de forma automática. '
          +     'Si tiene un cambio importante, <b>guárdelo de inmediato</b>.</p>'
          +   '<div class="bs-reloj"><b id="bovs-sesion-num">60</b>'
          +     '<span>segundos para el cierre</span></div>'
          + '</div>'
          + '<div class="bs-pie">'
          +   '<button type="button" class="bs-btn" id="bovs-sesion-salir">Salir ahora</button>'
          + '</div>';
        document.body.appendChild(caja);

        var barra = document.createElement('div');
        barra.id = 'bovs-sesion-barra';
        barra.style.width = '100%';
        document.body.appendChild(barra);

        document.getElementById('bovs-sesion-salir').onclick = cerrarAhora;

        restante = Math.max(0, parseInt(segundos, 10) || 60);
        pintar();
        if (!cuenta) {
            cuenta = setInterval(function () {
                restante -= 1;
                pintar();
                if (restante <= 0) { cerrarAhora(); }
            }, 1000);
        }
    }

    function pintar() {
        var num   = document.getElementById('bovs-sesion-num');
        var barra = document.getElementById('bovs-sesion-barra');
        var caja  = document.getElementById('bovs-aviso-sesion');
        var seg   = Math.max(0, restante);

        if (num) { num.textContent = seg; }
        if (barra) {
            barra.style.width = Math.max(0, Math.min(100, (seg / 60) * 100)) + '%';
        }
        if (caja) {
            caja.className = (seg <= 15) ? 'bs-urgente' : '';
        }
    }

    /* ------------------------------------------------------------------
     * Cerrar la sesión ya
     * ------------------------------------------------------------------ */
    function cerrarAhora() {
        if (cerrando) { return; }
        cerrando = true;
        if (cuenta) { clearInterval(cuenta); cuenta = null; }
        if (intervalo) { clearInterval(intervalo); intervalo = null; }
        post({ action: 'cerrar_ahora' }, function () {
            window.location.href = URL_LOGIN;
        });
        setTimeout(function () { window.location.href = URL_LOGIN; }, 2500);
    }

    /* ------------------------------------------------------------------
     * Consultar el estado
     * ------------------------------------------------------------------ */
    function consultar() {
        if (cerrando) { return; }
        post({ action: 'estado_sesion' }, function (r) {
            if (!r) { return; }
            if (r.ok === false) {
                /* Sin sesión iniciada (o ya expiró por otra vía). Si el aviso
                   estaba visible, se cierra; si no, se deja de consultar para
                   no molestar en páginas públicas. */
                if (document.getElementById('bovs-aviso-sesion')) {
                    cerrarAhora();
                } else if (intervalo) {
                    clearInterval(intervalo);
                    intervalo = null;
                }
                return;
            }
            if (r.cierre && !document.getElementById('bovs-aviso-sesion')) {
                mostrarAviso(r.restante);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            consultar();
            intervalo = setInterval(consultar, INTERVALO);
        });
    } else {
        consultar();
        intervalo = setInterval(consultar, INTERVALO);
    }

    /* Expuesto para soporte y pruebas: permite mostrar el aviso a mano. */
    window.bovsSesionAviso = mostrarAviso;
})();
