<?php

include("../../lib/validar_session.php");

ValidaSession("../login");

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<?php
/* Datos de presentación de la tarjeta (el correo real lo trae el AJAX) */
$perfilNombre = isset($_SESSION['nombresx']) ? trim((string)$_SESSION['nombresx']) : '';
$perfilUsuario = isset($_SESSION['usuariox']) ? trim((string)$_SESSION['usuariox']) : '';
$perfilActEmail = isset($_SESSION['act_email']) ? (int)$_SESSION['act_email'] : 1;

if ($perfilNombre === '') {
	$perfilNombre = $perfilUsuario;
}

/* Iniciales para el avatar: FERNANDO PÉREZ → FP */
$perfilIniciales = '';
$perfilPartes = preg_split('/\s+/u', $perfilNombre);
if (is_array($perfilPartes)) {
	foreach ($perfilPartes as $perfilParte) {
		if ($perfilParte === '') continue;
		$perfilIniciales .= mb_strtoupper(mb_substr($perfilParte, 0, 1, 'UTF-8'), 'UTF-8');
		if (mb_strlen($perfilIniciales, 'UTF-8') >= 2) break;
	}
}
if ($perfilIniciales === '') {
	$perfilIniciales = mb_strtoupper(mb_substr($perfilUsuario, 0, 2, 'UTF-8'), 'UTF-8');
}
?>

<div class="bovs-perfil">
	<form id="form" role="form" enctype="multipart/form-data">
		<input type="hidden" id="action" value="changepassword">

		<div class="bovs-perfil__card">

			<header class="bovs-perfil__head">
				<div class="bovs-perfil__avatar"><?php echo htmlspecialchars($perfilIniciales, ENT_QUOTES, 'UTF-8'); ?></div>

				<div class="bovs-perfil__id">
					<h3>Mi perfil</h3>
					<p>Datos de tu cuenta y validación del correo electrónico</p>
				</div>

				<span class="bovs-perfil__chip <?php echo $perfilActEmail === 1 ? 'is-ok' : 'is-pend'; ?>"
				      id="bovsPerfilChip">
					<i class="fa <?php echo $perfilActEmail === 1 ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"
					   aria-hidden="true"></i>
					<?php echo $perfilActEmail === 1 ? 'Correo verificado' : 'Correo pendiente'; ?>
				</span>
			</header>

			<div class="bovs-perfil__cuerpo">

				<div class="bovs-campo">
					<label for="usuario">Usuario</label>
					<input type="text" id="usuario" readonly="readonly" autocomplete="off">
				</div>

				<div class="bovs-campo">
					<label for="nombre">Nombre y apellido</label>
					<input type="text" id="nombre" readonly="readonly" autocomplete="off">
				</div>

				<div class="bovs-campo bovs-campo--ancho">
					<label for="email">Correo electrónico</label>
					<input type="text" id="email" readonly="readonly" autocomplete="off">
					<p class="bovs-campo__ayuda">
						El correo se usa para alertas del sistema y para recuperar tu contraseña.
					</p>
				</div>

			</div>

			<footer class="bovs-perfil__pie">
				<button id="exit" type="button" class="bovs-btn bovs-btn--suave">
					<i class="fa fa-reply" aria-hidden="true"></i> Salir
				</button>
				<button id="verificar" type="button" class="bovs-btn bovs-btn--primario">
					<i class="fa fa-envelope-open-o" aria-hidden="true"></i> Verificar correo
				</button>
			</footer>

		</div>
	</form>
</div>

<style>
/* ------------------------- Tarjeta "Mi perfil" ------------------------- */
.bovs-perfil {
	padding: 4px 4px 26px;
}

.bovs-perfil__card {
	max-width: 800px;
	margin: 0 auto;
	background: #ffffff;
	border: 1px solid #e6ecf3;
	border-radius: 18px;
	overflow: hidden;
	box-shadow: 0 22px 46px -36px rgba(15, 23, 42, .65);
}

.bovs-perfil__head {
	display: flex;
	align-items: center;
	gap: 14px;
	flex-wrap: wrap;
	padding: 20px 22px;
	background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 72%);
	border-bottom: 1px solid #eef2f7;
}

