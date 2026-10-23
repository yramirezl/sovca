<?php
date_default_timezone_set('America/Caracas');

include("../../lib/validar_session.php");
ValidaSession("../login");
require_once '../../lib/estatus_animal_helper.php';
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
   LABEL CON CHECKBOXES AL LADO (Nro Arete / Código)
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
    accent-color: #16a34a;
}

/* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
@keyframes crud-flash-input {
    0%   { box-shadow: 0 0 0 0 rgba(22, 163, 74, .75);
           background-color: #dcfce7;
           border-color: #16a34a; }
    30%  { box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
           background-color: #f0fdf4;
           border-color: #16a34a; }
    70%  { box-shadow: 0 0 0 0 rgba(22, 163, 74, .35);
           background-color: #ffffff; }
    100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
           background-color: inherit; }
}
.crud-form .form-control.crud-unlocked,
.crud-form input.crud-unlocked,
.crud-form select.crud-unlocked,
.crud-form textarea.crud-unlocked,
.crud-form select.btn-sm.crud-unlocked {
    animation: crud-flash-input 2.4s ease !important;
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
    animation: crud-flash-btn 2.4s ease !important;
    outline: 3px solid rgba(22, 163, 74, .60) !important;
    outline-offset: 3px !important;
    position: relative;
    z-index: 2;
}

@keyframes crud-flash-check {
    0%   { transform: scale(1); background-color: rgba(22, 163, 74, .30);
           box-shadow: 0 0 0 0 rgba(22, 163, 74, .55); }
    25%  { transform: scale(1.25); background-color: rgba(22, 163, 74, .55);
           box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
    60%  { transform: scale(1); background-color: rgba(22, 163, 74, .30);
           box-shadow: 0 0 0 0 rgba(22, 163, 74, .30); }
    100% { transform: scale(1); background-color: transparent;
           box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.crud-form input[type="checkbox"].crud-just-unlocked-check {
    animation: crud-flash-check 2.4s ease !important;
    outline: 2px solid #16a34a !important;
    outline-offset: 2px !important;
    border-radius: 3px;
}

@keyframes crud-flash-check-label {
    0%   { background-color: rgba(22, 163, 74, .30); color: #15803d;
           transform: translateX(0); }
    30%  { background-color: rgba(22, 163, 74, .18); color: #15803d;
           transform: translateX(-3px); }
    60%  { background-color: rgba(22, 163, 74, .08); color: #15803d;
           transform: translateX(0); }
    100% { background-color: transparent; color: inherit; }
}
.crud-form label.crud-just-unlocked-check-label {
    animation: crud-flash-check-label 2.4s ease !important;
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
            <h3 class="crud-form__title">Registrar Animal</h3>
            <p class="crud-form__subtitle">Complete los datos para incorporar un nuevo animal al rebaño</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid">

            <!-- ========== CARD 1: Datos Básicos ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-tag"></i>
                    <h3>Datos Básicos</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="nombre_animal">Nombre del Animal
                            <a data-toggle="tooltip" title="Indica el nombre del animal para identificarlo dentro del rebaño.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="nombre_animal" type="text" tabindex="1" maxlength="100"
                               placeholder="Nombre del Animal" onpaste="return true"
                               onkeypress="return esnombre_animal(event)">
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="raza_animal">Raza del Animal *</label>
                            <select name="raza_animal" id="raza_animal" class="form-control bbb" tabindex="2">
                                <option></option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_raza'><p></p></div>
                        </div>

                        <div class="crud-form__field">
                            <label for="aptitud_animal">Aptitud del Animal *</label>
                            <select name="aptitud_animal" id="aptitud_animal" class="form-control bbb" tabindex="7">
                                <option></option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_aptitud'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="sexo">Sexo *</label>
                            <select id="sexo" class="btn-sm" tabindex="3">
                                <option value=''>Seleccione</option>
                                <option value="1">Hembra</option>
                                <option value="2">Macho</option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_sexo'><p></p></div>
                        </div>

                        <div class="crud-form__field">
                            <label for="estatus_animal">Estatus *
                                <a data-toggle="tooltip" title="ACTIVO aplica a hembras y machos. DONADOR y DONADORA identifican a los reproductores cuyo material se usa en inseminación artificial (macho) o en transferencia de embriones (hembra).">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select id="estatus_animal" class="btn-sm" tabindex="3">
                                <?php echo bovs_estatus_animal_opciones_html(0, 1); ?>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_estatus_animal'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fech_nac">Fecha Nac. *</label>
                            <input class="form-control bbb" id="fech_nac" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="4" readonly="true">
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_fech_nac'><p></p></div>
                        </div>

                        <div class="crud-form__field">
                            <label>Nro Arete/ID Chip
                                <a data-toggle="tooltip" title="Identificador que se asignará al animal; puede ser su número de arete, chapeta o el ID del chip.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="numero_arete" class="form-control" placeholder="Nro Arete">
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_nro_arete'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label>Peso/A.N</label>
                            <input type="text" id="peso_nac" class="form-control" placeholder="Peso"
                                   onkeypress="return esnumero_peso_nac(event)" onpaste="return false" tabindex="5">
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_peso_nac'><p></p></div>
                        </div>

                        <div class="crud-form__field">
                            <label>Alzada</label>
                            <input type="text" id="alza" class="form-control" placeholder="Alzada"
                                   onkeypress="return esnumero_alza(event);" onpaste="return false">
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_alza'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_ingreso">Fecha Ingreso
                                <a data-toggle="tooltip" title="Si el animal se adquirió en otro establecimiento, indica la fecha en que ingresó a la granja. Si nació en la finca, indica cuándo se incorporó al rebaño.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input class="form-control bbb" id="fecha_ingreso" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="6" readonly="true">
                            <i class="fa fa-lock crud-form__lock"></i>
                        </div>

                        <div class="crud-form__field">
                            <label for="rena">RENA
                                <a data-toggle="tooltip" title="RENA: número único del Registro Nacional del animal.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="rena" class="form-control" placeholder="Rena">
                            <i class="fa fa-lock crud-form__lock"></i>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Categorías / Parentescos ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-sitemap"></i>
                    <h3>Categorías / Parentescos</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="categoria_actual">Categoría Actual</label>
                            <input class="form-control bbb" id="categoria_actual" type="text"
                                   placeholder="Categoria Actual" onpaste="return false" tabindex="16">
                            <i class="fa fa-lock crud-form__lock"></i>
                        </div>

                        <div class="crud-form__field">
                            <label for="categoria_ingreso">Categoría Ingreso
                                <a data-toggle="tooltip" title="Selecciona la categoría con la que el animal inició en el establecimiento ganadero.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select name="categoria_ingreso" id="categoria_ingreso" class="form-control bbb" tabindex="9">
                                <option></option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_cat_ingreso'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="categoria_futura">Categoría Futura
                                <a data-toggle="tooltip" title="Selecciona la categoría estimada para cuando el animal se retire del establecimiento ganadero.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select name="categoria_futura" id="categoria_futura" class="form-control bbb" tabindex="10">
                                <option></option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                            <div id='ms_cat_futura'><p></p></div>
                        </div>

                        <div class="crud-form__field">
                            <label for="servicio_animal">Servicio *</label>
                            <select name="servicio_animal" id="servicio_animal" class="form-control bbb" tabindex="11">
                                <option></option>
                            </select>
                            <i class="fa fa-lock crud-form__lock"></i>
                        </div>
                    </div>

                    <label>DATOS DE LA MADRE</label>

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
                        <small class="sel-animal-hint">Si la madre no está registrada en el hato,
                            <a href="#" id="manual_madre_link">use la búsqueda manual</a>.
                        </small>
                    </div>

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Madre seleccionada</label>
                            <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_madre" class="form-control" placeholder="Indique el Nombre">
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_rgd_madre'><p></p></div>
                        <div id='ms_nombre_madre'><p></p></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el caso manual -->
                    <div class="sel-animal-oculto" id="manual_madre">
                        <div class="crud-label-row">
                            <label>N°.Arete-Chip/Cód</label>
                            <div class="crud-check-group">
                                <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                                <label><input type="checkbox" id="b_codigo"> Código</label>
                            </div>
                        </div>
                        <input type="text" id="rgd_madre" class="form-control" placeholder="N° Arete-Chip"
                               onpaste="return true" onkeypress="return es_codigo_nro_arete(event);">
                        <button id="buscar" type="button" class="btn btn-danger btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <label>DATOS DEL PADRE</label>

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
                        <small class="sel-animal-hint">Si el padre no está registrado en el hato,
                            <a href="#" id="manual_padre_link">use la búsqueda manual</a>.
                        </small>
                    </div>

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label>Padre seleccionado</label>
                            <button id="limpiar_m" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input type="text" id="nombre_padre" class="form-control" placeholder="Indique el Nombre">
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_rgd_padre'><p></p></div>
                        <div id='ms_nombre_padre'><p></p></div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el caso manual -->
                    <div class="sel-animal-oculto" id="manual_padre">
                        <div class="crud-label-row">
                            <label>N°.Arete-Chip/Cód</label>
                            <div class="crud-check-group">
                                <label><input type="checkbox" id="macho_nro_arete"> Nro Arete</label>
                                <label><input type="checkbox" id="macho_codigo"> Código</label>
                            </div>
                        </div>
                        <input type="text" id="rgd_padre" class="form-control" placeholder="N° Arete-Chip"
                               onpaste="return true" onkeypress="return es_codigo_nro_arete(event);">
                        <button id="buscar_m" type="button" class="btn btn-primary btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 3: Localización / Foto ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-map-marker"></i>
                    <h3>Localización / Foto</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="lote">Lote *</label>
                        <select name="lote" id="lote" class="form-control bbb" tabindex="2">
                            <option></option>
                        </select>
                        <i class="fa fa-lock crud-form__lock"></i>
                    </div>

                    <div class="crud-form__field">
                        <label for="procedencia">Procedencia
                            <a data-toggle="tooltip" title="Si el animal se adquirió en otro establecimiento, indica el lugar de procedencia. Si nació en la finca, indica «Propia».">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="procedencia" type="text" placeholder="Procedencia"
                               onpaste="return false" tabindex="16">
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_procedencia'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc" id="observaciones" rows="2" placeholder="Observaciones"
                                  onpaste="return true" tabindex="17" onblur="alsalir(this.id);"
                                  autocomplete="off"></textarea>
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_observaciones' class="aaa"><p></p></div>
                    </div>

                    <div class="crud-form__field" style="text-align:center;">
                        <img class="imgusr" alt="" src="#" id="file_url" width="150" height="125" tabindex="18">
                        <label for="InputFile" style="text-align:left;">Adjuntar imagen no mayor a 5 MB.</label>
                        <input type="file" id="InputFile" name="InputFile"
                               accept="image/png, image/jpeg , image/jpg" tabindex="8">
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <input type="hidden" id="action" value="add">
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="20">
                <i class="fa fa-fw fa-save"></i> Guardar
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

<!-- Scripts (idénticos al original) -->
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
var fundo = <?php echo $_SESSION['mfundos_id'];  ?>;

var buscarx = 0;
var ID_GLOBAL = 0;

var buscarx_m = 0;
var ID_GLOBAL_M = 0;
document.getElementById('fecha_ingreso').disabled = true;
document.getElementById('categoria_ingreso').disabled = true;
document.getElementById('categoria_futura').disabled = true;
document.getElementById('aptitud_animal').disabled = true;
document.getElementById('categoria_actual').disabled = true;

BloquearCampos();
BloquearMacho();
BloquearMadre();

var conta_f = 0;

function LimpiarCampos() {

    document.getElementById("nombre_madre").value = '';
    document.getElementById("rgd_madre").value = '';
    document.getElementById("nombre_m").value = '';

    document.getElementById("tipo_servicio").value = '';
    document.getElementById("aptitud_animal").value = '';
    document.getElementById("raza_animal").value = '';
}


function BloquearCampos() {

    document.getElementById("nombre_animal").disabled = false;
    document.getElementById("raza_animal").disabled = true;
    document.getElementById("sexo").disabled = true;

    document.getElementById("estatus_animal").disabled = true;
    document.getElementById("fech_nac").disabled = true;

    document.getElementById("numero_arete").disabled = true;
    document.getElementById("peso_nac").disabled = true;
    document.getElementById("alza").disabled = true;
    document.getElementById("rena").disabled = true;
    document.getElementById("fecha_ingreso").disabled = true;
    document.getElementById("aptitud_animal").disabled = true;
    document.getElementById("categoria_ingreso").disabled = true;
    document.getElementById("categoria_futura").disabled = true;

    document.getElementById("servicio_animal").disabled = true;
    document.getElementById("rgd_madre").disabled = true;
    document.getElementById("nombre_madre").disabled = true;
    document.getElementById("rgd_padre").disabled = true;
    document.getElementById("nombre_padre").disabled = true;
    document.getElementById("lote").disabled = true;

    document.getElementById("procedencia").disabled = true;
    document.getElementById("observaciones").disabled = true;
    document.getElementById("InputFile").disabled = true;
}


$.post("../../controllers/mrazas_controller", {
    action: "get_razas"
}).done(function(data) {
    $("#raza_animal").html(data);
});
$.post("../../controllers/mlotes_controller", {
    action: "get_lote"
}).done(function(data) {
    $("#lote").html(data);
});
$.post("../../controllers/maptitudes_controller", {
    action: "get_aptitud"
}).done(function(data) {
    $("#aptitud_animal").html(data);
});

$.post("../../controllers/mcategorias_controller", {
    action: "get_categoria_ingreso"
}).done(function(data) {
    $("#categoria_ingreso").html(data);
});


$.post("../../controllers/mtiposervicios_controller", {
    action: "get_servicio_animal"
}).done(function(data) {
    $("#servicio_animal").html(data);
});

// Filtrar la categoria futura

$('#sexo').change(function(event) {

    var fecha_nac = $("#fech_nac").val();
    if (fecha_nac != "") {
        Buscar_Categoria_Actual();
    }

    $.post("../../controllers/mcategorias_controller", {
        action: "get_categoria_ingreso",
        filtrar_x_sexo: $("#sexo").val()
    }).done(function(data) {
        /* Las categorías dependen del sexo: se recargan y se limpia lo elegido */
        $("#categoria_ingreso").html(data);
        $("#categoria_ingreso").val('');
        $("#categoria_futura").html('<option value="">--</option>');
        document.getElementById('categoria_futura').disabled = true;

    });

});


