<html>
<button id="save" type="button" class="btn btn-success pull-right" align="center" tabindex="20"><i class="fa fa-fw fa-save"></i>Guardar</button>

<head>
    <script src="https://cdn.jsdelivr.net/gh/jamesssooi/Croppr.js@2.3.0/dist/croppr.min.js"></script>
    <link href="https://cdn.jsdelivr.net/gh/jamesssooi/Croppr.js@2.3.0/dist/croppr.min.css" rel="stylesheet" />
    <title>Recorta tu imagen y transforma a base64</title>
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

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />

<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // Input File
            const inputImage = document.querySelector('#image');
            // Nodo donde estará el editor
            const editor = document.querySelector('#editor');
            // El canvas donde se mostrará la previa
            const miCanvas = document.querySelector('#preview');
            // Contexto del canvas
            const contexto = miCanvas.getContext('2d');
            // Ruta de la imagen seleccionada
            let urlImage = undefined;
            // Evento disparado cuando se adjunte una imagen
            inputImage.addEventListener('change', abrirEditor, false);

            /**
             * Método que abre el editor con la imagen seleccionada
             */
            function abrirEditor(e) {
                // Obtiene la imagen
                urlImage = URL.createObjectURL(e.target.files[0]);

                // Borra editor en caso que existiera una imagen previa
                editor.innerHTML = '';
                let cropprImg = document.createElement('img');
                cropprImg.setAttribute('id', 'croppr');
                editor.appendChild(cropprImg);

                // Limpia la previa en caso que existiera algún elemento previo
                contexto.clearRect(0, 0, miCanvas.width, miCanvas.height);

                // Envia la imagen al editor para su recorte
                document.querySelector('#croppr').setAttribute('src', urlImage);

                // Crea el editor
                new Croppr('#croppr', {
                    aspectRatio: 1,
                    startSize: [70, 70],
                    onCropEnd: recortarImagen
                })
            }

            /**
             * Método que recorta la imagen con las coordenadas proporcionadas con croppr.js
             */
            function recortarImagen(data) {
                // Variables
                const inicioX = data.x;
                const inicioY = data.y;
                const nuevoAncho = data.width;
                const nuevaAltura = data.height;
                const zoom = 1;
                let imagenEn64 = '';
                // La imprimo
                miCanvas.width = nuevoAncho;
                miCanvas.height = nuevaAltura;
                // La declaro
                let miNuevaImagenTemp = new Image();
                // Cuando la imagen se carge se procederá al recorte
                miNuevaImagenTemp.onload = function() {
                    // Se recorta
                    contexto.drawImage(miNuevaImagenTemp, inicioX, inicioY, nuevoAncho * zoom, nuevaAltura * zoom, 0, 0, nuevoAncho, nuevaAltura);
                    // Se transforma a base64
                    imagenEn64 = miCanvas.toDataURL("image/jpeg");
                    // Mostramos el código generado
                    document.querySelector('#base64').textContent = imagenEn64;
                }
                // Proporciona la imagen cruda, sin editarla por ahora
                miNuevaImagenTemp.src = urlImage;
            }
        });


        $("#save").click(function() {

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
        formData.append('action', 'addimagen');
  

        $.ajax({
            url: "../../controllers/mimagen_controller",
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            success: function(data) {
                // $(".message").html(data);

                // var apg = document.getElementById("tocon").value;


              

            }
        });

    },

    cancel: function() {

    }
});


});


    </script>
</head>

<body>
    <h2>1 Introduce tu imagen</h2>
    <!-- Input file donde se adjunta la imagen -->
    <input type="file" id="image">

    <h2>2 Recorta</h2>
    <!-- Editor donde se recortará la imagen con la ayuda de croppr.js -->
    <div id="editor"></div>

    <h2>3 Previsualiza el resultado</h2>
    <!-- Previa del recorte -->
    <canvas id="preview"></canvas>


</body>


</html>

