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
/* Este formulario necesita 2 columnas desiguales (5 y 6 original).
   Forzamos la proporción dentro del grid. */
@media (min-width: 993px) {
    #grid_servicios_registrar {
        grid-template-columns: 5fr 6fr;
    }
}

/* ============================================================
   LABEL CON CHECKBOXES AL LADO
   El label de un campo + los checkboxes de búsqueda quedan en la misma
   fila, con los checkboxes alineados a la derecha.
   ============================================================ */
.crud-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.crud-label-row > label {
    margin: 0 !important;
    font-size: inherit;
    text-transform: uppercase;
    font-weight: 700;
    color: #475569;
    letter-spacing: .04em;
    flex: 1 1 auto;
    min-width: 0;
}
.crud-label-row .crud-check-group {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    flex: 0 0 auto;
}
.crud-label-row .crud-check-group label {
    margin: 0 !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    color: #334155 !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    white-space: nowrap;
}
.crud-label-row .crud-check-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    accent-color: var(--cf-success, #16a34a);
}

/* ============================================================
   UTILIDADES DEL FORMULARIO DE SERVICIO
   ============================================================ */
.svc-hint {
    margin: 0;
    padding: 9px 12px;
    border-left: 3px solid #16a34a;
    border-radius: 6px;
    background: #f0fdf4;
    color: #3f6212;
    font-size: 13px;
    line-height: 1.45;
    min-height: 38px;
    display: flex;
    align-items: center;
}

.svc-hint-inline {
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: #94a3b8;
    font-size: 11.5px;
}