$('#categoria_ingreso').change(function(event) {
    document.getElementById('categoria_futura').disabled = false;
    /* El sexo y la fecha de nacimiento se mantienen editables: al cambiarlos se
       recalcula la Categoría Actual (ver #sexo y #fech_nac change). */
    document.getElementById('fech_nac').disabled = false;


    $.post("../../controllers/mcategorias_controller", {
        action: "get_categoria_futura",
        filtrar_x_sexo: $("#sexo").val(),
        categoria_ingreso: $("#categoria_ingreso").val()
    }).done(function(data) {
        $("#categoria_futura").html(data);

    });
});



$("#fech_nac").change(function() {
    document.getElementById('rena').disabled = false;
    document.getElementById('alza').disabled = false;
    document.getElementById('numero_arete').disabled = false;
    document.getElementById('fecha_ingreso').disabled = false;
    document.getElementById('peso_nac').disabled = false;
    document.getElementById('aptitud_animal').disabled = false;


    var sexo = $("#sexo").val();
    if (sexo != "") {
        Buscar_Categoria_Actual();
    }

    var sexo_temp = $("#sexo").val();
    if (sexo_temp != "") {
        document.getElementById('categoria_ingreso').disabled = false;

    }


});


$("#raza_animal").change(function() {

    document.getElementById('sexo').disabled = false;
    document.getElementById('aptitud_animal').disabled = false;

});

