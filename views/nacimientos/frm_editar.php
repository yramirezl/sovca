<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = isset($valores[0]) ? $valores[0] : 0;
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

/* FEEDBACK VISUAL DE DESBLOQUEO */
@keyframes crud-flash-input {
    0%   { box-shadow: 0 0 0 0 rgba(22, 163, 74, .75); background-color: #dcfce7; border-color: #16a34a; }
    30%  { box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); background-color: #f0fdf4; border-color: #16a34a; }
    70%  { box-shadow: 0 0 0 0 rgba(22, 163, 74, .35); background-color: #ffffff; }
    100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); background-color: inherit; }
}
.crud-form .form-control.crud-unlocked,
.crud-form input.crud-unlocked,
.crud-form select.crud-unlocked,
.crud-form textarea.crud-unlocked,
.crud-form select.btn-sm.crud-unlocked {
    animation: crud-flash-input 4.8s ease !important;
    border-color: #16a34a !important;
    transition: none !important;
}
@keyframes crud-flash-btn {
    0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, .65); }
    18%  { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(22, 163, 74, 0); }
    45%  { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, .45); }
    70%  { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.crud-form button.crud-just-unlocked-btn,
.crud-form .btn.crud-just-unlocked-btn {
    animation: crud-flash-btn 4.8s ease !important;
    outline: 3px solid rgba(22, 163, 74, .60) !important;
    outline-offset: 3px !important;
    position: relative; z-index: 2;
}
@keyframes crud-flash-check {
    0%   { transform: scale(1); background-color: rgba(22, 163, 74, .30); box-shadow: 0 0 0 0 rgba(22, 163, 74, .55); }
    25%  { transform: scale(1.25); background-color: rgba(22, 163, 74, .55); box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
    60%  { transform: scale(1); background-color: rgba(22, 163, 74, .30); box-shadow: 0 0 0 0 rgba(22, 163, 74, .30); }
    100% { transform: scale(1); background-color: transparent; box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.crud-form input[type="checkbox"].crud-just-unlocked-check {
    animation: crud-flash-check 4.8s ease !important;
    outline: 2px solid #16a34a !important;
    outline-offset: 2px !important;
    border-radius: 3px;
}
@keyframes crud-flash-check-label {
    0%   { background-color: rgba(22, 163, 74, .30); color: #15803d; transform: translateX(0); }
    30%  { background-color: rgba(22, 163, 74, .18); color: #15803d; transform: translateX(-3px); }
    60%  { background-color: rgba(22, 163, 74, .08); color: #15803d; transform: translateX(0); }
    100% { background-color: transparent; color: inherit; }
}
.crud-form label.crud-just-unlocked-check-label {
    animation: crud-flash-check-label 4.8s ease !important;
    border-radius: 6px !important;
    padding: 3px 8px !important;
    font-weight: 800 !important;
    transition: none !important;
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Nacimiento</h3>
            <p class="crud-form__subtitle">Modifique los datos del registro de nacimiento</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="id_reg_nac">

        <div class="crud-form__grid">

            <!-- ========== CARD 1: Datos de la Madre ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3>Datos de la Madre</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="tipo_servicio">Tipo de Servicio Utilizado
                            <a data-toggle="tooltip" title="Indica el tipo de servicio utilizado.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <select name="tipo_servicio" id="tipo_servicio" class="form-control bbb" tabindex="7">
                            <option></option>
                        </select>
                    </div>

                    <label>Datos de la Donadora</label>

                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_d">
                            <div class="sel-animal__bar">
                                <i class="fa fa-search"></i>
                                <input type="text" class="sel-animal__filtro" data-sel="filtro"
                                       placeholder="Buscar por nombre, código SOVCA o N° de arete…"
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

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Donadora seleccionada</label>
                            <button id="limpiar_dona" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_h_donadora" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_dona'><p></p></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el flujo interno -->
                    <div class="sel-animal-oculto">
                        <label>Nro. Arete-Chip / Cód</label>
                        <div class="crud-check-group">
                            <label><input type="checkbox" id="dona_nro_arete"> Nro Arete</label>
                            <label><input type="checkbox" id="dona_codigo"> Código</label>
                        </div>
                        <input type="text" id="cod_animal_dona" class="form-control"
                               placeholder="Código de la Donadora"
                               onpaste="return true"
                               onkeypress="return es_codigo_nro_arete_dona(event);">
                        <button id="buscar_dona" type="button" class="btn btn-danger btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <label>Datos de la Madre / Receptora</label>

                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_h">
                            <div class="sel-animal__bar">
                                <i class="fa fa-search"></i>
                                <input type="text" class="sel-animal__filtro" data-sel="filtro"
                                       placeholder="Buscar por nombre, código SOVCA o N° de arete…"
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

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Madre / Receptora seleccionada</label>
                            <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_h" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_h'><p></p></div>
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el flujo interno -->
                    <div class="sel-animal-oculto">
                        <label>Nro. Arete-Chip / Cód</label>
                        <div class="crud-check-group">
                            <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                            <label><input type="checkbox" id="b_codigo"> Código</label>
                        </div>
                        <input type="text" id="cod_animal_h" class="form-control"
                               onpaste="return true"
                               onkeypress="return es_codigo_nro_arete(event);">
                        <button id="buscar" type="button" class="btn btn-danger btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label>Parto Nro
                                <a data-toggle="tooltip" title="Indica los partos efectivos que ha tenido la madre.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="nro_parto" class="form-control" placeholder="Nro de Parto">
                        </div>
                        <div class="crud-form__field">
                            <label for="fecha_parto">Fecha del Parto</label>
                            <input class="form-control bbb" id="fecha_parto" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="proposito">Entrar en lactancia ?</label>
                            <select id="proposito" class="btn-sm" tabindex="4">
                                <option value="*">Seleccione</option>
                                <option value="2">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label>Periodo de lactancia</label>
                            <input type="text" id="periodo_prodleche" class="form-control" placeholder="Periodo">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_secado">Fecha de secado</label>
                            <input class="form-control bbb" id="fecha_secado" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Datos del Padre + Ternero ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-mars"></i>
                    <h3>Datos del Padre / Ternero</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_m">
                            <div class="sel-animal__bar">
                                <i class="fa fa-search"></i>
                                <input type="text" class="sel-animal__filtro" data-sel="filtro"
                                       placeholder="Buscar por nombre, código SOVCA o N° de arete…"
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

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Padre / Macho seleccionado</label>
                            <button id="limpiar_m" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_m" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_m'><p></p></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el flujo interno -->
                    <div class="sel-animal-oculto">
                        <label>Nro de Arete / Código del Macho</label>
                        <div class="crud-check-group">
                            <label><input type="checkbox" id="macho_nro_arete"> Nro Arete</label>
                            <label><input type="checkbox" id="macho_codigo"> Código</label>
                        </div>
                        <input type="text" id="cod_animal_m" class="form-control"
                               placeholder="Código del Macho"
                               onpaste="return false"
                               onkeypress="return es_codigo_nro_arete_macho(event);">
                        <button id="buscar_m" type="button" class="btn btn-primary btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <label>Datos del Ternero/a</label>

                    <div class="crud-form__row--3">
                        <div class="crud-form__field">
                            <label for="nacio">¿ Nació ?</label>
                            <select id="nacio" class="btn-sm" tabindex="3">
                                <option value="*">--</option>
                                <option value="1">Vivo</option>
                                <option value="2">Muerto</option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label for="sexo">Sexo</label>
                            <select id="sexo" class="btn-sm" tabindex="3">
                                <option value="*">--</option>
                                <option value="1">Hembra</option>
                                <option value="2">Macho</option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label>Peso</label>
                            <input type="text" id="peso_nacer" class="form-control" placeholder="Peso al Nacer"
                                   onpaste="return false" tabindex="12"
                                   onkeypress="return filterFloat(event,this)">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label>ID-N° Arete
                                <a data-toggle="tooltip" title="Identificador que se asignará al ternero o a la ternera.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="id_ternero" class="form-control"
                                   placeholder="Id del Ternero/a"
                                   onpaste="return false"
                                   onkeypress="return es_id_ternero(event);">
                            <div id='ms_id_ternero'><p></p></div>
                        </div>
                        <div class="crud-form__field">
                            <label>Estado
                                <a data-toggle="tooltip" title="Indica si el ternero ya tiene una ficha en el Registro de Animales.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="fichado" class="form-control" readonly="true">
                        </div>
                    </div>

                    <div class="crud-form__field" id="fichar_id" style="display:none;">
                        <label for="fichar">¿ Ficharlo ?
                            <a data-toggle="tooltip" title="Si el ternero o la ternera aún no tiene ficha, puedes crearla desde esta opción.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <div>
                            <button id="fichar" type="button" class="btn btn-primary">
                                <i class="fa fa-fw fa-bookmark"></i>Crear Ficha
                            </button>
                        </div>
                    </div>

                    <!-- ===== Crías del parto (mellizos) ===== -->
                    <style>
                        #bloque_crias_parto { display:block; }
                        #bloque_crias_parto .cria-item {
                            border: 1px solid #e3e7ed; border-radius: 8px;
                            padding: 8px 10px; margin-bottom: 8px; background: #fbfcfd;
                        }
                        #bloque_crias_parto .cria-item__cab {
                            display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
                        }
                        #bloque_crias_parto .cria-item__cab strong { font-size: 12.5px; color: #334155; }
                        #bloque_crias_parto .cria-item__estado {
                            font-size: 11px; padding: 1px 8px; border-radius: 10px; font-weight: 700;
                        }
                        #bloque_crias_parto .cria-item__estado.is-ok { background: #e8f5ec; color: #146b2e; }
                        #bloque_crias_parto .cria-item__estado.is-pend { background: #fdeceb; color: #b3261e; }
                        #bloque_crias_parto .cria-item__quitar { margin-left: auto; }
                        #bloque_crias_parto .cria-item__grid {
                            display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px;
                        }
                        #bloque_crias_parto .cria-item__grid > label {
                            display: block; margin: 0; font-size: 11.5px;
                            color: #64748b; font-weight: 600;
                        }
                        #bloque_crias_parto .cria-item__grid > label.ancho { grid-column: 1 / -1; }
                        #bloque_crias_parto .cria-item__grid .form-control {
                            height: 30px; font-size: 12.5px; padding: 4px 8px;
                        }
                    </style>
                    <div class="crud-form__field" id="bloque_crias_parto" style="display:none;">
                        <label>Crías del parto
                            <a data-toggle="tooltip" title="Cada ternero/a lleva su propia ficha. Aquí puedes corregir los datos de cada cría (ID/arete, sexo, peso, si nació y aptitud).">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <div id="crias_parto_tbody"></div>
                        <button type="button" class="btn btn-success btn-xs" id="agregar_cria_parto">
                            <i class="fa fa-plus"></i> Agregar cría
                        </button>
                        <a class="btn btn-default btn-xs" id="ir_fichar_crias"
                           href="frm_ficha.php?%$%=<?php echo rawurlencode(base64_encode('id=' . intval($cad0))); ?>">
                            <i class="fa fa-bookmark"></i> Fichar crías
                        </a>
                        <small class="text-muted" style="display:block;margin-top:6px;">
                            Este parto tiene varias crías. Cada una lleva su propia ficha en el Registro de Animales.
                        </small>
                    </div>

                    <div class="crud-form__field">
                        <label for="aptitud_animal">Aptitud del Animal</label>
                        <select name="aptitud_animal" id="aptitud_animal" class="form-control bbb" tabindex="7">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc" id="observaciones" tabindex="8" rows="3"
                                  placeholder="Observaciones" onpaste="return true"
                                  onkeypress="return ms_direccion(event)"
                                  onblur="alsalir(this.id);" autocomplete="off"></textarea>
                        <div id='ms_observaciones' class="aaa"><p></p></div>
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="modificar" type="button" class="btn btn-primary" tabindex="24">
                <i class="fa fa-fw fa-pencil"></i> EDITAR
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="23">
                <i class="fa fa-fw fa-save"></i> Guardar
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

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
<script type="text/javascript" src="../../js/servicio-selector.js?v=16"></script>
<script type="text/javascript" src="../../js/crud-animal-selector.js?v=15"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
var fundo = <?php echo $_SESSION['mfundos_id']; ?>;
var periodoLactanciaDias = 0;
var periodoProduccionLecheDias = 0;
var buscarx = 0;
var ID_GLOBAL = 0;
var buscarx_m = 0;
var ID_GLOBAL_M = 0;
var buscarx_dona = 0;
var ID_GLOBAL_DONA = 0;

document.getElementById("fichar").disabled = true;
$('#modificar').hide();
$('#save').hide();
$('#fichar').hide();
$('label[for="fichar"]').hide();

LimpiarCampos();
BloquearCampos();
document.getElementById("save").disabled = true;

$.post("../../controllers/mtiposervicios_controller", {
    action: "get_servicio_animal"
}).done(function(data) { $("#tipo_servicio").html(data); });

$.post("../../controllers/maptitudes_controller", {
    action: "get_aptitud"
}).done(function(data) { $("#aptitud_animal").html(data); });

$.post("../../controllers/mperiodos_controller", {
    action: "get_periodos_lactancia"
}).done(function(data) {
    var parsedJson = $.parseJSON(data);
    periodoLactanciaDias = parseInt(parsedJson.periodo_lactancia_dias, 10) || 0;
    periodoProduccionLecheDias = parseInt(parsedJson.periodo_prodleche_dias, 10) || 0;
    $("#periodo_prodleche").val(parsedJson.periodo_lactancia);
    actualizarFechaSecadoNacimiento();
});

$(document).ready(function() {
    $.post("../../controllers/mnacimientos_controller", {
        action: "search",
        id_reg_nac: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_reg_nac").val(parsedJson.id);
        $("#fichado").val(parsedJson.fichado);
        if (parsedJson.fichado == 'FICHADO') {
            document.getElementById("fichar").disabled = true;
            $('#modificar').hide();
            $('#save').hide();
            $('#fichar').hide();
            $('label[for="fichar"]').hide();
        } else {
            document.getElementById("fichar").disabled = false;
            $('#modificar').show();
            $('#save').show();
            $('#fichar').show();
            $('label[for="fichar"]').show();
            $('#fichar_id').show();
        }
        $("#codigo_ternero").val(parsedJson.codigo_ternero);
        $("#cod_animal_h").val(parsedJson.cod_animal_h);
        $("#nombre_h").val(parsedJson.nombre_animal_h);
        $("#cod_animal_m").val(parsedJson.cod_animal_m);
        $("#nombre_m").val(parsedJson.nombre_animal_m);
        $("#fecha_parto").val(parsedJson.fecha_parto);
        $("#peso_nacer").val(parsedJson.peso_nacer);
        $("#sexo").val(parsedJson.sexo);
        $("#aptitud_animal").val(parsedJson.aptitud_animal);
        $("#nacio").val(parsedJson.nacio);
        $("#nro_parto").val(parsedJson.nro_parto);
        $("#fecha_secado").val(parsedJson.fecha_secado);
        $("#observaciones").val(parsedJson.observaciones);
        /* Compatibilidad: los registros viejos guardaban 1 = Lactancia.
           Ahora "Entrar en lactancia" usa el período configurado (valor 2). */
        var proposito_guardado = String(parsedJson.proposito);
        if (proposito_guardado === '1') proposito_guardado = '2';
        $("#proposito").val(proposito_guardado);
        $("#tipo_servicio").val(parsedJson.tipo_servicio);
        $("#cod_animal_dona").val(parsedJson.cod_animal_dona);
        $("#id_ternero").val(parsedJson.id_ternero);
        proposito = $("#proposito").val();
        actualizarFechaSecadoNacimiento();
        cargarCriasParto();
    }, "json");
});

$('#modificar').click(function() {
    DesbloquearTodo();
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("modificar").disabled = true;
});

function DesbloquearTodo() {
    DesBloquearCampos();
    DesBloquearMacho();
    document.getElementById("proposito").disabled = false;
    var nacio = $("#nacio").val();
    if (nacio == '1') {
        document.getElementById("id_ternero").disabled = false;
        document.getElementById("sexo").disabled = false;
        document.getElementById("peso_nacer").disabled = false;
        document.getElementById("aptitud_animal").disabled = false;
    }
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("tipo_servicio").disabled = false;
    document.getElementById("save").disabled = false;
    var t_servicio = $("#tipo_servicio").val();
    if (t_servicio != "") {
        if (t_servicio != 3) {
            DesbloquearMadre();
            BloquearDonadora();
        } else {
            DesbloquearDonadora();
            BloquearMadre();
        }
    }
}

$("#tipo_servicio").change(function() {
    var t_servicio = $("#tipo_servicio").val();
    if (t_servicio != "") {
        if (t_servicio != 3) {
            DesbloquearMadre();
            BloquearDonadora();
        } else {
            DesbloquearDonadora();
            BloquearMadre();
        }
    }
});

function DesbloquearDonadora() {
    document.getElementById("buscar_dona").disabled = true;
    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById('dona_codigo').disabled = false;
    document.getElementById('dona_nro_arete').disabled = false;
}
function BloquearDonadora() {
    document.getElementById("buscar_dona").disabled = true;
    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById('dona_codigo').disabled = true;
    document.getElementById('dona_nro_arete').disabled = true;
}
function DesBloquearMacho() {
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById('macho_codigo').disabled = false;
    document.getElementById('macho_nro_arete').disabled = false;
}
function BloquearMacho() {
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById('macho_codigo').disabled = true;
    document.getElementById('macho_nro_arete').disabled = true;
}
function DesbloquearMadre() {
    document.getElementById("buscar").disabled = true;
    document.getElementById("limpiar").disabled = true;
    document.getElementById('b_codigo').disabled = false;
    document.getElementById('b_nro_arete').disabled = false;
}
function BloquearMadre() {
    document.getElementById("buscar").disabled = true;
    document.getElementById("limpiar").disabled = true;
    document.getElementById('b_codigo').disabled = true;
    document.getElementById('b_nro_arete').disabled = true;
}

$('#limpiar_dona').click(function() {
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("nombre_h_donadora").value = "";
    document.getElementById("cod_animal_dona").value = "";
    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById("buscar_dona").disabled = true;
    document.getElementById('dona_codigo').checked = false;
    document.getElementById('dona_nro_arete').checked = false;
    DesbloquearDonadora();
});

$('#limpiar').click(function() {
    LimpiarCampos();
    BloquearCampos();
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("buscar").disabled = false;
    document.getElementById("limpiar").disabled = true;
    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
    DesbloquearMadre();
});

$('#limpiar_m').click(function() {
    document.getElementById("cod_animal_m").disabled = false;
    document.getElementById("buscar_m").disabled = false;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("nombre_m").value = '';
    document.getElementById("cod_animal_m").value = '';
});

$('#buscar').click(function() {
    var rgb_animal_h = $("#cod_animal_h").val();
    if (rgb_animal_h != "") {
        ID_GLOBAL = rgb_animal_h;
        buscarx = buscarx;
        document.getElementById("nombre_h").value = '';
        var rgb_animal_h = $("#cod_animal_h").val();
        document.getElementById("nombre_h").value = '';

        $.post("../../controllers/mnacimientos_controller", {
            action: "get_buscar_rgb_h",
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
                $("#nombre_h").val(data);
                document.getElementById("buscar").disabled = true;
                document.getElementById('b_codigo').disabled = true;
                document.getElementById('b_nro_arete').disabled = true;
                document.getElementById("fecha_parto").disabled = false;
                document.getElementById("limpiar").disabled = false;
            }
            var valor = $("#nombre_h").val();
            var array = valor.split("*");
            var log = array[0];
            var array2 = log.split('"');
            var log2 = array2[1];
            var log_n = array[1];
            var array_n2 = log_n.split('"');
            var log_n2 = array_n2[0];
            var log_n3 = array[2];
            var array_n3 = log_n3.split('"');
            var log_n4 = array_n3[0];
            log_n4 = parseInt(log_n4);
            if (log_n4 > 1) {
                document.getElementById("nro_parto").disabled = true;
            } else {
                document.getElementById("nro_parto").disabled = false;
            }
            $("#cod_animal_h").html(log2).val(log2);
            $("#nombre_h").html(log_n2).val(log_n2);
            $("#nro_parto").html(log_n4).val(log_n4);
        });
    } else {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Debe Ingresar el Código';
        setTimeout(function() { document.getElementById("ms_cod_animal_h").innerHTML = ''; }, 3000);
    }
});

$('#buscar_dona').click(function() {
    var rgb_animal_h_dona = $("#cod_animal_dona").val();
    ID_GLOBAL_DONA = rgb_animal_h_dona;
    buscarx_dona = buscarx_dona;
    document.getElementById("nombre_h_donadora").value = '';

    $.post("../../controllers/mnacimientos_controller", {
        action: "get_buscar_rgb_h_dona",
        rgb_animal_h_dona: rgb_animal_h_dona,
        buscarx_dona: buscarx_dona
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        if (parsedJson.resultado == 'error') {
            $(".message").html(parsedJson.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
        } else {
            $("#nombre_h_donadora").val(data);
            document.getElementById("buscar_dona").disabled = true;
            document.getElementById('dona_codigo').disabled = true;
            document.getElementById('dona_nro_arete').disabled = true;
            document.getElementById("cod_animal_dona").disabled = true;
            document.getElementById("limpiar_dona").disabled = false;
            document.getElementById('b_codigo').disabled = false;
            document.getElementById('b_nro_arete').disabled = false;
        }
        var valor = $("#nombre_h_donadora").val();
        var array = valor.split("*");
        var log = array[0];
        var array2 = log.split('"');
        var log2 = array2[1];
        var log_n = array[1];
        var array_n2 = log_n.split('"');
        var log_n2 = array_n2[0];
        $("#cod_animal_dona").html(log2).val(log2);
        $("#nombre_h_donadora").html(log_n2).val(log_n2);
    });
});

$("#dona_codigo").click(function() {
    var id_fundo = '0' + fundo + '-';
    document.getElementById("nombre_h_donadora").value = '';
    document.getElementById("cod_animal_dona").value = '';
    if ($('#dona_codigo').prop('checked') == true) {
        $("#dona_nro_arete").val(0);
        $("#dona_codigo").val(1);
        document.getElementById('dona_codigo').checked = true;
        document.getElementById('dona_nro_arete').checked = false;
        document.getElementById('cod_animal_dona').disabled = false;
        document.getElementById("cod_animal_dona").value = 'SOVCA-' + id_fundo;
        document.getElementById('buscar_dona').disabled = false;
        buscarx_dona = 1;
    } else {
        $("#dona_codigo").val(0);
        $("#dona_nro_arete").val(0);
        document.getElementById('dona_codigo').checked = false;
        document.getElementById('dona_nro_arete').checked = false;
        document.getElementById('cod_animal_dona').disabled = true;
        document.getElementById('buscar_dona').disabled = true;
        buscarx_dona = 0;
        ID_GLOBAL_DONA = 0;
    }
});

$("#dona_nro_arete").click(function() {
    document.getElementById("nombre_h_donadora").value = '';
    document.getElementById("cod_animal_dona").value = '';
    document.getElementById('buscar_dona').disabled = true;
    if ($('#dona_nro_arete').prop('checked') == true) {
        $("#dona_nro_arete").val(1);
        $("#dona_codigo").val(0);
        document.getElementById('dona_codigo').checked = false;
        document.getElementById('dona_nro_arete').checked = true;
        document.getElementById('cod_animal_dona').disabled = false;
        document.getElementById('buscar_dona').disabled = false;
        buscarx_dona = 2;
    } else {
        $("#dona_codigo").val(0);
        $("#dona_nro_arete").val(0);
        document.getElementById('dona_codigo').checked = false;
        document.getElementById('dona_nro_arete').checked = false;
        document.getElementById('cod_animal_dona').disabled = true;
        document.getElementById('buscar_dona').disabled = true;
        buscarx_dona = 0;
        ID_GLOBAL_DONA = 0;
    }
});

$("#macho_codigo").click(function() {
    var id_fundo = '0' + fundo + '-';
    document.getElementById("nombre_m").value = '';
    document.getElementById("cod_animal_m").value = '';
    if ($('#macho_codigo').prop('checked') == true) {
        $("#macho_nro_arete").val(0);
        $("#macho_codigo").val(1);
        document.getElementById('macho_codigo').checked = true;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('cod_animal_m').disabled = false;
        document.getElementById("cod_animal_m").value = 'SOVCA-' + id_fundo;
        document.getElementById('buscar_m').disabled = false;
        buscarx_m = 1;
    } else {
        $("#macho_codigo").val(0);
        $("#macho_nro_arete").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('cod_animal_m').disabled = true;
        document.getElementById('buscar_m').disabled = true;
        buscarx_m = 0;
        ID_GLOBAL_M = 0;
    }
});

$("#macho_nro_arete").click(function() {
    document.getElementById("nombre_m").value = '';
    document.getElementById("cod_animal_m").value = '';
    if ($('#macho_nro_arete').prop('checked') == true) {
        $("#macho_nro_arete").val(1);
        $("#macho_codigo").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = true;
        document.getElementById('cod_animal_m').disabled = false;
        document.getElementById('buscar_m').disabled = false;
        buscarx_m = 2;
    } else {
        $("#macho_codigo").val(0);
        $("#macho_nro_arete").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('cod_animal_m').disabled = true;
        document.getElementById('buscar_m').disabled = true;
        buscarx_m = 0;
        ID_GLOBAL_M = 0;
    }
});

$("#b_codigo").click(function() {
    var id_fundo = '0' + fundo + '-';
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    if ($('#b_codigo').prop('checked') == true) {
        $("#b_nro_arete").val(0);
        $("#b_codigo").val(1);
        document.getElementById('b_codigo').checked = true;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = false;
        document.getElementById("cod_animal_h").value = 'SOVCA-' + id_fundo;
        document.getElementById('buscar').disabled = false;
        buscarx = 1;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$("#b_nro_arete").click(function() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    if ($('#b_nro_arete').prop('checked') == true) {
        $("#b_nro_arete").val(1);
        $("#b_codigo").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = true;
        document.getElementById('cod_animal_h').disabled = false;
        document.getElementById('buscar').disabled = false;
        buscarx = 2;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$('#buscar_m').click(function() {
    var rgb_animal_m = $("#cod_animal_m").val();
    var tipo_s = $("#tipo_servicio").val();
    ID_GLOBAL_M = rgb_animal_m;
    buscarx_m = buscarx_m;
    document.getElementById("nombre_m").value = '';

    $.post("../../controllers/mnacimientos_controller", {
        action: "get_buscar_rgb_m",
        rgb_animal_m: rgb_animal_m,
        buscarx_m: buscarx_m,
        tipo_s: tipo_s
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        if (parsedJson.resultado == 'error') {
            $(".message").html(parsedJson.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
        } else {
            $("#nombre_m").html(data).val(data);
            document.getElementById("buscar_m").disabled = true;
            document.getElementById("cod_animal_m").disabled = true;
            document.getElementById("limpiar_m").disabled = false;
        }
        var valor = $("#nombre_m").val();
        var array = valor.split("*");
        var log = array[0];
        var array2 = log.split('"');
        var log2 = array2[1];
        var log_n = array[1];
        var array_n2 = log_n.split('"');
        var log_n2 = array_n2[0];
        $("#cod_animal_m").html(log2).val(log2);
        $("#nombre_m").html(log_n2).val(log_n2);
    });
});

$("#id_ternero").blur(function() {
    $.post("../../controllers/mnacimientos_controller", {
        action: "search_id_ternero",
        id_ternero: $("#id_ternero").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        var validar = parsedJson.id_ternero;
        if (validar != 0) {
            document.getElementById('ms_id_ternero').style.display = 'block';
            document.getElementById("ms_id_ternero").innerHTML = 'El codigo ya se Encuentra Registrado';
            document.getElementById('id_ternero').focus();
            var valor = $("#id_ternero").val();
            setTimeout(function() {
                document.getElementById("ms_id_ternero").innerHTML = '';
                $("#id_ternero").val(valor);
            }, 3000);
        }
    }, "json");
});

$('#fecha_parto').datepicker({
    endDate: '+0d', todayBtn: false, todayHighlight: true, autoclose: true,
    language: 'es', showOnFocus: true
});
$('#fecha_secado').datepicker({
    todayBtn: false, todayHighlight: true, autoclose: true,
    language: 'es', showOnFocus: true
});

$("#derecha").click(function() {
    if (tick == 0) {
        var cont_alert = $('.bbb').filter(function() { return $(this).val() == ""; }).size();
        document.getElementById('derecha').disabled = true;
        $("#derecha").css("display", "none");
        if (cont_alert > 30) {
            $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
        } else {
            $('.base').unslider('animate:1'); tick = 1;
            document.getElementById('izquierda').disabled = false;
            $("#izquierda").css("display", "block");
            setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        }
    } else if (tick == 1) {
        var cont_alert3 = $('.ccc').filter(function() { return $(this).val() == ""; }).size();
        if (cont_alert3 > 50) {
            $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
        } else {
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        }
    }
});

$("#izquierda").click(function() {
    if (tick == 1) {
        $('.base').unslider('animate:0'); tick = 0;
        document.getElementById('izquierda').disabled = true;
        $("#izquierda").css("display", "none");
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
    } else if (tick == 2) {
        $('.base').unslider('animate:1'); tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
    }
});

$("#exit").click(function() {
    $.confirm({
        title: '¿Desea Salir?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            setTimeout(function() { $(location).attr('href', '../nacimientos/'); }, 1000);
        },
        cancel: function() {}
    });
});

/* ============================================================
   CRÍAS DEL PARTO (MELLIZOS)
   Un parto puede tener 2 crías y cada una lleva su propia ficha.
   Se listan aquí para poder corregir sus datos y se guardan con
   el resto del parto.
   ============================================================ */
var MAX_CRIAS_PARTO = 5;
var criasParto = [];

function escaparHtmlCria(valor) {
    return $('<div>').text(valor === null || valor === undefined ? '' : String(valor)).html();
}

function opcionesAptitudCria(seleccion) {
    var html = '<option value="0">Indique la Aptitud</option>';
    $('#aptitud_animal option').each(function() {
        var valor = $(this).val();
        if (valor === '' || valor === null || valor === undefined) { return; }
        html += '<option value="' + escaparHtmlCria(valor) + '"' +
                (String(valor) === String(seleccion) ? ' selected' : '') + '>' +
                escaparHtmlCria($(this).text()) + '</option>';
    });
    return html;
}

function pintarCriasParto() {
    var $body = $('#crias_parto_tbody');
    $body.empty();

    $.each(criasParto, function(i, cria) {
        var fichada = parseInt(cria.fichado, 10) === 1;

        var html = '<div class="cria-item" data-id-cria="' + (parseInt(cria.id_cria, 10) || 0) + '">' +
            '<div class="cria-item__cab">' +
                '<strong>Cría ' + (i + 1) + '</strong>' +
                '<span class="cria-item__estado ' + (fichada ? 'is-ok' : 'is-pend') + '">' +
                    (fichada ? 'Fichada' : 'Sin fichar') +
                '</span>' +
                '<button type="button" class="btn btn-danger btn-xs cria-item__quitar quitar-cria" title="Quitar cría">' +
                    '<i class="fa fa-trash"></i>' +
                '</button>' +
            '</div>' +
            '<div class="cria-item__grid">' +
                '<label class="ancho">ID / Arete' +
                    '<input type="text" class="form-control cria-id" value="' + escaparHtmlCria(cria.id_ternero) + '"' +
                    (fichada ? ' readonly="readonly" title="Esta cría ya tiene ficha (' + escaparHtmlCria(cria.codigo_animal || 'animal registrado') + '); el ID no se puede cambiar."' : '') +
                    '>' +
                '</label>' +
                '<label>Sexo' +
                    '<select class="form-control cria-sexo">' +
                        '<option value="0">—</option>' +
                        '<option value="1"' + (parseInt(cria.sexo, 10) === 1 ? ' selected' : '') + '>Hembra</option>' +
                        '<option value="2"' + (parseInt(cria.sexo, 10) === 2 ? ' selected' : '') + '>Macho</option>' +
                    '</select>' +
                '</label>' +
                '<label>Peso' +
                    '<input type="text" class="form-control cria-peso" value="' + escaparHtmlCria(cria.peso_nacimiento) + '"' +
                    ' onpaste="return false" onkeypress="return filterFloat(event,this)">' +
                '</label>' +
                '<label>¿Nació?' +
                    '<select class="form-control cria-nacio">' +
                        '<option value="0">—</option>' +
                        '<option value="1"' + (parseInt(cria.estatus_nacido, 10) === 1 ? ' selected' : '') + '>Vivo</option>' +
                        '<option value="2"' + (parseInt(cria.estatus_nacido, 10) === 2 ? ' selected' : '') + '>Muerto</option>' +
                    '</select>' +
                '</label>' +
                '<label class="ancho">Aptitud' +
                    '<select class="form-control cria-aptitud">' + opcionesAptitudCria(cria.aptitud) + '</select>' +
                '</label>' +
            '</div>' +
        '</div>';

        $body.append(html);
    });

    actualizarBotonAgregarCria();
}

function actualizarBotonAgregarCria() {
    var total = $('#crias_parto_tbody .cria-item').length;
    $('#agregar_cria_parto').prop('disabled', total >= MAX_CRIAS_PARTO);
}

function obtenerCriasEdicion() {
    var lista = [];

    $('#crias_parto_tbody .cria-item').each(function() {
        var $fila = $(this);
        lista.push({
            id: parseInt($fila.attr('data-id-cria'), 10) || 0,
            id_ternero: $.trim($fila.find('.cria-id').val()),
            sexo: $fila.find('.cria-sexo').val(),
            peso_nacimiento: $.trim($fila.find('.cria-peso').val()),
            estatus_nacido: $fila.find('.cria-nacio').val(),
            aptitud: $fila.find('.cria-aptitud').val()
        });
    });

    return lista;
}

function cargarCriasParto() {
    var idReg = $('#id_reg_nac').val();
    if (!idReg) { return; }

    $.post('../../controllers/mnacimientos_controller', {
        action: 'listar_terneros_nacimiento',
        id_reg_nac: idReg
    }, null, 'json').done(function(resp) {
        if (!resp || resp.resultado !== 'ok') { return; }

        criasParto = resp.terneros || [];

        if (criasParto.length > 1) {
            $('#bloque_crias_parto').show();
            /* Con mellizos el botón de ficha del ternero único no aplica */
            $('#fichar_id').hide();
            pintarCriasParto();
        } else {
            $('#bloque_crias_parto').hide();
        }
    });
}

$('#agregar_cria_parto').on('click', function() {
    if ($('#crias_parto_tbody .cria-item').length >= MAX_CRIAS_PARTO) { return; }

    criasParto.push({
        id_cria: 0,
        id_ternero: '',
        sexo: 0,
        peso_nacimiento: '',
        estatus_nacido: 1,
        aptitud: $('#aptitud_animal').val() || 0,
        fichado: 0,
        codigo_animal: ''
    });
    pintarCriasParto();
});

$('#crias_parto_tbody').on('click', '.quitar-cria', function() {
    if ($('#crias_parto_tbody .cria-item').length <= 1) { return; }

    var $fila = $(this).closest('.cria-item');
    var indice = $fila.index();
    criasParto.splice(indice, 1);
    pintarCriasParto();
});

$('#save').click(function() {
    document.getElementById('save').disabled = true;
    if (ValidarCamposVacios()) {
        $.confirm({
            title: '¿Está Seguro de Guardar el Registro ?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-danger',
            confirm: function() {
                $.post("../../controllers/mnacimientos_controller", {
                    action: "edit",
                    id_reg_nac: $("#id_reg_nac").val(),
                    tipo_servicio: $("#tipo_servicio").val(),
                    cod_animal_h: $("#cod_animal_h").val(),
                    cod_animal_m: $("#cod_animal_m").val(),
                    cod_animal_dona: $("#cod_animal_dona").val(),
                    id_ternero: $("#id_ternero").val(),
                    fecha_parto: $("#fecha_parto").val(),
                    nro_parto: $("#nro_parto").val(),
                    nacio: $("#nacio").val(),
                    aptitud_animal: $("#aptitud_animal").val(),
                    peso_nacer: $("#peso_nacer").val(),
                    sexo: $("#sexo").val(),
                    fecha_secado: $("#fecha_secado").val(),
                    proposito: $("#proposito").val(),
                    observaciones: $("#observaciones").val(),
                    terneros: $('#bloque_crias_parto').is(':visible')
                        ? JSON.stringify(obtenerCriasEdicion())
                        : ''
                }).done(function(data) {
                    /* Parseo tolerante: un error fatal de PHP (HTML) no debe
                       dejar la pantalla sin mensaje. */
                    var parsedJson;
                    try {
                        parsedJson = (typeof data === 'string') ? $.parseJSON(data) : data;
                    } catch (e) {
                        $(".message").html('<div class="alert alert-danger">El servidor no devolvió una respuesta válida.' +
                            '<br><small>Detalle: ' + String(data).substring(0, 300) + '</small></div>');
                        return;
                    }
                    parsedJson = parsedJson || {};
                    $(".message").html(parsedJson.mensaje || '');
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
                        setTimeout(function() { $(location).attr('href', './'); }, 3000);
                    }
                }, "json").fail(function(xhr) {
                    $(".message").html('<div class="alert alert-danger">Error de comunicación con el servidor (HTTP ' +
                        xhr.status + ').<br><small>Detalle: ' +
                        String(xhr.responseText || '').substring(0, 300) + '</small></div>');
                });
            },
            cancel: function() {}
        });
    } else {
        document.getElementById('modificar').disabled = false;
    }
});

function LimpiarCampos() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    document.getElementById("cod_animal_m").value = '';
    document.getElementById("nombre_m").value = '';
    document.getElementById("fecha_parto").value = '';
    document.getElementById("fecha_secado").value = '';
    document.getElementById("peso_nacer").value = '';
    document.getElementById("aptitud_animal").value = '';
    document.getElementById("proposito").value = '';
    document.getElementById("observaciones").value = '';
    document.getElementById("nacio").value = '';
    document.getElementById("nro_parto").value = '';
    document.getElementById('dona_codigo').checked = false;
    document.getElementById('dona_nro_arete').checked = false;
    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
    document.getElementById("nombre_h_donadora").value = '';
    document.getElementById("cod_animal_dona").value = '';
}

function BloquearCampos() {
    document.getElementById("limpiar").disabled = true;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
    document.getElementById("fecha_parto").disabled = true;
    document.getElementById("nacio").disabled = true;
    document.getElementById("aptitud_animal").disabled = true;
    document.getElementById("peso_nacer").disabled = true;
    document.getElementById("proposito").disabled = true;
    document.getElementById("fecha_secado").disabled = true;
    /* Observaciones queda siempre disponible: es texto libre del parto. */
    document.getElementById("periodo_prodleche").disabled = true;
    document.getElementById("sexo").disabled = true;
    document.getElementById("nro_parto").disabled = true;
    document.getElementById("cod_animal_dona").disabled = true;
    document.getElementById("nombre_h_donadora").disabled = true;
    document.getElementById("dona_nro_arete").disabled = true;
    document.getElementById("dona_codigo").disabled = true;
    document.getElementById("buscar").disabled = true;
    document.getElementById("nombre_h").disabled = true;
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("b_nro_arete").disabled = true;
    document.getElementById("b_codigo").disabled = true;
    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById("buscar_dona").disabled = true;
    document.getElementById("id_ternero").disabled = true;
    document.getElementById("tipo_servicio").disabled = true;
    BloquearMacho();
}

function DesBloquearCampos() {
    document.getElementById("limpiar").disabled = false;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
}

function es_codigo_nro_arete_dona(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Za-z0-9\.\-]/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_dona').style.display = 'block';
        document.getElementById("ms_cod_animal_dona").innerHTML = 'Sólo se pemite el - y el .';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_dona").innerHTML = '';
        return patron.test(n);
    }
}
function es_codigo_nro_arete(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Za-z0-9\.\-]/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Sólo se pemite el - y el .';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_h").innerHTML = '';
        return patron.test(n);
    }
}
function es_id_ternero(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Za-z0-9\.\-]/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_id_ternero').style.display = 'block';
        document.getElementById("ms_id_ternero").innerHTML = 'Sólo se pemite el - y el .';
        return patron.test(n);
    } else {
        document.getElementById("ms_id_ternero").innerHTML = '';
        return patron.test(n);
    }
}
function esnumer_m(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9]$/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_m').style.display = 'block';
        document.getElementById("ms_cod_animal_m").innerHTML = 'Use solo números';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_m").innerHTML = '';
        return patron.test(n);
    }
}
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

