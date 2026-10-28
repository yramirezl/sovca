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
   ESTILOS ESPECÍFICOS DEL FORMULARIO DE NACIMIENTOS
   (tabla de terneros + buscador de servicios gestantes)
   ============================================================ */
.nac-terneros-hint { display:block; margin:2px 0 8px; color:#687780; font-size:12px; }
.nac-terneros-tope { display:none; margin:-4px 0 8px; color:#b94a48; font-size:12px; font-weight:600; }
.nac-terneros-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin:0 0 8px; }
.nac-terneros-toolbar > label { flex:1 1 auto; min-width:0; }
.nac-terneros-toolbar > .btn { flex:0 0 auto; }
#tabla_terneros { width:100%; min-width:540px; margin-bottom:0; table-layout:fixed; }
#tabla_terneros th { white-space:nowrap; font-size:12px; padding:7px 6px; }
#tabla_terneros td { vertical-align:middle !important; padding:6px; }
#tabla_terneros th:nth-child(1), #tabla_terneros td:nth-child(1) { width:5%; text-align:center; }
#tabla_terneros th:nth-child(2) { width:17%; }
#tabla_terneros th:nth-child(3) { width:17%; }
#tabla_terneros th:nth-child(4) { width:13%; }
#tabla_terneros th:nth-child(5) { width:20%; }
#tabla_terneros th:nth-child(6) { width:23%; }
#tabla_terneros th:nth-child(7), #tabla_terneros td:nth-child(7) { width:5%; text-align:center; }
#tabla_terneros .ternero-input { width:100%; height:32px; padding:5px 8px; font-size:12.5px; }
.ternero-ordinal { font-weight:700; color:#64748b; }
.ternero-cell-id { position:relative; }
.ternero-id-error { position:absolute; left:6px; right:6px; bottom:2px; min-height:0; color:#b94a48; font-size:10px; line-height:12px; }

.nac-servicio-buscador { position:relative; margin:0 0 14px; }
.nac-servicio-resultados {
    display:none; position:absolute; top:100%; left:0; right:0; z-index:1050;
    max-height:260px; overflow-y:auto; background:#fff; border:1px solid #d8e0e4;
    box-shadow:0 5px 14px rgba(0,0,0,.12);
}
.nac-servicio-resultados.open { display:block; }
.nac-servicio-opcion {
    display:block; width:100%; padding:9px 11px; border:0;
    border-bottom:1px solid #edf0f2; background:#fff; text-align:left;
}
.nac-servicio-opcion:hover, .nac-servicio-opcion:focus { background:#eef7fc; }
.nac-servicio-opcion strong { display:block; color:#234e35; }
.nac-servicio-opcion small { color:#65747c; }
.nac-servicio-seleccionado {
    display:none; margin:8px 0; padding:8px 10px;
    border-left:3px solid #2f8f4a; background:#f1f8f3; font-size:12px;
}

/* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
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
        <span class="crud-form__icon"><i class="fa fa-plus-circle"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Nacimiento</h3>
            <p class="crud-form__subtitle">Registre el parto y los terneros/as del mismo</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="servicio_gestante_id" value="">

        <div class="crud-form__grid" id="grid_nacimientos_registrar">

            <!-- ========== CARD 1: Datos de la Madre ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3>Datos de la Madre</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field nac-servicio-buscador">
                        <label for="buscar_servicio_gestante">Buscar servicio gestante (7–10 meses)</label>
                        <div class="input-group">
                            <input type="search" id="buscar_servicio_gestante" class="form-control"
                                   placeholder="Código de servicio, código, nombre o arete de la hembra" autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" id="limpiar_servicio_gestante" class="btn btn-default" disabled>
                                    <i class="fa fa-times"></i> Limpiar
                                </button>
                            </span>
                        </div>
                        <div id="servicios_gestantes_resultados" class="nac-servicio-resultados"></div>
                        <div id="servicio_gestante_seleccionado" class="nac-servicio-seleccionado"></div>
                        <small class="text-muted">Seleccionar un servicio completará la madre y el padre. También puedes usar la búsqueda manual inferior.</small>
                    </div>

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
                        <label>Nro. Arete-Chip / Cód
                            <a data-toggle="tooltip" title="Si el servicio fue por transferencia de embriones, indica los datos de la madre donadora.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
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
                        <label>Nro. Arete-Chip / Cód
                            <a data-toggle="tooltip" title="Indica los datos de la hembra que lleva la preñez, ya sea por monta del toro o por transferencia de embriones.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <div class="crud-check-group">
                            <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                            <label><input type="checkbox" id="b_codigo"> Código</label>
                        </div>
                        <input type="text" id="cod_animal_h" class="form-control"
                               placeholder="Código de la HEMBRA"
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

            <!-- ========== CARD 2: Datos del Padre + Terneros ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-mars"></i>
                    <h3>Datos del Padre / Terneros</h3>
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

                    <!-- ===== Terneros ===== -->
                    <div class="nac-terneros-toolbar">
                        <label style="font-size:1.25rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; margin:0;">
                            Terneros / As del Parto
                        </label>
                        <button id="agregar_ternero" type="button" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Agregar ternero/a
                        </button>
                    </div>
                    <small class="nac-terneros-hint">Registra aquí las crías del mismo parto (máximo 5). Los datos de la madre, el padre, el servicio y la fecha se guardan una sola vez.</small>
                    <small class="nac-terneros-tope" id="nac_terneros_tope">
                        <i class="fa fa-exclamation-circle"></i>
                        <span id="nac_terneros_tope_texto">Ya registraste el máximo de crías posibles de este parto.</span>
                    </small>

                    <div class="table-responsive">
                        <table id="tabla_terneros" class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>¿Nació?</th>
                                    <th>Sexo</th>
                                    <th>Peso al nacer</th>
                                    <th>ID / Arete</th>
                                    <th>Aptitud</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="terneros_tbody"></tbody>
                        </table>
                    </div>
                    <select id="aptitud_catalogo" style="display:none;">
                        <option value="">Seleccione</option>
                    </select>

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
            <button id="save" type="button" class="btn btn-success" tabindex="11">
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
var serviciosGestantesDisponibles = [];
var temporizadorBusquedaGestante = null;
var solicitudBusquedaGestante = null;
var tipoServicioGestanteSeleccionado = '';

/* Un parto admite como máximo 5 crías: en ganadería ovina y caprina los
   partos múltiples (2 a 5 crías) son habituales. Es el mismo tope que
   aplica la app sin conexión y el controlador al guardar. */
var MAX_TERNEROS = 5;
var ternerosBloqueados = true;

function maxTernerosAlcanzado() {
    return $('#terneros_tbody .ternero-row').length >= MAX_TERNEROS;
}

/* El botón "Agregar ternero/a" depende de dos cosas: que el bloque del
   parto esté habilitado y que no se haya llegado al máximo de crías. */
function actualizarBotonAgregarTernero() {
    var tope = maxTernerosAlcanzado();

    $('#agregar_ternero')
        .prop('disabled', ternerosBloqueados || tope)
        .attr('title', tope ? 'Máximo ' + MAX_TERNEROS + ' crías por parto' : '');

    $('#nac_terneros_tope').toggle(tope);
    $('#nac_terneros_tope_texto').text(
        'Ya registraste las ' + MAX_TERNEROS + ' crías posibles de este parto. '
        + 'Quita una para poder agregar otra.'
    );
}

function escaparHtmlNacimiento(valor) {
    return $('<div>').text(valor === null || valor === undefined ? '' : String(valor)).html();
}

function actualizarNumeracionTerneros() {
    var filas = $('#terneros_tbody .ternero-row');
    $('#terneros_total').text(filas.length);
    filas.each(function(index) {
        $(this).find('.ternero-ordinal').text(index + 1);
        $(this).find('.quitar-ternero').prop('disabled', filas.length <= 1);
    });
    actualizarBotonAgregarTernero();
}

function agregarFilaTernero(datos) {
    datos = datos || {};
    var opcionesAptitud = $('#aptitud_catalogo').html() || '<option value="">Seleccione</option>';
    var fila = $('<tr class="ternero-row">' +
        '<td class="ternero-ordinal"></td>' +
        '<td><select class="form-control input-sm ternero-input ternero-nacio"><option value="">Seleccione</option><option value="1">Vivo</option><option value="2">Muerto</option></select></td>' +
        '<td><select class="form-control input-sm ternero-input ternero-sexo"><option value="">Seleccione</option><option value="1">Hembra</option><option value="2">Macho</option></select></td>' +
        '<td><input type="text" class="form-control input-sm ternero-input ternero-peso" placeholder="Peso" onpaste="return false" onkeypress="return filterFloat(event,this)"></td>' +
        '<td class="ternero-cell-id"><input type="text" class="form-control input-sm ternero-input ternero-id" placeholder="ID / Arete" onpaste="return false" onkeypress="return es_id_ternero(event)"><small class="ternero-id-error"></small></td>' +
        '<td><select class="form-control input-sm ternero-input ternero-aptitud">' + opcionesAptitud + '</select></td>' +
        '<td class="ternero-cell-accion"><button type="button" class="btn btn-danger btn-xs quitar-ternero" title="Quitar ternero"><i class="fa fa-trash"></i></button></td>' +
    '</tr>');
    $('#terneros_tbody').append(fila);
    fila.find('.ternero-nacio').val(datos.estatus_nacido || '');
    fila.find('.ternero-sexo').val(datos.sexo || '');
    fila.find('.ternero-peso').val(datos.peso_nacimiento || '');
    fila.find('.ternero-id').val(datos.id_ternero || '');
    fila.find('.ternero-aptitud').val(datos.aptitud || '');
    // Las crías se pueden cargar sin pasos previos: el único dato que
    // habilita el resto de la fila es "¿Nació?" (ver el handler change).
    fila.find('.ternero-nacio').prop('disabled', false);
    fila.find('.ternero-input').not('.ternero-nacio').prop('disabled', !datos.estatus_nacido);
    actualizarNumeracionTerneros();
}

function reiniciarListaTerneros() {
    $('#terneros_tbody').empty();
    ternerosBloqueados = true;
    agregarFilaTernero();
    actualizarBotonAgregarTernero();
}

function obtenerTernerosFormulario() {
    var terneros = [];
    $('#terneros_tbody .ternero-row').each(function() {
        var fila = $(this);
        terneros.push({
            estatus_nacido: fila.find('.ternero-nacio').val(),
            sexo: fila.find('.ternero-sexo').val(),
            peso_nacimiento: fila.find('.ternero-peso').val().trim(),
            id_ternero: fila.find('.ternero-id').val().trim(),
            aptitud: fila.find('.ternero-aptitud').val()
        });
    });
    return terneros;
}

function buscarServiciosGestantesWeb() {
    var lista = $('#servicios_gestantes_resultados');
    if (solicitudBusquedaGestante) solicitudBusquedaGestante.abort();
    lista.html('<div class="nac-servicio-opcion text-muted">Buscando servicios gestantes...</div>').addClass('open');
    solicitudBusquedaGestante = $.post('../../controllers/mnacimientos_controller', {
        action: 'buscar_servicios_gestantes',
        termino: $('#buscar_servicio_gestante').val().trim()
    }, null, 'json').done(function(resp) {
        serviciosGestantesDisponibles = resp && resp.ok ? (resp.servicios || []) : [];
        lista.empty();
        if (!serviciosGestantesDisponibles.length) {
            lista.html('<div class="nac-servicio-opcion text-muted">No hay servicios gestantes de 7 a 10 meses que coincidan en este fundo.</div>').addClass('open');
            return;
        }
        serviciosGestantesDisponibles.forEach(function(servicio) {
            var opcion = $('<button type="button" class="nac-servicio-opcion">');
            opcion.attr('data-servicio-id', servicio.id);
            opcion.html('<strong>' + escaparHtmlNacimiento(servicio.codigo_servicio || 'Servicio') + ' · ' + escaparHtmlNacimiento(servicio.nombre_hembra || 'Hembra sin nombre') + '</strong>' +
                '<small>Código ' + escaparHtmlNacimiento(servicio.codigo_rgb_id_animal_hembra) + ' · Arete ' + escaparHtmlNacimiento(servicio.arete_hembra || '—') + ' · Gestación ' + escaparHtmlNacimiento(servicio.meses_gestacion) + ' meses · Servicio ' + escaparHtmlNacimiento(servicio.nro_parto) + '</small>');
            lista.append(opcion);
        });
        lista.addClass('open');
    }).fail(function(xhr, estado) {
        if (estado === 'abort') return;
        lista.html('<div class="nac-servicio-opcion text-danger">No se pudieron consultar los servicios. Intenta de nuevo.</div>').addClass('open');
    });
}

function seleccionarServicioGestanteWeb(servicio) {
    LimpiarCampos();
    BloquearCampos();
    $('#servicio_gestante_id').val(servicio.id);
    tipoServicioGestanteSeleccionado = String(servicio.tipo_servicio || '');
    $('#tipo_servicio').val(tipoServicioGestanteSeleccionado);
    $('#cod_animal_h').val(servicio.codigo_rgb_id_animal_hembra || '').prop('disabled', false).prop('readonly', true);
    $('#nombre_h').val(servicio.nombre_hembra || '').prop('disabled', false).prop('readonly', true);
    $('#cod_animal_m').val(servicio.codigo_rgb_id_animal_macho || '').prop('disabled', false).prop('readonly', true);
    $('#nombre_m').val(servicio.nombre_macho || '').prop('disabled', false).prop('readonly', true);
    $('#cod_animal_dona').val(servicio.codigo_rgb_id_animal_dona || '').prop('disabled', false).prop('readonly', true);
    $('#nombre_h_donadora').val(servicio.nombre_donadora || '').prop('disabled', false).prop('readonly', true);
    $('#nro_parto').val(servicio.nro_parto || 1).prop('disabled', false);
    // Con el servicio gestante elegido ya se puede completar el parto
    // (crías, propósito y fecha de secado). Antes esto dependía de la
    // pregunta "¿Entrará a producción de leche?".
    habilitarBloquePartoNacimiento();
    $('#tipo_servicio').prop('disabled', true);
    $('#buscar, #limpiar, #buscar_m, #limpiar_m, #buscar_dona, #limpiar_dona, #b_codigo, #b_nro_arete, #macho_codigo, #macho_nro_arete').prop('disabled', true);
    $('#fecha_parto').prop('disabled', false);
    $('#buscar_servicio_gestante').val(servicio.codigo_servicio || 'Servicio gestante seleccionado').prop('readonly', true);    $('#servicios_gestantes_resultados').removeClass('open').empty();
    $('#servicio_gestante_seleccionado').text('Servicio ' + (servicio.codigo_servicio || servicio.id) + ' · Fecha ' + (servicio.fecha_servicio || 'no disponible') + ' · Gestación ' + servicio.meses_gestacion + ' meses · Parto #' + servicio.nro_parto).show();
    $('#limpiar_servicio_gestante').prop('disabled', false);
}

function limpiarServicioGestanteWeb() {
    $('#servicio_gestante_id').val('');
    tipoServicioGestanteSeleccionado = '';
    $('#tipo_servicio').prop('disabled', false).val('');
    $('#cod_animal_h, #nombre_h, #cod_animal_m, #nombre_m, #cod_animal_dona, #nombre_h_donadora').prop('readonly', false);
    LimpiarCampos();
    BloquearCampos();
    $('#buscar_servicio_gestante').val('').prop('readonly', false);
    $('#servicio_gestante_seleccionado').hide().text('');
    $('#limpiar_servicio_gestante').prop('disabled', true);
}

LimpiarCampos();
BloquearCampos();

$.post("../../controllers/mtiposervicios_controller", {
    action: "get_servicio_animal"
}).done(function(data) {
    var tipoServicioActual = $("#tipo_servicio").val();
    $("#tipo_servicio").html(data);
    var tipoServicioCargado = tipoServicioGestanteSeleccionado || tipoServicioActual;
    if (tipoServicioCargado) $("#tipo_servicio").val(tipoServicioCargado);
    $("#tipo_servicio").prop('disabled', !!$('#servicio_gestante_id').val());
});
$.post("../../controllers/maptitudes_controller", {
    action: "get_aptitud"
}).done(function(data) {
    $("#aptitud_catalogo").html(data);
    $('#terneros_tbody .ternero-aptitud').each(function() {
        var seleccion = $(this).val();
        $(this).html(data).val(seleccion);
    });
});

$('#agregar_ternero').on('click', function() {
    if ($(this).prop('disabled')) return;

    if (maxTernerosAlcanzado()) {
        actualizarBotonAgregarTernero();
        return;
    }

    agregarFilaTernero();
});
$('#terneros_tbody').on('click', '.quitar-ternero', function() {
    if ($('#terneros_tbody .ternero-row').length > 1) {
        $(this).closest('.ternero-row').remove();
        actualizarNumeracionTerneros();
    }
});
$('#terneros_tbody').on('change', '.ternero-nacio', function() {
    var fila = $(this).closest('.ternero-row');
    var habilitar = this.value === '1' || this.value === '2';
    fila.find('.ternero-input').not('.ternero-nacio').prop('disabled', !habilitar);
});
$('#terneros_tbody').on('blur', '.ternero-id', function() {
    var input = $(this);
    var valor = input.val().trim();
    input.closest('td').find('.ternero-id-error').text('');
    if (!valor) return;
    $.post('../../controllers/mnacimientos_controller', { action: 'search_id_ternero', id_ternero: valor }, function(data) {
        if (data && Number(data.id_ternero) !== 0) {
            input.closest('td').find('.ternero-id-error').text('Este ID/arete ya está registrado en este fundo.');
        }
    }, 'json');
});
$('#buscar_servicio_gestante').on('focus', function() {
    if (!$('#servicio_gestante_id').val()) buscarServiciosGestantesWeb();
}).on('input', function() {
    if ($('#servicio_gestante_id').val()) return;
    clearTimeout(temporizadorBusquedaGestante);
    temporizadorBusquedaGestante = setTimeout(buscarServiciosGestantesWeb, 250);
}).on('keydown', function(event) {
    if (event.key === 'Escape') $('#servicios_gestantes_resultados').removeClass('open');
});
$('#servicios_gestantes_resultados').on('click', 'button.nac-servicio-opcion', function() {
    var idServicio = Number($(this).attr('data-servicio-id'));
    var servicio = serviciosGestantesDisponibles.find(function(item) { return Number(item.id) === idServicio; });
    if (servicio) seleccionarServicioGestanteWeb(servicio);
});
$('#limpiar_servicio_gestante').on('click', limpiarServicioGestanteWeb);
$(document).on('click', function(event) {
    if (!$(event.target).closest('.nac-servicio-buscador').length) $('#servicios_gestantes_resultados').removeClass('open');
});

$.post("../../controllers/mperiodos_controller", {
    action: "get_periodos_lactancia"
}).done(function(data) {
    var parsedJson = $.parseJSON(data);
    periodoLactanciaDias = parseInt(parsedJson.periodo_lactancia_dias, 10) || 0;
    periodoProduccionLecheDias = parseInt(parsedJson.periodo_prodleche_dias, 10) || 0;
    $("#periodo_prodleche").val(parsedJson.periodo_lactancia);
    actualizarFechaSecadoNacimiento();
});

$("#tipo_servicio").change(function() {
    LimpiarCampos();
    BloquearCampos();
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

$('#fecha_parto').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_secado').datepicker({
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
            $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
        } else {
            $('.base').unslider('animate:1');
            tick = 1;
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
        $('.base').unslider('animate:0');
        tick = 0;
        document.getElementById('izquierda').disabled = true;
        $("#izquierda").css("display", "none");
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
    } else if (tick == 2) {
        $('.base').unslider('animate:1');
        tick = 1;
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

$('#save').click(function() {
    var terneros = obtenerTernerosFormulario();
    var idsTerneros = {};
    var errorTerneros = '';
    if (!terneros.length) errorTerneros = 'Agrega al menos un ternero/a.';
    terneros.some(function(ternero, index) {
        if (ternero.estatus_nacido !== '1' && ternero.estatus_nacido !== '2') errorTerneros = 'Indica si nació cada ternero/a.';
        else if (ternero.sexo !== '1' && ternero.sexo !== '2') errorTerneros = 'Indica el sexo de cada ternero/a.';
        else if (!ternero.peso_nacimiento) errorTerneros = 'Indica el peso del ternero/a #' + (index + 1) + '.';
        else if (!ternero.aptitud || ternero.aptitud === '*') errorTerneros = 'Indica la aptitud del ternero/a #' + (index + 1) + '.';
        if (!errorTerneros && ternero.id_ternero) {
            var idNormalizado = ternero.id_ternero.toLocaleUpperCase('es');
            if (idsTerneros[idNormalizado]) errorTerneros = 'No repitas el mismo ID/arete en dos crías del mismo parto.';
            idsTerneros[idNormalizado] = true;
        }
        return !!errorTerneros;
    });
    if (errorTerneros) {
        $('.message').html('<div class="alert alert-warning">' + errorTerneros + '</div>');
        return;
    }
    var primerTernero = terneros[0];

    $.confirm({
        title: '¿Está Seguro de Guardar el Registro ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            $.post("../../controllers/mnacimientos_controller", {
                action: "add",
                tipo_servicio: $("#tipo_servicio").val(),
                cod_animal_h: $("#cod_animal_h").val(),
                cod_animal_m: $("#cod_animal_m").val(),
                cod_animal_dona: $("#cod_animal_dona").val(),
                mregservicios_id: $("#servicio_gestante_id").val(),
                terneros: JSON.stringify(terneros),
                id_ternero: primerTernero.id_ternero,
                fecha_parto: $("#fecha_parto").val(),
                nro_parto: $("#nro_parto").val(),
                nacio: primerTernero.estatus_nacido,
                aptitud_animal: primerTernero.aptitud,
                peso_nacer: primerTernero.peso_nacimiento,
                sexo: primerTernero.sexo,
                fecha_secado: $("#fecha_secado").val(),
                proposito: $("#proposito").val(),
                observaciones: $("#observaciones").val()
            }).done(function(data) {
                /* El parseo va dentro de try/catch: si PHP devuelve un error
                   fatal (HTML) el antiguo $.parseJSON lanzaba una excepción
                   silenciosa y el usuario no veía nada. */
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
});

function LimpiarCampos() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    document.getElementById("cod_animal_m").value = '';
    document.getElementById("nombre_m").value = '';
    document.getElementById("fecha_parto").value = '';
    document.getElementById("fecha_secado").value = '';
    reiniciarListaTerneros();
    document.getElementById("proposito").value = '';
    document.getElementById("observaciones").value = '';
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
    $('#terneros_tbody .ternero-input').prop('disabled', true);
    ternerosBloqueados = true;
    actualizarBotonAgregarTernero();
    document.getElementById("proposito").disabled = true;
    document.getElementById("fecha_secado").disabled = true;
    /* Observaciones queda siempre disponible: es texto libre del parto. */
    document.getElementById("periodo_prodleche").disabled = true;
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
    BloquearMacho();
}

function DesBloquearCampos() {
    document.getElementById("limpiar").disabled = false;
    document.getElementById("limpiar_m").disabled = true;
    document.getElementById("buscar_m").disabled = true;
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
    /* El bloque del parto (crías, propósito, secado y observaciones) se
       habilita al fijar el servicio o la fecha del parto. */
    document.getElementById("observaciones").disabled = false;
    document.getElementById("proposito").disabled = false;
    document.getElementById("fecha_secado").disabled = false;
    ternerosBloqueados = false;
    actualizarBotonAgregarTernero();
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

$('#fecha_parto').on('change changeDate', function() {
    habilitarBloquePartoNacimiento();
});

function habilitarBloquePartoNacimiento() {
    // Ya no existe la pregunta "¿Entrará a producción de leche?": al
    // fijar la fecha del parto se habilitan las crías, el propósito y la
    // fecha de secado, y se recalcula el secado con el período del fundo.
    DesBloquearMacho();
    $('.ternero-nacio').prop('disabled', false);
    ternerosBloqueados = false;
    actualizarBotonAgregarTernero();
    document.getElementById("proposito").disabled = false;
    document.getElementById("fecha_secado").disabled = false;
    /* Observaciones es texto libre del parto: debe poder escribirse. Antes
       quedaba deshabilitado por BloquearCampos() y no se volvía a habilitar. */
    document.getElementById("observaciones").disabled = false;
    actualizarFechaSecadoNacimiento();
}

$('#proposito').change(function(event) {
    actualizarFechaSecadoNacimiento();
});

function actualizarFechaSecadoNacimiento() {
    var proposito = $("#proposito").val();
    /* "Entrar en lactancia" = Sí (2): la fecha de secado es fecha de parto
       + el período de lactancia configurado en el fundo (PPL). */
    /* "Entrar en lactancia" = Sí (2): la fecha de secado es fecha de parto
       + el PERÍODO DE LACTANCIA del fundo (PBL). */
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

<?php include_once("../layouts/pie.php") ?>