/* Fila de búsqueda exacta: campo + botones en una sola línea */
.svc-buscar {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

/* Controles internos del módulo que ya no se muestran: el listado filtrable
   hace la búsqueda por nombre, código BOVS o N° de arete. */
.svc-oculto {
    display: none !important;
}

.svc-buscar .form-control {
    flex: 1 1 auto;
    min-width: 0;
}

.svc-buscar .btn {
    flex: 0 0 auto;
    white-space: nowrap;
}

@media (max-width: 480px) {
    .svc-buscar { flex-wrap: wrap; }
    .svc-buscar .btn { flex: 1 1 auto; }
}

/* ============================================================
   AVISO DE CICLOS ABIERTOS
   Una hembra debe tener un único servicio vigente. El aviso se
   muestra bajo el animal elegido y no bloquea el registro.
   ============================================================ */
.bovs-aviso-ciclo {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 8px;
    padding: 9px 11px;
    border-radius: 8px;
    font-size: 12.5px;
    line-height: 1.4;
    border: 1px solid transparent;
}
.bovs-aviso-ciclo:empty { display: none; }
.bovs-aviso-ciclo i {
    font-size: 14px;
    line-height: 1.35;
    margin-top: 1px;
    flex: 0 0 auto;
}
.bovs-aviso-ciclo--alerta {
    background: #fff7ed;
    border-color: #fdba74;
    color: #7c2d12;
}
.bovs-aviso-ciclo--alerta i { color: #c2410c; }
.bovs-aviso-ciclo--info {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1e3a8a;
}
.bovs-aviso-ciclo--info i { color: #2563eb; }
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-venus-mars"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Servicio</h3>
            <p class="crud-form__subtitle">Registre el servicio reproductivo de una hembra con un macho</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="fecha2" value="">

        <div class="crud-form__grid" id="grid_servicios_registrar">

            <!-- ========== CARD 1: Tipo de servicio (paso 1) ========== -->
            <div class="crud-form__card crud-form__card--full">
                <div class="crud-form__card-head">
                    <i class="fa fa-list-ol"></i>
                    <h3>1 · Tipo de servicio</h3>
                </div>
                <div class="crud-form__card-body">
                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="tipo_servicio">Tipo de Servicio Utilizado
                                <a data-toggle="tooltip" title="Indica el tipo de servicio utilizado.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select name="tipo_servicio" id="tipo_servicio" class="form-control bbb" tabindex="1">
                                <option></option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label>Resumen del servicio</label>
                            <p class="svc-hint" id="resumen_tipo">Seleccione el tipo de servicio para habilitar el resto del formulario.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== CARD 2: Datos de la hembra (paso 2) ========== -->
            <div class="crud-form__card" id="card_hembra">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3 id="titulo_hembra">2 · Datos de la Hembra</h3>
                </div>
                <div class="crud-form__card-body">

                    <!-- ==== Listado de hembras que cumplen la condición ==== -->
                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_h">
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
                        <div id="diag_hembra"></div>
                    </div>

                    <!-- ==== Animal elegido ==== -->
                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Animal seleccionado</label>
                            <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_h" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_h'></div>
                        <div id='ms_nombre'></div>
                        <div id="aviso_ciclo"></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para la validación
                         del módulo pero ya no se muestra, el listado la ejecuta sola. -->
                    <div class="svc-oculto">
                        <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                        <label><input type="checkbox" id="b_codigo"> Código</label>
                        <input type="text" id="cod_animal_h" onpaste="return true" onkeypress="return es_codigo_nro_arete(event);">
                        <button id="buscar" type="button" tabindex="57">Buscar</button>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 3 (monta / inseminación): Macho / Donador ========== -->
            <div class="crud-form__card" id="card_macho">
                <div class="crud-form__card-head">
                    <i class="fa fa-mars"></i>
                    <h3 id="titulo_macho">3 · Macho que realiza la monta</h3>
                </div>
                <div class="crud-form__card-body">

                    <!-- ==== Listado de machos que cumplen la condición ==== -->
                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_m">
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

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Macho seleccionado</label>
                            <button id="limpiar_m" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_m" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_m'></div>
                    </div>

                    <div class="svc-oculto">
                        <label><input type="checkbox" id="macho_nro_arete"> Nro Arete</label>
                        <label><input type="checkbox" id="macho_codigo"> Código</label>
                        <input type="text" id="cod_animal_m" onpaste="return true" onkeypress="return es_codigo_nro_arete_macho(event);">
                        <button id="buscar_m" type="button" tabindex="57">Buscar</button>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 3 (inseminación / transferencia): Hembra donadora ========== -->
            <div class="crud-form__card" id="card_dona">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3 id="titulo_dona">3 · Datos de la Hembra Donadora</h3>
                </div>
                <div class="crud-form__card-body">

                    <!-- ==== Listado de hembras donadoras ==== -->
                    <div class="crud-form__field">
                        <div class="sel-animal" id="sel_d">
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

                    <!-- ==== Animal elegido ==== -->
                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Donadora seleccionada</label>
                            <button id="limpiar_dona" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_h_donadora" class="form-control"
                               placeholder="Seleccione una fila del listado" readonly="true">
                        <div id='ms_cod_animal_dona'></div>
                    </div>

                    <div class="svc-oculto">
                        <label><input type="checkbox" id="dona_nro_arete"> Nro Arete</label>
                        <label><input type="checkbox" id="dona_codigo"> Código</label>
                        <input type="text" id="cod_animal_dona" onpaste="return true" onkeypress="return es_codigo_nro_arete_dona(event);">
                        <button id="buscar_dona" type="button" tabindex="57">Buscar</button>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 4: Datos del servicio ========== -->
            <div class="crud-form__card crud-form__card--full" id="card_datos">
                <div class="crud-form__card-head">
                    <i class="fa fa-clipboard"></i>
                    <h3 id="titulo_datos">4 · Datos del Servicio</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label># Servicio
                                <a data-toggle="tooltip" title="Número de servicio que se está implementando en este período de gestación.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="nro_servicio" class="form-control" readonly="true" placeholder="#Servicio">
                        </div>
                        <div class="crud-form__field">
                            <label>Periodo de Palpación
                                <a data-toggle="tooltip" title="Cantidad de días estimados para realizar la palpación de la hembra después del servicio.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="periodo_palpacion" class="form-control" placeholder="Periodo">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_servicio">Fecha de Servicio</label>
                            <input class="form-control bbb" id="fecha_servicio" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                            <div id='ms_fecha_servicio'></div>
                        </div>
                        <div class="crud-form__field">
                            <label for="fecha_fpp">Fecha Prob. Palpación
                                <a data-toggle="tooltip" title="Indica la fecha probable para realizar la palpación de la hembra.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input class="form-control bbb" id="fecha_fpp" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                    </div>

                    <label>Propósito del Servicio</label>
                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="raza_animal">Raza Esperada</label>
                            <select name="raza_animal" id="raza_animal" class="form-control bbb" tabindex="2">
                                <option></option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label for="aptitud_animal">Aptitud Esperada</label>
                            <select name="aptitud_animal" id="aptitud_animal" class="form-control bbb" tabindex="7">
                                <option></option>
                            </select>
                            <div id='ms_aptitud_animal'></div>
                        </div>
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="limpiar_todo" type="button" class="btn btn-warning" tabindex="90">
                <i class="fa fa-fw fa-refresh"></i> Limpiar todo el formulario
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
<script src="../../js/servicio-selector.js?v=16"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
var buscarx = 0;
var ID_GLOBAL = 0;

var buscarx_m = 0;
var ID_GLOBAL_M = 0;

var buscarx_dona = 0;
var ID_GLOBAL_DONA = 0;
var fundo = <?php echo $_SESSION['mfundos_id']; ?>;
LimpiarCampos();
BloquearCampos();

document.getElementById("fecha_fpp").disabled = true;
document.getElementById("nro_servicio").disabled = true;
document.getElementById("periodo_palpacion").disabled = true;
document.getElementById("limpiar_todo").disabled = false;
document.getElementById("tipo_servicio").disabled = false;
document.getElementById("save").disabled = true;

$.post("../../controllers/mtiposervicios_controller", {
    action: "get_servicio_animal"
}).done(function(data) {
    $("#tipo_servicio").html(data);
});
$.post("../../controllers/maptitudes_controller", {
    action: "get_aptitud"
}).done(function(data) {
    $("#aptitud_animal").html(data);
});

$.post("../../controllers/mrazas_controller", {
    action: "get_razas"
}).done(function(data) {
    $("#raza_animal").html(data);
});

$.post("../../controllers/mperiodos_controller", {
    action: "get_periodo_palpacion_det"
}).done(function(data) {
    /* La respuesta puede venir vacía/`null` (p. ej. sin período global para el
       rol) y $.parseJSON lanzaba un error en consola. */
    var parsedJson = null;
    try {
        parsedJson = $.parseJSON(data);
    } catch (e) {
        parsedJson = null;
    }

    if (parsedJson && (typeof parsedJson.periodo_palpacion !== 'undefined')) {
        $("#periodo_palpacion").html(parsedJson.periodo_palpacion);
        $("#periodo_palpacion").val(parsedJson.periodo_palpacion);
    }
});

$("#tipo_servicio").change(function() {
    LimpiarCampos();
    BloquearCampos();

    /* BloquearCampos() apaga el propio selector del tipo: se vuelve a habilitar
       para poder corregir el tipo de servicio sin recargar el formulario. */
    document.getElementById("tipo_servicio").disabled = false;

    aplicarTipoServicio($("#tipo_servicio").val());
});

$("#fecha_servicio").change(function() {
    document.getElementById("save").disabled = false;
});

$("#limpiar_todo").click(function() {
    LimpiarCampos();
    document.getElementById("tipo_servicio").value = '';
    document.getElementById("nro_servicio").value = '';
    document.getElementById("fecha_servicio").value = '';
    document.getElementById("fecha_fpp").value = '';
    document.getElementById("aptitud_animal").value = '';
    document.getElementById("raza_animal").value = '';

    BloquearCampos();

    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("buscar").disabled = true;
    document.getElementById("limpiar").disabled = true;
    document.getElementById("tipo_servicio").disabled = false;
    document.getElementById("limpiar_todo").disabled = false;

    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("nombre_h_donadora").value = "";
    document.getElementById("cod_animal_dona").value = "";

    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById("buscar_dona").disabled = true;

    document.getElementById('dona_codigo').checked = false;
    document.getElementById('dona_nro_arete').checked = false;
    document.getElementById('macho_codigo').checked = false;
    document.getElementById('macho_nro_arete').checked = false;

    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
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
    /* OJO: el original deshabilitaba cod_animal_h (copia/pega): el bloque que
       se limpia es el de la DONADORA. */
    document.getElementById("cod_animal_dona").disabled = true;
    document.getElementById("nombre_h_donadora").value = "";
    document.getElementById("cod_animal_dona").value = "";

    document.getElementById("limpiar_dona").disabled = true;
    document.getElementById("buscar_dona").disabled = true;

    document.getElementById('dona_codigo').checked = false;
    document.getElementById('dona_nro_arete').checked = false;
    DesbloquearDonadora();

    if (sel_donadora) { sel_donadora.vaciar(); }
});

$('#limpiar_m').click(function() {
    document.getElementById('macho_codigo').checked = false;
    document.getElementById('macho_nro_arete').checked = false;
    document.getElementById('macho_codigo').disabled = false;
    document.getElementById('macho_nro_arete').disabled = false;

    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("nombre_m").value = '';
    document.getElementById("cod_animal_m").value = '';
    document.getElementById('cod_animal_m').disabled = true;

    if (sel_macho) { sel_macho.vaciar(); }
});

$('#buscar_dona').click(function() {
    var rgb_animal_h_dona = $("#cod_animal_dona").val();
    ID_GLOBAL_DONA = rgb_animal_h_dona;

    buscarx_dona = buscarx_dona;

    document.getElementById("nombre_h_donadora").value = '';

    $.post("../../controllers/mservicios_controller", {
        action: "get_buscar_rgb_h_dona",
        rgb_animal_h_dona: rgb_animal_h_dona,
        buscarx_dona: buscarx_dona
    }).done(function(data) {
        var respuesta = partesRespuestaBusqueda(data);

        if (respuesta.error) {
            $(".message").html(respuesta.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
            /* Sin datos válidos no hay que seguir con el parseo de la
               respuesta (antes lanzaba un error de JavaScript) */
            return;
        }

        document.getElementById("buscar_dona").disabled = true;
        document.getElementById('dona_codigo').disabled = true;
        document.getElementById('dona_nro_arete').disabled = true;
        document.getElementById("cod_animal_dona").disabled = true;
        document.getElementById("limpiar_dona").disabled = false;
        document.getElementById('b_codigo').disabled = false;
        document.getElementById('b_nro_arete').disabled = false;

        $("#cod_animal_dona").val(respuesta.codigo);
        $("#nombre_h_donadora").val(respuesta.nombre);
    });
});

$("#dona_codigo").click(function() {
    var id_fundo = '0' + fundo + '-0';
    document.getElementById("nombre_m").value = '';
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
    var id_fundo = '0' + fundo + '-0';
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

function LimpiarCampos() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    document.getElementById("cod_animal_m").value = '';
    document.getElementById("nombre_m").value = '';
    document.getElementById("nombre_h").value = '';

    var aviso = document.getElementById('aviso_ciclo');
    if (aviso) { aviso.innerHTML = ''; }

    /* Se limpia directamente: esta función corre al iniciar la página, antes
       de que se defina limpiarDiagnostico() en el bloque de script final. */
    var diag = document.getElementById('diag_hembra');
    if (diag) { diag.innerHTML = ''; }

    document.getElementById('dona_codigo').checked = false;
    document.getElementById('dona_nro_arete').checked = false;

    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;

    document.getElementById("nombre_h_donadora").value = '';
    document.getElementById("cod_animal_dona").value = '';
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

$("#macho_codigo").click(function() {
    var id_fundo = '0' + fundo + '-0';
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

$('#fecha_servicio').change(function(event) {
    var fecha_pp = 0;
    var fecha = 0;
    var dias = 0;
    if (dias != 0) {
        dias = dias;
    } else {
        dias = parseInt($("#periodo_palpacion").val());
    }
    fecha = $("#fecha_servicio").val();

    var array_fecha = fecha.split('-');
    var new_fecha = array_fecha[2] + '-' + array_fecha[1] + '-' + array_fecha[0];

    var fecha_pp = new Date(new_fecha);
    var mifecha = sumarDias(fecha_pp, dias);

    fecha_pp = formatDate(mifecha);

    $("#fecha_fpp").val(fecha_pp);
});

function sumarDias(fecha, dias) {
    fecha.setDate(fecha.getDate() + dias);
    return fecha;
}

function formatDate(mifecha) {
    var d = mifecha,
        dformat = [padLeft(d.getDate()),
            padLeft(d.getMonth() + 1),
            d.getFullYear()
        ].join('-');
    return dformat;
}

function sumarDiasGestacion(fecha, dias) {
    fecha.setDate(fecha.getDate() + dias);
    return fecha;
}

function padLeft(n) {
    return ("00" + n).slice(-2);
}

$('#limpiar').click(function() {
    LimpiarCampos();
    BloquearCampos();
    document.getElementById("nro_servicio").value = '';
    document.getElementById("limpiar").disabled = true;

    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;

    /* Vuelve a dejar los bloques como los pide el tipo de servicio elegido
       (el macho se habilita cuando se valide la hembra) */
    aplicarTipoServicio(tipoServicioActual());

    if (sel_hembra) { sel_hembra.vaciar(); }
    if (sel_macho) { sel_macho.vaciar(); }
    if (sel_donadora) { sel_donadora.vaciar(); }
});

$('#buscar').click(function() {
    var rgb_animal_h = $("#cod_animal_h").val();

    if (rgb_animal_h != "") {
        ID_GLOBAL = rgb_animal_h;
        buscarx = buscarx;
        document.getElementById("nombre_h").value = '';

        var rgb_animal_h = $("#cod_animal_h").val();
        document.getElementById("nombre_h").value = '';

        $.post("../../controllers/mservicios_controller", {
            action: "get_buscar_rgb_h",
            rgb_animal_h: rgb_animal_h,
            buscarx: buscarx
        }).done(function(data) {
            var respuesta = partesRespuestaBusqueda(data);

            if (respuesta.error) {
                $(".message").html(respuesta.mensaje);
                $('.base').unslider('animate:0');
                $('.base').unslider('animate:1');
                setTimeout(function() { $(".alert").alert('close'); }, 3000);
                /* Sin datos válidos no hay que seguir con el parseo de la
                   respuesta (antes lanzaba un error de JavaScript) */
                return;
            }

            document.getElementById("cod_animal_h").disabled = true;
            document.getElementById("buscar").disabled = true;

            document.getElementById('b_codigo').disabled = true;
            document.getElementById('b_nro_arete').disabled = true;
            document.getElementById("limpiar").disabled = false;

            document.getElementById("macho_codigo").disabled = false;
            document.getElementById("macho_nro_arete").disabled = false;

            document.getElementById("fecha_servicio").disabled = false;
            document.getElementById("raza_animal").disabled = false;
            document.getElementById("aptitud_animal").disabled = false;

            $("#cod_animal_h").val(respuesta.codigo);
            $("#nombre_h").val(respuesta.nombre);
            $("#nro_servicio").val(respuesta.nro_servicio);

            /* Aviso: una hembra debe tener un solo servicio vigente */
            consultarAvisoCiclo(respuesta.codigo);
        });
    } else {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Debe Ingresar el Código';
        setTimeout(function() {
            document.getElementById("ms_cod_animal_h").innerHTML = '';
        }, 3000);
    }
});

$('#buscar_m').click(function() {
    var rgb_animal_m = $("#cod_animal_m").val();
    var tipo_s = $("#tipo_servicio").val();

    ID_GLOBAL_M = rgb_animal_m;
    buscarx_m = buscarx_m;
    document.getElementById("nombre_m").value = '';

    $.post("../../controllers/mservicios_controller", {
        action: "get_buscar_rgb_m",
        rgb_animal_m: rgb_animal_m,
        buscarx_m: buscarx_m,
        tipo_s: tipo_s
    }).done(function(data) {
        var respuesta = partesRespuestaBusqueda(data);

        if (respuesta.error) {
            $(".message").html(respuesta.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
            /* Sin datos válidos no hay que seguir con el parseo de la
               respuesta (antes lanzaba un error de JavaScript) */
            return;
        }

        document.getElementById("buscar_m").disabled = true;
        document.getElementById("cod_animal_m").disabled = true;
        document.getElementById("limpiar_m").disabled = false;

        $("#cod_animal_m").val(respuesta.codigo);
        $("#nombre_m").val(respuesta.nombre);
    });
});

$('#fecha_servicio').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fup').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$('#fecha_fpp').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fp').datepicker({
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fppt').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$("#derecha").click(function() {
    if (tick == 0) {
        var cont_alert = $('.bbb').filter(function() { return $(this).val() == ""; }).size();
        document.getElementById('derecha').disabled = true;
        $("#derecha").css("display", "none");
        if (cont_alert > 30) {
            $.confirm({
                title: '¡existen campos vacios !',
                cancelButton: false,
                confirmButton: true,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success',
                content: false
            });
        } else {
            $('.base').unslider('animate:1');
            tick = 1;
            document.getElementById('izquierda').disabled = false;
            $("#izquierda").css("display", "block");
            setTimeout(function() {
                document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 800);
        }
    } else if (tick == 1) {
        var cont_alert3 = $('.ccc').filter(function() { return $(this).val() == ""; }).size();
        if (cont_alert3 > 50) {
            $.confirm({
                title: '¡existen campos vacios !',
                cancelButton: false,
                confirmButton: true,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success',
                content: false
            });
        } else {
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function() {
                document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 800);
        }
    }
});

$("#izquierda").click(function() {
    if (tick == 1) {
        $('.base').unslider('animate:0');
        tick = 0;
        document.getElementById('izquierda').disabled = true;
        $("#izquierda").css("display", "none");
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 800);
    } else if (tick == 2) {
        $('.base').unslider('animate:1');
        tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 800);
    }
});

$("#exit").click(function() {
    $(location).attr('href', '../servicios/');
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
                $.post("../../controllers/mservicios_controller", {
                    action: "add",
                    cod_animal_h: $("#cod_animal_h").val(),
                    cod_animal_m: $("#cod_animal_m").val(),
                    cod_animal_dona: $("#cod_animal_dona").val(),
                    fecha_servicio: $("#fecha_servicio").val(),
                    nro_servicio: $("#nro_servicio").val(),
                    tipo_servicio: $("#tipo_servicio").val(),
                    aptitud_animal: $("#aptitud_animal").val(),
                    raza_animal: $("#raza_animal").val(),
                    fecha_fpp: $("#fecha_fpp").val()
                }).done(function(data) {
                    var parsedJson = $.parseJSON(data);
                    $(".message").html(parsedJson.mensaje);
                    switch (parsedJson.deslizador) {
                        case "1":
                            $('.base').unslider('animate:0');
                            tick = 0;
                            document.getElementById('izquierda').disabled = true;
                            $("#izquierda").css("display", "none");
                            document.getElementById('derecha').disabled = false;
                            $("#derecha").css("display", "block");
                            break;
                        case "2":
                            $('.base').unslider('animate:1');
                            tick = 1;
                            document.getElementById('izquierda').disabled = false;
                            $("#izquierda").css("display", "block");
                            document.getElementById('derecha').disabled = true;
                            $("#derecha").css("display", "none");
                            break;
                    }
                    if (parsedJson.resultado != 'error') {
                        $('.base').unslider('animate:0');
                        setTimeout(function() {
                            $(location).attr('href', './');
                        }, 3000);
                    } else {
                        setTimeout(function() {
                            $(".alert").alert('close');
                            document.getElementById('save').disabled = false;
                        }, 3000);
                    }
                }, "json");
            },
            cancel: function() {
                document.getElementById('save').disabled = false;
            }
        });
    }
});

function esnombre(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {
        document.getElementById('ms_nombre').style.display = 'block';
        document.getElementById("ms_nombre").innerHTML = 'Utilice solo letras mayusculas.';
        return patron.test(n);
    } else {
        document.getElementById("ms_nombre").innerHTML = '';
        return patron.test(n);
    }
}

function BloquearCampos() {
    document.getElementById("limpiar").disabled = true;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
    document.getElementById("fecha_servicio").disabled = true;
    document.getElementById("aptitud_animal").disabled = true;
    document.getElementById("buscar").disabled = false;
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("raza_animal").disabled = true;
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
    BloquearMacho();
}

function DesBloquearCampos() {
    document.getElementById("limpiar").disabled = false;
    document.getElementById("limpiar_m").disabled = false;
    document.getElementById("buscar_m").disabled = false;
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
    document.getElementById("tipo_servicio").disabled = false;
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("raza_animal").disabled = false;
    document.getElementById("fecha_servicio").disabled = false;
    document.getElementById("aptitud_animal").disabled = false;
}

function esnumero_h(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9]$/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Use solo números';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_h").innerHTML = '';
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
    var tipo = $("#tipo_servicio").val();
    var cod_animal_h = $("#cod_animal_h").val();
    var cod_animal_m = $("#cod_animal_m").val();
    var cod_animal_dona = $("#cod_animal_dona").val();
    var fecha_servicio = $("#fecha_servicio").val();

    if (tipo == "" || tipo == null) {
        avisoServicio('Debe indicar el Tipo de Servicio.');
    } else if (cod_animal_h == "") {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = (tipo == "3")
            ? 'Debe seleccionar la Hembra receptora'
            : 'Debe seleccionar la Hembra del servicio';
    } else if (tipo == "3" && cod_animal_dona == "") {
        document.getElementById('ms_cod_animal_dona').style.display = 'block';
        document.getElementById("ms_cod_animal_dona").innerHTML = 'Debe seleccionar la Hembra donadora';
    } else if (tipo != "3" && cod_animal_m == "") {
        document.getElementById('ms_cod_animal_m').style.display = 'block';
        document.getElementById("ms_cod_animal_m").innerHTML = (tipo == "2")
            ? 'Debe seleccionar el Macho donador'
            : 'Debe seleccionar el Macho que realiza la monta';
    } else if (fecha_servicio == "") {
        document.getElementById('ms_fecha_servicio').style.display = 'block';
        document.getElementById("ms_fecha_servicio").innerHTML = 'Este Campo es Obligatorio';
    } else {
        guardar = true;
    }

    return guardar;
}
</script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   AUXILIARES — No interfieren con la lógica original
   ═══════════════════════════════════════════════════════════════════════════ */

/* Botón "Regresar" del header envuelve al #exit original */
$(document).on('click', '#exit_top', function() {
    window.location.href = '../servicios/';
});

/* ═══════════════════════════════════════════════════════════════════════════
   DETECTOR DE DESBLOQUEO
   Resalta cualquier control que pase de disabled → enabled:
     - Inputs / selects / textareas  → flash verde con borde persistente
     - Botones                        → outline + animación de escala
     - Checkboxes + su label          → pulso + label verde en negrita
   ═══════════════════════════════════════════════════════════════════════════ */
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
 
    var DURATION = 4800;   // ms que dura el resaltado

    function highlight(el) {
        var tag = el.tagName;

        /* --- BOTONES --- */
        if (tag === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function () { el.classList.remove('crud-just-unlocked-btn'); }, DURATION);
            return;
        }

        /* --- CHECKBOXES --- */
        if (el.type === 'checkbox') {
            el.classList.add('crud-just-unlocked-check');
            var label = el.closest('label');
            if (label) {
                label.classList.add('crud-just-unlocked-check-label');
                setTimeout(function () {
                    el.classList.remove('crud-just-unlocked-check');
                    label.classList.remove('crud-just-unlocked-check-label');
                }, DURATION);
            } else {
                setTimeout(function () { el.classList.remove('crud-just-unlocked-check'); }, DURATION);
            }
            return;
        }

        /* --- INPUTS / SELECTS / TEXTAREAS --- */
        el.classList.add('crud-unlocked');
        setTimeout(function () { el.classList.remove('crud-unlocked'); }, DURATION);
    }

    function check() {
        var fields = document.querySelectorAll(
            '.crud-form input, .crud-form select, .crud-form textarea, .crud-form button'
        );
        Array.prototype.forEach.call(fields, function (el) {
            var wasDisabled = seen.get(el);
            var isDisabled = el.disabled;

            if (wasDisabled === true && isDisabled === false) {
                highlight(el);
            }
            seen.set(el, isDisabled);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        check();

        var observer = new MutationObserver(function (mutations) {
            var touched = false;
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'disabled') touched = true;
            });
            if (touched) check();
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['disabled'],
            subtree: true
        });
    });
})();

