<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = $valores[0];
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<div class="crud-form">

	<div class="message"></div>

	<header class="crud-form__header">
		<span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
		<div>
			<h3 class="crud-form__title">Editar usuario</h3>
			<p class="crud-form__subtitle">Actualiza el acceso, la suscripción y los datos de contacto.</p>
		</div>
		<div class="crud-form__header-actions">
			<button id="exit_top" type="button" class="btn btn-warning">
				<i class="fa fa-reply"></i> Regresar
			</button>
		</div>
	</header>

	<div id="rol_resumen" class="usuario-role-summary" aria-live="polite"></div>

	<form id="form" role="form" enctype="multipart/form-data">
		<input type="hidden" id="action" value="edit">
		<input type="hidden" id="id">
		<input type="hidden" id="act_email">

		<div class="crud-form__grid">

			<div class="crud-form__card">
				<div class="crud-form__card-head">
					<i class="fa fa-user"></i>
					<h3>Datos del usuario</h3>
				</div>
				<div class="crud-form__card-body">

					<div class="crud-form__field">
						<label for="usuario">Usuario</label>
						<input type="text" class="form-control" id="usuario" data-auto-uppercase="true"
							placeholder="Ingrese el nickname o usuario" onblur="alsalir(this.id)" tabindex="1">
						<div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;"
							id='ms_usuario'></div>
					</div>

					<div class="crud-form__row">
						<div class="crud-form__field">
							<label>Tipo de Usuario</label>
							<select id="t_user" class="form-control" tabindex="2">
								<option></option>
							</select>
						</div>

						<div class="crud-form__field">
							<label>Rol</label>
							<select id="rol" class="form-control" tabindex="2">
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
						<input type="date" id="fecha_activa_suscripcion" name="fecha_activa_suscripcion"
							class="form-control">
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
							<input type="password" class="form-control" id="password1" placeholder="Contraseña"
								tabindex="4" value="" autocomplete="new-password">
						</div>

						<div class="crud-form__field">
							<label for="password2">Confirmar contraseña</label>
							<input type="password" class="form-control" id="password2" placeholder="Contraseña"
								tabindex="5" autocomplete="new-password">
						</div>
					</div>

					<div id="pswd_info">
						<h4>La contraseña debe cumplir los siguientes requisitos:</h4>
						<ul id="indicaciones">
							<li id="letter" class="invalid">Al menos <strong>una letra</strong></li>
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

			<div class="crud-form__card">
				<div class="crud-form__card-head">
					<i class="fa fa-id-card"></i>
					<h3>Datos adicionales</h3>
				</div>
				<div class="crud-form__card-body">

					<div class="crud-form__field">
						<label for="nombre">Nombre y Apellido</label>
						<input type="text" class="form-control" id="nombre" data-auto-uppercase="true"
							placeholder="Ingrese el nombre del usuario" onblur="return esnombre2(this.value);"
							tabindex="6">
						<div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;"
							id='ms_nombre'></div>
					</div>

					<div class="crud-form__field">
						<label for="telefono">Teléfono de contacto (+58-000-0000000)</label>
						<input type="text" class="form-control" id="telefono" placeholder="Ingrese un número telefónico"
							onpaste="return false" tabindex="565" onkeypress="return estelef(event);"
							onblur="alsalirtlf(this.id)" autocomplete="off">
						<div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;"
							id='ms_telefono' class="aaa"></div>
					</div>

					<div id="confirma_telefono">
						<ul>
							<li id="conf_telefono" style="color:#fff;text-align:center"></li>
						</ul>
					</div>

					<div class="crud-form__field">
						<label for="email">Email</label>
						<input type="email" class="form-control" id="email" placeholder="@ruted.net"
							onkeypress="return esemail(event);" onblur="return esemail2(this.value);"
							onpaste="return false" tabindex="7">
						<div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;"
							id='ms_email'></div>
					</div>

					<div class="crud-form__field" style="text-align:center;">
						<img class="imgusr" alt="" src="#" id="file_url" width="120" height="150">
						<label for="InputFile" style="text-align:left; margin-top:8px;">Adjuntar nueva imagen no mayor a
							1 MB.</label>
						<input type="file" id="InputFile" tabindex="8">
					</div>

				</div>
			</div>

		</div>

		<div class="crud-form__actions">
			<button id="exit" type="button" class="btn btn-warning" style="display:none;">
				<i class="fa fa-fw fa-reply"></i> Cancelar
			</button>
			<button id="save" type="button" class="btn btn-success" tabindex="9">
				<i class="fa fa-fw fa-save"></i> Guardar cambios
			</button>
		</div>

	</form>