.bovs-perfil__avatar {
	flex: 0 0 auto;
	width: 54px;
	height: 54px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 16px;
	background: linear-gradient(160deg, #16a34a 0%, #0f766e 100%);
	color: #ffffff;
	font-size: 19px;
	font-weight: 800;
	letter-spacing: .02em;
	box-shadow: 0 14px 26px -16px rgba(21, 128, 61, .95);
}

.bovs-perfil__id {
	flex: 1 1 200px;
	min-width: 0;
}
.bovs-perfil__id h3 {
	margin: 0 0 3px;
	font-size: 18px;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.2;
}
.bovs-perfil__id p {
	margin: 0;
	font-size: 12.5px;
	line-height: 1.4;
	color: #7c8899;
}

.bovs-perfil__chip {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 5px 12px;
	border-radius: 999px;
	font-size: 11.5px;
	font-weight: 800;
	letter-spacing: .02em;
	white-space: nowrap;
}
.bovs-perfil__chip.is-ok {
	background: #dcfce7;
	border: 1px solid #bbf7d0;
	color: #15803d;
}
.bovs-perfil__chip.is-pend {
	background: #fff7ed;
	border: 1px solid #fed7aa;
	color: #b45309;
}

.bovs-perfil__cuerpo {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px 22px;
	padding: 22px;
}

.bovs-campo--ancho { grid-column: 1 / -1; }

.bovs-campo label {
	display: block;
	margin-bottom: 6px;
	font-size: 10.5px;
	font-weight: 800;
	letter-spacing: .08em;
	text-transform: uppercase;
	color: #7c8899;
}

.bovs-campo input {
	width: 100%;
	height: 44px;
	padding: 8px 12px;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	background: #f8fafc;
	color: #1f2937;
	font-size: 14px;
	font-weight: 600;
	box-sizing: border-box;
	outline: none;
}
.bovs-campo input[readonly] { cursor: default; }
.bovs-campo input:focus {
	border-color: #86efac;
	box-shadow: 0 0 0 3px rgba(22, 163, 74, .12);
}

.bovs-campo__ayuda {
	margin: 7px 0 0;
	font-size: 11.5px;
	line-height: 1.45;
	color: #94a3b8;
}

.bovs-perfil__pie {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 10px;
	flex-wrap: wrap;
	padding: 14px 22px 18px;
	border-top: 1px solid #eef2f7;
	background: #fbfdff;
}

@media (max-width: 640px) {
	.bovs-perfil__cuerpo { grid-template-columns: 1fr; padding: 18px; }
	.bovs-perfil__head { padding: 16px 18px; }
	.bovs-perfil__chip { order: 3; }
	.bovs-perfil__pie { padding: 14px 18px 16px; }
	.bovs-perfil__pie .bovs-btn { flex: 1 1 100%; }
}
</style>


<div class="modal" id="modal" tabindex="-1" role="dialog" aria-labelledby="modalValidarTitulo">
	<div class="modal-dialog" role="document">

		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="modalValidarTitulo">
					<i class="fa fa-envelope-open-o" aria-hidden="true"></i>
					Validación de correo electrónico
				</h4>
				<span id="reloj"></span>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div id="mensaje"></div>

			<div class="modal-body">
				<form id="formActivacion">

					<!-- Paso 1: correo a validar -->
					<div class="bovs-paso">
						<span class="bovs-paso__num">1</span>
						<div class="bovs-paso__cuerpo">
							<label class="bovs-lbl" for="valida_email_usu">Correo a validar</label>
							<div class="bovs-fila">
								<input type="email" id="valida_email_usu" name="valida_email_usu"
								       class="bovs-inp" maxlength="100" placeholder="tu-correo@ejemplo.com"
								       required onkeyup="validarEmail(this)">
								<button id="valida_correo" type="button" class="bovs-btn bovs-btn--suave"
								        title="Usar este correo">
									<i class="fa fa-check" aria-hidden="true"></i>
								</button>
							</div>
							<span id="mensaje-error" class="bovs-error"></span>
						</div>
					</div>

					<!-- Paso 2: código recibido -->
					<div class="bovs-paso">
						<span class="bovs-paso__num">2</span>
						<div class="bovs-paso__cuerpo">
							<label class="bovs-lbl">Código de verificación</label>

							<div class="bovs-fila bovs-fila--envio">
								<button type="button" class="bovs-btn bovs-btn--primario" id="enviar">
									<i class="fa fa-paper-plane" aria-hidden="true"></i> Enviar código
								</button>
								<button type="button" class="bovs-btn bovs-btn--suave" id="reenviar">Reenviar</button>
							</div>

							<p class="bovs-hint">
								El código llega al correo indicado y caduca en 5 minutos.
							</p>

							<div class="bovs-fila">
								<input autocomplete="off" id="codigo" name="codigo" type="text"
								       class="bovs-inp bovs-inp--codigo" placeholder="000000" maxlength="6"
								       inputmode="numeric">
								<button type="button" class="bovs-btn bovs-btn--primario" id="validar">Validar</button>
							</div>

							<span id="mensaje-error-codigo" class="bovs-error"></span>
						</div>
					</div>

				</form>
			</div>

			<div class="modal-footer">
				<button type="button" class="bovs-btn bovs-btn--suave" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<style>
/* ---------------- Validación de correo (modal del perfil) ---------------- */
#modal .modal-dialog {
	width: auto;
	max-width: 470px;
	margin: 40px auto;
}
#modal .modal-content {
	border: 0;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 26px 60px -28px rgba(15, 23, 42, .65);
}
#modal .modal-header {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 15px 18px;
	background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
	border-bottom: 1px solid #e2e8f0;
}
#modal .modal-header {
	position: sticky;
	top: 0;
	z-index: 3;
}
#modal .modal-title {
	flex: 1 1 auto;
	min-width: 0;
	margin: 0;
	font-size: 15px;
	font-weight: 800;
	letter-spacing: .01em;
	color: #0f172a;
}
#modal .modal-title i {
	color: #16a34a;
	margin-right: 6px;
}
#modal .modal-header .close {
	order: 3;
	margin: 0;
	font-size: 22px;
	line-height: 1;
	color: #94a3b8;
	opacity: .9;
}
#modal .modal-header .close:hover { color: #475569; }

