<!-- Botón para activar el modal -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalActivacion">
  Activar mi cuenta
</button>

<!-- El Modal -->
<div class="modal fade" id="modalActivacion" tabindex="-1" aria-labelledby="modalActivacionLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalActivacionLabel">Verificación de Correo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Por favor, introduce tu correo electrónico para activar tu cuenta.</p>
        <form id="formActivacion">
          <div class="mb-3">
            <label for="email" class="form-label">Correo Electrónico</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <button type="submit" class="btn btn-success">Enviar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">

// Con jQuery y Bootstrap
$(document).ready(function(){
  $('#modalActivacion').modal('show'); // O 'toggle'
});

</script>

