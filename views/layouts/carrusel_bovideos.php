<!--img id="imguni" height="140" src="../inicio/img/caprinos/carrusel/unidos.png" width="170" style="position:absolute;top:70px; left:255px;visibility:visible; z-index:3;"-->
<div class="slider ">


            <!-- Solo la primera lleva checked: si varias lo llevan, el navegador
                 se queda con la ULTIMA y el carrusel arranca en la 4a imagen. -->
            <input type="radio" name="slider" id="slider1" checked="checked"/>
            <input type="radio" name="slider" id="slider2"/>
            <input type="radio" name="slider" id="slider3"/>
            <input type="radio" name="slider" id="slider4"/>
            
            <div class="thumbs">
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_1.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_2.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_3.jpg)"></div>
                </div>
                
                <div class="thumb thumb10">
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
                    <div class="img" style="background-image: url(img/caprinos/carrusel/fondo_4.jpg)"></div>
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
            /* Carrusel de inicio (CAPRINA) — SOVCA
               · Arranca siempre en la diapositiva 1 (solo #slider1 lleva checked).
               · Avanza 1 → 2 → 3 → 4 → 1 cada INTERVALO ms.
               · Se detiene con la pestaña oculta y se reanuda al volver.
               · Al pulsar un punto de control se reinicia el ciclo, para que
                 no salte a la diapositiva siguiente justo después del clic. */
            (function () {
                var INTERVALO = 8000;
                var indice    = 0;
                var timer     = null;

                function slides() {
                    return $('.slider > input[type="radio"][name="slider"]');
                }

                function mostrar(i) {
                    var $r = slides();
                    if (!$r.length) { return; }
                    indice = (i + $r.length) % $r.length;
                    $r.eq(indice).prop('checked', true);
                }

                function siguiente() { mostrar(indice + 1); }

                function detener() {
                    if (timer) { clearInterval(timer); timer = null; }
                }

                function arrancar() {
                    detener();
                    timer = setInterval(siguiente, INTERVALO);
                }

                $(document).ready(function () {
                    var $r = slides();
                    if (!$r.length) { return; }

                    /* Estado inicial coherente: la diapositiva marcada o la 1a */
                    indice = $r.index($r.filter(':checked'));
                    if (indice < 0) { indice = 0; }
                    mostrar(indice);

                    $('.slider').addClass('nav-round').addClass('slow');
                    arrancar();

                    /* Clic en un punto: salta y reinicia el temporizador */
                    $('.slider .control label').on('click', function () {
                        var n = parseInt(String($(this).attr('for') || '').replace('slider', ''), 10);
                        if (!isNaN(n)) { mostrar(n - 1); }
                        arrancar();
                    });

                    /* Pausa cuando la pestaña no está visible */
                    $(document).on('visibilitychange', function () {
                        if (document.hidden) { detener(); } else { arrancar(); }
                    });
                });
            })();
        </script>