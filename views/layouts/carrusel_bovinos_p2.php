<div class="box box-primary bg-rotativo" id="backg">
<!--img id="imguni" height="75" src="../inicio/img/ovinos/publicidad/unidos.png" width="90" style="position:absolute;top:290px; left:10px;visibility:visible; z-index:1;"-->
</div>

<script>
            $(document).ready(function(){

               var fondos = ["../inicio/img/ovinos/publicidad/fondo_1.jpg","../inicio/img/ovinos/publicidad/fondo_2.jpg","../inicio/img/ovinos/publicidad/fondo_3.jpg","../inicio/img/ovinos/publicidad/fondo_4.jpg"];

                function fondoBody() {

                    var aleatorio = Math.floor(Math.random()*(fondos.length));
                    seleccion = fondos[aleatorio];
                   
                    document.getElementById('backg').style.backgroundImage="url("+seleccion+")";
                    
               }
             
               fondoBody();
               setInterval(fondoBody, 8000);
            });
</script>