<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Razas de Animales',
    'subtitle' => 'Catálogo de razas puras y cruzadas',
    'icon'     => 'fa-tags',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mrazas',
    'controller' => '../../controllers/mrazas_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'condicion', 'title' => 'Tipo de Raza'],
        ['data' => 'nombre',    'title' => 'Nombre de la Raza'],
        ['data' => 'id',        'title' => 'id'],
    ],

    'columnDefs' => [
        ['width' => '120px', 'targets' => [0]],
    ],

    'order' => [[1, 'asc']],

    'rowCallbackJs' => "
        $('td:eq(1)', nRow).css('font-weight', 'bold');
    ",

    'searchPlaceholder' => 'Buscar raza…',

    /* No editar razas tipo PURA si no es rol 1/4 */
    'beforeEditJs' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        var condicion = rowData ? (rowData.condicion || '') : '';
        if ((rolx != 1) && (rolx != 4) && condicion === 'PURA') {
            crud.dialogInfo('La Raza Seleccionada No Puede Ser Modificada');
            return false;
        }
        return true;
    ",

    /* No eliminar razas tipo PURA si no es rol 1 */
    'beforeDeleteJs' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        var condicion = rowData ? (rowData.condicion || '') : '';
        if ((rolx != 1) && condicion === 'PURA') {
            crud.dialogInfo('La Raza Seleccionada No Puede Ser Eliminada');
            return false;
        }
        return true;
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>