/* ═══════════════════════════════════════════════════════════════════════════
   SELECTOR DE ANIMALES (listado filtrable) Y ORDEN DEL FORMULARIO
   El tipo de servicio es el punto de partida: define qué animales hay que
   elegir (macho / donadora) y, por lo tanto, qué tarjetas se muestran.
   ═══════════════════════════════════════════════════════════════════════════ */
var sel_hembra = null;
var sel_macho = null;
var sel_donadora = null;

/* Último tipo de servicio aplicado (para no recargar listados de más) */
var tipoAplicado = null;

function tipoServicioActual() {
    return $("#tipo_servicio").val();
}

function resumenTipoServicio(t) {
    if (t == '1') {
        return 'MONTA: seleccione los datos de la hembra y el macho que realiza la monta.';
    }
    if (t == '2') {
        return 'INSEMINACIÓN ARTIFICIAL: seleccione los datos de la hembra receptora y del macho donador.';
    }
    if (t == '3') {
        return 'TRANSFERENCIA DE EMBRIONES: seleccione los datos de la hembra receptora y de la hembra donadora.';
    }

    return 'Seleccione el tipo de servicio para habilitar el resto del formulario.';
}

/* Aplica el tipo de servicio: muestra SOLO las tarjetas que corresponden,
   numera los paneles y habilita los bloques necesarios.
     MONTA         → datos de la hembra + macho que realiza la monta
     INSEMINACIÓN  → datos de la hembra receptora + macho donador
     TRANSFERENCIA → hembra receptora + datos de la hembra donadora
   La HEMBRA DONADORA solo participa en la transferencia de embriones (para la
   inseminación se usa el semen del macho donador). */
