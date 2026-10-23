<?php

include("../../lib/validar_session.php");

ValidaSession("../login");

//VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>
<?php
// El SUPER USUARIO puede actualizar cualquier dato de su propio perfil.
// El resto de los perfiles sigue viendo la pantalla de consulta (solo
// cambia la contraseña y la imagen).
$esSuperUsuario = (isset($_SESSION['rolx']) && (int) $_SESSION['rolx'] === 4);
?>

<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<style>
	/* Ajuste puntual: botón "Actualizar" de imagen dentro del field */
	.crud-form .btn-img-update {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-weight: 600;
		margin-top: 8px;
	}
</style>

<div class="crud-form">

	<div class="message"></div>

	<header class="crud-form__header">
		<span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>

		<div>
			<h3 class="crud-form__title">Mi perfil</h3>
			<p class="crud-form__subtitle">Consulta tus datos y actualiza tu contraseña e imagen cuando lo necesites.
			</p>
		</div>
		<div class="crud-form__header-actions">
			<button id="exit_top" type="button" class="btn btn-warning">
				<i class="fa fa-reply"></i> Regresar
			</button>
		</div>
	</header>

	<form id="form" role="form" enctype="multipart/form-data">

		<input type="hidden" id="action" value="changepassword">

		<div class="crud-form__grid">

			<!-- ========== CARD 1: Datos del usuario ========== -->
			<div class="crud-form__card">
				<div class="crud-form__card-head">
					<i class="fa fa-user"></i>
					<h3>Datos del usuario</h3>
				</div>
				<div class="crud-form__card-body">

					<div class="crud-form__row">
						<div class="crud-form__field">
							<label for="usuario">Usuario</label>
							<input type="text" class="form-control" id="usuario"
								<?php echo $esSuperUsuario ? 'data-auto-uppercase="true"' : 'readonly="readonly"'; ?>>
						</div>

						<div class="crud-form__field">
							<label>Rol</label>
							<input type="text" class="form-control" id="nombrerol" readonly="readonly">
						</div>
					</div>

					<div class="crud-form__row">
						<div class="crud-form__field">
							<label for="password1">Contraseña</label>
							<input type="password" class="form-control login-input" id="password1"
								placeholder="Password" autocomplete="new-password">
						</div>

						<div class="crud-form__field">
							<label for="password2">Confirmar contraseña</label>
							<input type="password" class="form-control login-input" id="password2"
								placeholder="Password" autocomplete="new-password">
						</div>
					</div>

					<div id="pswd_info">
						<h4>La contraseña debe cumplir los siguientes requisitos:</h4>
						<ul>
							<li id="letter" class="invalid">Al menos <strong>una letra</strong></li>
							<li id="capital" class="invalid">Al menos <strong>una letra mayúscula</strong></li>
							<li id="number" class="invalid">Al menos <strong>un número</strong></li>
							<li id="length" class="invalid">Longitud de almenos <strong>8 caracteres</strong></li>
							<li id="especial" class="invalid">Uso de caracteres especiales<strong> @ # $ %</strong></li>
						</ul>
					</div>
					<div id="pswd_confirm_info">
						<ul>
							<li id="compare" class="invalid">La nueva clave y la clave de confirmación deben ser iguales
							</li>
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
						<input type="text" class="form-control" id="nombre"
							<?php echo $esSuperUsuario ? 'data-auto-uppercase="true"' : 'readonly="readonly"'; ?>>
					</div>

					<div class="crud-form__field">
						<label for="email">Email</label>
						<input type="email" class="form-control" id="email"
							<?php echo $esSuperUsuario ? '' : 'readonly="readonly"'; ?>>
					</div>

					<div class="crud-form__field">
						<label for="telefono">Teléfono de contacto</label>
						<input type="text" class="form-control" id="telefono"
							placeholder="+58-000-0000000" autocomplete="off"
							<?php echo $esSuperUsuario ? '' : 'readonly="readonly"'; ?>>
					</div>

					<div class="crud-form__field" style="text-align:center;">
						<img class="imgusr" alt="" src="#" id="file_url" width="120" height="150">
						<label for="InputFile" style="text-align:left; margin-top:8px;">Adjuntar nueva imagen no mayor a
							1 MB.</label>
						<input type="file" id="InputFile" tabindex="8">
						<div>
							<button id="guardar_img" type="button" class="btn btn-success btn-xs btn-img-update"
								tabindex="50">
								<i class="fa fa-fw fa-save"></i> Actualizar
							</button>
						</div>
					</div>

				</div>
			</div>

		</div><!-- /.crud-form__grid -->

		<div class="crud-form__actions">
			<button id="exit" type="button" class="btn btn-warning" style="display:none;">
				<i class="fa fa-fw fa-reply"></i> Regresar
			</button>
			<?php if ($esSuperUsuario): ?>
				<button id="guardar_datos" type="button" class="btn btn-success" tabindex="9">
					<i class="fa fa-fw fa-save"></i> Guardar datos
				</button>
			<?php endif; ?>
			<button id="save" type="button" class="btn btn-primary" tabindex="10">
				<i class="fa fa-fw fa-save"></i> Actualizar contraseña
			</button>
		</div>

	</form>

</div><!-- /.crud-form -->

