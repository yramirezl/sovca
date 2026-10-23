<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />
<link rel="stylesheet" href="../../css/servicio-selector.css?v=15" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<style>
/* ============================================================
   LABEL CON CHECKBOXES AL LADO
   ============================================================ */
.crud-label-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap; margin-bottom: 4px;
}
.crud-label-row > label {
    margin: 0 !important; font-size: inherit; text-transform: uppercase;
    font-weight: 700; color: #475569; letter-spacing: .04em;
    flex: 1 1 auto; min-width: 0;
}
.crud-label-row .crud-check-group {
    display: inline-flex; align-items: center; gap: 14px; flex: 0 0 auto;
}
.crud-label-row .crud-check-group label {
    margin: 0 !important; font-size: 13.5px !important;
    font-weight: 600 !important; text-transform: none !important;
    letter-spacing: 0 !important; color: #334155 !important;
    display: inline-flex; align-items: center; gap: 6px;
    cursor: pointer; white-space: nowrap;
}
.crud-label-row .crud-check-group input[type="checkbox"] {
    width: 18px; height: 18px; margin: 0; cursor: pointer;
    accent-color: #16a34a;
}

/* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
@keyframes crud-flash-input {
    0%   { box-shadow: 0 0 0 0 rgba(22,163,74,.75); background-color:#dcfce7; border-color:#16a34a; }
    30%  { box-shadow: 0 0 0 10px rgba(22,163,74,0); background-color:#f0fdf4; border-color:#16a34a; }
    70%  { box-shadow: 0 0 0 0 rgba(22,163,74,.35); background-color:#ffffff; }
    100% { box-shadow: 0 0 0 0 rgba(22,163,74,0); background-color:inherit; }
}
.crud-form .form-control.crud-unlocked,
.crud-form input.crud-unlocked,
.crud-form select.crud-unlocked,
.crud-form textarea.crud-unlocked {
    animation: crud-flash-input 4.8s ease !important;
    border-color: #16a34a !important;
    transition: none !important;
}
@keyframes crud-flash-btn {
    0%   { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,.65); }
    18%  { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(22,163,74,0); }
    45%  { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,.45); }
    70%  { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(22,163,74,0); }
    100% { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,0); }
}
.crud-form button.crud-just-unlocked-btn,
.crud-form .btn.crud-just-unlocked-btn {
    animation: crud-flash-btn 4.8s ease !important;
    outline: 3px solid rgba(22,163,74,.60) !important;
    outline-offset: 3px !important;
    position: relative; z-index: 2;
}
@keyframes crud-flash-check {
    0%   { transform: scale(1);    background-color: rgba(22,163,74,.30); box-shadow: 0 0 0 0 rgba(22,163,74,.55); }
    25%  { transform: scale(1.25); background-color: rgba(22,163,74,.55); box-shadow: 0 0 0 10px rgba(22,163,74,0); }
    60%  { transform: scale(1);    background-color: rgba(22,163,74,.30); box-shadow: 0 0 0 0 rgba(22,163,74,.30); }
    100% { transform: scale(1);    background-color: transparent; box-shadow: 0 0 0 0 rgba(22,163,74,0); }
}
.crud-form input[type="checkbox"].crud-just-unlocked-check {
    animation: crud-flash-check 4.8s ease !important;
    outline: 2px solid #16a34a !important;
    outline-offset: 2px !important;
    border-radius: 3px;
}
@keyframes crud-flash-check-label {
    0%   { background-color: rgba(22,163,74,.30); color: #15803d; transform: translateX(0); }
    30%  { background-color: rgba(22,163,74,.18); color: #15803d; transform: translateX(-3px); }
    60%  { background-color: rgba(22,163,74,.08); color: #15803d; transform: translateX(0); }
    100% { background-color: transparent; color: inherit; }
}
.crud-form label.crud-just-unlocked-check-label {
    animation: crud-flash-check-label 4.8s ease !important;
    border-radius: 6px !important; padding: 3px 8px !important;
    font-weight: 800 !important; transition: none !important;
}

/* ============================================================
   GRID ESPECÍFICO: Formulario (60%) + Detalles (40%)
   ============================================================ */