$('#nacio').change(function(event) {
    document.getElementById("aptitud_animal").disabled = false;
    document.getElementById("peso_nacer").disabled = false;
    document.getElementById("observaciones").disabled = false;
    document.getElementById("sexo").disabled = false;
});

$('#fecha_parto').change(function(event) {
    // Sin la pregunta "¿Entrará a producción de leche?": la fecha de
    // parto habilita el resto del bloque y la fecha de secado se calcula
    // con el propósito elegido.
    DesBloquearMacho();
    document.getElementById("nacio").disabled = false;
    document.getElementById("proposito").disabled = false;
    document.getElementById("fecha_secado").disabled = false;
    actualizarFechaSecadoNacimiento();
});

$('#proposito').change(function(event) {
    actualizarFechaSecadoNacimiento();
});

function actualizarFechaSecadoNacimiento() {
    var proposito = $("#proposito").val();
    /* "Entrar en lactancia" = Sí (2): la fecha de secado es fecha de parto
       + el período de lactancia configurado en el fundo (PPL). */
    /* "Entrar en lactancia" = Sí (2): fecha de parto + período de
       lactancia del fundo (PBL). */
    var dias = proposito === '2' ? periodoLactanciaDias : 0;
    var partes = $("#fecha_parto").val().split('-');
    if (dias <= 0 || partes.length !== 3) {
        $("#fecha_secado").val("");
        return;
    }

    var dia = parseInt(partes[0], 10);
    var mes = parseInt(partes[1], 10);
    var anio = parseInt(partes[2], 10);
    var fecha = new Date(anio, mes - 1, dia);
    if (fecha.getFullYear() !== anio || fecha.getMonth() !== mes - 1 || fecha.getDate() !== dia) {
        $("#fecha_secado").val("");
        return;
    }
    fecha.setDate(fecha.getDate() + dias);
    $("#fecha_secado").val(formatDate(fecha));
}