</div>

<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<script language="JavaScript" src="../../plugins/mask/jquery.mask.min.js"></script>
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<script type="text/javascript">
var plan = '';
var fundo = 0;
var record = <?php echo intval($cad0); ?>;
/*
 * Sesión SUPER USUARIO: puede actualizar CUALQUIER campo de la cuenta
 * (usuario, tipo, rol, correo, teléfono, contraseña, plan, suscripción,
 * modalidad y permiso offline) sin los bloqueos que aplican al resto.
 */
var esSuperUsuarioSesion = <?php echo (isset($_SESSION['rolx']) && (int) $_SESSION['rolx'] === 4) ? 'true' : 'false'; ?>;

/*
 * Si el valor guardado no está en el catálogo que se acaba de pintar
 * (por ejemplo un tipo de usuario que ya no se ofrece, o un 0 viejo),
 * se agrega la opción con su nombre real para que el campo no quede
 * vacío al abrir el formulario.
 */
function asegurarOpcion(selector, valor, texto) {
	var $sel = $(selector);
	if (valor === null || valor === undefined || String(valor).trim() === '') {
		return;
	}
	var v = String(valor);
	if ($sel.find('option[value="' + v + '"]').length === 0) {
		$sel.append($('<option>').val(v).text(texto || ('Valor ' + v)));
	}
	$sel.val(v);
}

/* El cupo de fundos lo define el plan: cada opción del select trae
   data-max-fundos (ver controllers/mplanes_controller.php). El campo es
   informativo; el servidor lo vuelve a calcular al guardar. */
function actualizarCupoFundos() {
	var cupo = parseInt($('#t_plan option:selected').attr('data-max-fundos'), 10);
	if (isNaN(cupo) || cupo < 1) { cupo = 1; }
	var multi = $('#es_multifundo').is(':checked');
	$('#max_fundos').val(multi ? cupo : 1);
	$('#max_fundos_nota').text(multi
		? 'El plan seleccionado permite ' + cupo + ' fundo(s) en esta cuenta.'
		: 'La cuenta no es multifundo: solo puede tener 1 fundo.');
}

function aplicarCamposPorRol(valorRol) {
	var rolSeleccionado = parseInt(valorRol, 10) || 0;
	var esProductor = rolSeleccionado === 2;
	var esEncargado = rolSeleccionado === 3;
	// El SUPER USUARIO siempre ve los campos de contraseña (antes se
	// ocultaban para los roles 1 y 4 y no podía cambiar su propia clave
	// desde esta pantalla).
	var requiereClave = esSuperUsuarioSesion || (rolSeleccionado !== 1 && rolSeleccionado !== 4 && rolSeleccionado > 0);
	$('#grp_plan, #grp_suscripcion, #grp_fecha_suscripcion, #grp_modalidad').toggle(esProductor);
	$('#grp_max_fundos').toggle(esProductor && $('#es_multifundo').is(':checked'));
	$('label[for="password1"], #password1, label[for="password2"], #password2').toggle(requiereClave);

	// El permiso de acceso sin conexión es del productor (dueño del fundo):
	// a los demás roles no se les muestra ni se les habilita (los usuarios
	// de su fundo lo heredan).
	$('#grp_acceso_offline').toggle(esProductor);
	$('#acceso_offline').prop('disabled', !esProductor);

	if (!esProductor) {
		$('#es_multifundo').prop('checked', false);
		$('#max_fundos').val(1);
		$('#t_plan, #t_suscripcion, #fecha_activa_suscripcion').val('');
		$('#acceso_offline').prop('checked', false);
	}
	var resumen = '';
	if (esProductor) resumen = 'Productor: administra el plan, la vigencia y la modalidad de acceso a fundos.';
	else if (esEncargado) resumen = 'Encargado: su acceso queda asociado al fundo asignado por el productor.';
	$('#rol_resumen').text(resumen).toggleClass('visible', !!resumen);
}

$(document).on('input', '[data-auto-uppercase]', function () {
	var cursor = this.selectionStart;
	this.value = this.value.toLocaleUpperCase('es');
	if (cursor !== null && document.activeElement === this) {
		this.setSelectionRange(Math.min(cursor, this.value.length), Math.min(cursor, this.value.length));
	}
});

