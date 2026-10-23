<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<style>
/* FEEDBACK VISUAL DE DESBLOQUEO */
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

/* GRID */
@media (min-width: 993px) { #grid_reglote { grid-template-columns: 6fr 6fr; } }

/* DATATABLES dentro de las cards */
.crud-form .crud-datatable-wrap { width: 100%; overflow-x: auto; }
.crud-form .crud-datatable-wrap .dataTables_wrapper { padding: 0; }
.crud-form .crud-datatable-wrap table.dataTable {
    width: 100% !important; margin: 0 !important;
    border-collapse: collapse !important; font-size: 14px;
}
.crud-form .crud-datatable-wrap table.dataTable thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    font-size: 12px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 12px !important;
    border-bottom: 1px solid #e5e7eb !important; border-top: none !important;
    white-space: nowrap;
}
.crud-form .crud-datatable-wrap table.dataTable tbody td {
    padding: 10px 12px !important;
    border-bottom: 1px solid #eef1f4 !important; border-top: none !important;
    color: #1f2937; vertical-align: middle; background: #fff !important;
}
.crud-form .crud-datatable-wrap table.dataTable tbody tr { cursor: pointer; }
.crud-form .crud-datatable-wrap table.dataTable tbody tr:hover td { background: #f0fdf4 !important; }
.crud-form .crud-datatable-wrap table.dataTable tbody tr.selected td {
    background: #dbeafe !important; color: #0c2d6b !important; font-weight: 600 !important;
}
.crud-form .crud-datatable-wrap .dataTables_scrollBody { border-bottom: none !important; }
.crud-form .crud-datatable-wrap .dataTables_scrollHeadInner,
.crud-form .crud-datatable-wrap .dataTables_scrollHead { border: none !important; }

/* ACCIONES BAJO TABLAS */
.crud-form .crud-detalle-acciones {
    display: flex; gap: 8px; padding-top: 12px;
    border-top: 1px solid #e5e7eb; margin-top: 8px;
}
.crud-form .crud-detalle-acciones .btn {
    min-width: 44px; min-height: 40px;
    border-radius: 8px; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    border: none; cursor: pointer; padding: 0 14px;
}
.crud-form .crud-detalle-acciones .btn-success {
    background: linear-gradient(135deg,#16a34a 0%,#15803d 100%);
    color: #fff; box-shadow: 0 3px 10px rgba(22,163,74,.28);
}
.crud-form .crud-detalle-acciones .btn-success:hover:not(:disabled) {
    background: linear-gradient(135deg,#15803d 0%,#14532d 100%); transform: translateY(-1px);
}
.crud-form .crud-detalle-acciones .btn-danger {
    background: linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);
    color: #fff; box-shadow: 0 3px 10px rgba(220,38,38,.28);
}
.crud-form .crud-detalle-acciones .btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg,#b91c1c 0%,#991b1b 100%); transform: translateY(-1px);
}
.crud-form .crud-detalle-acciones .btn-dark {
    background: linear-gradient(135deg,#475569 0%,#334155 100%);
    color: #fff; box-shadow: 0 3px 10px rgba(51,65,85,.28);
}
.crud-form .crud-detalle-acciones .btn-dark:hover:not(:disabled) {
    background: linear-gradient(135deg,#334155 0%,#1e293b 100%); transform: translateY(-1px);
}
.crud-form .crud-detalle-acciones .btn:disabled { opacity: .45; cursor: not-allowed; }

/* SUBCARD */
.crud-form .crud-subcard {
    border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 12px 14px; background: #fbfdfb;
}
.crud-form .crud-subcard-title {
    margin: 0 0 10px;
    font-size: .95rem; font-weight: 800;
    letter-spacing: .06em; text-transform: uppercase;
    color: #334155;
    display: flex; align-items: center; gap: 8px;
}
.crud-form .crud-subcard-title i { color: #16a34a; }
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-medkit"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Control Sanitario por Lote</h3>
            <p class="crud-form__subtitle">Registre un control sanitario masivo aplicado a un lote completo</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="valor_animal">

        <div class="crud-form__grid" id="grid_reglote">

            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-th-large"></i>
                    <h3>Lote de Animales</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="lote_animal">Seleccione el Lote</label>
                        <select name="lote_animal" id="lote_animal" class="form-control bbb" tabindex="2">
                            <option></option>
                        </select>
                        <div id='ms_lote'><p></p></div>
                    </div>

                    <div class="crud-subcard">
                        <h4 class="crud-subcard-title"><i class="fa fa-list-ol"></i> Animales en el Lote</h4>
                        <div class="crud-datatable-wrap">
                            <table id="tablalote" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre Animal</th>
                                        <th>Nro Arete</th>
                                        <th>Raza</th>
                                        <th>Edad</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="crud-detalle-acciones">
                            <button id="quitar_animal" type="button" class="btn btn-danger btn-xs"
                                    title="Quitar animal del lote">
                                <i class="fa fa-fw fa-trash"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-flask"></i>
                    <h3>Detalles del Producto Aplicado</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-subcard">
                        <div class="crud-datatable-wrap">
                            <table id="tabla" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Dosis por Animal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
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

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_control">Fecha del Control</label>
                            <input class="form-control bbb" id="fecha_control" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                            <div id='ms_fecha_control'><p></p></div>
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
                        <label for="evento">Evento</label>
                        <input class="form-control bbb" id="evento" type="text"
                               placeholder="Indique la causa para realizar algún control sanitario."
                               onpaste="return false">
                        <div id='ms_evento'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="responsable">Responsable</label>
                        <input class="form-control bbb" id="responsable" type="text"
                               placeholder="Persona encargada del control"
                               onpaste="return false">
                        <div id='ms_responsable'><p></p></div>
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

        </div>

        <div class="crud-form__actions">
            <button id="limpiar" type="button" class="btn btn-dark" tabindex="57">
                <i class="fa fa-fw fa-eraser"></i> Limpiar
            </button>
            <button id="exit" type="button" class="btn btn-warning">
                <i class="fa fa-fw fa-reply"></i> Salir
            </button>
            <button id="guardar" type="button" class="btn btn-success" tabindex="23">
                <i class="fa fa-fw fa-save"></i> Grabar
            </button>
        </div>

    </form>

</div>

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
                    <label for="dosis">Dosis por Animal</label>
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
var id_lote = 0;
var valor_animal = 0;

$.post("../../controllers/mlotes_controller", { action: "get_lote" })
    .done(function(data) { $("#lote_animal").html(data); });

$('#lote_animal').click(function(event) {
    $('#tablalote').DataTable().clear().draw();
    CargarDetallesLote();
    document.getElementById("agregar").disabled = false;
});

BloquearCampos();
document.getElementById("quitar").disabled = true;

function CargarDetalles() {
    document.getElementById("quitar").disabled = true;
    var lote = $("#lote_animal").val();

    var table = $('#tabla').dataTable({
        "destroy": true,
        "ajax": {
            "url": "../../data_json/data_mproductos_control_temp?id_lote=" + lote,
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
            DesBloquearCampos();
        }
    }
}

function CargarDetallesLote() {
    var id_lote = $("#lote_animal").val();
    document.getElementById("quitar_animal").disabled = true;

    var tabla = $('#tablalote').dataTable({
        "destroy": true,
        "ajax": {
            "url": "../../data_json/data_mlotesctlsant?id_lote=" + id_lote,
            "dataSrc": ""
        },
        "fnRowCallback": function(nRow, mData, iDisplayIndex) {
            $('td:eq(4)', nRow).css('opacity', '0');
            $('td:eq(0)', nRow).css('font-weight', 'bold');
            return nRow;
        },
        "scrollX": "450px",
        "scrollY": "250px",
        "scrollCollapse": true,
        "columns": [
            { "data": "nombre",       "width": "200px" },
            { "data": "nro_arete",    "width": "130px" },
            { "data": "raza",         "width": "200px" },
            { "data": "edad",         "width": "250px" },
            { "data": "id_manimales", "width": "50px"  }
        ],
        "order": [[0, "desc"]],
        "bPaginate": false,
        "info": false,
        "bFilter": false
    });

    $('#tablalote tbody').on('click', 'tr', function() {
        document.getElementById("quitar_animal").disabled = false;
        if ($(this).hasClass('selected')) {
            $(this).removeClass('selected');
        } else {
            tabla.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
            valor_animal = tabla.$('tr.selected').children('td:last').text();
        }
    });
}

function BloquearCampos() {
    document.getElementById("limpiar").disabled = true;
    document.getElementById("fecha_control").disabled = true;
    document.getElementById("fecha_prox_control").disabled = true;
    document.getElementById("evento").disabled = true;
    document.getElementById("producto").disabled = true;
    document.getElementById("dosis").disabled = true;
    document.getElementById("responsable").disabled = true;
    document.getElementById("observaciones").disabled = true;
    document.getElementById("agregar").disabled = true;
    document.getElementById("quitar").disabled = true;
    document.getElementById("quitar_animal").disabled = true;
    document.getElementById("guardar").disabled = true;
}

$('#quitar_animal').click(function() {
    var valor_lote = $("#lote_animal").val();
    if (!valor_lote) {
        $.alert({
            title: '! Seleccione el Animal a desincorporar !',
            content: false, confirmButton: true, closeIcon: false,
            confirmButton: 'cerrar', confirmButtonClass: 'btn-success'
        });
    } else {
        $.confirm({
            title: '¿Desea desincorporar este Animal del Lote?',
            content: false, confirmButton: 'Si', cancelButton: 'No',
            confirmButtonClass: 'btn-danger', cancelButtonClass: 'btn-success',
            confirm: function() {
                $.post("../../controllers/mcontrolsanitario_controller", {
                    action: "quitar_animal",
                    id_animal_lote: valor_animal,
                    id_lote: valor_lote
                }).done(function(data) {
                    var parsedJson = $.parseJSON(data);
                    $(".message1").html(parsedJson.mensaje);
                    if (parsedJson.resultado != 'error') {
                        setTimeout(function() {
                            $(".alert").alert('close');
                            CargarDetallesLote();
                        }, 1500);
                    }
                });
            },
            cancel: function() {}
        });
    }
});

$("#guardar").click(function() {
    if (ValidarCamposVacios()) {
        GuardarDatos();
    } else {
        setTimeout(function() {
            document.getElementById(msj).innerHTML = '';
            document.getElementById('guardar').disabled = false;
        }, 2000);
    }
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
    $.post("../../controllers/mcontrolsanitario_controller", {
        action: "add_cs_lote",
        lote_animal: $("#lote_animal").val(),
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
            $('.base').unslider('animate:0');
            LimpiarCampos();
            BloquearCampos();
            setTimeout(function() {
                $(".alert").alert('close');
                $(location).attr('href', '../controlsanitariomasivo/');
            }, 3000);
        } else {
            setTimeout(function() { $(".alert").alert('close'); }, 2500);
        }
    }, "json");
}

$('#limpiar').click(function() {
    BloquearCampos();
    LimpiarCampos();
    document.getElementById("limpiar").disabled = true;
    document.getElementById("lote_animal").disabled = false;
});

function CargarCategoria() {
    $.post("../../controllers/mproductos_controller", { action: "get_categoria_con_productos" })
        .done(function(data) { $("#categoria").html(data); });
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

/* ═══════════════════════════════════════════════════════════
   FIX MODAL: appendTo('body') para sacarlo del stacking context
   de .crud-form, y abrir con backdrop/keyboard estándar.
   ═══════════════════════════════════════════════════════════ */
$("#agregar").click(function() {
    if (!$("#lote_animal").val()) {
        $.alert({
            title: '! Seleccione un lote primero !',
            content: false, confirmButton: true, closeIcon: false,
            confirmButton: 'cerrar', confirmButtonClass: 'btn-success'
        });
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

$("#exit").click(function() {
    $.confirm({
        title: '¿Desea Salir?',
        content: false, confirmButton: 'Si', cancelButton: 'No',
        confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-danger',
        confirm: function() {
            setTimeout(function() {
                $(location).attr('href', '../controlsanitariomasivo/');
            }, 1000);
        },
        cancel: function() {}
    });
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
    document.getElementById("lote_animal").value = '';
    document.getElementById("fecha_control").value = '';
    document.getElementById("fecha_prox_control").value = '';
    document.getElementById("evento").value = '';
    document.getElementById("producto").value = '';
    document.getElementById("dosis").value = '';
    document.getElementById("responsable").value = '';
    document.getElementById("observaciones").value = '';
    $('#tablalote').DataTable().clear().draw();
    $('#tabla').DataTable().clear().draw();
}

function DesBloquearCampos() {
    document.getElementById("limpiar").disabled = false;
    document.getElementById("fecha_control").disabled = false;
    document.getElementById("fecha_prox_control").disabled = false;
    document.getElementById("evento").disabled = false;
    document.getElementById("responsable").disabled = false;
    document.getElementById("observaciones").disabled = false;
    document.getElementById("guardar").disabled = false;
    document.getElementById("quitar_animal").disabled = true;
}

$('#quitar').click(function() {
    var value = $('tr.selected').children('td:last').text();
    if (!value) {
        $.alert({
            title: '! Seleccione el item a desincorporar !',
            content: false, confirmButton: true, closeIcon: false,
            confirmButton: 'cerrar', confirmButtonClass: 'btn-success'
        });
    } else {
        $.confirm({
            title: '¿Desea desincorporar este item?',
            content: false, confirmButton: 'Si', cancelButton: 'No',
            confirmButtonClass: 'btn-danger', cancelButtonClass: 'btn-success',
            confirm: function() {
                $.post("../../controllers/mdeta_productosxcontrol_controller", {
                    action: "delete_producto_temp",
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
        action: "add_productos_temp",
        producto: $("#producto").val(),
        dosis: $("#dosis").val(),
        lote: $("#lote_animal").val(),
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
                document.getElementById("lote_animal").disabled = true;
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
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
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

<?php include_once("../layouts/pie.php") ?>