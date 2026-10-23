<?php
/**
 * _crud_index.php — Plantilla reusable para páginas index.php con DataTables.
 *
 * IMPORTANTE: NO incluir la columna 'id' en $crud['columns'].
 * El id viaja en el atributo data-id del <tr>.
 *
 * Novedades:
 *  - 'buttonLabels' => []  para renombrar botones
 *  - Cada columna puede incluir 'render' => 'helper' (nombre en CrudIndex.renders)
 */

$crud = array_merge([
    'title'    => 'Listado',
    'subtitle' => '',
    'icon'     => 'fa-list',
    'theme'    => 'primary',

    'ajaxUrl'    => '',
    'controller' => '',
    'deleteRecordField' => 'record',
    'deleteAction' => 'delete',

    'routes' => [
        'add' => null, 'edit' => null, 'view' => null,
        'import' => null, 'download' => null, 'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => [],

    'columns'    => [],
    'columnDefs' => [],
    'rowCallbackJs' => '',
    'searchPlaceholder' => 'Buscar…',
    'scrollY' => null,
    'showCount' => true,
    'autoRedirectMs' => 300000,

    'idField' => 'id',
    'dblClickEdit'   => false,
    'beforeEditJs'   => '',
    'beforeDeleteJs' => '',
    'deleteExtraDataJs' => '',
    'customScript'   => '',

    'extraButtonsHtml' => '',
], $crud);

/* -------- Auto-eliminar la columna del id -------- */
$filtered = [];
foreach ($crud['columns'] as $col) {
    if (isset($col['data']) && $col['data'] === $crud['idField']) continue;
    $filtered[] = $col;
}
$crud['columns'] = $filtered;

/* -------- Definición de botones -------- */
$buttonDefs = [
    'add'      => ['id' => 'add',      'class' => 'crud-btn--success', 'icon' => 'fa-plus',           'label' => 'Agregar',        'title' => ''],
    'edit'     => ['id' => 'edit',     'class' => 'crud-btn--primary', 'icon' => 'fa-pencil',         'label' => 'Editar',         'title' => ''],
    'view'     => ['id' => 'ver',      'class' => 'crud-btn--info',    'icon' => 'fa-book',           'label' => 'Ver ficha',      'title' => ''],
    'import'   => ['id' => 'importar', 'class' => 'crud-btn--excel',   'icon' => 'fa-file-excel-o',   'label' => 'Importar Excel', 'title' => 'Cargar varios registros desde Excel'],
    'download' => ['id' => 'data',     'class' => 'crud-btn--warning', 'icon' => 'fa-cloud-download', 'label' => 'Descargar',      'title' => ''],
    'delete'   => ['id' => 'delete',   'class' => 'crud-btn--danger',  'icon' => 'fa-trash-o',        'label' => 'Eliminar',       'title' => ''],
];

foreach ($crud['buttonLabels'] as $k => $lbl) {
    if (isset($buttonDefs[$k])) $buttonDefs[$k]['label'] = $lbl;
}

$crudRol    = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
$crudAcceso = isset($crud['acceso']) ? (int)$crud['acceso'] : 1;

/* -------- Construir la definición de columnas para el JS -------- */
$columnsJs = "[\n";
$n = count($crud['columns']);
foreach ($crud['columns'] as $i => $col) {
    $columnsJs .= "    { data: '" . addslashes($col['data']) . "'";
    if (!empty($col['render'])) {
        $columnsJs .= ", render: CrudIndex.renders." . preg_replace('/[^A-Za-z0-9_]/', '', $col['render']);
    }
    // Columna oculta (p. ej. un valor auxiliar para ordenar).
    if (array_key_exists('visible', $col)) {
        $columnsJs .= ", visible: " . ($col['visible'] ? 'true' : 'false');
    }
    $columnsJs .= " }";
    if ($i < $n - 1) $columnsJs .= ",";
    $columnsJs .= "\n";
}
$columnsJs .= "  ]";

$jsRoutes = [];
foreach (['add','edit','view','import','download'] as $r) {
    $jsRoutes[$r] = $crud['routes'][$r] ?: null;
}

$scrollYJs = is_null($crud['scrollY']) ? 'null' : "'" . addslashes($crud['scrollY']) . "'";
?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-index.css?v=3" type="text/css" />

<div class="crud-page" data-theme="<?php echo htmlspecialchars($crud['theme']); ?>">

    <div class="message"></div>

    <div class="crud-card">

        <header class="crud-header">
            <span class="crud-header__icon"><i class="fa <?php echo htmlspecialchars($crud['icon']); ?>"></i></span>
            <div class="crud-header__text">
                <h3 class="crud-header__title"><?php echo htmlspecialchars($crud['title']); ?></h3>
                <?php if ($crud['subtitle']): ?>
                    <p class="crud-header__subtitle"><?php echo htmlspecialchars($crud['subtitle']); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($crud['showCount']): ?>
                <span class="crud-header__badge" id="crud-count">—</span>
            <?php endif; ?>
        </header>

        <div class="crud-toolbar">
            <div class="crud-toolbar__actions">
                <?php foreach ($crud['showButtons'] as $btnKey): ?>
                    <?php if (isset($buttonDefs[$btnKey])): $b = $buttonDefs[$btnKey]; ?>
                        <button id="<?php echo $b['id']; ?>" type="button"
                                class="crud-btn <?php echo $b['class']; ?>"
                                <?php if ($b['title']): ?>title="<?php echo htmlspecialchars($b['title']); ?>"<?php endif; ?>>
                            <i class="fa <?php echo $b['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($b['label']); ?></span>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php echo $crud['extraButtonsHtml']; ?>
            </div>
        </div>

        <div class="crud-table-wrap">
            <table id="tabla" class="table crud-table" width="100%">
                <thead>
                    <tr>
                        <?php foreach ($crud['columns'] as $col): ?>
                            <th><?php echo htmlspecialchars($col['title']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>

<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script src="../../js/crud-index.js?v=5"></script>
<script>
(function () {
    var crud = CrudIndex.init({
        tableSelector: '#tabla',
        ajaxUrl:       '<?php echo $crud['ajaxUrl']; ?>',
        controller:    '<?php echo $crud['controller']; ?>',
        deleteRecordField: '<?php echo addslashes($crud['deleteRecordField']); ?>',
        deleteAction:  '<?php echo addslashes($crud['deleteAction']); ?>',

        rol:    <?php echo $crudRol; ?>,
        acceso: <?php echo $crudAcceso; ?>,

        routes: <?php echo json_encode($jsRoutes, JSON_UNESCAPED_SLASHES); ?>,

        autoRedirect: {
            enabled: <?php echo $crud['autoRedirectMs'] > 0 ? 'true' : 'false'; ?>,
            ms: <?php echo (int)$crud['autoRedirectMs']; ?>,
            url: '<?php echo $crud['routes']['home']; ?>'
        },

        columns: <?php echo $columnsJs; ?>,

        columnDefs: <?php echo json_encode($crud['columnDefs'], JSON_UNESCAPED_UNICODE); ?>,

        searchPlaceholder: '<?php echo addslashes($crud['searchPlaceholder']); ?>',
        scrollY: <?php echo $scrollYJs; ?>,
        idField: '<?php echo addslashes($crud['idField']); ?>',
        dblClickEdit: <?php echo $crud['dblClickEdit'] ? 'true' : 'false'; ?>,

        <?php if ($crud['beforeEditJs']): ?>
        beforeEdit: function (id, rowData) {
            <?php echo $crud['beforeEditJs']; ?>
        },
        <?php endif; ?>

        <?php if ($crud['beforeDeleteJs']): ?>
        beforeDelete: function (id, rowData) {
            <?php echo $crud['beforeDeleteJs']; ?>
        },
        <?php endif; ?>

        <?php if (!empty($crud['deleteExtraDataJs'])): ?>
        deleteExtraData: function (id, rowData) {
            <?php echo $crud['deleteExtraDataJs']; ?>
        },
        <?php endif; ?>

        rowCallback: function (nRow, mData, iDisplayIndex, $) {
            <?php echo $crud['rowCallbackJs']; ?>
            return nRow;
        }
    });

    <?php echo $crud['customScript']; ?>
})();
</script>