$('#grp_plan, #grp_suscripcion, #grp_fecha_suscripcion, #grp_modalidad, #grp_max_fundos, #grp_acceso_offline').hide();
$('label[for="t_suscripcion"]').hide();
$('#t_suscripcion').hide();
$('label[for="fecha_activa_suscripcion"]').hide();
$('#fecha_activa_suscripcion').hide();

$(document).ready(function () {

	$.post("../../controllers/musuarios_controller", {
		action: "search",
		record: record
	}).done(function (data) {

		var parsedJson = null;
		try {
			parsedJson = $.parseJSON(data);
		} catch (e) {
			parsedJson = null;
		}

		// Cuenta inexistente (enlace vencido o id inválido): se avisa y se
		// regresa al listado, en lugar de dejar el formulario en blanco.
		if (!parsedJson || !parsedJson.id) {
			var detalle = (parsedJson && parsedJson.error)
				? parsedJson.error
				: 'No se pudo cargar la información de la cuenta.';

			$('.message').html('<div class="alert alert-danger alert-dismissable">'
				+ '<h4><i class="icon fa fa-ban"></i> Alerta!</h4>' + detalle + '</div>');

			$.alert({
				title: 'No se pudo abrir la cuenta',
				content: detalle,
				confirmButton: 'Volver al listado',
				confirm: function () {
					window.location.href = '../usuarios/';
				}
			});
			return;
		}

		$("#id").val(parsedJson.id);
		$("#usuario").val(parsedJson.usuario);
		$("#nombre").val(parsedJson.nombre);
		$("#email").val(parsedJson.email);
		$("#telefono").val(parsedJson.telefono);
		$("#act_email").val(parsedJson.act_email);

		var st_mail = $("#act_email").val();
		var rol = parsedJson.rol;
		var tipo_user = parsedJson.tipo_user;
		var texto_tipo_user = parsedJson.tipo_user_nombre || (Number(tipo_user) === 0 ? 'Sin tipo asignado' : '');

		// Cuenta INACTIVA: se puede consultar y actualizar, pero no inicia
		// sesión. Para volver a habilitarla se usa "Reactivar" en el listado.
		if (parseInt(parsedJson.status, 10) === 0) {
			$('.message').html('<div class="alert alert-warning alert-dismissable">'
				+ '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
				+ '<h4><i class="icon fa fa-info"></i> Cuenta inactiva</h4>'
				+ 'Este usuario no puede iniciar sesión en el sistema. Aquí puede actualizar sus datos, '
				+ 'pero para volver a habilitarlo use <strong>Reactivar</strong> en el listado de usuarios.'
				+ '</div>');
		}

		fundo = parsedJson.fundo;
		plan = parsedJson.t_plan;

		if (parseInt(parsedJson.es_multifundo, 10) === 1) {
			$("#es_multifundo").prop('checked', true);
			$("#max_fundos").val(parsedJson.max_fundos || 1);
			$("#grp_max_fundos").show();
		} else {
			$("#es_multifundo").prop('checked', false);
			$("#max_fundos").val(1);
			$("#grp_max_fundos").hide();
		}

		// Permiso de uso del modo offline (del productor; nace apagado).
		// El valor puede llegar como 1/0 (lo normal) o como true/false,
		// según cómo lo devuelva el conector de la base de datos.
		var valorOffline = String(parsedJson.acceso_offline).toLowerCase().trim();
		$("#acceso_offline").prop('checked', !(valorOffline === '0' || valorOffline === 'f' || valorOffline === 'false' || valorOffline === ''));

		// El correo se bloquea cuando ya fue confirmado… salvo para el
		// SUPER USUARIO, que puede actualizar cualquier campo.
		if (st_mail != 0 && !esSuperUsuarioSesion) {
			$("#email").prop('disabled', true);
		}

		if (rol == 3) {
			$('#t_plan').hide();
			$('label[for="plan_reg"]').hide();
		}
		if (rol == 2) {
			$('label[for="t_suscripcion"]').show();
			$('#t_suscripcion').show();
			$('label[for="fecha_activa_suscripcion"]').show();
			$('#fecha_activa_suscripcion').show();
		}

		/* Carga en cadena: primero poblamos los select, luego seteamos el valor */
		// Solo el ENCARGADO tiene fundo y rol fijos (los asigna el productor).
		// Antes se bloqueaban para CUALQUIER cuenta con fundo, así que al
		// editar a un productor no se podían corregir ni el usuario, ni el
		// rol, ni el tipo. El SUPER USUARIO nunca se bloquea.
		if (Number(rol) === 3 && !esSuperUsuarioSesion) {

			document.getElementById('t_user').disabled = true;
			document.getElementById('rol').disabled = true;
			document.getElementById('usuario').disabled = true;

			$.post("../../controllers/musuarios_controller", {
				action: "get_roles_id",
				rol_id: rol
			}).done(function (data) {
				$("#rol").html(data);
				asegurarOpcion('#rol', rol, parsedJson.rol_nombre);
				aplicarCamposPorRol(rol);
			});

			$.post("../../controllers/musuarios_controller", {
				action: "get_t_user_id",
				t_user_id: tipo_user
			}).done(function (data) {
				$("#t_user").html(data);
				asegurarOpcion('#t_user', tipo_user, texto_tipo_user);
			});

		} else {

			$.post("../../controllers/musuarios_controller", {
				action: "get_t_user"
			}).done(function (data) {
				$("#t_user").html(data);
				asegurarOpcion('#t_user', tipo_user, texto_tipo_user);
			});

			$.post("../../controllers/musuarios_controller", {
				action: "get_roles"
			}).done(function (data) {
				$("#rol").html(data);
				asegurarOpcion('#rol', rol, parsedJson.rol_nombre);
				aplicarCamposPorRol(rol);
			});
		}

		$.post("../../controllers/musuarios_controller", {
			action: "get_suscripciones"
		}).done(function (data) {
			$("#t_suscripcion").html(data);
			if (parsedJson.t_suscripcion) {
				$("#t_suscripcion").val(String(parsedJson.t_suscripcion));
			}
		});

		$.post("../../controllers/mplanes_controller", {
			action: "get_t_plan"
		}).done(function (data) {
			$("#t_plan").html(data);
			if (parsedJson.t_plan) {
				$("#t_plan").val(String(parsedJson.t_plan));
			/* Recalcular el cupo con el plan ya seleccionado. */
			actualizarCupoFundos();
			}
		});

		if (parsedJson.fecha_activa_suscripcion) {
			$("#fecha_activa_suscripcion").val(parsedJson.fecha_activa_suscripcion);
		}

		if (parsedJson.imagen && parsedJson.imagen !== '' && parsedJson.imagen !== null) {
			$('.imgusr').attr('src', '../../dist/img/fotos_usuarios/' + parsedJson.imagen);
		} else {
			$('.imgusr').attr('src', '../../dist/img/fotos_usuarios/no_user.png');
		}

	}, "json");

});