$("#sexo").change(function() {
    document.getElementById('fech_nac').disabled = false;
    document.getElementById('numero_arete').disabled = false;

    cargarEstatusPorSexo($("#sexo").val(), 1);
});

/* ═════════════════════════════════════════════════════════════════════════
   ESTATUS DEL ANIMAL SEGÚN EL SEXO (catálogo mestatusanimales)
     hembra → 1 ACTIVO · 4 DONADORA ACTIVO · 5 DONADORA
     macho  → 1 ACTIVO · 2 DONADOR ACTIVO  · 3 DONADOR
   ═════════════════════════════════════════════════════════════════════════ */
function cargarEstatusPorSexo(sexo, valor) {
    var campo = document.getElementById('estatus_animal');

    if (sexo == '' || sexo == null) {
        campo.disabled = true;
        return;
    }

    $.post("../../controllers/manimales_controller", {
        action: "get_estatus_animal",
        sexo: sexo,
        estatus_animal: (valor === undefined ? 1 : valor)
    }).done(function(data) {
        $("#estatus_animal").html(data);

        var deseado = (valor === undefined ? 1 : valor);
        $("#estatus_animal").val(deseado);

        if ($("#estatus_animal").val() == null) {
            $("#estatus_animal").val(1);
        }

        campo.disabled = false;
    });
}


