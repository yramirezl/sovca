<div class="box box-primary bg-rotativo bg-wide" id="backg">
<!--img id="imguni" height="75" src="../inicio/img/unidos.png" width="90" style="position:absolute;top:290px; left:10px;visibility:visible; z-index:1;"-->
</div>

<script>
            $(document).ready(function(){

               var fondos = ["../../dist/file/img/fondo6.jpeg","../../dist/file/img/fondo7.jpeg","../../dist/file/img/fondo8.jpeg","../../dist/file/img/fondo9.jpeg"];

               // function fondoBody() {

                    var aleatorio = Math.floor(Math.random()*(fondos.length));
                    seleccion = fondos[aleatorio];
                   
                    document.getElementById('backg').style.backgroundImage="url("+seleccion+")";
                    //alert(seleccion);
                    
              // }
             
                //setInterval(fondoBody, 4000);

            });
</script>