<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<script type="text/javascript">
	$(document).ready(function () {

		//cargar datos del formulario ********************************************************************
		$.post("../../controllers/musuarios_controller", {
			action: "find_perfil"
		}).done(function (data) {

			var parsedJson = $.parseJSON(data);

			$("#usuario").val(parsedJson.usuario);
			$("#nombrerol").val(parsedJson.nombrerol);
			$("#nombre").val(parsedJson.nombre);
			$("#email").val(parsedJson.email);
			$("#telefono").val(parsedJson.telefono);

			if (parsedJson.imagen != null) {
				$('.imgusr').attr('src', '../../dist/img/fotos_usuarios/' + parsedJson.imagen);
			} else {
				$('.imgusr').attr('src', '../../dist/img/fotos_usuarios/no_user.png');
			}
		}, "json");

		//**********************************************************************************************

		//** enviar los datos al controlador ***********************************************************

		// Guardar los datos de contacto (solo lo ve el SUPER USUARIO).
		$("#guardar_datos").click(function () {
			var formData = new FormData();
			formData.append('action', 'act_perfil');
			formData.append('usuario', $("#usuario").val());
			formData.append('nombre', $("#nombre").val());
			formData.append('email', $("#email").val());
			formData.append('telefono', $("#telefono").val());

			$.ajax({
				url: "../../controllers/musuarios_controller",
				type: "POST",
				data: formData,
				contentType: false,
				cache: false,
				processData: false,
				success: function (data) {
					$(".message").html(data);
				}
			});
		});

		// Igual que en el formulario de edición: el usuario y el nombre se
		// guardan en mayúsculas.
		$(document).on('input', '[data-auto-uppercase]', function () {
			var cursor = this.selectionStart;
			this.value = this.value.toLocaleUpperCase('es');
			if (cursor !== null && document.activeElement === this) {
				this.setSelectionRange(Math.min(cursor, this.value.length), Math.min(cursor, this.value.length));
			}
		});

		$("#save").click(function () {

			var formData = new FormData();
			formData.append('action', $("#action").val());
			formData.append('password1', $("#password1").val());
			formData.append('password2', $("#password2").val());

			$.ajax({
				url: "../../controllers/musuarios_controller",
				type: "POST",
				data: formData,
				contentType: false,
				cache: false,
				processData: false,
				success: function (data) {
					$(".message").html(data);
				}
			});

		});
		// ********************************************************************************************

		$("#guardar_img").click(function () {
			var accion = "add_img";
			var formData = new FormData();
			formData.append('file', $('input[type=file]')[0].files[0]);
			formData.append('action', accion);
			formData.append('id', $("#id").val());

			$.ajax({
				url: "../../controllers/musuarios_controller",
				type: "POST",
				data: formData,
				contentType: false,
				cache: false,
				processData: false,
				success: function (data) {
					$(".message").html(data);
					setTimeout(function () {
						$(".alert").alert('close');
						$(location).attr('href', "../inicio/");


					}, 1500);

				}
			});

		});




		//salir del formulario *****************************************
		$("#exit").click(function () {
			$(location).attr('href', '../inicio/');
		});
		//**************************************************************


		/******************************************************************************/
		$('#password1').keyup(function () {
			// set password variable
			var pswd = $(this).val();
			//validate the length
			if (pswd.length < 8) {
				$('#length').removeClass('valid').addClass('invalid');
			} else {
				$('#length').removeClass('invalid').addClass('valid');
			}
			//validate letter
			if (pswd.match(/[A-z]/)) {
				$('#letter').removeClass('invalid').addClass('valid');
			} else {
				$('#letter').removeClass('valid').addClass('invalid');
			}

			//validate capital letter
			if (pswd.match(/[A-Z]/)) {
				$('#capital').removeClass('invalid').addClass('valid');
			} else {
				$('#capital').removeClass('valid').addClass('invalid');
			}

			//validate number
			if (pswd.match(/\d/)) {
				$('#number').removeClass('invalid').addClass('valid');
			} else {
				$('#number').removeClass('valid').addClass('invalid');
			}

			//validate caracteres especiales
			if (pswd.match(/[@#$%]/)) {
				$('#especial').removeClass('invalid').addClass('valid');
			} else {
				$('#especial').removeClass('valid').addClass('invalid');
			}


		}).focus(function () {
			$('#pswd_info').show();
		}).blur(function () {
			$('#pswd_info').hide();
		});

		$('#password2').keyup(function () {
			var pw3 = $(this).val();
			var pswd = $("#password2").val();

			if (pswd === pw3) {

				$('#compare').removeClass('invalid').addClass('valid');
			} else {
				$('#compare').removeClass('valid').addClass('invalid');
			}
		}).focus(function () {
			$('#pswd_confirm_info').show();
		}).blur(function () {
			$('#pswd_confirm_info').hide();
		});

		/********************************************************************/

	});

	//############################################################################

	function readURL(input) {

		if (input.files && input.files[0]) {
			var reader = new FileReader();

			reader.onload = function (e) {
				$('#file_url').attr('src', e.target.result);
			}

			reader.readAsDataURL(input.files[0]);
		}
	}

	$("#InputFile").change(function () {

		if (validarImagen()) {
			readURL(this);

		}


	});

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
				cancel: function () {

				}
			});
			return false;
		} else {
			return true;

		}

	}
</script>

<!-- Script auxiliar: header action dispara el exit oculto -->
<script type="text/javascript">
	$(document).on('click', '#exit_top', function () { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>