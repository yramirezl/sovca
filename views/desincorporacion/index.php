<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Desincorporación de Animales',
    'subtitle' => 'Historial de animales desincorporados del rebaño',
    'icon'     => 'fa-sign-out',
    'theme'    => 'danger',

    'ajaxUrl'    => '../../data_json/data_mdesincorporacion',
    'controller' => '../../controllers/mdesincorporacion_controller',
    'deleteAction' => 'delete',
    'deleteRecordField' => 'record',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'view' => 'frm_ficha',
        'home' => '../inicio/'
    ],

    'showButtons' => ['add', 'edit', 'view', 'delete'],
    'buttonLabels' => ['view' => 'Mostrar Ficha'],

    'columns' => [
        ['data' => 'codigo',          'title' => 'Código del Animal'],
        ['data' => 'nro_arete',       'title' => 'Nro Arete / Microchip'],
        ['data' => 'nombre',          'title' => 'Nombre del Animal'],
        ['data' => 'estatus_animal',  'title' => 'Estatus del Animal'],
    ],

    'columnDefs' => [
        ['width' => '250px', 'targets' => [0]],
        ['width' => '250px', 'targets' => [1]],
        ['width' => '350px', 'targets' => [2]],
        ['width' => '250px', 'targets' => [3]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar desincorporación…',
    'scrollY' => '240px',

    'idField' => 'id_desincorporacion',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
    ",

    'autoRedirectMs' => 120000,

    /* Permisos del módulo (mismo criterio que mdesincorporacion_controller.php):
       - PRODUCTOR: registrar, editar y eliminar.
       - ENCARGADO: registrar y editar; nunca eliminar.
       - ADMINISTRADOR / SUPER USUARIO en modo soporte: SOLO editar. La
         anulación es excepcional y exige motivo (se guarda en la observación). */
    'beforeDeleteJs' => "
        var rolAnula = " . (int)$_SESSION['rolx'] . ";
        var soporteAnula = " . (!empty($_SESSION['supervisor_mode']) ? 'true' : 'false') . ";

        if (!(soporteAnula && (rolAnula == 1 || rolAnula == 4))) { return true; }

        motivoAnulacion = '';
        var espera = $.Deferred();

        $.confirm({
            title: 'Anulación en modo soporte',
            content: '<p>La anulación de desincorporaciones corresponde al productor. Como administrador en modo soporte puedes hacerlo de forma <b>excepcional</b>, indicando el motivo; quedará guardado en la observación del registro.</p>'
                   + '<textarea id=\"bovs-motivo-anulacion\" class=\"form-control\" rows=\"3\" maxlength=\"150\" placeholder=\"Motivo de la anulación (obligatorio)\"></textarea>',
            confirmButton: 'Anular',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-danger',
            cancelButtonClass: 'btn-default',
            confirm: function () {
                var motivo = $.trim($('#bovs-motivo-anulacion').val());
                if (motivo === '') {
                    espera.resolve(false);
                    $.alert({ title: 'Motivo obligatorio', content: 'Escribe el motivo para anular en modo soporte.' });
                    return;
                }
                motivoAnulacion = motivo;
                espera.resolve(true);
            },
            cancel: function () {
                espera.resolve(false);
            }
        });

        return espera.promise();
    ",

    'deleteExtraDataJs' => "return { motivo_anulacion: (typeof motivoAnulacion === 'string' ? motivoAnulacion : '') };",

    'customScript' => "
        // Compartida con beforeDelete/deleteExtraData (mismo ámbito).
        var motivoAnulacion = '';

        var rolx = " . (int)$_SESSION['rolx'] . ";
        var enSoporte = " . (!empty($_SESSION['supervisor_mode']) ? 'true' : 'false') . ";
        var esProductor = (rolx == 2);
        var esEncargado = (rolx == 3);
        var adminSoporte = ((rolx == 1 || rolx == 4) && enSoporte);
        var puedeRegistrar = esProductor || esEncargado;
        var puedeEditar = esProductor || esEncargado || adminSoporte;
        var puedeEliminar = esProductor || adminSoporte;

        if (!puedeRegistrar) { \$('#add').hide(); }
        if (!puedeEditar) { \$('#edit').hide(); }
        if (!puedeEliminar) { \$('#delete').hide(); }
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>