<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-user-plus"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar usuario</h3>
            <p class="crud-form__subtitle">Configura el acceso, rol y datos de contacto de la cuenta.</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <div id="rol_resumen" class="usuario-role-summary" aria-live="polite"></div>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid">

            <!-- ========== CARD 1: Datos del usuario ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-user"></i>
                    <h3>Datos del usuario</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="usuario">Usuario</label>
                        <input type="text" class="form-control" id="usuario" data-auto-uppercase="true"
                               placeholder="Ingrese el nickname o usuario"
                               onblur="alsalir(this.id)" tabindex="1" autocomplete="new-text">
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_usuario'></div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label>Tipo de Usuario</label>
                            <select id="t_user" class="form-control">
                                <option></option>
                            </select>
                        </div>

                        <div class="crud-form__field">
                            <label>Rol</label>
                            <select id="rol" class="form-control">
                                <option></option>
                            </select>
                        </div>
                    </div>

                    <div class="crud-form__field" id="grp_plan">
                        <label for="plan_reg">Plan de Registros</label>
                        <select id="t_plan" name="t_plan" class="form-control">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field" id="grp_suscripcion">
                        <label for="t_suscripcion">Tipo de Suscripción</label>
                        <select id="t_suscripcion" name="t_suscripcion" class="form-control">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field" id="grp_fecha_suscripcion">
                        <label for="fecha_activa_suscripcion">Fecha de Activación</label>
                        <input type="date" id="fecha_activa_suscripcion" name="fecha_activa_suscripcion" class="form-control">
                    </div>

                    <div class="crud-form__field" id="grp_fundo">
                        <label for="fundo">Fundo</label>
                        <select id="fundo" name="fundo" class="form-control">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field" id="grp_modalidad">
                        <label>Modalidad de Cuenta</label>
                        <div class="checkbox" style="margin-top:0;">
                            <label style="font-weight:normal;">
                                <input type="checkbox" id="es_multifundo" value="1">
                                Permitir varios fundos en esta cuenta
                            </label>
                        </div>
                    </div>

                    <div class="crud-form__field" id="grp_max_fundos" style="display:none;">
                        <label for="max_fundos">Fundos permitidos por el plan</label>
                        <input type="text" id="max_fundos" class="form-control" value="1" readonly
                            style="background:#f5f6f7; cursor:not-allowed;">
                        <small id="max_fundos_nota" style="color:#888; display:block; margin-top:4px;">
                            El cupo de fundos lo define el plan seleccionado.
                        </small>
                    </div>

                    <div class="crud-form__field" id="grp_acceso_offline">
                        <label>Acceso sin conexión (offline)</label>
                        <div class="checkbox" style="margin-top:0;">
                            <label style="font-weight:normal;">
                                <input type="checkbox" id="acceso_offline" value="1" disabled>
                                Permitir que esta cuenta use la app sin conexión
                            </label>
                        </div>
                        <small style="color:#888; display:block; margin-top:4px;">
                            Solo el productor (dueño del fundo) tiene este permiso: los usuarios
                            de su fundo lo heredan automáticamente. El plan DIAMANTE es el único que
                            lo trae activo; con los demás planes queda apagado hasta que el
                            administrador lo habilite aquí. Si está apagado, al intentar entrar a la
                            app offline se le indicará que no tiene acceso y que contacte al
                            administrador del sistema.
                        </small>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="password1">Contraseña</label>
                            <input type="password" class="form-control" id="password1" placeholder="Password"
                                   tabindex="4" autocomplete="new-password">
                        </div>

                        <div class="crud-form__field">
                            <label for="password2">Confirmar contraseña</label>
                            <input type="password" class="form-control" id="password2" placeholder="Password"
                                   tabindex="5" autocomplete="new-password">
                        </div>
                    </div>

                    <input type="hidden" id="action" value="add">

                    <div id="pswd_info">
                        <h4>La contraseña debe cumplir los siguientes requisitos:</h4>
                        <ul id="indicaciones">
                            <li id="letter" class="invalid">Al menos <strong>una letra minúscula</strong></li>
                            <li id="capital" class="invalid">Al menos <strong>una letra mayúscula</strong></li>
                            <li id="number" class="invalid">Al menos <strong>un número</strong></li>
                            <li id="length" class="invalid">Longitud de almenos <strong>8 caracteres</strong></li>
                            <li id="especial" class="invalid">Uso de caracteres especiales<strong> @ # $ %</strong></li>
                        </ul>
                    </div>
                    <div id="pswd_confirm_info">
                        <ul>
                            <li id="compare" class="invalid">La nueva clave y la clave de confirmación deben ser iguales</li>
                        </ul>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Datos adicionales ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-id-card"></i>
                    <h3>Datos adicionales</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="nombre">Nombre y Apellido</label>
                        <input type="text" class="form-control" id="nombre" data-auto-uppercase="true"
                               placeholder="Ingrese el nombre del usuario"
                               onblur="return esnombre2(this.value);" tabindex="6" autocomplete="new-text">
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_nombre'></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="telefono">Teléfono de contacto (+58-000-0000000)</label>
                        <input type="text" class="form-control" id="telefono" placeholder="Ingrese un número telefónico"
                               onpaste="return false" tabindex="565" onkeypress="return estelef(event);"
                               onblur="alsalirtlf(this.id)" autocomplete="off">
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_telefono'></div>
                    </div>

                    <div id="confirma_telefono">
                        <ul>
                            <li id="conf_telefono" style="color:#fff;text-align:center"></li>
                        </ul>
                    </div>

                    <div class="crud-form__field">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" placeholder="indique un correo electrónico"
                               onpaste="return false" tabindex="7" autocomplete="new-email">
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_email'></div>
                    </div>

                    <div class="crud-form__field" style="text-align:center;">
                        <img class="imgusr" alt="" src="#" id="file_url" width="120" height="150">
                        <label for="InputFile" style="text-align:left; margin-top:8px;">Adjuntar imagen no mayor a 1 MB.</label>
                        <input type="file" id="InputFile" tabindex="8">
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="9">
                <i class="fa fa-fw fa-save"></i> Guardar usuario
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script language="JavaScript" src="../../plugins/mask/jquery.mask.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<script type="text/javascript">