/* Cuenta atrás del código */
#modal #reloj {
	display: none;
	order: 2;
	padding: 3px 10px;
	border-radius: 999px;
	background: #fff7ed;
	border: 1px solid #fed7aa;
	color: #b45309;
	font-size: 12.5px;
	font-weight: 800;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}
#modal #mensaje {
	display: none;
	padding: 9px 18px;
	background: #fef2f2;
	border-bottom: 1px solid #fecaca;
	color: #b91c1c;
	font-size: 12.5px;
	font-weight: 700;
	text-align: center;
}

#modal .modal-body {
	padding: 18px;
}

/* Pasos numerados */
.bovs-paso {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 0 0 16px;
	margin-bottom: 16px;
	border-bottom: 1px dashed #e2e8f0;
}
.bovs-paso:last-of-type {
	padding-bottom: 0;
	margin-bottom: 0;
	border-bottom: 0;
}
.bovs-paso__num {
	flex: 0 0 auto;
	width: 26px;
	height: 26px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: #dcfce7;
	border: 1px solid #bbf7d0;
	color: #15803d;
	font-size: 12px;
	font-weight: 800;
}
.bovs-paso__cuerpo { flex: 1 1 auto; min-width: 0; }

.bovs-lbl {
	display: block;
	margin: 0 0 6px;
	font-size: 11px;
	font-weight: 800;
	letter-spacing: .07em;
	text-transform: uppercase;
	color: #7c8899;
}

.bovs-fila {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
	flex-wrap: wrap;
}
.bovs-fila--envio { margin-bottom: 10px; }

.bovs-inp {
	flex: 1 1 180px;
	min-width: 0;
	height: 42px;
	padding: 8px 12px;
	border: 1px solid #d5dde7;
	border-radius: 10px;
	font-size: 14px;
	color: #1f2937;
	background: #fff;
	outline: none;
	box-sizing: border-box;
	transition: border-color .15s ease, box-shadow .15s ease;
}
.bovs-inp:focus {
	border-color: #16a34a;
	box-shadow: 0 0 0 3px rgba(22, 163, 74, .16);
}
.bovs-inp:disabled { background: #f8fafc; color: #64748b; }
.bovs-inp--codigo {
	flex: 0 1 160px;
	text-align: center;
	font-size: 18px;
	font-weight: 800;
	letter-spacing: .35em;
}

.bovs-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 7px;
	height: 42px;
	padding: 0 16px;
	border: 1px solid transparent;
	border-radius: 10px;
	font-size: 13.5px;
	font-weight: 700;
	cursor: pointer;
	transition: filter .15s ease, background .15s ease;
}
.bovs-btn--primario {
	background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
	color: #fff;
	box-shadow: 0 8px 18px -12px rgba(21, 128, 61, .95);
}
.bovs-btn--primario:hover { filter: brightness(1.06); }
.bovs-btn--suave {
	background: #f8fafc;
	border-color: #e2e8f0;
	color: #475569;
}
.bovs-btn--suave:hover { background: #f1f5f9; }
.bovs-btn:disabled,
.bovs-btn.disabled { opacity: .5; cursor: not-allowed; }

.bovs-error {
	display: block;
	margin-top: 2px;
	font-size: 12px;
	font-weight: 600;
	color: #dc2626;
}
.bovs-error:empty { display: none; }

.bovs-hint {
	margin: 0 0 10px;
	font-size: 12px;
	line-height: 1.45;
	color: #7c8899;
}

#modal .modal-footer {
	padding: 12px 18px;
	border-top: 1px solid #eef2f7;
	background: #fbfdff;
	text-align: right;
}

