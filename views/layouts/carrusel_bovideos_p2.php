<div class="box box-primary bg-rotativo" id="backg"></div>

<script>
/* Banner rotativo del menú lateral (CAPRINA) — SOVCA
   · Recorre las imágenes EN ORDEN. Antes usaba Math.random(), así que podía
     repetir la misma imagen varias veces seguidas y parecía "congelado".
   · Precarga las imágenes para evitar el parpadeo blanco al cambiar.
   · Se detiene cuando la pestaña no está visible. */
(function () {
    var fondos = [
        "../inicio/img/caprinos/publicidad/fondo_1.jpg",
        "../inicio/img/caprinos/publicidad/fondo_2.jpg",
        "../inicio/img/caprinos/publicidad/fondo_3.jpg",
        "../inicio/img/caprinos/publicidad/fondo_4.jpg"
    ];
    var INTERVALO = 8000;
    var indice    = 0;
    var timer     = null;

    function pintar(i) {
        var caja = document.getElementById('backg');
        if (!caja || !fondos.length) { return; }
        indice = (i + fondos.length) % fondos.length;
        caja.style.backgroundImage = "url(" + fondos[indice] + ")";
    }

    function siguiente() { pintar(indice + 1); }

    function detener() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    function arrancar() {
        detener();
        timer = setInterval(siguiente, INTERVALO);
    }

    $(document).ready(function () {
        if (!document.getElementById('backg') || !fondos.length) { return; }

        /* Precarga: la 1a imagen se ve al instante y las demás ya están en caché */
        for (var k = 0; k < fondos.length; k++) {
            var im = new Image();
            im.src = fondos[k];
        }

        pintar(0);
        arrancar();

        $(document).on('visibilitychange', function () {
            if (document.hidden) { detener(); } else { arrancar(); }
        });
    });
})();
</script>