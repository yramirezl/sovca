#!/usr/bin/env bash
# =============================================================================
#  Genera los iconos de la aplicacion a partir del logo oficial de SOVCA
# =============================================================================
#
#  Entrada : dist/img/sovca.png        (wordmark 2500x1000, fondo transparente)
#  Salida  : app_offline/icons/*.png   (los usan la app offline Y la PWA web)
#
#  El icono es la cabeza de carnero que dibuja la letra "O" del logo, en crema
#  sobre el verde de la marca. Asi el icono instalado en el telefono se
#  reconoce como parte del sistema y no como un dibujo generico.
#
#  Se genera un juego por cada "purpose" del manifest:
#
#    icon-192/512.png            -> purpose "any"       (esquinas redondeadas,
#                                                       fondo transparente)
#    icon-maskable-192/512.png   -> purpose "maskable"  (fondo a sangre y el
#                                                       dibujo mas pequeno, para
#                                                       que el recorte circular
#                                                       de Android no lo corte)
#    apple-touch-icon.png        -> iOS, 180x180 opaco  (iOS pinta la
#                                                       transparencia de negro)
#
#  Uso:  bash scripts/generar_iconos_app.sh
#
#  Requiere ImageMagick 6 (comando `convert`).
# =============================================================================

set -euo pipefail

cd "$(dirname "$0")/.."

LOGO="dist/img/sovca.png"
DEST="app_offline/icons"

VERDE="#006532"   # verde de las letras del logo
CREMA="#F1ECDD"   # crema de la marca (background_color del manifest)

if ! command -v convert >/dev/null 2>&1; then
    echo "ERROR: falta ImageMagick (comando convert)." >&2
    exit 1
fi
if [ ! -f "$LOGO" ]; then
    echo "ERROR: no se encuentra $LOGO" >&2
    exit 1
fi

mkdir -p "$DEST"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# -----------------------------------------------------------------------------
# 1) Sacar la silueta de la cabeza de carnero
# -----------------------------------------------------------------------------
# El logo solo tiene dos verdes:
#   letras   #006532  -> el canal G vale 101 (0.396 en escala 0..1)
#   animales #003300  -> el canal G vale  51 (0.20 en escala 0..1)
# Aplanamos sobre blanco y usamos el canal G como mascara: nos quedamos con
# los pixeles cuyo verde esta cerca de 0.20 -> esa es la silueta de los
# animales (carnero en la "O" y cabra en la "A").
convert "$LOGO" -background white -flatten \
        -channel G -separate +channel \
        -fx "max(0,min(1,(1-abs(u-0.2)/0.05)))" \
        -threshold 50% \
        "$TMP/alfa.png"

# El carnero esta dentro de la letra O. Su caja exacta es (715x667+374+184);
# el resto del logo queda fuera.
convert "$TMP/alfa.png" -crop 715x667+374+184 +repage "$TMP/silueta.png"

# La silueta queda como escala de grises; se pasa a alfa y se pinta en crema.
convert "$TMP/silueta.png" \
        -alpha copy -channel RGB -fill "$CREMA" -colorize 100 +channel \
        "$TMP/marca.png"

echo "silueta lista: $(identify -format '%wx%h' "$TMP/marca.png")"

# -----------------------------------------------------------------------------
# 2) Componer los iconos
# -----------------------------------------------------------------------------
# 512 es la medida de referencia; los demas salen de la misma proporcion.
#   any      -> el dibujo ocupa ~66% del lienzo
#   maskable -> ~55%, dentro del circulo seguro (40% del radio)
#   ios      -> ~66% sobre fondo a sangre

icono_redondeado() {   # $1=lado  $2=ancho del dibujo  $3=salida
    local lado="$1" ancho="$2" salida="$3" radio
    radio=$((lado * 22 / 100))
    convert -size "${lado}x${lado}" xc:none \
            -fill "$VERDE" -draw "roundrectangle 0,0,$((lado-1)),$((lado-1)),$radio,$radio" \
            \( "$TMP/marca.png" -resize "${ancho}x" \) \
            -gravity center -composite \
            -depth 8 -define png:color-type=6 \
            "$salida"
}

icono_a_sangre() {     # $1=lado  $2=ancho del dibujo  $3=salida
    local lado="$1" ancho="$2" salida="$3"
    convert -size "${lado}x${lado}" xc:"$VERDE" \
            \( "$TMP/marca.png" -resize "${ancho}x" \) \
            -gravity center -composite \
            -alpha off -depth 8 -define png:color-type=2 \
            "$salida"
}

icono_redondeado 512 340 "$DEST/icon-512.png"
icono_redondeado 192 128 "$DEST/icon-192.png"
icono_a_sangre   512 280 "$DEST/icon-maskable-512.png"
icono_a_sangre   192 105 "$DEST/icon-maskable-192.png"
icono_a_sangre   180 120 "$DEST/apple-touch-icon.png"

echo
echo "Iconos generados en $DEST:"
identify -format '  %f  %wx%h  %[opaque]\n' \
    "$DEST/icon-192.png" "$DEST/icon-512.png" \
    "$DEST/icon-maskable-192.png" "$DEST/icon-maskable-512.png" \
    "$DEST/apple-touch-icon.png"