function aplicarTipoServicio(t) {
    var esIA = (t == '2');
    var esTE = (t == '3');
    var hayTipo = (t != '' && t != null);
    var cambioTipo = (tipoAplicado !== t);

    tipoAplicado = t;

    /* ---- Tarjetas visibles ---- */
    $("#card_hembra").toggle(hayTipo);
    $("#card_dona").toggle(esTE);
    $("#card_macho").toggle((t == '1') || esIA);
    $("#card_datos").toggle(hayTipo);

    /* ---- Títulos (la numeración depende de cuántas tarjetas se muestran) ---- */
    if (esIA) {
        $("#titulo_hembra").text('2 · Datos de la Hembra Receptora');
        $("#titulo_macho").text('3 · Datos del Macho Donador');
        $("#titulo_datos").text('4 · Datos del Servicio');
    } else if (esTE) {
        $("#titulo_hembra").text('2 · Hembra Receptora');
        $("#titulo_dona").text('3 · Datos de la Hembra Donadora');
        $("#titulo_datos").text('4 · Datos del Servicio');
    } else {
        $("#titulo_hembra").text('2 · Datos de la Hembra');
        $("#titulo_macho").text('3 · Macho que realiza la monta');
        $("#titulo_datos").text('4 · Datos del Servicio');
    }

    $("#resumen_tipo").text(resumenTipoServicio(t));

    /* ---- Bloques habilitados: el macho se habilita al validar la hembra ---- */
    if (hayTipo) {
        DesbloquearMadre();

        if (esTE) {
            DesbloquearDonadora();
        } else {
            BloquearDonadora();
        }
    }

    /* ---- Listados: el de hembras es el mismo; macho y donadora dependen del tipo ---- */
    if (sel_hembra) {
        if (cambioTipo) { sel_hembra.vaciar(); }
        sel_hembra.cargar();
    }

    if (sel_macho && ((t == '1') || esIA) && cambioTipo) {
        sel_macho.vaciar();
        sel_macho.recargar();
    }

    if (sel_donadora && esTE && cambioTipo) {
        sel_donadora.vaciar();
        sel_donadora.recargar();
    }
}