$("#categoria_futura").change(function() {

    document.getElementById('servicio_animal').disabled = false;
    document.getElementById('procedencia').disabled = false;
    document.getElementById('observaciones').disabled = false;
    document.getElementById('lote').disabled = false;
    document.getElementById('InputFile').disabled = false;

});



$("#fecha_ingreso").change(function() {


    if (conta_f == 0) {
        conta_f++;

        validarfecha();
        conta_f++;


    } else {
        conta_f = 0;

    }
    DesbloquearMadre();

});

function validarfecha() {


    var f_ing = $("#fecha_ingreso").val();
    var f_nac = $("#fech_nac").val();

    var fecha_ing_array = f_ing.split('-');
    var new_fecha_ing = fecha_ing_array[2] + '/' + fecha_ing_array[1] + '/' + fecha_ing_array[0];


    var fecha_nac_array = f_nac.split('-');
    var new_fecha_nac = fecha_nac_array[2] + '/' + fecha_nac_array[1] + '/' + fecha_nac_array[0];


    var fecha_ing = new Date(new_fecha_ing);
    var fecha_nac = new Date(new_fecha_nac);
    if ((fecha_ing != '') && (fecha_nac != '')) {


        if ((fecha_ing < fecha_nac)) {

            document.getElementById('aptitud_animal').value = "";

            document.getElementById('aptitud_animal').disabled = true;


            $.confirm({

                title: '¡ Fecha de Ingreso Incorrecta !',
                content: false,
                cancelButton: 'Ok',
                confirmButton: '..',
                confirmButtonClass: 'none',
                cancelButtonClass: 'btn-danger',
                cancel: function() {

                }

            });
            document.getElementById('aptitud_animal').value = "";
            exit();


        } else {
            document.getElementById('aptitud_animal').disabled = false;
            /* La FECHA DE NACIMIENTO queda siempre editable: cada vez que cambia
               (igual que el sexo) se recalcula la Categoría Actual. */
            document.getElementById('fecha_ingreso').disabled = true;


        }


    }



}
$("#derecha").click(function() {

    if (tick == 0) {

        var cont_alert = $('.bbb').filter(function() {
            return $(this).val() == "";
        }).size();
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
                document.body.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 800);

        }

    } else if (tick == 1) {

        var cont_alert3 = $('.ccc').filter(function() {
            return $(this).val() == "";
        }).size();

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
                document.body.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 800);

        }

    }

});

$('#fech_nac').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true

});
$('#fecha_ingreso').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true

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
            document.body.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 800);

    } else if (tick == 2) {

        $('.base').unslider('animate:1');
        tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 800);

    }

});

$("#exit").click(function() {

    $.confirm({
        title: '¿Desea Salir?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-primary',
        cancelButtonClass: 'btn-success',

        confirm: function() {

            setTimeout(function() {

                $(location).attr('href', '../animales/');

            }, 1000);
        },

        cancel: function() {

        }
    });

});

$('#save').click(function() {

    document.getElementById('save').disabled = true;
    if (ValidarCamposVacios()) {
        Guardar_Ficha();

    } else {
        setTimeout(function() {
            document.getElementById(msj).innerHTML = '';
            document.getElementById('save').disabled = false;
        }, 2000);
    }

});