@media (min-width: 993px) {
    #grid_control { grid-template-columns: 1.2fr 0.8fr; }
}

/* ============================================================
   TABLA DE DETALLES embebida en card
   ============================================================ */
.crud-form .crud-detalle-tabla {
    width: 100%; border-collapse: collapse; font-size: 14px;
}
.crud-form .crud-detalle-tabla thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    font-size: 12px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 12px; text-align: left;
    border-bottom: 1px solid var(--cf-border);
    white-space: nowrap;
}
.crud-form .crud-detalle-tabla tbody td {
    padding: 10px 12px; border-bottom: 1px solid #eef1f4;
    color: var(--cf-text); vertical-align: middle;
}
.crud-form .crud-detalle-tabla tbody tr { cursor: pointer; }
.crud-form .crud-detalle-tabla tbody tr:hover td { background: #f0fdf4; }
.crud-form .crud-detalle-tabla tbody tr.selected td {
    background: #dbeafe !important; color: #0c2d6b !important;
    font-weight: 600 !important;
}
.crud-form .crud-detalle-acciones {
    display: flex; gap: 8px; padding-top: 12px;
    border-top: 1px solid var(--cf-border); margin-top: 4px;
}

/* Botones mini en la card de detalles */
.crud-form .crud-detalle-acciones .btn {
    min-width: 44px; min-height: 40px;
    border-radius: 8px; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    border: none; cursor: pointer;
}
.crud-form .crud-detalle-acciones .btn-success {
    background: linear-gradient(135deg,#16a34a 0%,#15803d 100%);
    color: #fff; box-shadow: 0 3px 10px rgba(22,163,74,.28);
}
.crud-form .crud-detalle-acciones .btn-success:hover:not(:disabled) {
    background: linear-gradient(135deg,#15803d 0%,#14532d 100%);
    transform: translateY(-1px);
}
.crud-form .crud-detalle-acciones .btn-danger {
    background: linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);
    color: #fff; box-shadow: 0 3px 10px rgba(220,38,38,.28);
}
.crud-form .crud-detalle-acciones .btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg,#b91c1c 0%,#991b1b 100%);
    transform: translateY(-1px);
}
.crud-form .crud-detalle-acciones .btn:disabled { opacity: .45; cursor: not-allowed; }
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa-solid fa-syringe"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Control Sanitario</h3>
            <p class="crud-form__subtitle">Registre un nuevo control sanitario y los productos aplicados</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid" id="grid_control">

            <!-- ========== CARD 1: Datos del control ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-solid fa-clipboard-list"></i>
                    <h3>Datos del Control</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label>Seleccione el Animal
                            <span class="sel-animal-hint">(sólo animales activos del fundo)</span>
                        </label>
                        <div class="sel-animal" id="sel_animal">
                            <div class="sel-animal__bar">
                                <i class="fa fa-search"></i>
                                <input type="text" class="sel-animal__filtro" data-sel="filtro"
                                       placeholder="Buscar por nombre, código BOVS o N° de arete…"
                                       autocomplete="off">
                                <button type="button" class="sel-animal__recargar" data-sel="recargar" title="Actualizar el listado">
                                    <i class="fa fa-refresh"></i>
                                </button>
                            </div>
                            <div class="sel-animal__lista" data-sel="lista"></div>
                            <div class="sel-animal__pie">
                                <span data-sel="contador"></span>
                                <span data-sel="resumen"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el flujo interno -->
                    <div class="sel-animal-oculto">
                        <div class="crud-label-row">
                            <label for="cod_animal">N°. Arete-Chip / Código</label>
                            <div class="crud-check-group">
                                <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                                <label><input type="checkbox" id="b_codigo"> Código</label>
                            </div>
                        </div>
                        <input class="form-control bbb" id="cod_animal" type="text"
                               placeholder="Indique el Código o Nro Arete de la Hembra"
                               onpaste="return true"
                               onkeypress="return es_codigo_nro_arete(event);">
                        <div id='ms_cod_animal'><p></p></div>
                        <button id="buscar" type="button" class="btn btn-success btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <div class="crud-label-row">
                                <label for="nombre_animal">Animal seleccionado</label>
                                <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                    <i class="fa fa-fw fa-trash-o"></i> Cambiar
                                </button>
                            </div>
                            <input class="form-control bbb" id="nombre_animal" type="text" tabindex="1"
                                   placeholder="Seleccione una fila del listado" onpaste="return false" readonly="true">
                        </div>
                        <div class="crud-form__field">
                            <label for="fecha_control">Fecha del Control</label>
                            <input class="form-control bbb" id="fecha_control" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                            <div id='ms_fecha_control'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="evento">Evento</label>
                        <input class="form-control bbb" id="evento" type="text"
                               placeholder="Indique la causa para realizar algún control sanitario."
                               onpaste="return false">
                        <div id='ms_evento'><p></p></div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="responsable">Responsable</label>
                            <input class="form-control bbb" id="responsable" type="text"
                                   placeholder="Persona encargada del control"
                                   onpaste="return false">
                            <div id='ms_responsable'><p></p></div>
                        </div>
                        <div class="crud-form__field">
                            <label for="fecha_prox_control">Próximo Control</label>
                            <input class="form-control bbb" id="fecha_prox_control" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                            <div id='ms_fecha_prox_control'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc aaa" id="observaciones" rows="3"
                                  placeholder="Observaciones" onpaste="return true"
                                  tabindex="0" autocomplete="off"></textarea>
                        <div id='ms_observaciones'><p></p></div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Detalles del Producto Aplicado ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-flask"></i>
                    <h3>Detalles del Producto Aplicado</h3>
                </div>
                <div class="crud-form__card-body">

                    <table id="tabla" class="crud-detalle-tabla">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Dosis</th>
                                <th style="width:1px"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <div class="crud-detalle-acciones">
                        <button id="agregar" type="button" class="btn btn-success btn-xs" tabindex="18"
                                title="Agregar producto">
                            <i class="fa fa-fw fa-plus"></i>
                        </button>
                        <button id="quitar" type="button" class="btn btn-danger btn-xs"
                                title="Quitar producto seleccionado">
                            <i class="fa fa-fw fa-trash"></i>
                        </button>
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
        
            <button id="cargar" type="button" class="btn btn-success" tabindex="23">
                <i class="fa fa-fw fa-save"></i> Grabar
            </button>
            <button id="nuevo" type="button" class="btn btn-primary">
                <i class="fa fa-fw fa-plus"></i> Nuevo Control
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

<!-- =================== MODAL: Detalle del Producto =================== -->
<div class="modal fade" id="modal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px; overflow:hidden; border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#16a34a 0%,#15803d 100%); color:#fff; border:none; padding:18px 22px;">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"
                        style="color:#fff; opacity:.9;">&times;</button>
                <h4 class="modal-title" id="myModalLabel1" style="font-weight:800; letter-spacing:.04em; text-transform:uppercase;">
                    <i class="fa fa-flask"></i> Detalle del Producto Aplicado
                </h4>
            </div>

            <div class="modal-body" style="padding:22px 24px;">
                <div class="message1"></div>

                <div class="crud-form__row">
                    <div class="crud-form__field">
                        <label for="categoria">Categoría</label>
                        <select name="categoria" id="categoria" class="form-control bbb" tabindex="5">
                            <option></option>
                        </select>
                    </div>
                    <div class="crud-form__field">
                        <label for="producto">Producto</label>
                        <select name="producto" id="producto" class="form-control bbb" tabindex="5">
                            <option></option>
                        </select>
                    </div>
                </div>

                <div class="crud-form__field" style="margin-top:14px;">
                    <label for="dosis">Dosis</label>
                    <input class="form-control bbb" id="dosis" type="text"
                           placeholder="Indique la Dosis Aplicada." onpaste="return false">
                    <div id='ms_dosis'><p></p></div>
                </div>

                <div class="crud-form__field" style="margin-top:14px;">
                    <label for="detalles_indicaciones">Detalles / Indicaciones</label>
                    <textarea class="form-control ccc aaa" id="detalles_indicaciones" rows="3"
                              placeholder="Detalles o indicaciones del producto aplicado"
                              onpaste="return true" tabindex="0" autocomplete="off"></textarea>
                    <div id='ms_detalles_indicaciones'><p></p></div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #e5e7eb; padding:16px 22px; background:#f8fafc;">
                <button id="cancelar" type="button" class="btn btn-default pull-left"
                        style="min-width:130px; min-height:44px; border-radius:8px;">
                    <i class="fa fa-fw fa-times"></i> Cancelar
                </button>
                <button id="incluir" type="button" class="btn btn-success"
                        style="min-width:150px; min-height:44px; border-radius:8px; font-weight:700;">
                    <i class="fa fa-fw fa-save"></i> Incluir
                </button>
            </div>
        </div>
    </div>
</div>

<script language="JavaScript" src="../../plugins/maxLength/maxLength.js"></script>
<script type="text/javascript" src="../../plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="../../plugins/datepicker/locales/bootstrap-datepicker.es.js" charset="UTF-8"></script>
<script src="../../plugins/unslider/unslider-min.js"></script>
<script type="text/javascript" src="../../js/bootstrap-multiselect.js"></script>
<script language="JavaScript" src="../../plugins/mask/jquery.mask.min.js"></script>
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script src="../../plugins/balloon/jquery.balloon.min.js"></script>
<script src="../../plugins/leaflet/leaflet.js"></script>
<script src="../../plugins/leaflet/leaflet.label.js"></script>
<script src="../../plugins/select3/bootstrap-select.min.js"></script>
<script src="../../plugins/select3/i18n/defaults-es_ES.min.js"></script>
<script src="../../js/servicio-selector.js?v=16"></script>
<script src="../../js/crud-animal-selector.js?v=15"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
var ID_GLOBAL = 0;
var buscarx = 0;
var num_reg = 0;
var msj = "";
var Save_Reg = false;
var id_contsanitario = 0;
BloquearCampos();
document.getElementById('cod_animal').disabled = true;
document.getElementById('buscar').disabled = true;
document.getElementById('nuevo').disabled = true;

var fundo = <?php echo $_SESSION['mfundos_id']; ?>;

function CargarDetalles() {
    document.getElementById("quitar").disabled = true;

    var table = $('#tabla').dataTable({
        "destroy": true,
        "ajax": {
            "url": "../../data_json/data_mproductos_control?id_control=" + id_contsanitario,
            "dataSrc": ""
        },
        "fnRowCallback": function(nRow, mData, iDisplayIndex) {
            $('td:eq(2)', nRow).css('opacity', '0');
            $('td:eq(0)', nRow).css('font-weight', 'bold');
            return nRow;
        },
        "scrollX": true,
        "scrollY": "120px",
        "columns": [
            { "data": "nombre_producto" },
            { "data": "dosis" },
            { "data": "id" }
        ],
        "order": [[0, "desc"]],
        "bPaginate": false,
        "info": false,
        "bFilter": false
    });

    var can_reg = $(tabla).find('tbody tr').length;

    if (num_reg < 1) {
        $('#tabla tbody').on('click', 'tr', function() {
            if ($(this).hasClass('selected')) {
                $(this).removeClass('selected');
            } else {
                table.$('tr.selected').removeClass('selected');
                $(this).addClass('selected');
            }
        });
        num_reg = 1;
        if (can_reg > 0) {
            document.getElementById("quitar").disabled = false;
        }
    }
}

$("#b_codigo").click(function() {
    document.getElementById("nombre_animal").value = '';
    document.getElementById("cod_animal").value = '';

    if ($('#b_codigo').prop('checked') == true) {
        var id_fundo = '0' + fundo + '-';
        $("#b_nro_arete").val(0);
        $("#b_codigo").val(1);
        document.getElementById('b_codigo').checked = true;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal').disabled = false;
        document.getElementById("cod_animal").value = 'SOVCA-' + id_fundo;
        document.getElementById('buscar').disabled = false;
        buscarx = 1;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$("#b_nro_arete").click(function() {
    document.getElementById("nombre_animal").value = '';
    document.getElementById("cod_animal").value = '';

    if ($('#b_nro_arete').prop('checked') == true) {
        $("#b_nro_arete").val(1);
        $("#b_codigo").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = true;
        document.getElementById('cod_animal').disabled = false;
        document.getElementById('buscar').disabled = false;
        buscarx = 2;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$("#cargar").click(function() {
    if (ValidarCamposVacios()) {
        GuardarDatos();
    } else {
        setTimeout(function() {
            document.getElementById(msj).innerHTML = '';
            document.getElementById('cargar').disabled = false;
        }, 2000);
    }
});

$("#nuevo").click(function() {
    window.setTimeout('location.reload()', 1000);
});

function ValidarCamposVacios() {
    var guardar = false;
    var cod_animal = $("#cod_animal").val();
    var fecha_control = $("#fecha_control").val();
    var evento = $("#evento").val();
    var responsable = $("#responsable").val();

    if (cod_animal == "") {
        document.getElementById('ms_cod_animal').style.display = 'block';
        document.getElementById("ms_cod_animal").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_cod_animal";
    } else if (fecha_control == "") {
        document.getElementById('ms_fecha_control').style.display = 'block';
        document.getElementById("ms_fecha_control").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_fecha_control";
    } else if (evento == "") {
        document.getElementById('ms_evento').style.display = 'block';
        document.getElementById("ms_evento").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_evento";
    } else if (responsable == "") {
        document.getElementById('ms_responsable').style.display = 'block';
        document.getElementById("ms_responsable").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_responsable";
    } else {
        guardar = true;
    }
    return guardar;
}

function GuardarDatos() {
    id_contsanitario = 0;
    $.post("../../controllers/mcontrolsanitario_controller", {
        action: "add",
        cod_animal: $("#cod_animal").val(),
        fecha_control: $("#fecha_control").val(),
        fecha_prox_control: $("#fecha_prox_control").val(),
        evento: $("#evento").val(),
        responsable: $("#responsable").val(),
        observaciones: $("#observaciones").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $(".message").html(parsedJson.mensaje);
        switch (parsedJson.deslizador) {
            case "1":
                $('.base').unslider('animate:0'); tick = 0;
                document.getElementById('izquierda').disabled = true;
                $("#izquierda").css("display", "none");
                document.getElementById('derecha').disabled = false;
                $("#derecha").css("display", "block");
                break;
            case "2":
                $('.base').unslider('animate:1'); tick = 1;
                document.getElementById('izquierda').disabled = false;
                $("#izquierda").css("display", "block");
                document.getElementById('derecha').disabled = true;
                $("#derecha").css("display", "none");
                break;
        }
        if (parsedJson.resultado != 'error') {
            document.getElementById("agregar").disabled = true;
            $.post("../../controllers/mcontrolsanitario_controller", {
                action: "buscar_maxid"
            }, null, "json").done(function(data) {
                id_contsanitario = parseInt(data, 10) || 0;
                if (id_contsanitario > 0) {
                    document.getElementById("fecha_control").disabled = true;
                    document.getElementById("fecha_prox_control").disabled = true;
                    document.getElementById("evento").disabled = true;
                    document.getElementById("responsable").disabled = true;
                    document.getElementById("observaciones").disabled = true;
                    document.getElementById("cargar").disabled = true;
                    document.getElementById("agregar").disabled = false;
                }
            }).fail(function() {
                id_contsanitario = 0;
                document.getElementById("agregar").disabled = true;
            });

            setTimeout(function() {
                $(".alert").alert('close');
            }, 2000);
        } else {
            setTimeout(function() {
                $(".alert").alert('close');
                id_contsanitario = 0;
            }, 2000);
        }
    }, "json");
}

$('#limpiar').click(function() {
    BloquearCampos();
    LimpiarCampos();
    document.getElementById("b_nro_arete").disabled = false;
    document.getElementById("b_codigo").disabled = false;
    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
    document.getElementById("limpiar").disabled = true;
});

function CargarCategoria() {
    $.post("../../controllers/mproductos_controller", {
        action: "get_categoria_con_productos"
    }).done(function(data) {
        $("#categoria").html(data);
    });
}

$('#categoria').change(function(event) {
    $.post("../../controllers/mproductos_controller", {
        action: "get_productos",
        categoria: $("#categoria").val()
    }).done(function(data) {
        $("#producto").html(data);
        document.getElementById("producto").disabled = false;
    });
});

$('#producto').change(function(event) {
    document.getElementById("dosis").disabled = false;
    document.getElementById("detalles_indicaciones").disabled = false;
    document.getElementById("incluir").disabled = false;
});

$('#buscar').click(function() {
    var rgb_animal_h = $("#cod_animal").val();
    ID_GLOBAL = rgb_animal_h;
    buscarx = buscarx;
    document.getElementById("nombre_animal").value = '';

    $.post("../../controllers/mcontrolsanitario_controller", {
        action: "get_buscar_rgb",
        rgb_animal_h: rgb_animal_h,
        buscarx: buscarx
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        if (parsedJson.resultado == 'error') {
            $(".message").html(parsedJson.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
        } else {
            $("#nombre_animal").html(data);
            $("#nombre_animal").val(data);

            DesBloquearCampos();
            document.getElementById("buscar").disabled = true;
            document.getElementById("cod_animal").disabled = true;
            document.getElementById("limpiar").disabled = false;
            document.getElementById("b_nro_arete").disabled = true;
            document.getElementById("b_codigo").disabled = true;
            document.getElementById("cargar").disabled = false;
        }

        var valor = $("#nombre_animal").val();
        var array = valor.split("*");
        var log = array[0];
        var array2 = log.split('"');
        var log2 = array2[1];

        var log_n = array[1];
        var array_n2 = log_n.split('"');
        var log_n2 = array_n2[0];

        $("#cod_animal").html(log2).val(log2);
        $("#nombre_animal").html(log_n2).val(log_n2);
    });
});

function HabilitarProducto() {
    document.getElementById("producto").disabled = true;
    document.getElementById("dosis").disabled = true;
    document.getElementById("detalles_indicaciones").disabled = true;
    document.getElementById("cancelar").disabled = false;
    document.getElementById("incluir").disabled = true;
    $("#producto").val(null);
    $("#dosis").val(null);
    $("#detalles_indicaciones").val(null);
}

$("#agregar").click(function() {
    if (id_contsanitario <= 0) {
        document.getElementById("agregar").disabled = true;
        return;
    }
    $('#modal1').appendTo('body');
    $('#modal1').modal({ backdrop: true, keyboard: true, show: true });
    HabilitarProducto();
    CargarCategoria();
});

$('#fecha_control').datepicker({
    endDate: '+0d', todayBtn: false, todayHighlight: true,
    autoclose: true, language: 'es', showOnFocus: true
});

$('#fecha_prox_control').datepicker({
    todayBtn: false, todayHighlight: true, autoclose: true,
    language: 'es', showOnFocus: true, startDate: '+0d'
});



function filterFloat(evt, input) {
    var key = window.Event ? evt.which : evt.keyCode;
    var chark = String.fromCharCode(key);
    var tempValue = input.value + chark;
    if (key >= 48 && key <= 57) {
        if (filter(tempValue) === false) return false; else return true;
    } else {
        if (key == 8 || key == 13 || key == 0) return true;
        else if (key == 46) { if (filter(tempValue) === false) return false; else return true; }
        else return false;
    }
}

function filter(__val__) {
    var preg = /^([0-9]+\.?[0-9]{0,2})$/;
    if (preg.test(__val__) === true) return true; else return false;
}

function LimpiarCampos() {
    document.getElementById("cod_animal").value = '';
    document.getElementById("fecha_control").value = '';
    document.getElementById("fecha_prox_control").value = '';
    document.getElementById("evento").value = '';
    document.getElementById("producto").value = '';
    document.getElementById("dosis").value = '';
    document.getElementById("responsable").value = '';
    document.getElementById("observaciones").value = '';
}

function BloquearCampos() {
    document.getElementById("limpiar").disabled = true;
    document.getElementById("cod_animal").disabled = true;
    document.getElementById("fecha_control").disabled = true;
    document.getElementById("fecha_prox_control").disabled = true;
    document.getElementById("evento").disabled = true;
    document.getElementById("producto").disabled = true;
    document.getElementById("dosis").disabled = true;
    document.getElementById("responsable").disabled = true;
    document.getElementById("observaciones").disabled = true;
    document.getElementById("agregar").disabled = true;
    document.getElementById("quitar").disabled = true;
    document.getElementById("cargar").disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("limpiar").disabled = false;
    document.getElementById("cod_animal").disabled = false;
    document.getElementById("fecha_control").disabled = false;
    document.getElementById("fecha_prox_control").disabled = false;
    document.getElementById("evento").disabled = false;
    document.getElementById("responsable").disabled = false;
    document.getElementById("buscar").disabled = false;
    document.getElementById("observaciones").disabled = false;
}

$('#quitar').click(function() {
    var value = $('tr.selected').children('td:last').text();
    if (!value) {
        $.alert({
            title: '! Seleccione el item a desincorporar !',
            content: false,
            confirmButton: true,
            closeIcon: false,
            confirmButton: 'cerrar',
            confirmButtonClass: 'btn-success'
        });
    } else {
        $.confirm({
            title: '¿Desea desincorporar este item?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-danger',
            cancelButtonClass: 'btn-success',
            confirm: function() {
                $.post("../../controllers/mdeta_productosxcontrol_controller", {
                    action: "delete_producto",
                    id_det: value
                }).done(function(data) {
                    $(".message").html(data);
                    var parsedJson = $.parseJSON(data);
                    $(".message").html(parsedJson.mensaje);
                    setTimeout(function() {
                        $(".alert").alert('close');
                        CargarDetalles();
                    }, 2000);
                });
            },
            cancel: function() {}
        });
    }
});

$("#incluir").click(function() {
    $.post("../../controllers/mdeta_productosxcontrol_controller", {
        action: "add_productos",
        id_control_sanit: id_contsanitario,
        producto: $("#producto").val(),
        dosis: $("#dosis").val(),
        detalles_indicaciones: $("#detalles_indicaciones").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $(".message1").html(parsedJson.mensaje);
        if (parsedJson.resultado != 'error') {
            LimpiarModal();
            setTimeout(function() {
                $('#modal1').modal('hide');
                $(".alert").alert('close');
                $("#agregar").focus();
                CargarDetalles();
                document.getElementById('nuevo').disabled = false;
            }, 1500);
        }
    }, "json");
});

function LimpiarModal() {
    document.getElementById("incluir").disabled = true;
    document.getElementById("cancelar").disabled = true;
    $("#producto").val(null);
    $("#dosis").val(null);
    $("#detalles_indicaciones").val(null);
    document.getElementById("dosis").disabled = true;
    document.getElementById("detalles_indicaciones").disabled = true;
    document.getElementById("producto").disabled = true;
}

$(function() {
    $("#producto").maxLength(7);
    $("#dosis").maxLength(50);
});

$("#cancelar").click(function() {
    $('#modal1').modal('hide');
    $("#agregar").focus();
    document.getElementById("incluir").disabled = true;
    document.getElementById("cancelar").disabled = true;
    $("#producto").val(null);
    $("#dosis").val(null);
    $("#detalles_indicaciones").val(null);
    document.getElementById("dosis").disabled = true;
    document.getElementById("detalles_indicaciones").disabled = true;
    document.getElementById("producto").disabled = true;
});
/* ═══════════════════════════════════════════════════════════════════════════
   SALIDA — Función unificada para #exit y #exit_top
   ═══════════════════════════════════════════════════════════════════════════ */
function confirmarSalida(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    $.confirm({
        title: '¿Desea Salir?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            setTimeout(function() {
                window.location.href = '../desincorporacion/';
            }, 1000);
        },
        cancel: function() {}
    });
}
$(document).on('click', '#exit_top', confirmarSalida);
</script>

<script type="text/javascript">
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    var DURATION = 4800;
    function findLabelForCheckbox(el) {
        var lbl = el.closest('label'); if (lbl) return lbl;
        var p = el.previousElementSibling;
        while (p) { if (p.tagName === 'LABEL') return p; p = p.previousElementSibling; }
        var n = el.nextElementSibling;
        while (n) { if (n.tagName === 'LABEL') return n; n = n.nextElementSibling; }
        return null;
    }
    function highlight(el) {
        if (el.tagName === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function () { el.classList.remove('crud-just-unlocked-btn'); }, DURATION); return;
        }
        if (el.type === 'checkbox') {
            el.classList.add('crud-just-unlocked-check');
            var l = findLabelForCheckbox(el);
            if (l) {
                l.classList.add('crud-just-unlocked-check-label');
                setTimeout(function(){ el.classList.remove('crud-just-unlocked-check'); l.classList.remove('crud-just-unlocked-check-label'); }, DURATION);
            } else { setTimeout(function(){ el.classList.remove('crud-just-unlocked-check'); }, DURATION); }
            return;
        }
        el.classList.add('crud-unlocked');
        setTimeout(function () { el.classList.remove('crud-unlocked'); }, DURATION);
    }
    function check() {
        var fields = document.querySelectorAll('.crud-form input, .crud-form select, .crud-form textarea, .crud-form button');
        Array.prototype.forEach.call(fields, function (el) {
            var was = seen.get(el), now = el.disabled;
            if (was === true && now === false) highlight(el);
            seen.set(el, now);
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        check();
        var observer = new MutationObserver(function (m) {
            var t = false;
            m.forEach(function (x) { if (x.type === 'attributes' && x.attributeName === 'disabled') t = true; });
            if (t) check();
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['disabled'], subtree: true });
    });
})();
</script>

<!-- SELECTOR AMIGABLE: listado de animales aptos para el control sanitario -->
<script type="text/javascript">
$(function () {
    bovsCrudAnimalSelector({
        lista:         'sel_animal',
        url:           '../../controllers/mcontrolsanitario_controller',
        accion:        'listar_candidatos_control_sanitario',
        rol:           'animal',
        campoCodigo:   'cod_animal',
        botonBuscar:   'buscar',
        botonCambiar:  'limpiar',
        textoVacio:    'No hay animales activos registrados en este fundo.',
        textoResumen:  function (ok) { return ok + ' animales disponibles'; },
        alElegir:      function (fila) { buscarx = 1; }
    });

    /* El "Cambiar" del bloque también limpia el nombre mostrado */
    $('#limpiar').on('click', function () {
        $('#nombre_animal').val('').html('');
    });
});
</script>

<?php include_once("../layouts/pie.php") ?>