function sumarDias(fecha, dias) { fecha.setDate(fecha.getDate() + dias); return fecha; }
function formatDate(mifecha) {
    var d = mifecha,
        dformat = [padLeft(d.getDate()), padLeft(d.getMonth() + 1), d.getFullYear()].join('-');
    return dformat;
}
function padLeft(n) { return ("00" + n).slice(-2); }
function calcularfecha() {
    var fecha1 = $("#fecha_fup").val();
    var fecha2 = $("#fecha_servicio").val();
    dias = 0;
    if (fecha1 != "") {
        var aFecha1 = fecha1.split('-');
        var aFecha2 = fecha2.split('-');
        var fFecha1 = Date.UTC(aFecha1[2], aFecha1[1] - 1, aFecha1[0]);
        var fFecha2 = Date.UTC(aFecha2[2], aFecha2[1] - 1, aFecha2[0]);
        var dif = fFecha2 - fFecha1;
        dias = Math.floor(dif / (1000 * 60 * 60 * 24));
    }
    return dias;
}

function ValidarCamposVacios() {
    var guardar = false;
    var cod_animal_h = $("#cod_animal_h").val();
    var cod_animal_m = $("#cod_animal_m").val();
    var tipo_servicio = $("#tipo_servicio").val();
    var aptitud_animal = $("#aptitud_animal").val();
    var cod_animal_dona = $("#cod_animal_dona").val();

    if ((tipo_servicio == 3) && (cod_animal_dona == "")) {
        document.getElementById('ms_cod_animal_dona').style.display = 'block';
        document.getElementById("ms_cod_animal_dona").innerHTML = 'Este Campo es Obligatorio';
    } else if (cod_animal_h == "") {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Este Campo es Obligatorio';
    } else if (cod_animal_m == "") {
        document.getElementById('ms_cod_animal_m').style.display = 'block';
        document.getElementById("ms_cod_animal_m").innerHTML = 'Este Campo es Obligatorio';
    } else {
        guardar = true;
    }
    return guardar;
}