$("#t_user").change(function () {
	$('label[for="fundo"]').hide();
	$('#fundo').hide();

	var t_user = $("#t_user").val();

	$.post("../../controllers/musuarios_controller", {
		action: "get_roles_tipo",
		t_user: t_user
	}).done(function (data) {
		$("#rol").html(data);
		aplicarCamposPorRol('');
	});
});

$("#rol").change(function () {

	var rol = $("#rol").val();

	if (rol == 2) {
		$('#t_plan').show();
		$('label[for="plan_reg"]').show();
		$('label[for="t_suscripcion"]').show();
		$('#t_suscripcion').show();
		$('label[for="fecha_activa_suscripcion"]').show();
		$('#fecha_activa_suscripcion').show();

		$.post("../../controllers/mplanes_controller", {
			action: "get_t_plan"
		}).done(function (data) {
			$("#t_plan").html(data);
			if (plan != '') {
				$("#t_plan").val(plan);
			}
		});

	} else {
		$('#t_plan').hide();
		$('label[for="plan_reg"]').hide();
		$('label[for="t_suscripcion"]').hide();
		$('#t_suscripcion').hide();
		$('label[for="fecha_activa_suscripcion"]').hide();
		$('#fecha_activa_suscripcion').hide();
	}
	aplicarCamposPorRol(rol);
});

$("#es_multifundo").on('change', function () {
	if ($(this).is(':checked') && parseInt($('#rol').val(), 10) === 2) {
		$('#grp_max_fundos').slideDown(150);
		actualizarCupoFundos();
	} else {
		$(this).prop('checked', false);
		$('#grp_max_fundos').slideUp(150);
		$('#max_fundos').val(1);
	}
});