function iniciarSelectores() {
    $("#sel_h").bovsSelectorAnimal({
        rol: 'receptora',
        tipo: tipoServicioActual,
        onElegir: function (fila) { elegirDelListado('receptora', fila); },
        /* Si lo escrito no coincide con ninguna hembra del listado,
           se explica el motivo (otro fundo, no activa, ciclo abierto…). */
        onSinResultados: function (texto) { diagnosticarHembra(texto); },
        onConResultados: function () { limpiarDiagnostico(); }
    });

    $("#sel_d").bovsSelectorAnimal({
        rol: 'donadora',
        tipo: tipoServicioActual,
        onElegir: function (fila) { elegirDelListado('donadora', fila); }
    });

    $("#sel_m").bovsSelectorAnimal({
        rol: 'macho',
        tipo: tipoServicioActual,
        onElegir: function (fila) { elegirDelListado('macho', fila); }
    });

    /* El plugin devuelve el objeto jQuery: la instancia vive en el .data() */
    sel_hembra = $("#sel_h").data('bovsSelectorAnimal');
    sel_donadora = $("#sel_d").data('bovsSelectorAnimal');
    sel_macho = $("#sel_m").data('bovsSelectorAnimal');

    aplicarTipoServicio(tipoServicioActual());
}

/* Lleva la fila elegida en el listado a la búsqueda exacta del formulario, de
   modo que se reutilizan TODAS las validaciones originales del módulo
   (edad mínima, ciclo reproductivo, estado fisiológico, estatus del animal…).
   OJO: no se usa .trigger('click') en el checkbox porque jQuery, además de los
   manejadores, ejecuta el clic nativo y vuelve a apagar la casilla (quedaba
   buscarx = 0 y la búsqueda se hacía sin filtro). */