// Igual que en la edición: una sesión SUPER USUARIO puede definir todos
// los campos, incluida la contraseña de la cuenta que crea.
var esSuperUsuarioSesion = <?php echo (isset($_SESSION['rolx']) && (int) $_SESSION['rolx'] === 4) ? 'true' : 'false'; ?>;

document.getElementById('rol').disabled = true;

	$(document).ready(function() {

		$('#ext_mail').hide();
		$(document).on('input', '[data-auto-uppercase]', function() {
			var cursor = this.selectionStart;
			this.value = this.value.toLocaleUpperCase('es');
			if (cursor !== null && document.activeElement === this) {
				this.setSelectionRange(Math.min(cursor, this.value.length), Math.min(cursor, this.value.length));
			}
		});

		$('#grp_plan, #grp_suscripcion, #grp_fecha_suscripcion, #grp_fundo, #grp_modalidad, #grp_max_fundos, #grp_acceso_offline').hide();

		// El plan DIAMANTE es el único que incluye el acceso sin conexión.
		// Con los demás planes la casilla queda apagada, pero el administrador
		// puede activarla a mano (es quien asigna el permiso).
		function planEsDiamante() {
			var texto = ($('#t_plan option:selected').text() || '').toUpperCase();
			return texto.indexOf('DIAMANTE') > -1;
		}

		function actualizarAccesoOfflinePorPlan() {
			$('#acceso_offline').prop('checked', planEsDiamante());
		}

		function actualizarCamposPorRol() {
			var rol = parseInt($('#rol').val(), 10) || 0;
			var esProductor = rol === 2;
			var esEncargado = rol === 3;
			var requiereClave = esSuperUsuarioSesion || (rol !== 1 && rol !== 4 && rol > 0);

			$('#grp_plan, #grp_suscripcion, #grp_fecha_suscripcion, #grp_modalidad').toggle(esProductor);
			$('#grp_fundo').toggle(esEncargado);
			$('#grp_max_fundos').toggle(esProductor && $('#es_multifundo').is(':checked'));
			$('label[for="password1"], #password1, label[for="password2"], #password2').toggle(requiereClave);

			// El permiso de acceso sin conexión es del productor (dueño del
			// fundo): a los demás roles no se les muestra ni se les habilita.
			$('#grp_acceso_offline').toggle(esProductor);
			$('#acceso_offline').prop('disabled', !esProductor);

			if (!esProductor) {
				$('#es_multifundo').prop('checked', false);
				$('#max_fundos').val(1);
				$('#t_plan, #t_suscripcion, #fecha_activa_suscripcion').val('');
				$('#acceso_offline').prop('checked', false);
			}
			if (!esEncargado) $('#fundo').val('');

			var resumen = '';
			if (esProductor) resumen = 'Productor: selecciona el plan, la vigencia y si la cuenta puede administrar varios fundos.';
			else if (esEncargado) resumen = 'Encargado: selecciona el fundo al que quedará asociado. El plan y la suscripción dependen del productor.';
			$('#rol_resumen').text(resumen).toggleClass('visible', !!resumen);

			if (esEncargado && $('#fundo option').length < 2) {
				$.post('../../controllers/mfundos_controller', { action: 'get_fundos_encargado' })
					.done(function(data) { $('#fundo').html(data); });
			}
		}

	$.post("../../controllers/mplanes_controller", {
		action: "get_t_plan"
	}).done(function(data) {
		$("#t_plan").html(data);
                /* Recalcular el cupo mostrado con el plan ya cargado. */
                actualizarCupoFundos();

	});

	$.post("../../controllers/musuarios_controller.php", {
		action: "get_suscripciones"
	}).done(function(data) {
		$("#t_suscripcion").html(data);
	});
	
///////////////////////

		$.post("../../controllers/musuarios_controller", {
			action: "get_t_user"
		}).done(function(data) {
			$("#t_user").html(data);
		});

		$("#t_user").change(function() {
			$('#rol').val('');
			var t_user = $("#t_user").val();

			$.post("../../controllers/musuarios_controller", {
				action: "get_roles_tipo",
				t_user: t_user
			}).done(function(data) {
				$("#rol").html(data);
				actualizarCamposPorRol();
			});
			document.getElementById('rol').disabled = false;
		});

		$('#rol').on('change', actualizarCamposPorRol);

		// El plan DIAMANTE es el único que activa el acceso sin conexión:
		// al elegirlo se marca solo, y al cambiarlo por otro plan se
		// desmarca (el administrador puede volver a marcarlo a mano).
		$('#t_plan').on('change', function() {
			if (parseInt($('#rol').val(), 10) === 2) {
				actualizarAccesoOfflinePorPlan();
			}
			actualizarCupoFundos();
		});
		// NUEVO: toggle del campo max_fundos según el checkbox
		$("#es_multifundo").on('change', function() {
			if ($(this).is(':checked') && parseInt($('#rol').val(), 10) === 2) {
				$('#grp_max_fundos').slideDown(150);
				actualizarCupoFundos();
			} else {
				$(this).prop('checked', false);
				$('#grp_max_fundos').slideUp(150);
				$('#max_fundos').val(1);
			}
		});

		$("#save").click(function() {
			var formData = new FormData();
			formData.append('file', $('input[type=file]')[0].files[0]);
			formData.append('action', $("#action").val());
			formData.append('nombre', $("#nombre").val());
			formData.append('usuario', $("#usuario").val());
			// validar
			formData.append('fundo', $("#fundo").val());			//
			formData.append('t_user', $("#t_user").val());
			formData.append('t_plan', $("#t_plan").val());			
			formData.append('t_suscripcion', $("#t_suscripcion").val());
			formData.append('fecha_activa_suscripcion', $("#fecha_activa_suscripcion").val());
			formData.append('email', $("#email").val());
			formData.append('telefono', $("#telefono").val());
			formData.append('rol', $("#rol").val());
			formData.append('password1', $("#password1").val());
			formData.append('password2', $("#password2").val());

			// NUEVO: enviar campos multi-fundo
			formData.append('es_multifundo', $("#es_multifundo").is(':checked') ? 1 : 0);
			formData.append('max_fundos', $("#es_multifundo").is(':checked') ? ($("#max_fundos").val() || 1) : 1);
			formData.append('acceso_offline', $("#acceso_offline").is(':checked') ? 1 : 0);

			$.ajax({
				url: "../../controllers/musuarios_controller",
				type: "POST",
				data: formData,
				contentType: false,
				cache: false,
				processData: false,
				success: function(data) {
					$(".message").html(data);

					var apg = document.getElementById("tocon").value;


					if (apg == 1) {

						setTimeout(function() {

							$(location).attr('href', '../usuarios/frm_registrar');

						}, 3000);

					}

				}
			});

		});
		// ********************************************************************************************
		$("#exit").click(function() {
			$(location).attr('href', '../usuarios/');
		});
		//**************************************************************

		$("#InputFile").change(function() {

			if (validarImagen()) {
				readURL(this);

			}


		});



		/******************************************************************************/
		$('#password1').keyup(function() {
			// set password variable
			var pswd = $(this).val();

			$.post("../../controllers/validar_contrasena", {
				action: "validar",
				clave: pswd
			}).done(function(data) {
				$("#indicaciones").html(data);

			});

		}).focus(function() {
			$('#pswd_info').show();
		}).blur(function() {
			$('#pswd_info').hide();
		});


		$('#password2').keyup(function() {
			var pw3 = $(this).val();
			var pswd = $("#password2").val();

			if (pswd != "") {

				if (pswd === pw3) {

					$('#compare').removeClass('invalid').addClass('valid');
				} else {
					$('#compare').removeClass('valid').addClass('invalid');
				}
			}
		}).focus(function() {
			$('#pswd_confirm_info').show();
		}).blur(function() {
			$('#pswd_confirm_info').hide();
		});

		/********************************************************************/
	});

	//############################################################################

	function alsalir(e) {
		var caso1 = document.getElementById(e).value;
		if (caso1.length > 0 && caso1.length < 5) {
			document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 5 caractéres';
		} else {
			document.getElementById('ms_' + e).innerHTML = '';
		}
	}

	//############################################################################

	function esnombre2(v) {
		var caso2 = document.getElementById('nombre').value;
		if (caso2.length < 5) {

			document.getElementById('ms_nombre').innerHTML = 'Debe escribir al menos 5 caractéres';

		} else {

			var texto2 = v.split(" ");
			var n_palabras = texto2.length;

			if (n_palabras >= 2 && n_palabras <= 4) {

				document.getElementById("ms_nombre").innerHTML = '';
				//document.getElementById("valido").value = '1';
			} else {
				document.getElementById("ms_nombre").innerHTML = 'Ingrese nombre(s) y apellido(s)';
				//document.getElementById("valido").value = '0';
			}
		} //end else
	}

	//############################################################################

	function esemail(e) {

		k = (document.all) ? e.keyCode : e.which;
		if (k == 8 || k == 0 || k == 13) return true;
		patron = /[a-z0-9\_\-\.\+\@\ ]/;
		n = String.fromCharCode(k);

		if (patron.test(n) == '') {

			document.getElementById('ms_email').style.display = 'block';
			document.getElementById("ms_email").innerHTML = 'No utilice caractéres especiales ni en mayuscula';
			return patron.test(n);

		} else {

			document.getElementById("ms_email").value = '';
			return patron.test(n);

		}
	}

	//############################################################################

	function esemail2(v) {

		patron = /^([a-z0-9_\.\-].{5,})+\@(([a-z])+\.)+(([a-z])+\.)+([a-z])+$/;

		if (patron.test(v)) {

			document.getElementById("ms_email").innerHTML = '';

		} else {

			document.getElementById("ms_email").innerHTML = 'Ingrese un correo electrónico valido!';

		}

	}

	//############################################################################

	function readURL(input) {

		if (input.files && input.files[0]) {
			var reader = new FileReader();

			reader.onload = function(e) {
				$('#file_url').attr('src', e.target.result);
			}

			reader.readAsDataURL(input.files[0]);
		}
	}


	function validarImagen() {
		var fileSize = $('#InputFile')[0].files[0].size;
		var siezekiloByte = parseInt(fileSize / 1024);
		if (siezekiloByte > 1024) {
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
	}

	function estelefccc(e) {

		k = (document.all) ? e.keyCode : e.which;
		if (k == 8 || k == 0 || k == 13) return true;
		patron = /[0-9\-\(\)]/;
		n = String.fromCharCode(k);

		if (patron.test(n) == '') {

			document.getElementById('ms_telefono').style.display = 'block';
			document.getElementById("ms_telefono").innerHTML = 'Use solo números y "-"';
			return patron.test(n);

		} else {

			document.getElementById("ms_telefono").innerHTML = '';
			return patron.test(n);

		}

	}

	function alsalirtlf(e) {
		var caso1 = document.getElementById(e).value;
		if (caso1.length < 9) {
			document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 9 caractéres';
		} else {
			document.getElementById('ms_' + e).innerHTML = '';
		}
	}
	$('#telefono').mask('+00-000-0000-000', {

		translation: {

			'A': {

				pattern: /[03]/,
				optional: false

			}

		},

	});
</script>

<!-- Script auxiliar: header action dispara el exit oculto -->
<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>