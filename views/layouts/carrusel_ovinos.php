<!--img id="imguni" height="140" src="../inicio/img/ovinos/carrusel/unidos.png" width="170" style="position:absolute;top:70px; left:255px;visibility:visible; z-index:3;"-->
<div class="slider ">


            <input type="radio" name="slider" id="slider1" checked=""/>
            <input type="radio" name="slider" id="slider2" checked=""/>
            <input type="radio" name="slider" id="slider3" checked=""/>
            <input type="radio" name="slider" id="slider4" checked=""/>
            
            <div class="thumbs">
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_1.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_2.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_3.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/ovinos/carrusel/fondo_4.jpg)"></div>
                </div>
                
    
            </div>
            
            <div class="control">
                <label for="slider1"></label>
                <label for="slider2"></label>
                <label for="slider3"></label>
                <label for="slider4"></label>
            </div>
        </div>
        
        
         
        <script>
            $(document).ready(function(){
                
                function cambioslider() {



                        if($("#slider2").is(":checked")){
                            $("#slider3").prop("checked", true);
                        }else if($("#slider3").is(":checked")){
                            $("#slider4").prop("checked", true);
                        }else if($("#slider4").is(":checked")){
                            $("#slider1").prop("checked", true);
                        }else{
                            $("#slider2").prop("checked", true);
                        }
                    }
                    setInterval(cambioslider, 5000);
                


                        $('.slider').addClass("nav-round");
                        $('.slider').addClass("slow");
                
            });
        </script>