function modoCodigo(cual) {
    if (cual == 'receptora') {
        $("#b_nro_arete").prop('checked', false);
        $("#b_codigo").prop('checked', true);
        $("#nombre_h").prop('disabled', false).val('');
        buscarx = 1;
    } else if (cual == 'donadora') {
        $("#dona_nro_arete").prop('checked', false);
        $("#dona_codigo").prop('checked', true);
        $("#nombre_h_donadora").prop('disabled', false).val('');
        buscarx_dona = 1;
    } else {
        $("#macho_nro_arete").prop('checked', false);
        $("#macho_codigo").prop('checked', true);
        $("#nombre_m").prop('disabled', false).val('');
        buscarx_m = 1;
    }
}

function elegirDelListado(cual, fila) {
    var chk, campo, boton;

    if (cual == 'receptora') {
        chk = '#b_codigo';
        campo = '#cod_animal_h';
        boton = '#buscar';
    } else if (cual == 'donadora') {
        chk = '#dona_codigo';
        campo = '#cod_animal_dona';
        boton = '#buscar_dona';
    } else {
        chk = '#macho_codigo';
        campo = '#cod_animal_m';
        boton = '#buscar_m';
    }

    if ($(chk).prop('disabled')) {
        /* El bloque puede estar bloqueado por dos motivos distintos */
        var texto = 'Indique el tipo de servicio antes de elegir el animal.';

        if (cual == 'macho') {
            texto = 'Primero valide la Hembra que recibe el servicio.';
        } else if ($(campo).val() != '') {
            texto = (cual == 'donadora')
                ? 'Para cambiar la Hembra donadora use el botón Limpiar de este bloque.'
                : 'Para cambiar la Hembra del servicio use el botón Limpiar de este bloque.';
        }

        avisoServicio(texto);
        return;
    }

    modoCodigo(cual);

    /* Si la hembra del servicio cambia, el macho ya validado se descarta: la
       validación del macho sólo se habilita después de la hembra. */
    if ((cual != 'macho') && ($("#cod_animal_m").val() != '') && ($("#cod_animal_m").val() != undefined)) {
        limpiarMachoElegido();
    }

    $(campo).prop('disabled', false).val(fila.codigo);
    $(boton).prop('disabled', false).trigger('click');
}