@media (max-width: 560px) {
	#modal .modal-dialog { margin: 12px; }
	.bovs-fila--envio .bovs-btn { flex: 1 1 auto; }
	.bovs-inp--codigo { flex: 1 1 auto; }
}
</style>






<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />





<script type="text/javascript">
	var rol = <?php echo $_SESSION['rolx'];  ?>; //verifica el rol del usuario
	var act_email = <?php echo $_SESSION['act_email'];  ?>; //verifica el rol del usuario
	var reenviar = 0;


	$(document).ready(function() {

		/* El modal nace dentro de #content-wrapper (z-index 800): se mueve al
		   body para que la barra superior y el backdrop no lo tapen. */
		$('#modal').appendTo('body');

		$('#reloj').hide();
		$('#mensaje').hide();
		$('#validar').hide();
		$('#codigo').hide();
		$('#reenviar').hide();



		document.getElementById('enviar').disabled = true;
		document.getElementById('valida_email_usu').disabled = true;
		document.getElementById('valida_email_usu').disabled = true;
		document.getElementById('valida_correo').disabled = true;
		$('#validar').addClass('disabled').prop('disabled', true);


		//cargar datos del formulario ********************************************************************
		$.post("../../controllers/musuarios_controller", {
			action: "find_perfil"
		}).done(function(data) {

			var parsedJson = $.parseJSON(data);

			$("#usuario").val(parsedJson.usuario);
			$("#nombre").val(parsedJson.nombre);
			$("#email").val(parsedJson.email);

			$("#valida_email_usu").val(parsedJson.email);


		}, "json");

		//**********************************************************************************************

		setTimeout(function() {

			document.getElementById('valida_correo').disabled = false;
			document.getElementById('valida_email_usu').disabled = false;


		}, 3000);

		//**********************************************************************************************

		$("#verificar").click(function() {
			$('#reloj').hide();
			$('#mensaje').hide();
			$('#validar').hide();
			$('#codigo').hide();

			document.getElementById('enviar').disabled = true;
			document.getElementById('valida_email_usu').disabled = true;
			document.getElementById('valida_correo').disabled = true;
			$('#validar').addClass('disabled').prop('disabled', true);
			$('#modal').modal({
				backdrop: 'static',
				keyboard: false
			});


		});

		$("#valida_correo").click(function() {
			validar_correo_usuarios();


		});

		$("#validar").click(function() {
			validar_codigo();


		});
		$("#exit").click(function() {


			$(location).attr('href', '../inicio/');
			


		});

		function inactivar_codigo() {

			const mensajeError = document.getElementById('mensaje-error');

			$.post("../../controllers/mavalidacorreo_controller", {

				action: "inactivar_codigo"


			}).done(function(data) {

				var parsedJson = $.parseJSON(data);
				var anulado = parsedJson.anulado;
				if (anulado == true) {

					mensajeError.textContent = 'El Código ha caducado';
					mensajeError.style.color = "orange";

					setTimeout(function() {
						mensajeError.textContent = "";
						$('#enviar').hide();
						$('#validar').hide();
						$('#codigo').hide();
						$('#reenviar').show();
						document.getElementById('validar').disabled = true;
						document.getElementById('valida_correo').disabled = true;


					}, 2500);



				} else {

					mensajeError.textContent = 'Error al Validar el Codigo';
					mensajeError.style.color = "red";

					setTimeout(function() {
						mensajeError.textContent = "";

					}, 2500);

				}

			}, "json");



		}

		function validar_codigo() {

			const mensajeError = document.getElementById('mensaje-error');

			var codigo = $("#codigo").val();
			var valor_correo = $("#valida_email_usu").val();
	

			if (codigo != '') {

				$.post("../../controllers/mavalidacorreo_controller", {

					action: "validar_codigo",
					codigo: codigo,
					correo: valor_correo


				}).done(function(data) {

					var parsedJson = $.parseJSON(data);
					var valido = parsedJson.validado;
					if (valido == true) {

						mensajeError.textContent = 'Su correo ha sido verificado de forma exitosa';
						mensajeError.style.color = "green";

						setTimeout(function() {
							mensajeError.textContent = "";
							var email_new = $("#valida_email_usu").val();
							$("#email").val(email_new);

							/* La tarjeta deja de avisar que el correo está pendiente */
							var chipPerfil = document.getElementById('bovsPerfilChip');
							if (chipPerfil) {
								chipPerfil.className = 'bovs-perfil__chip is-ok';
								chipPerfil.innerHTML = '<i class="fa fa-check-circle" aria-hidden="true"></i> Correo verificado';
							}

							$('#modal').modal('toggle');
							$(location).attr('href', '../inicio/');

						}, 2500);



					} else {
						document.getElementById('validar').disabled = true;
						document.getElementById('codigo').disabled = true;

						mensajeError.textContent = 'El Código ingresado es incorrecto';
						mensajeError.style.color = "red";

						setTimeout(function() {
							mensajeError.textContent = "";
							document.getElementById('codigo').disabled = false;


						}, 2500);

					}

				}, "json");

			} else {

				mensajeError.textContent = 'Campo Vacio';
				mensajeError.style.color = "red";

				setTimeout(function() {
					mensajeError.textContent = "";
				}, 2500);

			}


		}

		
		$("#enviar").click(function() {
			reenviar = 0;
			$('#reenviar').hide();
			$('#valida_correo').hide();
			$('#codigo').val('');


			enviar_codigo(reenviar);

		});

		$("#reenviar").click(function() {

			document.getElementById('valida_correo').disabled = false;
			reenviar = 1;
			enviar_codigo(reenviar);

		});

		function enviar_codigo(reenviar) {

			const mensajeError = document.getElementById('mensaje-error');
			var valor_correo = $("#valida_email_usu").val();

			if (valor_correo != '') {

				$.post("../../controllers/mavalidacorreo_controller", {

					action: "genera_codigo",
					correo: valor_correo,
					cod_valida: reenviar


				}).done(function(data) {

					var parsedJson = $.parseJSON(data);
					var enviado = parsedJson.enviado;
					if (enviado == true) {

						mensajeError.textContent = 'Codigo Enviado de forma exitosa';
						mensajeError.style.color = "green";

						setTimeout(function() {
							mensajeError.textContent = "";
							document.getElementById('enviar').disabled = true;
							document.getElementById('reenviar').disabled = true;

							activar_cronometro();


						}, 1500);



					} else {

						mensajeError.textContent = 'Error al Generar Codigo';
						mensajeError.style.color = "red";

						setTimeout(function() {
							mensajeError.textContent = "";

						}, 2500);

					}

				}, "json");

			} else {

				mensajeError.textContent = 'Campo Vacio';
				mensajeError.style.color = "red";

				setTimeout(function() {
					mensajeError.textContent = "";

				}, 2500);

			}


		}


		function validar_correo_usuarios() {
			const mensajeError = document.getElementById('mensaje-error');

			var valor_correo = $("#valida_email_usu").val();

			if (valor_correo != '') {

				$.post("../../controllers/mavalidacorreo_controller", {

					action: "search_correo",
					correo: valor_correo


				}).done(function(data) {

					var parsedJson = $.parseJSON(data);
					var correo_usu = parsedJson.correo_usu;
					if (correo_usu == 0) {

						document.getElementById('valida_email_usu').disabled = true;
						document.getElementById('valida_correo').disabled = true;

						mensajeError.textContent = "¡Correo Disponible!";
						mensajeError.style.color = "green";
						setTimeout(function() {
							mensajeError.textContent = "";
							document.getElementById('enviar').disabled = false;


						}, 2500);


					} else {

						mensajeError.textContent = "¡Correo registrado por otro Usuario!";
						mensajeError.style.color = "red";
						setTimeout(function() {
							mensajeError.textContent = "";

						}, 2500);



					}

				}, "json");

			} else {


				mensajeError.textContent = 'Campo Vacio';
				mensajeError.style.color = "orange";
				setTimeout(function() {
					mensajeError.textContent = "";

				}, 2500);
			}


		}
		function activar_cronometro() {

			$('#reloj').show();
			$('#reloj').show();
			$('#codigo').show();
			$('#validar').show();
			$('#enviar').hide();

			document.getElementById('valida_email_usu').disabled = true;
			// Tiempo inicial en segundos (5 minutos * 60 segundos/minuto)
			let tiempoTotal = 5 * 60; // 300 segundos
			// let tiempoTotal = 10; // 300 segundos


			function actualizarReloj() {
				const reloj = document.getElementById('reloj');
				const mensaje = document.getElementById('mensaje');

				// Calcular minutos y segundos
				const minutos = Math.floor(tiempoTotal / 60);
				const segundos = tiempoTotal % 60;

				// Formatear la visualización (ej: 05:00)
				const displayMinutos = minutos < 10 ? '0' + minutos : minutos;
				const displaySegundos = segundos < 10 ? '0' + segundos : segundos;

				reloj.innerHTML = `${displayMinutos}:${displaySegundos}`;

				if (tiempoTotal <= 0) {
					$('#mensaje').show();

					clearInterval(intervalo); // Detener el temporizador
					reloj.innerHTML = '00:00';
					mensaje.innerHTML = '¡Tiempo Expirado!';
					document.getElementById('enviar').disabled = true;
					document.getElementById('validar').disabled = true;
					document.getElementById('codigo').disabled = true;


					setTimeout(function() {
						$('#reloj').hide();
						$('#mensaje').hide();

						$('#validar').hide();
						$('#codigo').hide();
						$('#enviar').hide();
						$('#reenviar').show();
						document.getElementById('reenviar').disabled = false;

					}, 2000);
					inactivar_codigo()

				} else {
					tiempoTotal--; // Decrementar el tiempo
				}
			}

			// Actualizar el reloj cada segundo (1000 milisegundos)
			const intervalo = setInterval(actualizarReloj, 1000);

			// Llamar a la función una vez para mostrar el tiempo inicial
			actualizarReloj();


		}

		$(document).ready(function() {
			if (rol == 2 && act_email == 0) {
				$('#modal').modal('show'); // O 'toggle'
			}
		});

		//** enviar los datos al controlador ***********************************************************

		$("#save").click(function() {

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
				success: function(data) {
					$(".message").html(data);
				}
			});

		});
		// ********************************************************************************************

		$("#guardar_img").click(function() {
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
				success: function(data) {
					$(".message").html(data);
					setTimeout(function() {
						$(".alert").alert('close');
						$(location).attr('href', "../inicio/");


					}, 1500);

				}
			});

		});



		//salir del formulario *****************************************
		$("#exit").click(function() {
			$(location).attr('href', '../inicio/');
		});
		//**************************************************************


		/******************************************************************************/
		$('#password1').keyup(function() {
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


		}).focus(function() {
			$('#pswd_info').show();
		}).blur(function() {
			$('#pswd_info').hide();
		});

		$('#password2').keyup(function() {
			var pw3 = $(this).val();
			var pswd = $("#password2").val();

			if (pswd === pw3) {

				$('#compare').removeClass('invalid').addClass('valid');
			} else {
				$('#compare').removeClass('valid').addClass('invalid');
			}
		}).focus(function() {
			$('#pswd_confirm_info').show();
		}).blur(function() {
			$('#pswd_confirm_info').hide();
		});

		/********************************************************************/


	});

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

	$("#InputFile").change(function() {

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
				cancel: function() {

				}
			});
			return false;
		} else {
			return true;

		}

	}

	function validarEmail(elemento) {
		const email = elemento.value;
		const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Expresión regular simple para email
		const mensajeError = document.getElementById('mensaje-error');

		if (regex.test(email)) {
			// Es válido
			mensajeError.textContent = "¡Correo válido!";
			mensajeError.style.color = "green";
			setTimeout(function() {
				mensajeError.textContent = "";

			}, 1500);

		} else {
			// Es inválido
			mensajeError.textContent = "Formato de correo inválido.";
			mensajeError.style.color = "red";
			setTimeout(function() {
				mensajeError.textContent = "";

			}, 1500);

		}
	}

	$('#codigo').on('keyup', () => {
		const largominimo = 6;
		//Obtenemos el valor
		var input2 = $('#codigo').val();
		//Validamos que el valor sea mayor a 7 caracteres
		if (input2.length >= largominimo) {
			//Habiiltamos el botón '
			$('#validar').removeClass('disabled').prop('disabled', false);
		} else {
			//Volvemos a deshabilitar el botón
			$('#validar').addClass('disabled').prop('disabled', true);
		}
	});
</script>
<?php include_once("../layouts/pie.php") ?>