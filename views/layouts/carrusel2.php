<div class="box box-primary" style="width:100%;height:50%;position:absolute; background-size:cover;background-position: top center;" id="backg">
<!--img id="imguni" height="75" src="../inicio/img/unidos.png" width="90" style="position:absolute;top:290px; left:10px;visibility:visible; z-index:1;"-->
</div>

<script>
            $(document).ready(function(){

               var fondos = ["../inicio/img/fondo6.jpg","../inicio/img/fondo7.jpg","../inicio/img/fondo8.jpg","../inicio/img/fondo9.jpg"];

               // function fondoBody() {

                    var aleatorio = Math.floor(Math.random()*(fondos.length));
                    seleccion = fondos[aleatorio];
                   
                    document.getElementById('backg').style.backgroundImage="url("+seleccion+")";
                    //alert(seleccion);
                    
              // }
             
                //setInterval(fondoBody, 4000);

            });
</script>