/* Deja el bloque del macho como recién abierto el formulario */
function limpiarMachoElegido() {
    $("#macho_codigo, #macho_nro_arete").prop('checked', false).prop('disabled', true);
    $("#cod_animal_m").val('').prop('disabled', true);
    $("#nombre_m").val('');
    $("#buscar_m").prop('disabled', true);
    $("#limpiar_m").prop('disabled', true);

    if (sel_macho) { sel_macho.vaciar(); }
}

function avisoServicio(texto) {
    $(".message").html('<div class="alert alert-warning alert-dismissable">' +
        '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>' +
        '<h4><i class="icon fa fa-warning"></i> Alerta!</h4>' + texto + '</div>');

    try {
        $('.base').unslider('animate:0');
        $('.base').unslider('animate:1');
    } catch (e) { }

    setTimeout(function () { $(".alert").alert('close'); }, 3000);
}

/* Los controladores responden con json_encode() SIN JSON_UNESCAPED_UNICODE: la
   respuesta de las búsquedas exactas es la CADENA "codigo*nombre*nro_servicio"
   con los no-ASCII escapados ("Ma\u00f1anita"). El código original recortaba las
   comillas del texto crudo (.split('"')) y dejaba el escape a la vista. Aquí se
   decodifica el JSON de verdad y se devuelven las partes por separado. */