function Guardar_Ficha() {
    document.getElementById('save').disabled = true;

    $.confirm({

        title: '¿Está Seguro de Guardar el Registro ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-primary',
        cancelButtonClass: 'btn-success',

        confirm: function() {
            var formData = new FormData();
            formData.append('file', $('input[type=file]')[0].files[0]);
            formData.append('action', $("#action").val());
            formData.append('nombre_animal', $("#nombre_animal").val());
            formData.append('raza_animal', $("#raza_animal").val());
            formData.append('rena', $("#rena").val());
            formData.append('lote', $("#lote").val());
            formData.append('alza', $("#alza").val());
            formData.append('sexo', $("#sexo").val());
            formData.append('estatus_animal', $("#estatus_animal").val());
            formData.append('fech_nac', $("#fech_nac").val());
            formData.append('peso_nac', $("#peso_nac").val());
            formData.append('fecha_ingreso', $("#fecha_ingreso").val());
            formData.append('aptitud_animal', $("#aptitud_animal").val());
            formData.append('numero_arete', $("#numero_arete").val());
            formData.append('categoria_ingreso', $("#categoria_ingreso").val());
            formData.append('categoria_futura', $("#categoria_futura").val());
            formData.append('rgd_madre', $("#rgd_madre").val());
            formData.append('nombre_madre', $("#nombre_madre").val());
            formData.append('rgd_padre', $("#rgd_padre").val());
            formData.append('nombre_padre', $("#nombre_padre").val());
            formData.append('procedencia', $("#procedencia").val());
            formData.append('observaciones', $("#observaciones").val());
            formData.append('servicio_animal', $("#servicio_animal").val());

            $.ajax({
                url: "../../controllers/manimales_controller",
                type: "POST",
                data: formData,
                contentType: false,
                cache: false,
                processData: false,
                success: function(data) {

                    $(".message").html(data);

                    var apg = document.getElementById("tocon").value;


                    if (apg == 1) {

                        window.setTimeout('location.reload()', 3000);


                    } else {
                        setTimeout(function() {
                            $(".alert").alert('close');
                            document.getElementById('save').disabled = false;

                        }, 2500);

                    }

                }
            });

        },

        cancel: function() {
            document.getElementById('save').disabled = false;

        }
    });


}

function ValidarCamposVacios() {
    var guardar = false;
    var nombre_animal = $("#nombre_animal").val();
    var raza_animal = $("#raza_animal").val();
    var numero_arete = $("#numero_arete").val();
    var sexo = $("#sexo").val();
    var aptitud = $("#aptitud_animal").val();
    var cat_ingreso = $("#categoria_ingreso").val();
    var cat_futura = $("#categoria_futura").val();
    var fech_nac = $("#fech_nac").val();


    if (nombre_animal == "") {
        document.getElementById('ms_nombre').style.display = 'block';
        document.getElementById("ms_nombre").innerHTML = 'Valor Obligatorio';
        msj = "ms_nombre";
    } else if (raza_animal == "") {
        document.getElementById('ms_raza').style.display = 'block';
        document.getElementById("ms_raza").innerHTML = 'Valor Obligatorio';
        msj = "ms_raza";

    } else if (sexo == "") {
        document.getElementById('ms_sexo').style.display = 'block';
        document.getElementById("ms_sexo").innerHTML = 'Valor Obligatorio';
        msj = "ms_sexo";

    } else if (fech_nac == "") {
        document.getElementById('ms_fech_nac').style.display = 'block';
        document.getElementById("ms_fech_nac").innerHTML = 'Valor Obligatorio';
        msj = "ms_fech_nac";

    } else if (aptitud == "") {
        document.getElementById('ms_aptitud').style.display = 'block';
        document.getElementById("ms_aptitud").innerHTML = 'Valor Obligatorio';
        msj = "ms_aptitud";

    } else if ((cat_ingreso == "") || (cat_ingreso == "*") || (cat_ingreso == null)) {
        document.getElementById('ms_cat_ingreso').style.display = 'block';
        document.getElementById("ms_cat_ingreso").innerHTML = 'Valor Obligatorio';
        msj = "ms_cat_ingreso";

    } else if ((cat_futura == "") || (cat_futura == null)) {
        document.getElementById('ms_cat_futura').style.display = 'block';
        document.getElementById("ms_cat_futura").innerHTML = 'Valor Obligatorio';
        msj = "ms_cat_futura";

    } else {
        guardar = true;
    }


    return guardar;
}

function esnumero_peso_nac(e) {

    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9\.]$/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_peso_nac').style.display = 'block';
        document.getElementById("ms_peso_nac").innerHTML = 'Use solo nuúmeros y punto decimal';
        return patron.test(n);

    } else {

        document.getElementById("ms_peso_nac").innerHTML = '';
        return patron.test(n);

    }

}

function esnumero_alza(e) {

    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9\.]$/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_alza').style.display = 'block';
        document.getElementById("ms_alza").innerHTML = 'Use solo nuúmeros y punto decimal';
        return patron.test(n);

    } else {

        document.getElementById("ms_alza").innerHTML = '';
        return patron.test(n);

    }

}