// El plan DIAMANTE es el único que activa el acceso sin conexión: al
// elegirlo se marca solo y al cambiarlo por otro plan se desmarca (el
// administrador puede volver a marcarlo a mano).
$('#t_plan').on('change', function () {
	if (parseInt($('#rol').val(), 10) === 2) {
		var texto = ($('#t_plan option:selected').text() || '').toUpperCase();
		$('#acceso_offline').prop('checked', texto.indexOf('DIAMANTE') > -1);
		actualizarCupoFundos();
	}
});

$("#save").click(function () {
	var formData = new FormData();
	formData.append('file', $('input[type=file]')[0].files[0]);
	formData.append('action', $("#action").val());
	formData.append('id', $("#id").val());
	formData.append('nombre', $("#nombre").val());
	formData.append('usuario', $("#usuario").val());
	formData.append('email', $("#email").val());
	formData.append('telefono', $("#telefono").val());
	formData.append('rol', $("#rol").val());
	// El tipo de usuario también viaja: antes no se enviaba y el cambio se
	// perdía al guardar.
	formData.append('t_user', $("#t_user").val());
	formData.append('t_plan', $("#t_plan").val());
	formData.append('t_suscripcion', $("#t_suscripcion").val());
	formData.append('fecha_activa_suscripcion', $("#fecha_activa_suscripcion").val());
	formData.append('password1', $("#password1").val());
	formData.append('password2', $("#password2").val());
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
		success: function (data) {
			$(".message").html(data);

			// La respuesta puede venir sin el campo tocon (avisos de error):
			// se lee con guarda para no romper la pantalla.
			var campo = document.getElementById("tocon");
			var apg = campo ? campo.value : '0';

			if (apg == 1) {
				setTimeout(function () {
					$(location).attr('href', '../usuarios');
				}, 3000);
			}
		}
	});
});

$("#exit").click(function () {
	$(location).attr('href', '../usuarios/');
});

$("#InputFile").change(function () {
	if (validarImagen()) {
		readURL(this);
	}
});

$('#password1').keyup(function () {
	var pswd = $(this).val();

	$.post("../../controllers/validar_contrasena", {
		action: "validar",
		clave: pswd
	}).done(function (data) {
		$("#indicaciones").html(data);
	});

}).focus(function () {
	$('#pswd_info').show();
}).blur(function () {
	$('#pswd_info').hide();
});

$('#password2').keyup(function () {
	var pw3 = $(this).val();
	var pswd = $("#password2").val();

	if (pswd != "") {
		if (pswd === pw3) {
			$('#compare').removeClass('invalid').addClass('valid');
		} else {
			$('#compare').removeClass('valid').addClass('invalid');
		}
	}
}).focus(function () {
	$('#pswd_confirm_info').show();
}).blur(function () {
	$('#pswd_confirm_info').hide();
});

function alsalir(e) {
	var caso1 = document.getElementById(e).value;
	if (caso1.length > 0 && caso1.length < 5) {
		document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 5 caractéres';
	} else {
		document.getElementById('ms_' + e).innerHTML = '';
	}
}

function esnombre2(v) {
	var caso2 = document.getElementById('nombre').value;
	if (caso2.length < 5) {
		document.getElementById('ms_nombre').innerHTML = 'Debe escribir al menos 5 caractéres';
	} else {
		var texto2 = v.split(" ");
		var n_palabras = texto2.length;

		if (n_palabras >= 2 && n_palabras <= 4) {
			document.getElementById("ms_nombre").innerHTML = '';
		} else {
			document.getElementById("ms_nombre").innerHTML = 'Ingrese nombre(s) y apellido(s)';
		}
	}
}

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

function esemail2(v) {
	patron = /^([a-z0-9_\.\-].{5,})+\@(([lacteoslosandes])+\.)+(([gob])+\.)+([ve])+$/;

	if (patron.test(v)) {
		document.getElementById("ms_email").innerHTML = '';
	} else {
		document.getElementById("ms_email").innerHTML = 'Ingrese un correo electrónico valido!';
	}
}

function readURL(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();
		reader.onload = function (e) {
			$('#file_url').attr('src', e.target.result);
		};
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
			cancel: function () {}
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
	}
});
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function () { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>