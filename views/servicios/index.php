<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<style>
    /* ============================================================
       BADGES DESTACADOS DEL LISTADO
       Relieve = degradado + brillo superior + sombra interior
       inferior + borde inferior marcado + halo.
       ============================================================ */
    .bovs-badge-destacado {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        line-height: 1.15;
        white-space: nowrap;
    }

    /* Estatus Animal — "Disponible para Servicio": verde profundo con
       texto blanco y relieve marcado. */
    .bovs-badge-destacado--disponible {
        background: linear-gradient(180deg, #22c55e 0%, #16a34a 48%, #0d5c33 100%);
        color: #ffffff !important;
        border: 1px solid #0b4a29;
        text-shadow: 0 1px 2px rgba(0, 0, 0, .35);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .35),
            inset 0 -2px 5px rgba(0, 0, 0, .28),
            0 2px 0 #083d20,
            0 4px 12px rgba(9, 90, 50, .40);
    }

    /* Estado Fisiológico — "Gestante [ Pario ]": tono suave (verde
       claro) con texto verde oscuro para que siempre resalte. */
    .bovs-badge-destacado--fisio {
        background: linear-gradient(180deg, #f2fdf5 0%, #dcfce7 100%);
        color: #0f5132 !important;
        border: 1px solid #6ee7b7;
        text-shadow: none;
        box-shadow:
            inset 0 1px 0 #ffffff,
            inset 0 -1px 2px rgba(20, 108, 67, .10),
            0 1px 0 #bbf7d0,
            0 3px 8px rgba(22, 163, 74, .18);
    }

    /* ============================================================
       VISTA AGRUPADA POR HEMBRA (mismo patrón que Producción de Leche)
       ============================================================ */
    .sv-agrupada { padding: 2px 18px 10px; }
    .sv-agrupada[hidden] { display: none !important; }

    .sv-barra {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding: 4px 0 10px;
        border-bottom: 1px solid #e5e9ef;
        margin-bottom: 8px;
    }
    .sv-buscador {
        position: relative;
        /* Compacto: no necesita ocupar todo el ancho disponible. */
        flex: 0 1 260px;
        max-width: 280px;
        min-width: 170px;
    }
    .sv-buscador i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
    }
    .sv-buscador input {
        width: 100%;
        height: 31px;
        padding: 4px 10px 4px 28px;
        border: 1px solid #d7dee6;
        border-radius: 7px;
        font-size: 12.5px;
        background: #ffffff;
        color: #0f172a;
    }
    .sv-buscador input:focus {
        outline: none;
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, .15);
    }
    .sv-resumen {
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        margin-left: 4px;
        white-space: nowrap;
    }
    /* El único elemento que se va al extremo derecho es el botón. */
    #sv-todo { margin-left: auto; }

    .sv-lista {
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-height: 62vh;
        overflow-y: auto;
        padding-right: 2px;
        transition: opacity .15s ease;
    }
    .sv-lista.is-cargando { opacity: .5; pointer-events: none; }

    .sv-animal {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
        /* Dentro de un contenedor flex con scroll, los bloques no deben
           encogerse: sin esto se aplastan hasta quedar ilegibles. */
        flex: 0 0 auto;
    }
    .sv-animal.is-open {
        border-color: #bbf7d0;
        box-shadow: 0 2px 8px rgba(15, 81, 50, .07);
    }

    .sv-animal__cab {
        /* Cuadrícula de columnas fijas: el ancho de cada columna NO depende del
           contenido. Así un nombre largo (p. ej. "MADRASTRA 5756") no corre las
           columnas de estado ni desalinea las filas entre sí. */
        display: grid;
        grid-template-columns: 14px minmax(0, 24%) minmax(0, 1fr);
        align-items: start;
        column-gap: 8px;
        padding: 6px 10px;
        cursor: pointer;
        background: #ffffff;
        transition: background-color .15s ease;
    }
    .sv-animal__cab:hover { background: #f8fafc; }
    .sv-animal__cab:focus-visible { box-shadow: inset 0 0 0 2px rgba(22, 163, 74, .35); }

    .sv-animal__chevron {
        color: #94a3b8;
        font-size: 12px;
        width: 12px;
        margin-top: 13px;
        transition: transform .18s ease, color .18s ease;
    }
    .sv-animal.is-open .sv-animal__chevron { transform: rotate(90deg); color: #16a34a; }

    /* Datos del animal: ancho fijo por columna de la cuadrícula y texto
       recortado con puntos suspensivos (el valor completo queda en el title). */
    .sv-animal__id {
        min-width: 0;
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    .sv-animal__linea {
        display: flex;
        align-items: baseline;
        gap: 7px;
        min-width: 0;
        overflow: hidden;
    }
    .sv-cod {
        font-size: 12.8px;
        color: #0f172a;
        white-space: nowrap;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sv-animal__nombre {
        font-size: 11.5px;
        color: #475569;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sv-animal__meta {
        font-size: 10.5px;
        color: #94a3b8;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sv-animal__datos {
        /* 6 columnas iguales: cada valor cae siempre bajo su título, sin
           importar cuánto mida el texto de al lado. */
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        align-items: start;
        gap: 4px 10px;
        min-width: 0;
        padding-left: 10px;
    }
    .sv-dato { display: flex; flex-direction: column; min-width: 0; }
    .sv-dato__label {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #94a3b8;
        line-height: 1.5;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sv-dato__valor {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sv-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .sv-chip--abierto { background: #dcfce7; color: #0f5132; border: 1px solid #86efac; }
    .sv-chip--cerrado { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .sv-chip--neutro  { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }

    .sv-animal__detalle { border-top: 1px solid #eef2f7; background: #fcfdff; }
    .sv-animal__detalle[hidden] { display: none !important; }

    .sv-tabla { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .sv-tabla th {
        text-align: left;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
        background: #f8fafc;
        padding: 7px 10px;
        border-bottom: 1px solid #eef2f7;
        white-space: nowrap;
    }
    .sv-tabla td {
        padding: 7px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }
    .sv-tabla tbody tr:last-child td { border-bottom: 0; }
    .sv-tabla tbody tr:hover { background: #f0fdf4; }
    .sv-tabla td.sv-num { text-align: center; font-variant-numeric: tabular-nums; }
    .sv-tabla td.sv-acc { text-align: right; white-space: nowrap; }
    /* El tema global pinta los enlaces en verde con más especificidad que
       .crud-btn--primary; aquí se fuerza el texto blanco del botón. */
    .sv-tabla a.crud-btn { color: #ffffff !important; text-decoration: none !important; }
    .sv-btn-mini {
        padding: 3px 9px !important;
        font-size: 11.5px !important;
        white-space: nowrap;
        flex: 0 0 auto;
    }
    .sv-btn-mini i { font-size: 11px; }
    .sv-macho { color: #475569; }

    /* Fila del servicio vigente (la única editable) y marca de los históricos */
    .sv-tabla tbody tr.sv-fila-vigente { background: #f6fdf9; }
    .sv-tabla tbody tr.sv-fila-vigente:hover { background: #eefaf2; }
    .sv-ref {
        display: inline-block;
        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #b3c0ce;
        white-space: nowrap;
        cursor: help;
    }
    /* Servicio vigente pero bloqueado (ciclo cerrado): no se puede editar */
    .sv-ref--bloqueado { color: #b08968; }
    .sv-ref i { font-size: 9px; margin-right: 3px; }

    /* Valores de registros históricos: se atenúan para no competir con el
       estatus reproductivo vigente del animal. */
    .sv-muted { color: #94a3b8; font-weight: 600; }

    .sv-vacio,
    .sv-cargando {
        padding: 28px 16px;
        text-align: center;
        font-size: 13px;
        color: #64748b;
    }
    .sv-vacio i,
    .sv-cargando i { margin-right: 6px; }

    @media (max-width: 767px) {
        .sv-agrupada { padding: 2px 10px 10px; }
        .sv-buscador { flex: 1 1 100%; max-width: none; }
        .sv-animal__cab {
            grid-template-columns: 14px minmax(0, 1fr);
            row-gap: 2px;
        }
        .sv-animal__datos {
            grid-column: 2 / -1;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            padding-left: 0;
            padding-top: 4px;
            row-gap: 6px;
        }
        /* En móvil las etiquetas largas pueden ocupar dos líneas; se reserva
           el alto para que los valores queden alineados entre columnas. */
        .sv-dato__label {
            white-space: normal;
            min-height: 27px;
        }
        .sv-tabla thead { display: none; }
        .sv-tabla td {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 0;
        }
        .sv-tabla td::before {
            content: attr(data-l);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .sv-tabla tr {
            display: block;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .sv-tabla td.sv-acc { justify-content: flex-end; }
    }
</style>

<?php
$crud = [
    'title'    => 'Servicios y Gestación',
    'subtitle' => 'Control de servicios de las hembras',
    'icon'     => 'fa-seedling',
    'theme'    => 'danger',

    'ajaxUrl'    => '../../data_json/data_mservicios',
    'controller' => '../../controllers/mservicios_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => ['edit' => 'Editar'],

    'extraButtonsHtml' =>
        '<button id="control" type="button" class="crud-btn crud-btn--primary"><i class="fa fa-cow"></i> <span>Control</span></button>' .
        '<button id="data" type="button" class="crud-btn crud-btn--warning"><i class="fa fa-cloud-download"></i> <span>Descargar Data</span></button>' .
        '<button id="vista_agrupada" type="button" class="crud-btn crud-btn--info"><i class="fa fa-sitemap"></i> <span>Vista agrupada</span></button>',

    'columns' => [
        ['data' => 'codigo_servicio',              'title' => 'ID Servicio'],
        ['data' => 'codigo_rgb_id_animal_hembra',  'title' => 'Código / Hembra'],
        ['data' => 'nro_arete',                    'title' => 'Nro Arete / Microchip'],
        ['data' => 'nombre_h',                     'title' => 'Nombre / Hembra'],
        ['data' => 'f_servicio',                   'title' => 'Fecha del Servicio'],
        ['data' => 'estado_fisiologico',           'title' => 'Estado Fisiológico'],
        // 'condicion' refleja la situación reproductiva vigente de la hembra
        // (Servicio Activo, Disponible para Servicio, Periodo de Descanso…),
        // no el estatus registral del animal (ACTIVO, VENDIDO, MUERTO).
        ['data' => 'condicion',                    'title' => 'Estatus Reproductivo'],
        ['data' => 'estatus_servicio',             'title' => 'Estado del Servicio'],
        ['data' => 'nro_servicio',                 'title' => 'Nro de Servicio'],
        ['data' => 'nombre_m',                     'title' => 'Nombre / MACHO'],
        // Columna oculta: fecha ISO para ordenar por fecha real (desc).
        ['data' => 'f_servicio_orden',             'title' => 'Fecha (orden)', 'visible' => false],
    ],

    'columnDefs' => [
        ['width' => '120px', 'targets' => [0]],
        ['width' => '150px', 'targets' => [1]],
        ['width' => '160px', 'targets' => [2]],
        ['width' => '220px', 'targets' => [3]],
        ['width' => '150px', 'targets' => [4]],
        ['width' => '150px', 'targets' => [5]],
        ['width' => '180px', 'targets' => [6]],
        ['width' => '150px', 'targets' => [7]],
        ['width' => '150px', 'targets' => [8]],
        ['width' => '220px', 'targets' => [9]],
        ['targets' => [10], 'visible' => false, 'bVisible' => false, 'searchable' => false],
    ],

    'order' => [[0, 'desc']],
    'searchPlaceholder' => 'Buscar servicio…',
    'scrollY' => '240px',
    'autoRedirectMs' => 1200000,

    'rowCallbackJs' => "
        if ((mData.estatus_servicio || '') === 'CERRADO') {
            \$('td:eq(7)', nRow).css('color', '#dc2626');
            \$('td:eq(6)', nRow).css('color', '#dc2626');
            \$('td:eq(5)', nRow).css('color', '#dc2626');
        }

        // Gestante [ Pario ]: se muestra como badge verde con relieve
        var estadoFisio = String(mData.estado_fisiologico || '');
        if (/gestante\\s*\\[\\s*par[ií]o\\s*\\]/i.test(estadoFisio)) {
            \$('td:eq(5)', nRow).empty().append(
                \$('<span></span>').addClass('bovs-badge-destacado bovs-badge-destacado--fisio').text(estadoFisio)
            );
        }

        // Disponible para Servicio: badge con relieve (verde azulado)
        var condicion = String(mData.condicion || '');
        if (/disponible\\s+para\\s+servi/i.test(condicion)) {
            var condicionLimpia = condicion
                .replace(/^\\s*\\[\\*\\s*/, '')
                .replace(/\\s*\\*\\]\\s*\$/, '')
                .trim();
            \$('td:eq(6)', nRow).empty().append(
                \$('<span></span>').addClass('bovs-badge-destacado bovs-badge-destacado--disponible').text(condicionLimpia)
            );
        }

        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(3)', nRow).css('font-weight', 'bold');
        \$('td:eq(2)', nRow).css('text-align', 'center');
        \$('td:eq(6)', nRow).css('text-align', 'center');
        \$('td:eq(4)', nRow).css('text-align', 'center');
    ",

    'customScript' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        if (rolx == 3) {
            \$('#delete, #edit, #control').hide();
        }

        // Servicios más recientes primero: se ordena por la columna oculta
        // f_servicio_orden (ISO). Ordenar por la fecha d-m-Y daría un orden
        // alfabético incorrecto.
        try { crud.table.api().order([[10, 'desc']]).draw(false); } catch (e) {}

        // La vista agrupada (js/servicios-agrupado.js) necesita el objeto crud
        // para devolver el listado a su estado normal, y saber si el rol
        // puede editar (el encargado no edita en este módulo).
        window.SV_CRUD = crud;
        window.SV_PUEDE_EDITAR = (rolx != 3);

        \$('#control').on('click', function() {
            var value = crud.getSelectedId();
            if (!value) { crud.dialogInfo('¡Seleccione un registro!'); return; }
            window.location.href = 'frm_control?%\$%=' + window.btoa(value);
        });

        \$('#data').on('click', function() {
            \$.confirm({
                title: '¿Descargar la Data de los Servicios?',
                content: false,
                confirmButton: 'Ok',
                cancelButton: 'Cerrar',
                confirmButtonClass: 'btn-success',
                cancelButtonClass: 'btn-danger',
                confirm: function() {
                    window.open('../../Reportes/reportes/data/servicios.php', '',
                        'titlebars=0,toolbar=0,scrollbars=0,location=1,statusbar=0,menubar=0,resizable=0,width=320,height=240,top=250,left=500'
                    );
                },
                autoClose: 'cancel|5000'
            });
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<!-- Vista agrupada por hembra (mismo patrón que Producción de Leche) -->
<script src="../../js/servicios-agrupado.js?v=8"></script>

<?php include_once("../layouts/pie.php"); ?>