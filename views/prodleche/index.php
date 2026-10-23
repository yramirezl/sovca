<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$plRol         = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
$plPuedeBorrar = ($plRol !== 3);
?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-index.css?v=3" type="text/css" />

<style>
/* ============================================================
   Producción de Leche — animales (paginado) + detalle (paginado)
   ============================================================ */
.crud-page a.crud-btn--success,
.crud-page a.crud-btn--primary,
.crud-page a.crud-btn--warning,
.crud-page a.crud-btn--danger,
.crud-page a.crud-btn--info { color: #ffffff; }
.crud-page a.crud-btn--ghost { color: var(--crud-text); }

.pl-lista { padding: 4px 18px 6px; min-height: 120px; }

.pl-animal {
    border: 1px solid var(--crud-border);
    border-radius: 12px;
    background: #fff;
    margin-bottom: 10px;
    overflow: hidden;
    transition: border-color .18s ease, box-shadow .18s ease;
}
.pl-animal.is-open {
    border-color: #16a34a;
    box-shadow: 0 10px 26px -18px rgba(22, 163, 74, .85);
}

.pl-animal__cab {
    display: block;
    padding: 12px 16px;
    cursor: pointer;
    outline: none;
}
.pl-animal__cab:hover { background: #f8fafc; }
.pl-animal__cab:focus-visible { box-shadow: inset 0 0 0 2px rgba(37, 99, 235, .35); }

/* Línea 1: identificación + acciones | Línea 2: datos */
.pl-animal__fila {
    display: flex;
    align-items: center;
    gap: 10px 14px;
    flex-wrap: wrap;
}
.pl-animal__datos {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    margin-top: 8px;
    padding-left: 34px;
}

.pl-animal__chevron {
    flex: 0 0 auto;
    width: 22px;
    color: #94a3b8;
    font-size: 13px;
    transition: transform .18s ease, color .18s ease;
}
.pl-animal.is-open .pl-animal__chevron { transform: rotate(90deg); color: #16a34a; }

.pl-animal__id { flex: 1 1 0; min-width: 120px; }
.pl-animal__id strong {
    display: block;
    font-size: 15.5px;
    font-weight: 800;
    letter-spacing: .02em;
    color: #0f172a;
    line-height: 1.2;
}
.pl-animal__nombre {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pl-animal__meta {
    display: block;
    font-size: 11.5px;
    color: var(--crud-muted);
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pl-dato { flex: 1 1 80px; min-width: 70px; }
.pl-dato__label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #94a3b8;
    line-height: 1.2;
}
.pl-dato__valor {
    display: block;
    font-size: 14.5px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.35;
}
.pl-dato__valor small { font-size: 11px; font-weight: 600; color: #64748b; }

.pl-animal__acciones {
    flex: 0 0 auto;
    display: flex;
    gap: 8px;
    margin-left: auto;
    flex-wrap: wrap;
}
.pl-btn-mini {
    padding: 8px 12px;
    font-size: 13px;
    border-radius: 8px;
}
.pl-btn-mini i { font-size: 12px; }

/* Acciones compactas dentro de la tabla de registros */
.pl-btn-ico {
    padding: 6px 8px;
    font-size: 12px;
    border-radius: 7px;
}
.pl-btn-ico i { font-size: 12px; }

/* ---------------------------- Detalle ---------------------------- */
.pl-animal__detalle {
    position: relative;
    border-top: 1px dashed #d7e6db;
    padding: 12px 16px 14px;
    background: #fbfdfb;
}

/* Velo de carga: mantiene el contenido en su sitio (sin encoger/estirar) */
.pl-lista,
.pl-animal__detalle { position: relative; }
.pl-velo {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 2;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .58);
    border-radius: 10px;
    cursor: progress;
}
.pl-velo span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--crud-border);
    box-shadow: 0 6px 16px rgba(15, 23, 42, .08);
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
}
.is-cargando > .pl-velo { display: flex; }
.is-cargando > :not(.pl-velo) { opacity: .45; }

/* La lista es muy alta: el chip se ancla a la ventana para quedar siempre visible */
.pl-lista > .pl-velo { background: rgba(255, 255, 255, .45); }
.pl-lista > .pl-velo span {
    position: fixed;
    bottom: 26px;
    left: 50%;
    transform: translateX(-50%);
}
.pl-detalle__cab {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    align-items: baseline;
    margin-bottom: 8px;
    font-size: 12.5px;
    color: #475569;
}
.pl-detalle__cab strong { color: #0f172a; }
.pl-detalle__cab .pl-chip {
    background: #e8f5e9;
    border: 1px solid #c8e6c9;
    color: #1b5e20;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 11.5px;
    font-weight: 700;
}

.pl-tabla { width: 100%; min-width: 460px; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
.pl-tabla th:nth-child(1), .pl-tabla td:nth-child(1) { width: 128px; }
.pl-tabla th:nth-child(5), .pl-tabla td:nth-child(5) { width: 106px; }
.pl-tabla th {
    background: linear-gradient(180deg, #475569 0%, #1e293b 100%);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: 8px 6px;
    text-align: center;
    white-space: nowrap;
}
.pl-tabla td {
    padding: 6px;
    border-bottom: 1px solid #e8eef4;
    text-align: center;
    font-size: 13px;
    color: #1f2937;
    height: 34px;
}
.pl-tabla tbody tr:nth-child(even) { background: #f8fbf9; }
.pl-tabla tbody tr:hover { background: #f0fdf4; }
.pl-tabla td.pl-num { text-align: center; font-variant-numeric: tabular-nums; }
.pl-tabla td.pl-total { font-weight: 800; }
.pl-tabla td.pl-acciones { white-space: nowrap; }
.pl-tabla td.pl-acciones .crud-btn { margin: 0 2px; }

/* Fila en edición */
.pl-tabla tr.pl-editando { background: #fffbeb !important; }
.pl-tabla tr.pl-editando td { padding: 4px 5px; }
.pl-inp {
    width: 100%;
    min-width: 0;
    height: 30px;
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 13px;
    color: #1f2937;
    background: #fff;
    outline: none;
    box-sizing: border-box;
    text-align: center;
}
.pl-inp:focus { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.15); }
.pl-inp[type="date"] { padding: 4px 3px; }

.pl-bloqueado { color: #cbd5e1; cursor: help; }

/* --------------------- Mini paginador (detalle) --------------------- */
.pl-mini-pag {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 8px;
    font-size: 12.5px;
    color: #64748b;
}
.pl-mini-pag__btn {
    border: 1px solid var(--crud-border);
    background: #fff;
    color: #1f2937;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.pl-mini-pag__btn:hover:not(:disabled) { background: #f1f5f9; }
.pl-mini-pag__btn:disabled { opacity: .45; cursor: default; }

/* ------------------- Paginador de animales ------------------- */
.pl-pag {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 18px;
    padding: 4px 24px 18px;
    font-size: 13px;
    color: #475569;
}
.pl-pag[hidden] { display: none !important; }

/* Caja de navegación: [«] [‹] 1 2 3 [›] [»] */
.pl-pag__ctrl {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 4px;
    background: #fff;
    border: 1px solid var(--crud-border);
    border-radius: 12px;
    box-shadow: 0 2px 8px -6px rgba(15, 23, 42, .45);
}
.pl-pag__btn,
.pl-pag__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: #334155;
    font-size: 14px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease;
}
.pl-pag__btn i { font-size: 16px; }
.pl-pag__btn:hover:not(:disabled),
.pl-pag__num:hover:not(.is-active) {
    background: #eef7f1;
    color: #15803d;
}
.pl-pag__btn:disabled {
    color: #cbd5e1;
    background: transparent;
    cursor: not-allowed;
}
.pl-pag__btn--extremo { color: #475569; }
.pl-pag__num.is-active {
    background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 5px 12px -6px rgba(22, 163, 74, .95);
    cursor: default;
}
.pl-pag__puntos {
    min-width: 22px;
    text-align: center;
    color: #94a3b8;
    font-weight: 700;
    user-select: none;
}
/* En escritorio mandan los números; el "1 / 3" es solo para el teléfono */
.pl-pag__actual {
    display: none;
    padding: 0 6px;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
}
.pl-pag__size { display: flex; align-items: center; gap: 8px; }
.pl-pag__size select {
    height: 34px;
    border: 1px solid var(--crud-border);
    border-radius: 9px;
    padding: 0 8px;
    background: #fff;
    color: #1f2937;
}

.pl-vacio,
.pl-cargando {
    padding: 34px 18px;
    text-align: center;
    color: var(--crud-muted);
    font-size: 14.5px;
}
.pl-vacio i { display: block; font-size: 26px; margin-bottom: 8px; color: #cbd5e1; }

/* ---------------------------- Buscador ---------------------------- */
.pl-buscador {
    position: relative;
    flex: 0 1 340px;
    min-width: 220px;
}
.pl-buscador i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
    pointer-events: none;
}
.pl-buscador input {
    width: 100%;
    height: 40px;
    padding: 8px 12px 8px 34px;
    border: 1px solid var(--crud-border);
    border-radius: 10px;
    font-size: 14px;
    color: #1f2937;
    background: #fff;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
}
.pl-buscador input:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, .18);
}

/* ---------------------------- Aviso flotante ---------------------------- */
.pl-toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    max-width: 340px;
    padding: 12px 16px;
    border-radius: 12px;
    background: #0f172a;
    color: #fff;
    font-size: 13.5px;
    line-height: 1.35;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .28);
    opacity: 0;
    transform: translateY(12px);
    transition: opacity .22s ease, transform .22s ease;
    pointer-events: none;
}
.pl-toast.is-visible { opacity: 1; transform: translateY(0); }
.pl-toast i { margin-right: 8px; color: #4ade80; }
.pl-toast--error i { color: #f87171; }

@media (max-width: 700px) {
    .pl-lista { padding: 4px 12px 6px; }
    .pl-animal__cab { padding: 12px; }
    .pl-animal__acciones { width: 100%; }
    .pl-animal__acciones .crud-btn { flex: 1 1 auto; }
    .pl-animal__datos { padding-left: 0; }
    .pl-animal__datos .pl-dato { flex: 1 1 45%; }
    .pl-tabla { font-size: 12px; }
    .pl-tabla th, .pl-tabla td { padding: 5px 4px; }
    .pl-tabla th:nth-child(1), .pl-tabla td:nth-child(1) { width: 104px; }
    .pl-tabla th:nth-child(5), .pl-tabla td:nth-child(5) { width: 74px; }
    .pl-inp { height: 28px; font-size: 12px; padding: 3px 4px; }

    /* En el teléfono los números se cambian por "1 / 3" y las flechas crecen */
    .pl-pag { padding: 4px 12px 16px; }
    .pl-pag__ctrl { padding: 3px; gap: 1px; }
    .pl-pag__nums { display: none; }
    .pl-pag__actual { display: inline; }
    .pl-pag__btn { min-width: 40px; height: 40px; }
    .pl-pag__btn i { font-size: 18px; }
}
</style>

<div class="crud-page" data-theme="info">

    <div class="message"></div>

    <div class="crud-card">

        <header class="crud-header">
            <span class="crud-header__icon"><i class="fa fa-bitbucket"></i></span>
            <div class="crud-header__text">
                <h3 class="crud-header__title">Producción de Leche</h3>
                <p class="crud-header__subtitle">Animales ordeñados — haga clic en un animal para ver y editar sus registros</p>
            </div>
            <span class="crud-header__badge" id="pl-badge">—</span>
        </header>

        <div class="crud-toolbar">
            <div class="crud-toolbar__actions">
                <a class="crud-btn crud-btn--success" href="frm_registrar">
                    <i class="fa fa-plus"></i><span>Agregar</span>
                </a>
                <button type="button" class="crud-btn crud-btn--ghost" id="pl-contraer">
                    <i class="fa fa-compress"></i><span>Contraer todo</span>
                </button>
            </div>
            <div class="pl-buscador">
                <i class="fa fa-search"></i>
                <input type="text" id="pl-buscar" autocomplete="off"
                       placeholder="Buscar por código, nombre, arete o lote…">
            </div>
        </div>

        <div class="pl-lista" id="pl-lista">
            <div class="pl-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando animales…</div>
        </div>

        <div class="pl-pag" id="pl-pag" hidden>
            <span id="pl-pag-info">—</span>

            <div class="pl-pag__ctrl" role="navigation" aria-label="Paginación de animales">
                <button type="button" class="pl-pag__btn pl-pag__btn--extremo" data-pag="1"
                        title="Primera página" aria-label="Primera página">
                    <i class="fa fa-angle-double-left" aria-hidden="true"></i>
                </button>
                <button type="button" class="pl-pag__btn" data-pag="prev"
                        title="Página anterior" aria-label="Página anterior">
                    <i class="fa fa-angle-left" aria-hidden="true"></i>
                </button>

                <span class="pl-pag__nums" id="pl-pag-nums"></span>
                <span class="pl-pag__actual" id="pl-pag-actual">—</span>

                <button type="button" class="pl-pag__btn" data-pag="next"
                        title="Página siguiente" aria-label="Página siguiente">
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </button>
                <button type="button" class="pl-pag__btn pl-pag__btn--extremo" data-pag="last"
                        title="Última página" aria-label="Última página">
                    <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                </button>
            </div>

            <label class="pl-pag__size">
                <span>Mostrar</span>
                <select id="pl-tamano">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>por página</span>
            </label>
        </div>

    </div>
</div>

<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script>
(function () {

    var PUEDE_ELIMINAR = <?php echo $plPuedeBorrar ? 'true' : 'false'; ?>;
    var URL_DATOS      = '../../data_json/data_mprodleche';
    var URL_CTRL       = '../../controllers/mprodleche_controller';
    var TAMANO_DETALLE = 15;

    var estado = {
        pagina: 1,
        tamano: 25,
        q: '',
        totalPaginas: 1,
        totalAnimales: 0,
        totalRegistros: 0
    };

    var animales = [];
    var $lista   = document.getElementById('pl-lista');
    var $badge   = document.getElementById('pl-badge');
    var $buscar  = document.getElementById('pl-buscar');
    var $pag     = document.getElementById('pl-pag');
    var $pagInfo = document.getElementById('pl-pag-info');
    var $pagAct  = document.getElementById('pl-pag-actual');
    var $pagNums = document.getElementById('pl-pag-nums');
    var $tamano  = document.getElementById('pl-tamano');

    /* ---------------------------- Utilidades ---------------------------- */

    function esc(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function num(valor, dec) {
        dec = (dec === undefined) ? 2 : dec;
        var partes = Number(valor || 0).toFixed(dec).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    function hoyISO() {
        var d = new Date();
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var dia = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + dia;
    }

    function parseResp(resp) {
        if (resp && typeof resp === 'object') return resp;
        try { return JSON.parse(String(resp)); } catch (e) { return null; }
    }

    function alerta(titulo, contenido, tipo) {
        $.alert({
            title: titulo,
            content: contenido,
            icon: 'fa fa-exclamation-triangle',
            type: tipo || 'orange',
            confirmButton: 'Entendido'
        });
    }

    function articuloPorCodigo(codigo) {
        return $lista.querySelector('.pl-animal[data-codigo="' + codigo + '"]');
    }

    /* Aviso flotante (no bloquea la pantalla) */
    var toastTemporizador = null;
    function toast(mensaje, tipo) {
        var caja = document.getElementById('pl-toast');

        if (!caja) {
            caja = document.createElement('div');
            caja.id = 'pl-toast';
            caja.className = 'pl-toast';
            document.body.appendChild(caja);
        }

        caja.className = 'pl-toast' + (tipo === 'error' ? ' pl-toast--error' : '');
        caja.innerHTML = '<i class="fa ' + (tipo === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i>'
                       + esc(mensaje);

        void caja.offsetWidth;
        caja.classList.add('is-visible');

        clearTimeout(toastTemporizador);
        toastTemporizador = setTimeout(function () {
            caja.classList.remove('is-visible');
        }, 3200);
    }

    /* ---------------------------- Render lista ---------------------------- */

    function dato(label, valor) {
        return '<div class="pl-dato">'
             +     '<span class="pl-dato__label">' + esc(label) + '</span>'
             +     '<span class="pl-dato__valor">' + valor + '</span>'
             + '</div>';
    }

    function renderLista() {
        if (!animales.length) {
            var conFiltro = estado.q !== '';
            $lista.innerHTML = '<div class="pl-vacio"><i class="fa fa-inbox"></i>'
                + (conFiltro
                    ? 'No se encontraron animales para «' + esc(estado.q) + '».'
                    : 'No hay registros de producción de leche en este fundo.')
                + '</div>';
            return;
        }

        var html = '';

        animales.forEach(function (a, indice) {
            var meta = [];
            if (a.nro_arete) meta.push('Arete ' + esc(a.nro_arete));
            if (a.lote)       meta.push(esc(a.lote));

            html += '<article class="pl-animal" data-indice="' + indice + '"'
                 +      ' data-codigo="' + esc(a.codigo) + '">'
                 +  '<div class="pl-animal__cab" role="button" tabindex="0"'
                 +       ' aria-expanded="false" aria-label="Ver registros de ' + esc(a.codigo) + '">'
                 +    '<div class="pl-animal__fila">'
                 +      '<span class="pl-animal__chevron"><i class="fa fa-chevron-right"></i></span>'
                 +      '<div class="pl-animal__id">'
                 +        '<strong>' + esc(a.codigo) + '</strong>'
                 +        (a.nombre ? '<span class="pl-animal__nombre">' + esc(a.nombre) + '</span>' : '')
                 +        (meta.length ? '<span class="pl-animal__meta" title="' + esc(meta.join(' · ')) + '">' + meta.join(' · ') + '</span>' : '')
                 +      '</div>'
                 +      '<div class="pl-animal__acciones">'
                 +        '<a class="crud-btn crud-btn--warning pl-btn-mini pl-btn-resumen" target="_blank" rel="noopener"'
                 +           ' href="' + esc(a.url_resumen) + '"'
                 +           ' title="Generar el resumen de producción en PDF">'
                 +           '<i class="fa fa-file-pdf-o"></i><span>Resumen</span></a>'
                 +        '<button type="button" class="crud-btn crud-btn--ghost pl-btn-mini pl-btn-ver">'
                 +           '<i class="fa fa-list"></i><span>' + num(a.registros, 0) + ' registros</span></button>'
                 +      '</div>'
                 +    '</div>'
                 +    '<div class="pl-animal__datos">'
                 +      dato('Última prod.', esc(a.ultima_txt || '—'))
                 +      dato('Días', num(a.dias, 0))
                 +      dato('Total', num(a.total) + ' <small>Kg</small>')
                 +      dato('Prom./día', num(a.promedio_dia) + ' <small>Kg</small>')
                 +    '</div>'
                 +  '</div>'
                 +  '<div class="pl-animal__detalle" hidden></div>'
                 + '</article>';
        });

        $lista.innerHTML = html;
    }

    function renderPaginador() {
        if (estado.totalPaginas <= 1) {
            $pag.hidden = true;
            return;
        }

        var desde = (estado.pagina - 1) * estado.tamano + 1;
        var hasta = Math.min(estado.pagina * estado.tamano, estado.totalAnimales);
        var enPrimera = estado.pagina <= 1;
        var enUltima  = estado.pagina >= estado.totalPaginas;

        $pag.hidden = false;
        $pagInfo.textContent = 'Mostrando ' + desde + '–' + hasta + ' de '
                             + num(estado.totalAnimales, 0) + ' animales';
        $pagAct.textContent = estado.pagina + ' / ' + estado.totalPaginas;

        marcarBotonPagina('1', enPrimera);
        marcarBotonPagina('prev', enPrimera);
        marcarBotonPagina('next', enUltima);
        marcarBotonPagina('last', enUltima);

        $pagNums.innerHTML = botonesNumeros();
    }

    /* Habilita/deshabilita una flecha y lo deja claro para lectores de pantalla */
    function marcarBotonPagina(pag, deshabilitado) {
        var boton = $pag.querySelector('[data-pag="' + pag + '"]');
        if (!boton) return;

        boton.disabled = deshabilitado;
        boton.setAttribute('aria-disabled', deshabilitado ? 'true' : 'false');
    }

    /*
     * Números de página: hasta 7 se muestran todos; con más se abre una
     * ventana alrededor de la actual y se usan puntos suspensivos.
     */
    function botonesNumeros() {
        var total = estado.totalPaginas;
        var act   = estado.pagina;
        var lista = [];
        var html  = '';
        var i;

        if (total <= 7) {
            for (i = 1; i <= total; i++) lista.push(i);
        } else {
            lista.push(1);

            var ini = Math.max(2, act - 1);
            var fin = Math.min(total - 1, act + 1);

            if (ini > 2) lista.push('...');
            for (i = ini; i <= fin; i++) lista.push(i);
            if (fin < total - 1) lista.push('...');

            lista.push(total);
        }

        lista.forEach(function (p) {
            if (p === '...') {
                html += '<span class="pl-pag__puntos">…</span>';
                return;
            }

            var activo = (p === act);

            html += '<button type="button" class="pl-pag__num' + (activo ? ' is-active' : '') + '"'
                  + ' data-pag-num="' + p + '"'
                  + ' title="Página ' + p + ' de ' + total + '"'
                  + ' aria-label="Página ' + p + '"'
                  + (activo ? ' aria-current="page"' : '') + '>' + p + '</button>';
        });

        return html;
    }

    function irAPagina(nueva) {
        if (nueva < 1) nueva = 1;
        if (nueva > estado.totalPaginas) nueva = estado.totalPaginas;
        if (!nueva || nueva === estado.pagina) return;

        estado.pagina = nueva;
        cargarLista();
        window.scrollTo(0, 0);
    }

    function pintarContador() {
        if (!estado.totalAnimales) {
            $badge.textContent = estado.q ? 'Sin resultados' : 'Sin producción';
            return;
        }

        $badge.textContent = num(estado.totalAnimales, 0) + ' animales · '
                           + num(estado.totalRegistros, 0) + ' ordeños';
    }

    /* ---------------------------- Carga de la lista ---------------------------- */

    function cargarLista() {
        var conDatos = !!$lista.querySelector('.pl-animal');

        if (conDatos) {
            veloCargando($lista, true, 'Actualizando…');
        } else {
            $lista.innerHTML = '<div class="pl-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando animales…</div>';
        }

        return $.getJSON(URL_DATOS, {
                action: 'lista',
                pagina: estado.pagina,
                tamano: estado.tamano,
                q: estado.q
            })
            .done(function (resp) {
                veloCargando($lista, false);

                if (!resp || resp.ok !== true) {
                    $pag.hidden = true;
                    $lista.innerHTML = '<div class="pl-vacio"><i class="fa fa-exclamation-triangle"></i>'
                                     + esc((resp && resp.mensaje) ? resp.mensaje : 'No se pudo cargar la producción.')
                                     + '</div>';
                    return;
                }

                animales            = resp.animales || [];
                estado.pagina       = resp.pagina || 1;
                estado.totalPaginas = resp.total_paginas || 1;
                estado.totalAnimales  = resp.total_animales || 0;
                estado.totalRegistros = resp.total_registros || 0;

                renderLista();
                renderPaginador();
                pintarContador();
            })
            .fail(function () {
                veloCargando($lista, false);
                $pag.hidden = true;
                $lista.innerHTML = '<div class="pl-vacio"><i class="fa fa-exclamation-triangle"></i>'
                                 + 'No se pudo cargar la producción de leche. Recargue la página.</div>';
            });
    }

    /* ---------------------------- Detalle ---------------------------- */

    function abrirAnimal(articulo, paginaDetalle) {
        var detalle = articulo.querySelector('.pl-animal__detalle');
        var cab     = articulo.querySelector('.pl-animal__cab');

        detalle.hidden = false;
        articulo.classList.add('is-open');
        cab.setAttribute('aria-expanded', 'true');

        cargarDetalle(articulo, paginaDetalle || 1);
    }

    function cerrarAnimal(articulo) {
        var detalle = articulo.querySelector('.pl-animal__detalle');
        var cab     = articulo.querySelector('.pl-animal__cab');

        detalle.hidden = true;
        articulo.classList.remove('is-open');
        cab.setAttribute('aria-expanded', 'false');
    }

    function cargarDetalle(articulo, pagina) {
        var codigo   = articulo.getAttribute('data-codigo');
        var detalle  = articulo.querySelector('.pl-animal__detalle');
        var conTabla = !!detalle.querySelector('.pl-tabla');

        /* Si ya hay registros se cubre con un velo: el bloque no cambia de alto */
        if (conTabla) {
            veloCargando(detalle, true);
        } else {
            detalle.innerHTML = '<div class="pl-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando registros…</div>';
        }

        $.getJSON(URL_DATOS, {
                action: 'detalle',
                codigo: codigo,
                pagina: pagina,
                tamano: TAMANO_DETALLE
            })
            .done(function (resp) {
                veloCargando(detalle, false);

                if (!resp || resp.ok !== true) {
                    detalle.innerHTML = '<div class="pl-vacio">'
                        + esc((resp && resp.mensaje) ? resp.mensaje : 'No se pudieron cargar los registros.')
                        + '</div>';
                    return;
                }

                renderDetalle(articulo, resp);
            })
            .fail(function () {
                veloCargando(detalle, false);
                detalle.innerHTML = '<div class="pl-vacio">No se pudieron cargar los registros.</div>';
            });
    }

    /* Muestra/oculta el velo de carga sin tocar el contenido del contenedor */
    function veloCargando(contenedor, activo, texto) {
        var velo = contenedor.querySelector('.pl-velo');

        if (activo) {
            if (!velo) {
                velo = document.createElement('div');
                velo.className = 'pl-velo';
                contenedor.appendChild(velo);
            }

            velo.innerHTML = '<span><i class="fa fa-spinner fa-spin"></i> '
                           + esc(texto || 'Cargando…') + '</span>';
            contenedor.classList.add('is-cargando');
            return;
        }

        contenedor.classList.remove('is-cargando');
        if (velo) velo.remove();
    }

    function accionesFila(r, dias) {
        if (!r.editable) {
            return '<span class="pl-bloqueado" title="Solo se pueden editar o eliminar los últimos '
                 + parseInt(dias || 30, 10) + ' días"><i class="fa fa-lock"></i></span>';
        }

        var html = '<button type="button" class="crud-btn crud-btn--primary pl-btn-ico"'
                 + ' data-editar="' + r.id + '" title="Editar este registro" aria-label="Editar">'
                 + '<i class="fa fa-pencil"></i></button>';

        if (PUEDE_ELIMINAR) {
            html += '<button type="button" class="crud-btn crud-btn--danger pl-btn-ico"'
                  + ' data-eliminar="' + r.id + '" title="Eliminar este registro" aria-label="Eliminar">'
                  + '<i class="fa fa-trash-o"></i></button>';
        }

        return html;
    }

    function renderDetalle(articulo, d) {
        var detalle = articulo.querySelector('.pl-animal__detalle');
        var filas   = '';

        detalle.setAttribute('data-pagina', d.pagina);

        d.registros.forEach(function (r) {
            filas += '<tr data-id="' + r.id + '"'
                  +     ' data-fecha="' + esc(r.fecha) + '"'
                  +     ' data-m="' + r.m + '" data-t="' + r.t + '">'
                  +    '<td>' + esc(r.fecha_txt) + '</td>'
                  +    '<td class="pl-num">' + num(r.m) + '</td>'
                  +    '<td class="pl-num">' + num(r.t) + '</td>'
                  +    '<td class="pl-num pl-total">' + num(r.total) + '</td>'
                  +    '<td class="pl-acciones">' + accionesFila(r, d.dias_edicion) + '</td>'
                  +  '</tr>';
        });

        detalle.innerHTML =
              '<div class="pl-detalle__cab">'
            +   '<span class="pl-chip"><i class="fa fa-list"></i> ' + num(d.total_registros, 0) + ' ordeños</span>'
            +   '<span>' + num(d.dias, 0) + ' días con producción</span>'
            +   '<span>Total del animal: <strong>' + num(d.total_kg) + ' Kg</strong></span>'
            +   '<span>Promedio por día: <strong>' + num(d.promedio_dia) + ' Kg</strong></span>'
            + '</div>'
            + '<div style="overflow-x:auto">'
            +   '<table class="pl-tabla">'
            +     '<thead><tr>'
            +       '<th>Fecha</th><th>Kg Mañana</th><th>Kg Tarde</th><th>Kg Total</th><th>Acciones</th>'
            +     '</tr></thead>'
            +     '<tbody>' + filas + '</tbody>'
            +   '</table>'
            + '</div>'
            + miniPag(d);
    }

    function miniPag(d) {
        var desde = d.total_registros ? ((d.pagina - 1) * d.tamano + 1) : 0;
        var hasta = Math.min(d.pagina * d.tamano, d.total_registros);

        return '<div class="pl-mini-pag">'
             +   '<button type="button" class="pl-mini-pag__btn" data-detalle-pag="' + (d.pagina - 1) + '"'
             +     (d.pagina <= 1 ? ' disabled' : '') + '>‹ Anterior</button>'
             +   '<span>' + desde + '–' + hasta + ' de ' + num(d.total_registros, 0) + ' registros</span>'
             +   '<button type="button" class="pl-mini-pag__btn" data-detalle-pag="' + (d.pagina + 1) + '"'
             +     (d.pagina >= d.total_paginas ? ' disabled' : '') + '>Siguiente ›</button>'
             + '</div>';
    }

    /* ---------------------------- Edición en línea ---------------------------- */

    function iniciarEdicion(articulo, fila) {
        var id    = fila.getAttribute('data-id');
        var fecha = fila.getAttribute('data-fecha');
        var m     = fila.getAttribute('data-m');
        var t     = fila.getAttribute('data-t');
        var hoy   = hoyISO();
        var maxFecha = (fecha && fecha > hoy) ? fecha : hoy;

        fila.classList.add('pl-editando');
        fila.innerHTML =
              '<td><input type="date" class="pl-inp" data-campo="fecha" value="' + esc(fecha) + '" max="' + esc(maxFecha) + '"></td>'
            + '<td><input type="number" class="pl-inp" data-campo="m" min="0" step="0.01" inputmode="decimal" value="' + esc(m) + '"></td>'
            + '<td><input type="number" class="pl-inp" data-campo="t" min="0" step="0.01" inputmode="decimal" value="' + esc(t) + '"></td>'
            + '<td class="pl-num pl-total" data-total>' + num(Number(m) + Number(t)) + '</td>'
            + '<td class="pl-acciones">'
            +   '<button type="button" class="crud-btn crud-btn--success pl-btn-ico" data-guardar="' + id + '"'
            +     ' title="Guardar" aria-label="Guardar"><i class="fa fa-check"></i></button>'
            +   '<button type="button" class="crud-btn crud-btn--ghost pl-btn-ico" data-cancelar'
            +     ' title="Cancelar" aria-label="Cancelar"><i class="fa fa-times"></i></button>'
            + '</td>';

        var campoM = fila.querySelector('[data-campo="m"]');
        if (campoM) { campoM.focus(); campoM.select(); }
    }

    function recalcularTotal(fila) {
        var m = parseFloat(fila.querySelector('[data-campo="m"]').value) || 0;
        var t = parseFloat(fila.querySelector('[data-campo="t"]').value) || 0;
        var celda = fila.querySelector('[data-total]');
        if (celda) celda.textContent = num(m + t);
    }

    function guardarEdicion(articulo, fila) {
        var codigo = articulo.getAttribute('data-codigo');
        var id     = fila.querySelector('[data-guardar]').getAttribute('data-guardar');
        var fecha  = fila.querySelector('[data-campo="fecha"]').value;
        var m      = fila.querySelector('[data-campo="m"]').value;
        var t      = fila.querySelector('[data-campo="t"]').value;

        if (!fecha) {
            alerta('Datos incompletos', 'Indique la fecha del ordeño.', 'orange');
            return;
        }
        if (m === '' || isNaN(parseFloat(m))) {
            alerta('Datos incompletos', 'Indique los Kg de la mañana.', 'orange');
            return;
        }
        if (t === '' || isNaN(parseFloat(t))) t = 0;

        $.post(URL_CTRL, {
                action: 'edit',
                id_prodleche: id,
                cod_animal_h: codigo,
                fecha_prodleche: fecha,
                kg_leche_m: m,
                kg_leche_t: t
            })
            .done(function (resp) {
                var r = parseResp(resp);

                if (r && r.resultado === 'ok') {
                    recargar(codigo, paginaActualDetalle(codigo), 'Registro actualizado correctamente.');
                } else {
                    alerta('No se pudo guardar',
                           (r && r.mensaje) ? r.mensaje : 'No hubo respuesta válida del servidor.',
                           'red');
                }
            })
            .fail(function () {
                alerta('No se pudo guardar', 'No hubo respuesta del servidor.', 'red');
            });
    }

    function paginaActualDetalle(codigo) {
        var art = $lista.querySelector('.pl-animal[data-codigo="' + codigo + '"]');
        var det = art ? art.querySelector('.pl-animal__detalle') : null;
        var p   = det ? parseInt(det.getAttribute('data-pagina'), 10) : 1;

        return (p && p > 0) ? p : 1;
    }

    /* ---------------------------- Eliminar ---------------------------- */

    function eliminar(articulo, id) {
        var codigo = articulo.getAttribute('data-codigo');

        $.confirm({
            title: 'Eliminar registro',
            content: '¿Desea eliminar este registro de producción de leche?',
            icon: 'fa fa-trash-o',
            type: 'red',
            confirmButton: 'Sí, eliminar',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-danger',
            confirm: function () {
                $.post(URL_CTRL, { action: 'delete', record: id })
                    .done(function (resp) {
                        resp = String(resp || '');

                        if (resp.indexOf('alert-success') !== -1) {
                            recargar(codigo, paginaActualDetalle(codigo), 'Registro eliminado correctamente.');
                        } else {
                            alerta('No se pudo eliminar', resp || 'Ha ocurrido un error.', 'red');
                        }
                    })
                    .fail(function () {
                        alerta('No se pudo eliminar', 'No hubo respuesta del servidor.', 'red');
                    });
            }
        });
    }

    /* Recarga la lista (misma página y búsqueda) y reabre el animal. */
    function recargar(codigo, paginaDetalle, mensaje) {
        cargarLista().done(function () {
            var art = articuloPorCodigo(codigo);

            if (art) {
                abrirAnimal(art, paginaDetalle || 1);
                if (mensaje) toast(mensaje);
            } else if (mensaje) {
                toast(mensaje + ' El animal se ubicó en otra página del listado.');
            }
        });
    }

    /* ---------------------------- Eventos ---------------------------- */

    $lista.addEventListener('click', function (evento) {
        var articulo = evento.target.closest('.pl-animal');
        if (!articulo) return;

        /* Enlaces (Resumen PDF) navegan normalmente */
        if (evento.target.closest('a')) return;

        var eliminarBtn = evento.target.closest('[data-eliminar]');
        if (eliminarBtn) {
            eliminar(articulo, eliminarBtn.getAttribute('data-eliminar'));
            return;
        }

        var editarBtn = evento.target.closest('[data-editar]');
        if (editarBtn) {
            iniciarEdicion(articulo, editarBtn.closest('tr'));
            return;
        }

        var guardarBtn = evento.target.closest('[data-guardar]');
        if (guardarBtn) {
            guardarEdicion(articulo, guardarBtn.closest('tr'));
            return;
        }

        if (evento.target.closest('[data-cancelar]')) {
            cargarDetalle(articulo, paginaActualDetalle(articulo.getAttribute('data-codigo')));
            return;
        }

        var pagBtn = evento.target.closest('[data-detalle-pag]');
        if (pagBtn) {
            cargarDetalle(articulo, parseInt(pagBtn.getAttribute('data-detalle-pag'), 10));
            return;
        }

        /* Clic en la cabecera: abrir / cerrar */
        if (evento.target.closest('.pl-animal__cab')) {
            if (articulo.classList.contains('is-open')) {
                cerrarAnimal(articulo);
            } else {
                abrirAnimal(articulo, 1);
            }
        }
    });

    $lista.addEventListener('input', function (evento) {
        var fila = evento.target.closest('tr.pl-editando');
        if (!fila) return;
        if (evento.target.getAttribute('data-campo') === 'm'
            || evento.target.getAttribute('data-campo') === 't') {
            recalcularTotal(fila);
        }
    });

    $lista.addEventListener('keydown', function (evento) {
        var filaEdit = evento.target.closest('tr.pl-editando');

        if (filaEdit) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                guardarEdicion(filaEdit.closest('.pl-animal'), filaEdit);
            }
            if (evento.key === 'Escape') {
                evento.preventDefault();
                cargarDetalle(filaEdit.closest('.pl-animal'), paginaActualDetalle(filaEdit.closest('.pl-animal').getAttribute('data-codigo')));
            }
            return;
        }

        if (evento.key !== 'Enter' && evento.key !== ' ') return;

        var cab = evento.target.closest('.pl-animal__cab');
        if (!cab) return;

        evento.preventDefault();
        var art = cab.closest('.pl-animal');
        if (art.classList.contains('is-open')) {
            cerrarAnimal(art);
        } else {
            abrirAnimal(art, 1);
        }
    });

    document.getElementById('pl-contraer').addEventListener('click', function () {
        var abiertos = $lista.querySelectorAll('.pl-animal.is-open');
        for (var i = 0; i < abiertos.length; i++) {
            cerrarAnimal(abiertos[i]);
        }
    });

    /* Paginador de animales */
    $pag.addEventListener('click', function (evento) {
        var numBtn = evento.target.closest('[data-pag-num]');
        if (numBtn) {
            irAPagina(parseInt(numBtn.getAttribute('data-pag-num'), 10));
            return;
        }

        var btn = evento.target.closest('[data-pag]');
        if (!btn || btn.disabled) return;

        var accion = btn.getAttribute('data-pag');

        if (accion === '1')    irAPagina(1);
        if (accion === 'prev') irAPagina(estado.pagina - 1);
        if (accion === 'next') irAPagina(estado.pagina + 1);
        if (accion === 'last') irAPagina(estado.totalPaginas);
    });

    $tamano.addEventListener('change', function () {
        var valor = parseInt($tamano.value, 10);
        if (!valor) return;

        estado.tamano  = valor;
        estado.pagina  = 1;
        cargarLista();
    });

    /* Búsqueda (con retardo) */
    var temporizador = null;
    $buscar.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            estado.q      = $buscar.value.trim();
            estado.pagina = 1;
            cargarLista();
        }, 300);
    });

    /* ---------------------------- Inicio ---------------------------- */

    cargarLista();

})();
</script>

<?php include_once("../layouts/pie.php"); ?>