function readURL(input) {

    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function(e) {
            $('#file_url').attr('src', e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}

function esnombre_animal(e) {
    var leng = document.getElementById('nombre_animal').value;
    leng = leng.length;

    if (leng > 2) {
        document.getElementById("raza_animal").disabled = false;

    } else {
        document.getElementById("raza_animal").disabled = true;

    }


    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[a-zñA-ZÑ0-9\ \.\-\/]$/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_nombre').style.display = 'block';
        document.getElementById("ms_nombre").innerHTML = 'No Utilice caracteres especiales.';
        return patron.test(n);

    } else {

        document.getElementById("ms_nombre").innerHTML = '';
        return patron.test(n);

    }


}

//////////////////VALIDANDO QUE NO SE REPITA EL NUMERO DE ARETE
$("#numero_arete").blur(function() {

    $.post("../../controllers/manimales_controller", {

        action: "search_nro_arete",
        nro_arete: $("#numero_arete").val()


    }).done(function(data) {

        var parsedJson = $.parseJSON(data);
        var validar = parsedJson.nro_arete;

        if (validar != 0) {
            document.getElementById('ms_nro_arete').style.display = 'block';
            document.getElementById("ms_nro_arete").innerHTML =
                'NUMERO DE ARETE Ya se Encuentra Registrado';
            document.getElementById('numero_arete').focus();
            var valor = $("#numero_arete").val();
            setTimeout(function() {

                document.getElementById("ms_nro_arete").innerHTML = '';
                $("#numero_arete").val(valor);

            }, 2000);

        }

    }, "json");

});
///////////////////////// VALIDANDO QUE NO SE REPITA EL NOMBRE

$("#nombre_animal").blur(function() {
    $.post("../../controllers/manimales_controller", {

        action: "search_nombre_animal",
        nombre_animal: $("#nombre_animal").val()


    }).done(function(data) {

        var parsedJson = $.parseJSON(data);
        var validar = parsedJson.nombre_animal;

        if (validar != 0) {
            document.getElementById('ms_nombre').style.display = 'block';
            document.getElementById("ms_nombre").innerHTML =
                'ESTE NOMBRE Ya se Encuentra Registrado';
            document.getElementById('ms_nombre').focus();
            var valor = $("#nombre_animal").val();
            setTimeout(function() {

                document.getElementById("ms_nombre").innerHTML = '';
                $("#nombre_animal").val(valor);

            }, 2000);

        }

    }, "json");

});

$("#InputFile").change(function() {

    if (validarImagen()) {
        readURL(this);

    }


});


function validarImagen() {
    var fileSize = $('#InputFile')[0].files[0].size;
    var siezekiloByte = parseInt(fileSize / 1024);
    if (siezekiloByte > 0) {

        if (siezekiloByte > 5120) {
            $('#InputFile').val("");

            $.confirm({

                title: 'La imagen es muy grande',
                content: false,
                confirmButtonClass: 'none',
                cancelButtonClass: 'btn-success',
                cancel: function() {

                }
            });
            return false;
        } else {
            return true;

        }
    } else {
        $.confirm({

            title: 'La imagen No pudo ser Cargada',
            content: false,
            confirmButtonClass: 'none',
            cancelButtonClass: 'btn-success',
            cancel: function() {

            }
        });
        return false;

    }


}

function Buscar_Categoria_Actual() {


    $.post("../../controllers/mcategorias_controller", {

        action: "search_categoria_actual",
        sexo: $("#sexo").val(),
        fecha_nac: $("#fech_nac").val()

    }).done(function(data) {

        var parsedJson = $.parseJSON(data);
        var cat_actual = (parsedJson && parsedJson.cat_actual) ? parsedJson.cat_actual : '';
        $("#categoria_actual").val(cat_actual);

    }, "json");

}

///////////////////////////////// AGREGANDO BUSQUEDA DE MADRE Y PADRE

$('#buscar').click(function() {
    var rgb_animal_h = $("#rgd_madre").val();
    BloquearMacho();

    if (rgb_animal_h != "") {
        ID_GLOBAL = rgb_animal_h;
        buscarx = buscarx;
        document.getElementById("nombre_madre").value = '';

        var rgb_animal_h = $("#rgd_madre").val();
        document.getElementById("nombre_madre").value = '';
        document.getElementById("limpiar").disabled = false;


        $.post("../../controllers/manimales_controller", {
            action: "get_buscar_rgb_madre",
            rgd_madre: rgb_animal_h,
            buscarx: buscarx
        }).done(function(data) {
            var parsedJson = $.parseJSON(data);
            if (parsedJson.resultado == 'error') {
                $(".message").html(parsedJson.mensaje);
                $('.base').unslider('animate:0');
                $('.base').unslider('animate:1');
                setTimeout(function() {
                    $(".alert").alert('close');

                }, 3000);

            }
            if (parsedJson.condicion == 'none') {

                setTimeout(function() {

                    $.confirm({
                        title: '¿Desea Agregar la Madre de Forma Manual ?',
                        content: false,
                        confirmButton: 'Si',
                        cancelButton: 'No',
                        confirmButtonClass: 'btn-success',
                        cancelButtonClass: 'btn-danger',
                        confirm: function() {
                            document.getElementById('nombre_madre').disabled = false;
                            document.getElementById('rgd_madre').disabled = false;
                            document.getElementById("buscar").disabled = true;
                            document.getElementById('b_codigo').disabled = true;
                            document.getElementById('b_nro_arete').disabled = true;
                            DesBloquearMacho();

                        },
                        cancel: function() {

                        }
                    });

                }, 2000);


            } else {

                document.getElementById("buscar").disabled = true;
                document.getElementById('b_codigo').disabled = true;
                document.getElementById('b_nro_arete').disabled = true;
                document.getElementById('nombre_madre').disabled = true;

                $("#nombre_madre").html(data);
                $("#nombre_madre").val(data);
                var valor = $("#nombre_madre").val();
                var array = valor.split("*");
                var log = array[0];
                var array2 = log.split('"');
                var log2 = array2[1];

                var log_n = array[1];
                var array_n2 = log_n.split('"');
                var log_n2 = array_n2[0];


                $("#rgd_madre").html(log2);
                $("#rgd_madre").val(log2);

                $("#nombre_madre").html(log_n2);
                $("#nombre_madre").val(log_n2);
                DesBloquearMacho();

            }

        });
    } else {
        document.getElementById('ms_rgd_madre').style.display = 'block';
        document.getElementById("ms_rgd_madre").innerHTML = 'Debe Ingresar el Código';
        setTimeout(function() {
            document.getElementById("ms_rgd_madre").innerHTML = '';

        }, 2000);
    }

});

$('#buscar_m').click(function() {
    var rgb_animal_m = $("#rgd_padre").val();


    if (rgb_animal_m != "") {
        ID_GLOBAL_M = rgb_animal_m;
        buscarx_m = buscarx_m;
        document.getElementById("nombre_padre").value = '';

        var rgb_animal_m = $("#rgd_padre").val();
        document.getElementById("nombre_padre").value = '';

        $.post("../../controllers/manimales_controller", {
            action: "get_buscar_rgb_padre",
            rgd_padre: rgb_animal_m,
            buscarx_m: buscarx_m
        }).done(function(data) {
            var parsedJson = $.parseJSON(data);
            if (parsedJson.resultado == 'error') {
                $(".message").html(parsedJson.mensaje);
                $('.base').unslider('animate:0');
                $('.base').unslider('animate:1');
                setTimeout(function() {
                    $(".alert").alert('close');

                }, 2500);

            }
            if (parsedJson.condicion == 'none') {

                setTimeout(function() {

                    $.confirm({
                        title: '¿Desea Agregar el Padre de Forma Manual ?',
                        content: false,
                        confirmButton: 'Si',
                        cancelButton: 'No',
                        confirmButtonClass: 'btn-success',
                        cancelButtonClass: 'btn-danger',
                        confirm: function() {
                            document.getElementById('nombre_padre').disabled = false;
                            document.getElementById('rgd_padre').disabled = false;
                            document.getElementById("buscar_m").disabled = true;
                            document.getElementById('macho_codigo').disabled = true;
                            document.getElementById('macho_nro_arete').disabled = true;
                            document.getElementById("limpiar_m").disabled = false;
                        },
                        cancel: function() {

                        }
                    });

                }, 2000);

            } else {

                document.getElementById("buscar_m").disabled = true;
                document.getElementById('macho_codigo').disabled = true;
                document.getElementById('macho_nro_arete').disabled = true;
                document.getElementById('nombre_padre').disabled = true;
                document.getElementById("limpiar_m").disabled = false;

                $("#nombre_padre").html(data);
                $("#nombre_padre").val(data);
                var valor = $("#nombre_padre").val();
                var array = valor.split("*");
                var log = array[0];
                var array2 = log.split('"');
                var log2 = array2[1];

                var log_n = array[1];
                var array_n2 = log_n.split('"');
                var log_n2 = array_n2[0];

                $("#rgd_padre").html(log2);
                $("#rgd_padre").val(log2);

                $("#nombre_padre").html(log_n2);
                $("#nombre_padre").val(log_n2);
            }

        });
    } else {
        document.getElementById('ms_rgd_padre').style.display = 'block';
        document.getElementById("ms_rgd_padre").innerHTML = 'Debe Ingresar el Código';
        setTimeout(function() {
            document.getElementById("ms_rgd_padre").innerHTML = '';

        }, 2000);
    }

});
$('#limpiar').click(function() {

    document.getElementById("rgd_madre").disabled = true;
    document.getElementById("buscar").disabled = false;
    document.getElementById("limpiar").disabled = true;

    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
    document.getElementById("b_codigo").disabled = false;
    document.getElementById("b_nro_arete").disabled = false;

    document.getElementById("nombre_madre").value = '';
    document.getElementById("rgd_madre").value = '';
    document.getElementById("nombre_madre").disabled = true;

});
$('#limpiar_m').click(function() {

    document.getElementById("rgd_padre").disabled = true;
    document.getElementById("buscar_m").disabled = false;
    document.getElementById("limpiar_m").disabled = true;

    document.getElementById('macho_codigo').checked = false;
    document.getElementById('macho_nro_arete').checked = false;
    document.getElementById("macho_codigo").disabled = false;
    document.getElementById("macho_nro_arete").disabled = false;

    document.getElementById("nombre_padre").value = '';
    document.getElementById("rgd_padre").value = '';
    document.getElementById("nombre_padre").disabled = true;

});
///*********************************** */

$("#b_codigo").click(function() {
    var id_fundo = '0' + fundo + '-';
    document.getElementById("nombre_madre").value = '';
    document.getElementById("rgd_madre").value = '';

    if ($('#b_codigo').prop('checked') == true) {
        $("#b_nro_arete").val(0);
        $("#b_codigo").val(1);
        document.getElementById('b_codigo').checked = true;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('rgd_madre').disabled = false;
        document.getElementById("rgd_madre").value = 'SOVCA-' + id_fundo;

        document.getElementById('buscar').disabled = false;
        buscarx = 1;

    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('rgd_madre').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;

    }

});
$("#b_nro_arete").click(function() {
    document.getElementById("nombre_madre").value = '';
    document.getElementById("rgd_madre").value = '';

    if ($('#b_nro_arete').prop('checked') == true) {
        $("#b_nro_arete").val(1);
        $("#b_codigo").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = true;
        document.getElementById('rgd_madre').disabled = false;
        document.getElementById('buscar').disabled = false;
        buscarx = 2;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('rgd_madre').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;

    }

});
///*********************************** */

$("#macho_codigo").click(function() {
    var id_fundo = '0' + fundo + '-';
    document.getElementById("nombre_padre").value = '';
    document.getElementById("rgd_padre").value = '';

    if ($('#macho_codigo').prop('checked') == true) {
        $("#macho_nro_arete").val(0);
        $("#macho_codigo").val(1);
        document.getElementById('macho_codigo').checked = true;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('rgd_padre').disabled = false;
        document.getElementById("rgd_padre").value = 'SOVCA-' + id_fundo;

        document.getElementById('buscar_m').disabled = false;
        buscarx_m = 1;



    } else {
        $("#macho_codigo").val(0);
        $("#macho_nro_arete").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('rgd_padre').disabled = true;
        document.getElementById('buscar_m').disabled = true;
        buscarx_m = 0;
        ID_GLOBAL_M = 0;


    }

});

$("#macho_nro_arete").click(function() {
    document.getElementById("nombre_padre").value = '';
    document.getElementById("rgd_padre").value = '';

    if ($('#macho_nro_arete').prop('checked') == true) {
        $("#macho_nro_arete").val(1);
        $("#macho_codigo").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = true;
        document.getElementById('rgd_padre').disabled = false;
        document.getElementById('buscar_m').disabled = false;
        buscarx_m = 2;
    } else {
        $("#macho_codigo").val(0);
        $("#macho_nro_arete").val(0);
        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById('rgd_padre').disabled = true;
        document.getElementById('buscar_m').disabled = true;
        buscarx_m = 0;
        ID_GLOBAL_M = 0;

    }

});

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
</script>

<!-- Scripts auxiliares NUEVOS (no interfieren con la lógica original) -->
<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });

