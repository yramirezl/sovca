<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
require_once '../../lib/estatus_animal_helper.php';

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

/* Reducir la duración del flash en editar (muchos campos se desbloquean a la vez) */
.crud-form .crud-unlocked,
.crud-form .crud-just-unlocked-btn,
.crud-form .crud-just-unlocked-check,
.crud-form .crud-just-unlocked-check-label {
    animation-duration: 1.5s !important;
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Animal</h3>
            <p class="crud-form__subtitle">Modifique los datos de la ficha del animal seleccionado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_animal">

        <div class="crud-form__grid">

            <!-- ========== CARD 1: Datos Básicos ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-tag"></i>
                    <h3>Datos Básicos</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="cod_animal">Código del Animal
                            <a data-toggle="tooltip" title="Código único que identifica al animal dentro del rebaño.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="cod_animal" type="text"
                               placeholder="CODIGO del Animal" onpaste="return false" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_animal">Nombre del Animal
                            <a data-toggle="tooltip" title="Indica el nombre del animal para identificarlo dentro del rebaño.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="nombre_animal" type="text" tabindex="1"
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
                                <option value="*">Seleccione</option>
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
                                <?php echo bovs_estatus_animal_opciones_html(0, 0); ?>
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
                            <label>Nro Arete-Chip
                                <a data-toggle="tooltip" title="Identificador que se asignará al animal; puede ser su número de arete, chapeta o el ID del chip.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="numero_arete" class="form-control" placeholder="Nro Arete-Chip">
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

                    <div class="crud-form__field">
                        <label for="categoria_actual">Categoría Actual</label>
                        <input class="form-control bbb" id="categoria_actual" type="text" placeholder="Categoria Actual"
                               onpaste="return false" tabindex="16">
                        <i class="fa fa-lock crud-form__lock"></i>
                    </div>

                    <div class="crud-form__row">
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
                    </div>

                    <div class="crud-form__field">
                        <label for="servicio_animal">Servicio</label>
                        <select name="servicio_animal" id="servicio_animal" class="form-control bbb" tabindex="11">
                            <option></option>
                        </select>
                        <i class="fa fa-lock crud-form__lock"></i>
                    </div>

                    <label>DATOS DE LA MADRE</label>

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
                               onpaste="return false" onkeypress="return es_codigo_nro_arete(event);">
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
                        <input type="text" id="rgd_padre" class="form-control" placeholder="N° Arete"
                               onpaste="return false" onkeypress="return es_codigo_nro_arete(event);">
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
                        <label for="lote">Lote</label>
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
                        <textarea class="form-control ccc" id="observaciones" rows="3" placeholder="Observaciones"
                                  onpaste="return true" tabindex="17" onblur="alsalir(this.id);"
                                  autocomplete="off"></textarea>
                        <i class="fa fa-lock crud-form__lock"></i>
                        <div id='ms_observaciones' class="aaa"><p></p></div>
                    </div>

                    <div class="crud-form__field" style="text-align:center;">
                        <img class="imgusr" alt="" src="#" id="file_url" width="120" height="150" tabindex="18">
                        <label for="InputFile" style="text-align:left;">Adjuntar imagen no mayor a 1 MB.</label>
                        <input type="file" id="InputFile" tabindex="8">
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="modificar" type="button" class="btn btn-primary" tabindex="23">
                <i class="fa fa-fw fa-pencil"></i> EDITAR
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="23">
                <i class="fa fa-fw fa-save"></i> Guardar
            </button>
        </div>

        <!-- ========== HISTORIAL DE MOVIMIENTOS ========== -->
        <div class="historial-card" id="box_historial">
            <div class="box-header">
                <h3 class="box-title">
                    <i class="fa fa-exchange"></i> Historial de Movimientos
                </h3>
            </div>
            <div class="box-body">

                <div id="movimientos_loading" style="text-align:center;padding:25px;color:#6B7480;">
                    <i class="fa fa-spinner fa-spin" style="font-size:28px;"></i>
                    <p style="margin-top:10px;">Cargando movimientos...</p>
                </div>

                <div id="movimientos_vacio" style="display:none;text-align:center;padding:25px;color:#6B7480;font-style:italic;">
                    <i class="fa fa-info-circle" style="font-size:32px;color:#B0B8C0;"></i>
                    <p style="margin-top:10px;">Este animal no tiene movimientos registrados.</p>
                </div>

                <div id="movimientos_tabla" style="display:none;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:130px;">Fecha</th>
                                <th style="width:90px;">Tipo</th>
                                <th>Origen → Destino</th>
                                <th style="width:120px;">Motivo</th>
                                <th style="width:180px;">Cambio de Categoría</th>
                                <th style="width:100px;">Estado</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="movimientos_body"></tbody>
                    </table>
                    <div style="text-align:right;padding:10px;color:#6B7480;font-size:13px;">
                        Total de movimientos: <strong id="movimientos_total">0</strong>
                    </div>
                </div>

            </div>
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

<!-- ⚠️ AQUÍ PEGÁ EL BLOQUE <script> ORIGINAL COMPLETO DEL frm_editar.php -->
<!-- (el que empieza con "var nombre_real = '';" y termina con la última  -->
<!-- función del historial "cargarMovimientos")                            -->
<script type="text/javascript">
    /* ═══════════════════════════════════════════════════════════════════════════
       JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
       Todas las validaciones, endpoints y cadenas de activación permanecen intactas.
       ═══════════════════════════════════════════════════════════════════════════ */
    var nombre_real = '';
    var fundo = <?php echo $_SESSION['mfundos_id']; ?>;

    var buscarx = 0;
    var ID_GLOBAL = 0;

    var buscarx_m = 0;
    var ID_GLOBAL_M = 0;

    var nombre_madre = '';
    var rgd_madre = '';
    var nombre_padre = '';
    var rgd_padre = '';


    BloquearCampos();
    BloquearMacho();
    BloquearMadre();

    document.getElementById('categoria_actual').disabled = true;


    $.post("../../controllers/mrazas_controller", {
        action: "get_razas"
    }).done(function (data) {
        $("#raza_animal").html(data);

    });
    $.post("../../controllers/mlotes_controller", {
        action: "get_lote"
    }).done(function (data) {
        $("#lote").html(data);

    });
    $.post("../../controllers/maptitudes_controller", {
        action: "get_aptitud"
    }).done(function (data) {
        $("#aptitud_animal").html(data);

    });



    $.post("../../controllers/mtiposervicios_controller", {
        action: "get_servicio_animal"
    }).done(function (data) {
        $("#servicio_animal").html(data);

    });
    $(document).ready(function () {


        //cargar datos del formulario ********************************************************************
        $.post("../../controllers/manimales_controller", {

            action: "search",
            record: <?php echo intval($cad0); ?>


        }).done(function (data) {

            parsedJson = $.parseJSON(data);
            nombre_madre = parsedJson.nombre_madre;
            rgd_madre = parsedJson.rgd_madre;
            nombre_padre = parsedJson.nombre_padre;
            rgd_padre = parsedJson.rgd_padre;
            //aca recibo lo que viene del controlador
            $("#id_animal").val(parsedJson.id_animal);
            $("#cod_animal").val(parsedJson.cod_animal);
            $("#nombre_animal").val(parsedJson.nombre_animal);
            nombre_real = parsedJson.nombre_animal;
            $("#estatus_animal").val(parsedJson.estatus_animal);
            $("#lote").val(parsedJson.lote);
            $("#rena").val(parsedJson.rena);
            $("#alza").val(parsedJson.alza);
            $("#raza_animal").val(parsedJson.raza_animal);
            $("#fech_nac").val(parsedJson.fech_nac);
            $("#peso_nac").val(parsedJson.peso_nac);
            $("#fecha_ingreso").val(parsedJson.fecha_ingreso);
            $("#aptitud_animal").val(parsedJson.aptitud_animal);
            $("#rgd_madre").val(parsedJson.rgd_madre);
            $("#nombre_madre").val(parsedJson.nombre_madre);
            $("#rgd_padre").val(parsedJson.rgd_padre);
            $("#nombre_padre").val(parsedJson.nombre_padre);
            $("#procedencia").val(parsedJson.procedencia);
            $("#servicio_animal").val(parsedJson.servicio_animal);
            $("#observaciones").val(parsedJson.observaciones);
            $("#numero_arete").val(parsedJson.numero_arete);
            $("#categoria_actual").val(parsedJson.categoria_actual);
            $('.imgusr').attr('src', '../../dist/img/fotos_animales/' + parsedJson.imagen);




            // VALIDACIONES


            $("#sexo").val(parsedJson.sexo);
            var sexo = parsedJson.sexo;

            /* El estatus depende del sexo: se recargan las opciones del catálogo
               (hembra: ACTIVO/DONADORA ACTIVO/DONADORA · macho: ACTIVO/DONADOR ACTIVO/DONADOR) */
            cargarEstatusPorSexo(parsedJson.sexo, parsedJson.estatus_animal);

            var cat_ini = parsedJson.categoria_ingreso;
            var cat_act = parsedJson.categoria_actual;



            if (cat_ini != '') {
                $.post("../../controllers/mcategorias_controller", {                    action: "get_categoria_ingreso",
                    filtrar_x_sexo: sexo
                }).done(function (data) {
                    $("#categoria_ingreso").html(data);
                    $("#categoria_ingreso").val(parsedJson.categoria_ingreso);

                });

            } else if (cat_act != '') {

                $.post("../../controllers/mcategorias_controller", {
                    action: "get_categoria_ingreso",
                    filtrar_x_sexo: sexo
                }).done(function (data) {
                    $("#categoria_ingreso").html(data);
                    $("#categoria_ingreso").val(parsedJson.categoria_ingreso);

                });

            }

            if ((cat_ini != '') && (sexo != '')) {

                $.post("../../controllers/mcategorias_controller", {
                    action: "get_categoria_futura",
                    filtrar_x_sexo: sexo,
                    categoria_ingreso: cat_ini
                }).done(function (data) {
                    $("#categoria_futura").html(data);
                    $("#categoria_futura").val(parsedJson.categoria_futura);
                });
            }

        }, "json");

    });


    $('#sexo').change(function (event) {
        $("#categoria_ingreso").val(0);
        $("#categoria_futura").val(0);
        document.getElementById('categoria_futura').disabled = true;
        var fecha_nac = $("#fech_nac").val();
        if (fecha_nac != "") {
            Buscar_Categoria_Actual();
        }

        /* Al cambiar el sexo el estatus anterior puede no ser válido
           (p. ej. DONADOR de un macho en una hembra) */
        cargarEstatusPorSexo($("#sexo").val(), 1);

        $.post("../../controllers/mcategorias_controller", {
            action: "get_categoria_ingreso",
            filtrar_x_sexo: $("#sexo").val()
        }).done(function (data) {
            $("#categoria_ingreso").html(data);

        });



    });

    /* ═══════════════════════════════════════════════════════════════════════
       ESTATUS DEL ANIMAL SEGÚN EL SEXO (catálogo mestatusanimales)
       ═══════════════════════════════════════════════════════════════════════ */
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
        }).done(function (data) {
            $("#estatus_animal").html(data);

            var deseado = (valor === undefined ? 1 : valor);
            $("#estatus_animal").val(deseado);

            if ($("#estatus_animal").val() == null) {
                $("#estatus_animal").val(1);
            }

            campo.disabled = false;
        });
    }

    $('#categoria_ingreso').change(function (event) {

        /* El sexo se mantiene editable: al cambiarlo se recalculan la categoría
           actual y el listado de categorías. */
        document.getElementById('categoria_futura').value = "";
        document.getElementById('categoria_futura').disabled = false;

        $.post("../../controllers/mcategorias_controller", {
            action: "get_categoria_futura",
            filtrar_x_sexo: $("#sexo").val(),
            categoria_ingreso: $("#categoria_ingreso").val()
        }).done(function (data) {
            $("#categoria_futura").html(data);

        });


    });


    $("#InputFile").change(function () {

        if (validarImagen()) {
            readURL(this);

        }


    });


    $("#derecha").click(function () {

        if (tick == 0) {

            var cont_alert = $('.bbb').filter(function () {
                return $(this).val() == "";
            }).size();
            //var cont_alert = $('.aaa p:contains("") ').size();
            //alert(cont_alert);
            document.getElementById('derecha').disabled = true;
            $("#derecha").css("display", "none");
            //if(cont_alert!=0){
            if (cont_alert > 30) {
                //alert('existen campos vacios');

                $.confirm({
                    title: '¡existen campos vacios !', // hides the title.
                    cancelButton: false, // hides the cancel button.
                    confirmButton: true, // hides the confirm button.
                    closeIcon: false,
                    confirmButton: 'cerrar',
                    confirmButtonClass: 'btn-success',
                    content: false // hides content block.
                });

            } else {


                $('.base').unslider('animate:1');
                tick = 1;
                document.getElementById('izquierda').disabled = false;
                $("#izquierda").css("display", "block");


                setTimeout(function () {
                    document.body.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 800);

            }

        } else if (tick == 1) {

            var cont_alert3 = $('.ccc').filter(function () {
                return $(this).val() == "";
            }).size();
            //var cont_alert = $('.aaa p:contains("") ').size();

            //if(cont_alert3!=0){
            if (cont_alert3 > 50) {
                $.confirm({
                    title: '¡existen campos vacios !', // hides the title.
                    cancelButton: false, // hides the cancel button.
                    confirmButton: true, // hides the confirm button.
                    closeIcon: false,
                    confirmButton: 'cerrar',
                    confirmButtonClass: 'btn-success',
                    content: false // hides content block.
                });

            } else {

                //$('.base').unslider('animate:2');

                //tick=2;
                document.getElementById('derecha').disabled = false;
                $("#derecha").css("display", "block");
                setTimeout(function () {
                    document.body.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 800);

            }

        }

    });


    $('#modificar').click(function () {

        DesBloquearCampos();
        DesBloquearMacho();
        DesBloquearMadre();
        document.getElementById('modificar').disabled = true;


    });

    $('#fech_nac').datepicker({

        todayBtn: false,
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        showOnFocus: true

    });
    $('#fecha_ingreso').datepicker({

        todayBtn: false,
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        showOnFocus: true

    });


    $("#izquierda").click(function () {

        if (tick == 1) {

            $('.base').unslider('animate:0');
            tick = 0;
            document.getElementById('izquierda').disabled = true;
            $("#izquierda").css("display", "none");
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function () {
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
            setTimeout(function () {
                document.body.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 800);

        }

    });

    $("#exit").click(function () {

        $.confirm({
            title: '¿Desea Salir?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-primary',
            cancelButtonClass: 'btn-success',

            confirm: function () {

                setTimeout(function () {

                    $(location).attr('href', '../animales/');

                }, 1000);
            },

            cancel: function () {

            }
        });

    });

    $('#save').click(function () {

        document.getElementById('save').disabled = true;


        if (ValidarCamposVacios()) {

            Guardar_Ficha();

        } else {
            setTimeout(function () {
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

            confirm: function () {
                var formData = new FormData();
                formData.append('file', $('input[type=file]')[0].files[0]);
                formData.append('action', $("#action").val());
                formData.append('id_animal', $("#id_animal").val());
                formData.append('cod_animal', $("#cod_animal").val());
                formData.append('nombre_animal', $("#nombre_animal").val());
                formData.append('raza_animal', $("#raza_animal").val());
                formData.append('rena', $("#rena").val());
                formData.append('estatus_animal', $("#estatus_animal").val());
                formData.append('lote', $("#lote").val());
                formData.append('alza', $("#alza").val());
                formData.append('numero_arete', $("#numero_arete").val());
                formData.append('sexo', $("#sexo").val());
                formData.append('fech_nac', $("#fech_nac").val());
                formData.append('peso_nac', $("#peso_nac").val());
                formData.append('fecha_ingreso', $("#fecha_ingreso").val());
                formData.append('aptitud_animal', $("#aptitud_animal").val());
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
                    success: function (data) {
                        $(".message").html(data);
                        var apg = document.getElementById("tocon").value;
                        if (apg == 1) {

                            setTimeout(function () {

                                $(location).attr('href', '../animales/');

                            }, 3000);

                        } else {
                            setTimeout(function () {
                                $(".alert").alert('close');
                                document.getElementById('save').disabled = false;

                            }, 2500);

                        }

                    }
                });

            },

            cancel: function () {
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

    function readURL(input) {

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function (e) {
                $('#file_url').attr('src', e.target.result);
            }

            reader.readAsDataURL(input.files[0]);
        }
    }

    function esnombre_animal(e) {
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

    function BloquearCampos() {

        document.getElementById("nombre_animal").disabled = true;
        document.getElementById("sexo").disabled = true;
        document.getElementById("lote").disabled = true;
        document.getElementById("estatus_animal").disabled = true;
        document.getElementById("rena").disabled = true;
        document.getElementById("alza").disabled = true;
        document.getElementById("raza_animal").disabled = true;
        document.getElementById("fech_nac").disabled = true;
        document.getElementById("peso_nac").disabled = true;
        document.getElementById("fecha_ingreso").disabled = true;
        document.getElementById("aptitud_animal").disabled = true;
        document.getElementById("categoria_ingreso").disabled = true;
        document.getElementById("categoria_futura").disabled = true;

        document.getElementById("rgd_madre").disabled = true;
        document.getElementById("nombre_madre").disabled = true;
        document.getElementById("rgd_padre").disabled = true;
        document.getElementById("nombre_padre").disabled = true;
        document.getElementById("procedencia").disabled = true;

        document.getElementById("servicio_animal").disabled = true;
        document.getElementById("observaciones").disabled = true;
        document.getElementById("numero_arete").disabled = true;
        document.getElementById("save").disabled = true;
        document.getElementById("InputFile").disabled = true;



    }

    function DesBloquearCampos() {

        document.getElementById("nombre_animal").disabled = false;
        document.getElementById("sexo").disabled = false;
        document.getElementById("lote").disabled = false;
        document.getElementById("estatus_animal").disabled = false;
        document.getElementById("rena").disabled = false;
        document.getElementById("alza").disabled = false;
        document.getElementById("raza_animal").disabled = false;
        document.getElementById("fech_nac").disabled = false;
        document.getElementById("peso_nac").disabled = false;
        document.getElementById("fecha_ingreso").disabled = false;
        document.getElementById("aptitud_animal").disabled = false;
        document.getElementById("categoria_ingreso").disabled = false;
        document.getElementById("categoria_futura").disabled = false;
        document.getElementById("procedencia").disabled = false;
        document.getElementById("servicio_animal").disabled = false;
        document.getElementById("observaciones").disabled = false;
        document.getElementById("numero_arete").disabled = false;
        document.getElementById("save").disabled = false;
        document.getElementById("InputFile").disabled = false;

    }

    //////////////////VALIDANDO QUE NO SE REPITA EL NUMERO DE ARETE

    $("#numero_arete").blur(function () {

        $.post("../../controllers/manimales_controller", {

            action: "search_nro_arete",
            nro_arete: $("#numero_arete").val(),
            cod_animal: $("#cod_animal").val(),



        }).done(function (data) {

            var parsedJson = $.parseJSON(data);
            var validar = parsedJson.nro_arete;

            if (validar != 0) {
                document.getElementById('ms_nro_arete').style.display = 'block';
                document.getElementById("ms_nro_arete").innerHTML =
                    'NUMERO DE ARETE Ya se Encuentra Registrado';
                document.getElementById('numero_arete').focus();
                var valor = $("#numero_arete").val();
                setTimeout(function () {

                    document.getElementById("ms_nro_arete").innerHTML = '';
                    $("#numero_arete").val(valor);

                }, 2000);

            }

        }, "json");

    });
    ///////////////////////// VALIDANDO QUE NO SE REPITA EL NOMBRE

    $("#nombre_animal").blur(function () {
        $.post("../../controllers/manimales_controller", {

            action: "search_nombre_animal",
            nombre_animal: $("#nombre_animal").val(),
            cod_animal: $("#cod_animal").val(),


        }).done(function (data) {

            var parsedJson = $.parseJSON(data);
            var validar = parsedJson.nombre_animal;

            if (validar != 0) {
                document.getElementById('ms_nombre').style.display = 'block';
                document.getElementById("ms_nombre").innerHTML =
                    'ESTE NOMBRE Ya se Encuentra Registrado';
                document.getElementById('ms_nombre').focus();
                var valor = $("#nombre_animal").val();
                setTimeout(function () {

                    document.getElementById("ms_nombre").innerHTML = '';
                    $("#nombre_animal").val(nombre_real);

                }, 2000);

            }

        }, "json");

    });

    function validarImagen() {
        var fileSize = $('#InputFile')[0].files[0].size;
        var siezekiloByte = parseInt(fileSize / 1024);
        if (siezekiloByte > 5120) {
            $('#InputFile').val("");

            $.confirm({

                title: 'La imagen es muy grande',
                content: false,
                confirmButtonClass: 'none',
                cancelButtonClass: 'btn-success',
                cancel: function () {

                }
            });
            return false;
        } else {
            return true;

        }
    }

    /// Validaciones Importantes


    $("#fecha_ingreso").change(function () {


        if (conta_f == 0) {
            conta_f++;

            validarfecha();
            conta_f++;


        } else {
            conta_f = 0;

        }


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
                    cancel: function () {

                    }

                });
                document.getElementById('aptitud_animal').value = "";
                exit();


            } else {
                document.getElementById('aptitud_animal').disabled = false;
                /* La FECHA DE NACIMIENTO queda siempre editable: al cambiarla se
                   recalcula la Categoría Actual. */
                document.getElementById('fecha_ingreso').disabled = true;


            }


        }
    }

    $("#fech_nac").change(function () {


        var sexo = $("#sexo").val();
        if (sexo != "") {
            Buscar_Categoria_Actual();
        }


    });

    function Buscar_Categoria_Actual() {


        $.post("../../controllers/mcategorias_controller", {

            action: "search_categoria_actual",
            sexo: $("#sexo").val(),
            fecha_nac: $("#fech_nac").val()

        }).done(function (data) {

            var parsedJson = $.parseJSON(data);
            var cat_actual = (parsedJson && parsedJson.cat_actual) ? parsedJson.cat_actual : '';
            $("#categoria_actual").val(cat_actual);

        }, "json");

    }

    ///////////////////////////////// AGREGANDO BUSQUEDA DE MADRE Y PADRE

    // BUSCA LOS DATOS DE LA HEMBRA

    $('#buscar').click(function () {
        // continuar con la busqueda, según la seleccion
        var rgb_animal_h = $("#rgd_madre").val();
        BloquearMacho();

        if (rgb_animal_h != "") {
            ID_GLOBAL = rgb_animal_h;
            buscarx = buscarx;
            document.getElementById("nombre_madre").value = '';
            // parametros de busqueda condicionada

            var rgb_animal_h = $("#rgd_madre").val();
            // num=parseInt(num);
            document.getElementById("nombre_madre").value = '';
            document.getElementById("limpiar").disabled = false;


            $.post("../../controllers/manimales_controller", {
                action: "get_buscar_rgb_madre",
                rgd_madre: rgb_animal_h,
                buscarx: buscarx
            }).done(function (data) {
                var parsedJson = $.parseJSON(data);
                if (parsedJson.resultado == 'error') {
                    $(".message").html(parsedJson.mensaje);
                    $('.base').unslider('animate:0');
                    $('.base').unslider('animate:1');
                    setTimeout(function () {
                        $(".alert").alert('close');

                    }, 3000);

                }
                if (parsedJson.condicion == 'none') {

                    setTimeout(function () {

                        $.confirm({
                            title: '¿Desea Agregar la Madre de Forma Manual ?',
                            content: false,
                            confirmButton: 'Si',
                            cancelButton: 'No',
                            confirmButtonClass: 'btn-success',
                            cancelButtonClass: 'btn-danger',
                            confirm: function () {
                                document.getElementById('nombre_madre').disabled =
                                    false;
                                document.getElementById('rgd_madre').disabled = false;
                                document.getElementById("buscar").disabled = true;
                                document.getElementById('b_codigo').disabled = true;
                                document.getElementById('b_nro_arete').disabled = true;
                                DesBloquearMacho();

                            },
                            cancel: function () {

                            }
                        });

                    }, 2000);


                } else {
                    DesBloquearMacho();
                    document.getElementById("buscar").disabled = true;
                    document.getElementById('b_codigo').disabled = true;
                    document.getElementById('b_nro_arete').disabled = true;
                    document.getElementById('nombre_madre').disabled = true;
                    document.getElementById('rgd_madre').disabled = true;


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


                }

            });
        } else {
            document.getElementById('ms_rgd_madre').style.display = 'block';
            document.getElementById("ms_rgd_madre").innerHTML = 'Debe Ingresar el Código';
            setTimeout(function () {
                document.getElementById("ms_rgd_madre").innerHTML = '';

            }, 2000);
        }

    });

    // BUSCA LOS DATOS DE LA HEMBRA

    $('#buscar_m').click(function () {
        // continuar con la busqueda, según la seleccion
        var rgb_animal_m = $("#rgd_padre").val();


        if (rgb_animal_m != "") {
            ID_GLOBAL_M = rgb_animal_m;
            buscarx_m = buscarx_m;
            document.getElementById("nombre_padre").value = '';
            // parametros de busqueda condicionada

            var rgb_animal_m = $("#rgd_padre").val();
            // num=parseInt(num);
            document.getElementById("nombre_padre").value = '';

            $.post("../../controllers/manimales_controller", {
                action: "get_buscar_rgb_padre",
                rgd_padre: rgb_animal_m,
                buscarx_m: buscarx_m
            }).done(function (data) {
                var parsedJson = $.parseJSON(data);
                if (parsedJson.resultado == 'error') {
                    $(".message").html(parsedJson.mensaje);
                    $('.base').unslider('animate:0');
                    $('.base').unslider('animate:1');
                    setTimeout(function () {
                        $(".alert").alert('close');

                    }, 2500);

                }
                if (parsedJson.condicion == 'none') {

                    setTimeout(function () {

                        $.confirm({
                            title: '¿Desea Agregar el Padre de Forma Manual ?',
                            content: false,
                            confirmButton: 'Si',
                            cancelButton: 'No',
                            confirmButtonClass: 'btn-success',
                            cancelButtonClass: 'btn-danger',
                            confirm: function () {
                                document.getElementById('nombre_padre').disabled = false;
                                document.getElementById('rgd_padre').disabled = false;
                                document.getElementById("buscar_m").disabled = true;
                                document.getElementById('macho_codigo').disabled = true;
                                document.getElementById('macho_nro_arete').disabled = true;
                                document.getElementById("limpiar_m").disabled = false;
                            },
                            cancel: function () {

                            }
                        });

                    }, 2000);

                } else {

                    document.getElementById("buscar_m").disabled = true;
                    document.getElementById('macho_codigo').disabled = true;
                    document.getElementById('macho_nro_arete').disabled = true;
                    document.getElementById('nombre_padre').disabled = true;
                    document.getElementById('rgd_padre').disabled = true;
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
            setTimeout(function () {
                document.getElementById("ms_rgd_padre").innerHTML = '';

            }, 2000);
        }

    });
    $('#limpiar').click(function () {

        document.getElementById("rgd_madre").disabled = true;
        document.getElementById("buscar").disabled = false;
        document.getElementById("limpiar").disabled = true;

        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById("b_codigo").disabled = false;
        document.getElementById("b_nro_arete").disabled = false;

        document.getElementById("nombre_madre").value = '';
        document.getElementById("rgd_madre").value = '';
        document.getElementById("nombre_madre").value = nombre_madre;
        document.getElementById("rgd_madre").value = rgd_madre;
        document.getElementById("nombre_madre").disabled = true;

    });
    $('#limpiar_m').click(function () {

        document.getElementById("rgd_padre").disabled = true;
        document.getElementById("buscar_m").disabled = false;
        document.getElementById("limpiar_m").disabled = true;

        document.getElementById('macho_codigo').checked = false;
        document.getElementById('macho_nro_arete').checked = false;
        document.getElementById("macho_codigo").disabled = false;
        document.getElementById("macho_nro_arete").disabled = false;

        document.getElementById("nombre_padre").value = '';
        document.getElementById("rgd_padre").value = '';

        document.getElementById("nombre_padre").value = nombre_padre;
        document.getElementById("rgd_padre").value = rgd_padre;

        document.getElementById("nombre_padre").disabled = true;

    });
    ///*********************************** */

    $("#b_codigo").click(function () {
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

            document.getElementById('rgd_madre').value = rgd_madre;
            document.getElementById("nombre_madre").value = nombre_madre;



        }

    });
    $("#b_nro_arete").click(function () {
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
            document.getElementById('rgd_madre').value = rgd_madre;
            document.getElementById("nombre_madre").value = nombre_madre;

        }

    });
    ///*********************************** */
    /////////  SELECCION DE MACHO ////////////

    ///*********************************** */

    $("#macho_codigo").click(function () {
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

            document.getElementById('rgd_padre').value = rgd_padre;
            document.getElementById("nombre_padre").value = nombre_padre;


        }

    });

    $("#macho_nro_arete").click(function () {
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

            document.getElementById('rgd_padre').value = rgd_padre;
            document.getElementById("nombre_padre").value = nombre_padre;

        }



    });
    ///*********************************** */

    ///*********************************** */

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
    ///*********************************** */

    function DesBloquearMadre() {
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

    /* ============================================================
       HISTORIAL DE MOVIMIENTOS DEL ANIMAL
       ============================================================ */
    var id_animal_hist = <?php echo intval($cad0); ?>;

    function cargarMovimientos() {

        $('#movimientos_loading').show();
        $('#movimientos_vacio').hide();
        $('#movimientos_tabla').hide();

        $.get("../../data_json/data_mmovimientos_animal.php", {
            id_animal: id_animal_hist
        }, function (data) {

            var movs;
            try {
                movs = (typeof data === 'string') ? JSON.parse(data) : data;
            } catch (e) {
                console.error('Respuesta cruda:', data);
                movs = [];
            }

            $('#movimientos_loading').hide();

            if (!movs || movs.length === 0) {
                $('#movimientos_vacio').show();
                return;
            }

            var html = '';
            $.each(movs, function (i, m) {

                var estado_html = m.revertido
                    ? '<span class="label label-warning">REVERTIDO</span>'
                    : '<span class="label label-success">ACTIVO</span>';

                var cat_txt = m.categoria_anterior + ' → ' + m.categoria_nueva;
                if (m.categoria_anterior === m.categoria_nueva) {
                    cat_txt = m.categoria_anterior + ' <small style="color:#6B7480;">(sin cambio)</small>';
                }

                html += '<tr class="fila_mov" data-id="' + m.movimiento_id + '" style="cursor:pointer;">'
                    + '<td><strong>' + m.fecha + '</strong></td>'
                    + '<td>' + m.tipo + '</td>'
                    + '<td>' + m.origen + ' → <strong>' + m.destino + '</strong></td>'
                    + '<td>' + m.motivo + '</td>'
                    + '<td>' + cat_txt + '</td>'
                    + '<td>' + estado_html + '</td>'
                    + '<td style="text-align:center;"><i class="fa fa-file-pdf-o" style="color:#C0392B;" title="Ver PDF"></i></td>'
                    + '</tr>';
            });

            $('#movimientos_body').html(html);
            $('#movimientos_total').text(movs.length);
            $('#movimientos_tabla').show();

            /* Click en una fila → abrir el PDF del movimiento */
            $('.fila_mov').off('click').on('click', function () {
                var mov_id = $(this).data('id');
                var parametros = window.btoa(mov_id);
                window.open(
                    "../../Reportes/reportes/sovca/rmov.php?%$%=" + parametros,
                    '',
                    "titlebars=0, toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=1,width=850,height=700,top=100,left=250"
                );
            });

            /* Hover visual */
            $('.fila_mov').hover(
                function () { $(this).css('background', '#F0F9F2'); },
                function () { $(this).css('background', ''); }
            );

        }).fail(function (xhr) {
            $('#movimientos_loading').hide();
            console.error('HTTP ' + xhr.status, xhr.responseText);
            $('#movimientos_vacio').html(
                '<i class="fa fa-exclamation-triangle" style="font-size:32px;color:#E74C3C;"></i>' +
                '<p style="margin-top:10px;color:#C0392B;">No se pudieron cargar los movimientos. Revise la consola (F12).</p>'
            ).show();
        });
    }

    /* Cargar los movimientos después de que la ficha cargue todos los datos */
    $(document).ready(function () {
        setTimeout(cargarMovimientos, 800);
    });
</script>
<!-- Scripts auxiliares NUEVOS (no interfieren con la lógica original) -->
<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });

(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    var DURATION = 1500;

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
   (el buscador manual de Nro Arete / Código queda oculto y disponible con el
   enlace "use la búsqueda manual" para madre/padre NO registrados en el hato)
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