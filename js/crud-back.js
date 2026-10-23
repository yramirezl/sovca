/**
 * crud-back.js — Navegación estandarizada del botón "Regresar".
 *
 * Objetivo
 *   Al hacer clic en el botón Regresar (#exit / #exit_top / [data-crud-back])
 *   el sistema vuelve DIRECTAMENTE al menú anterior, SIN diálogo de
 *   confirmación y sin depender de redirecciones hardcodeadas (que en varios
 *   formularios apuntaban al módulo equivocado).
 *
 * Prioridad del destino
 *   1) data-crud-back-url en el propio botón (override explícito).
 *   2) Página anterior real (document.referrer) si es del mismo origen y
 *      pertenece al sistema (contiene /views/). Esto devuelve al usuario
 *      exactamente al listado/vista desde donde entró al formulario.
 *   3) Índice del módulo actual (directorio padre de la URL). Fallback para
 *      accesos directos (sin referrer) o recargas.
 *
 * Notas de implementación
 *   - El botón se intercepta en fase de CAPTURA sobre document, por lo que se
 *     ejecuta antes que cualquier manejador antiguo (elemento o delegado) y lo
 *     neutraliza con stopPropagation(). Así se elimina el antiguo $.confirm
 *     "¿Desea Salir?" sin necesidad de editar cada formulario.
 *   - No requiere jQuery.
 */
(function (window, document) {
    'use strict';

    var SELECTOR = '#exit, #exit_top, [data-crud-back]';

    /** Índice del módulo actual: directorio padre de la URL (con barra final). */
    function moduleIndexUrl() {
        var path = window.location.pathname.replace(/\/+$/, '');
        var idx = path.lastIndexOf('/');
        if (idx > 0) {
            path = path.substring(0, idx);
        }
        return path + '/';
    }

    /** Página anterior real (menú anterior), si es válida dentro del sistema. */
    function previousMenuUrl() {
        var ref = document.referrer;
        if (!ref) {
            return null;
        }
        try {
            var u = new window.URL(ref, window.location.href);

            // Mismo origen (evita salir del sitio)
            if (u.origin !== window.location.origin) {
                return null;
            }
            // Debe ser una página del sistema
            if (u.pathname.indexOf('/views/') === -1) {
                return null;
            }
            // Evita volver a la misma página (recarga), que provocaría un loop
            if (u.pathname === window.location.pathname) {
                return null;
            }
            return u.pathname + u.search;
        } catch (e) {
            return null;
        }
    }

    /** Resuelve el destino final del botón "Regresar". */
    function resolveTarget(el) {
        if (el && el.getAttribute) {
            var override = el.getAttribute('data-crud-back-url');
            if (override) {
                return override;
            }
        }
        return previousMenuUrl() || moduleIndexUrl();
    }

    /** Navega al destino resuelto. */
    function goBack(el) {
        var url = resolveTarget(el);
        if (!url) {
            return;
        }
        if (url === window.location.href) {
            return;
        }
        window.location.assign(url);
    }

    /** Sube por el DOM buscando el botón "Regresar" que originó el clic. */
    function findTrigger(node) {
        while (node && node !== document) {
            if (node.nodeType === 1 && node.matches && node.matches(SELECTOR)) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    function onClickCapture(e) {
        // Ignora clics secundarios o con modificadores (permitir abrir en otra pestaña)
        if (e.defaultPrevented || e.button !== 0 ||
            e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        var el = findTrigger(e.target);
        if (!el) {
            return;
        }

        // Interceptar antes de los manejadores antiguos (bubble/delegados)
        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) {
            e.stopImmediatePropagation();
        }

        goBack(el);
    }

    document.addEventListener('click', onClickCapture, true);

    // API pública (opcional, para usos manuales)
    window.CrudBack = {
        go: goBack,
        resolveTarget: resolveTarget,
        moduleIndexUrl: moduleIndexUrl,
        previousMenuUrl: previousMenuUrl
    };
})(window, document);
