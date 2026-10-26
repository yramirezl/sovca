<div class="box box-primary" style="width:100%;height:50%;position:absolute; background-size:cover;background-position: top center;" id="backg">
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
               setInterval(fondoBody, 4000);

            });
</script>