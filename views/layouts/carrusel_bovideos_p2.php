<div class="box box-primary bg-rotativo" id="backg">
<!-- <img id="imguni" height="75" src="../inicio/img/bovs.png" width="130" style="position:absolute;top:380px; left:35px;visibility:visible; z-index:1;"> -->
</div>

<script>
            $(document).ready(function(){

               var fondos = ["../inicio/img/caprinos/publicidad/fondo_1.jpg","../inicio/img/caprinos/publicidad/fondo_2.jpg","../inicio/img/caprinos/publicidad/fondo_3.jpg","../inicio/img/caprinos/publicidad/fondo_4.jpg"];
              
                function fondoBody() {

                    var aleatorio = Math.floor(Math.random()*(fondos.length));
                    seleccion = fondos[aleatorio];
                   
                    document.getElementById('backg').style.backgroundImage="url("+seleccion+")";
                  
                    
               }
               fondoBody();
               setInterval(fondoBody, 8000);

            });
</script>