$('#fichar').click(function() {
    var value = $("#id_reg_nac").val();
    var parametros = window.btoa(value);
    $.confirm({
        title: '¿Desea Crear la Ficha Ahora ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-primary',
        cancelButtonClass: 'btn-success',
        confirm: function() {
            var parametros = window.btoa(value);
            $(location).attr('href', "frm_ficha?%$%=" + parametros);
        },
        cancel: function() {}
    });
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
            if (l) { l.classList.add('crud-just-unlocked-check-label');
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

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   BUSCADORES AMIGABLES: listado de animales que cumplen la condición
   (reemplaza el buscador manual de Nro Arete / Código, que queda oculto)
   ═══════════════════════════════════════════════════════════════════════════ */
$(function () {

    /* ---------- MADRE / RECEPTORA ---------- */
    bovsCrudAnimalSelector({
        lista:         'sel_h',
        url:           '../../controllers/mnacimientos_controller',
        accion:        'listar_candidatos_nacimiento',
        rol:           'madre',
        campoCodigo:   'cod_animal_h',
        botonBuscar:   'buscar',
        botonCambiar:  'limpiar',
        recargarCon:   'tipo_servicio',
        tipo:          function () { return $('#tipo_servicio').val(); },
        textoBloqueado: 'Indique primero el tipo de servicio utilizado.',
        textoVacio:     'No hay hembras que cumplan la condición de madre.',
        textoResumen:   function (ok, no) { return ok + ' disponibles · ' + no + ' no disponibles'; },
        alElegir:      function (fila) { buscarx = 1; }
    });

    /* ---------- DONADORA (solo Transferencia de Embriones) ---------- */
    bovsCrudAnimalSelector({
        lista:         'sel_d',
        url:           '../../controllers/mnacimientos_controller',
        accion:        'listar_candidatos_nacimiento',
        rol:           'donadora',
        campoCodigo:   'cod_animal_dona',
        botonBuscar:   'buscar_dona',
        botonCambiar:  'limpiar_dona',
        recargarCon:   'tipo_servicio',
        tipo:          function () { var t = $('#tipo_servicio').val(); return (t == '3') ? t : ''; },
        textoBloqueado: 'Solo aplica para Transferencia de Embriones.',
        textoVacio:     'No hay hembras donadoras registradas en el fundo.',
        alElegir:      function (fila) { buscarx_dona = 1; }
    });

    /* ---------- PADRE / MACHO ---------- */
    bovsCrudAnimalSelector({
        lista:         'sel_m',
        url:           '../../controllers/mnacimientos_controller',
        accion:        'listar_candidatos_nacimiento',
        rol:           'padre',
        campoCodigo:   'cod_animal_m',
        botonBuscar:   'buscar_m',
        botonCambiar:  'limpiar_m',
        recargarCon:   'tipo_servicio',
        tipo:          function () { return $('#tipo_servicio').val(); },
        params:        function () { return { tipo_s: $('#tipo_servicio').val() }; },
        textoBloqueado: 'Indique primero el tipo de servicio utilizado.',
        textoVacio:     'No hay machos que cumplan la condición.',
        textoResumen:   function (ok, no) { return ok + ' disponibles · ' + no + ' no disponibles'; },
        alElegir:      function (fila) { buscarx_m = 1; }
    });

});
</script>

<?php include_once("../layouts/pie.php") ?>