function partesRespuestaBusqueda(data) {
    var valor = data;

    if (typeof valor === 'string') {
        try {
            valor = $.parseJSON(valor);
        } catch (e) {
            /* No era JSON (respuesta HTML de error, por ejemplo) */
        }
    }

    if (valor && (typeof valor === 'object') && (valor.resultado == 'error')) {
        return { error: true, mensaje: valor.mensaje };
    }

    if (valor === null || (typeof valor === 'undefined') || (valor === '')) {
        return { error: true, mensaje: 'No se pudo obtener la información del animal.' };
    }

    var partes = String(valor).split('*');

    return {
        error:          false,
        codigo:         $.trim(partes[0] || ''),
        nombre:         $.trim(partes[1] || ''),
        nro_servicio:   $.trim(partes[2] || '')
    };
}

/* ================================================================
   DIAGNÓSTICO DE LA HEMBRA ESCRITA A MANO
   Si el texto no coincide con ninguna fila del listado, se consulta al
   servidor por qué: otro fundo, no activa, macho, menor de 12 meses o
   con un servicio abierto pendiente de cerrar.
   ================================================================ */
var diagHemTimer = null;

function limpiarDiagnostico() {
    var caja = document.getElementById('diag_hembra');
    if (caja) { caja.innerHTML = ''; }
}

function diagnosticarHembra(texto) {
    var caja = document.getElementById('diag_hembra');
    if (!caja) return;

    var t = $.trim(texto || '');

    clearTimeout(diagHemTimer);

    /* Con menos de 3 caracteres el filtro es demasiado amplio para diagnosticar */
    if (t.length < 3) {
        caja.innerHTML = '';
        return;
    }

    diagHemTimer = setTimeout(function () {
        $.post("../../controllers/mservicios_controller", {
            action: 'diagnostico_hembra',
            texto: t
        }).done(function (data) {
            var r = data;

            if (typeof r === 'string') {
                try { r = $.parseJSON(r); } catch (e) { r = null; }
            }

            caja.innerHTML = (r && r.ok && r.html) ? r.html : '';
        });
    }, 450);
}

/* Consulta si la hembra elegida arrastra ciclos abiertos y muestra el aviso
   bajo el campo del animal. Es solo informativo: no bloquea el registro. */
function consultarAvisoCiclo(codigo) {
    var caja = document.getElementById('aviso_ciclo');
    if (!caja) return;

    caja.innerHTML = '';

    if (!codigo) return;

    $.post("../../controllers/mservicios_controller", {
        action: 'avisos_ciclo_hembra',
        cod_animal_h: codigo
    }).done(function (data) {
        var r = data;

        if (typeof r === 'string') {
            try { r = $.parseJSON(r); } catch (e) { r = null; }
        }

        if (r && r.ok && r.html) {
            caja.innerHTML = r.html;
        }
    });
}

$(function () {
    iniciarSelectores();

    /* Al confirmar la búsqueda exacta, el listado marca la fila del animal */
    $('#buscar').on('click', function () {
        if (sel_hembra) { sel_hembra.marcar($("#cod_animal_h").val()); }
    });

    $('#buscar_m').on('click', function () {
        if (sel_macho) { sel_macho.marcar($("#cod_animal_m").val()); }
    });

    $('#buscar_dona').on('click', function () {
        if (sel_donadora) { sel_donadora.marcar($("#cod_animal_dona").val()); }
    });
});
</script>

<?php include_once("../layouts/pie.php") ?>