(function crudUnlockHighlighter() {
    var seen = new WeakMap();
 
    var DURATION = 4800;

    /* Busca el label asociado a un checkbox, en 3 estrategias */
    function findLabelForCheckbox(el) {
        var lbl = el.closest('label');
        if (lbl) return lbl;

        var prev = el.previousElementSibling;
        while (prev) {
            if (prev.tagName === 'LABEL') return prev;
            prev = prev.previousElementSibling;
        }

        var next = el.nextElementSibling;
        while (next) {
            if (next.tagName === 'LABEL') return next;
            next = next.nextElementSibling;
        }

        return null;
    }

    function highlight(el) {
        var tag = el.tagName;

        if (tag === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function () { el.classList.remove('crud-just-unlocked-btn'); }, DURATION);
            return;
        }

        if (el.type === 'checkbox') {
            el.classList.add('crud-just-unlocked-check');
            var label = findLabelForCheckbox(el);
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
</script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   BUSCADORES AMIGABLES: listado de animales que cumplen la condición
   (reemplaza el buscador manual de Nro Arete / Código, que queda oculto pero
   sigue disponible para el caso de madre/padre NO registrados en el hato)
   ═══════════════════════════════════════════════════════════════════════════ */
$(function () {

    /* ---------- MADRE ---------- */
    bovsCrudAnimalSelector({
        lista:         'sel_h',
        url:           '../../controllers/manimales_controller',
        accion:        'listar_candidatos_ficha',
        rol:           'madre',
        campoCodigo:   'rgd_madre',
        botonBuscar:   'buscar',
        botonCambiar:  'limpiar',
        textoVacio:     'No hay hembras que cumplan la condición.',
        textoResumen:   function (ok, no) { return ok + ' disponibles · ' + no + ' no disponibles'; },
        alElegir:      function (fila) { buscarx = 1; }
    });

    /* ---------- PADRE ---------- */
    bovsCrudAnimalSelector({
        lista:         'sel_m',
        url:           '../../controllers/manimales_controller',
        accion:        'listar_candidatos_ficha',
        rol:           'padre',
        campoCodigo:   'rgd_padre',
        botonBuscar:   'buscar_m',
        botonCambiar:  'limpiar_m',
        textoVacio:     'No hay machos que cumplan la condición.',
        textoResumen:   function (ok, no) { return ok + ' disponibles · ' + no + ' no disponibles'; },
        alElegir:      function (fila) { buscarx_m = 1; }
    });

    /* ---------- BÚSQUEDA MANUAL (madre/padre no registrados) ---------- */
    $('#manual_madre_link').on('click', function (e) { e.preventDefault(); $('#manual_madre').show(); });
    $('#manual_padre_link').on('click', function (e) { e.preventDefault(); $('#manual_padre').show(); });

});
</script>

<?php include_once("../layouts/pie.php") ?>