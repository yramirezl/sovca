/*
 * crud-mensajes.js  (v3)
 * ---------------------------------------------------------------------------
 * Los formularios declaran los contenedores de validación con un <p></p> de
 * relleno:
 *
 *     <div id='ms_cod_animal_h'><p></p></div>
 *     <div id='ms_nombre'><p></p></div>
 *
 * Ese <p> (aunque esté vacío) hace que el contenedor NO sea ":empty" para CSS,
 * así que la regla que pinta los mensajes (.crud-form div[id^="ms_"]:not(:empty))
 * dibujaba una barra rosada/roja sin texto debajo de cada campo, y en los campos
 * que declaran DOS contenedores (código + nombre) salían DOS barras repetidas.
 *
 * Este normalizador (sólo dentro de los formularios ".crud-form") quita esos
 * <p> de relleno, de modo que el contenedor vuelve a ser ":empty" (oculto) y sólo
 * se pinta cuando el JavaScript le escribe un mensaje real.
 *
 * La franja roja que delimita el FINAL DE CADA BLOQUE no la dibuja este script:
 * es `css/crud-form.css` (.crud-form__card::after), así aparece una sola vez por
 * tarjeta, en todas las vistas y sin depender de los contenedores de mensaje.
 *
 * No se toca nada más: los manejadores que hacen innerHTML = '...' o
 * style.display = 'block' siguen funcionando igual.
 */
(function () {
    /* Un <p> de relleno: sin elementos hijos y sin texto */
    function esRelleno(parrafo) {
        return parrafo.children.length === 0 &&
               (parrafo.textContent || parrafo.innerText || '').replace(/\s/g, '') === '';
    }

    function quitarRellenos() {
        var contenedores = document.querySelectorAll('.crud-form [id^="ms_"]');
        var i, j, parrafos;

        for (i = 0; i < contenedores.length; i++) {
            parrafos = contenedores[i].getElementsByTagName('p');

            /* De atrás hacia adelante: al eliminar no se descoloca el índice */
            for (j = parrafos.length - 1; j >= 0; j--) {
                if (esRelleno(parrafos[j])) {
                    parrafos[j].parentNode.removeChild(parrafos[j]);
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', quitarRellenos);
    } else {
        quitarRellenos();
    }
})();
