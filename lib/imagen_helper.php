<?php
/**
 * lib/imagen_helper.php
 * ---------------------------------------------------------------------------
 * Procesamiento y almacenamiento de imágenes para BOVS.
 *
 * Al subir una imagen (ficha de animales, foto de perfil, etc.) se redimensiona
 * y se recomprime a WebP (con fallback a JPEG si el GD del servidor no trae
 * soporte WebP). El binario se guarda en disco y se devuelve SOLO el nombre del
 * archivo resultante, que es lo que se almacena en la columna `imagen` de la BD.
 *
 * Idea adaptada de /var/www/html/sistema-bodega/bodega/app/helpers/imagen.php
 * (allá el binario se guarda en una tabla `imagenes`; aquí se conserva el
 * almacenamiento en disco para no romper las vistas existentes).
 *
 * Requiere la extensión GD (extension=gd en php.ini).
 */

if (!defined('BOVS_IMAGEN_MAX_BYTES')) {
    define('BOVS_IMAGEN_MAX_BYTES', 5 * 1024 * 1024); // 5 MB (sobre el archivo original)
}
if (!defined('BOVS_IMAGEN_MAX_DIM')) {
    define('BOVS_IMAGEN_MAX_DIM', 800); // lado mayor en px (nunca agranda)
}
if (!defined('BOVS_IMAGEN_CALIDAD')) {
    define('BOVS_IMAGEN_CALIDAD', 80); // 1-100
}

/**
 * ¿El usuario adjuntó realmente un archivo? ($_FILES['x']['error'] === 4 = no file)
 */
function bovs_imagen_hay_archivo($file) {
    return is_array($file)
        && isset($file['error'])
        && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Valida un archivo subido ($_FILES['x']) ANTES de procesarlo.
 * Devuelve un mensaje de error (string) o null si es válido.
 */
function bovs_imagen_error_archivo($file) {
    if (!bovs_imagen_hay_archivo($file)) {
        return 'No se recibió ninguna imagen.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'La imagen supera el tamaño permitido por el servidor.';
        }
        return 'Error al recibir la imagen (código ' . (int) $file['error'] . ').';
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return 'No se recibió ninguna imagen válida.';
    }
    if (isset($file['size']) && $file['size'] > BOVS_IMAGEN_MAX_BYTES) {
        return 'La imagen supera el máximo de ' . (int) (BOVS_IMAGEN_MAX_BYTES / (1024 * 1024)) . ' MB.';
    }
    $info = @getimagesize($file['tmp_name']);
    $tipos_ok = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP);
    if (!$info || !in_array($info[2], $tipos_ok, true)) {
        return 'El archivo no es una imagen válida (use JPG, PNG o WebP).';
    }
    return null;
}

/**
 * Redimensiona, recomprime a WebP y guarda el resultado en disco.
 *
 * @param string $origen      Ruta del archivo subido (tmp_name) o un data URI.
 * @param string $dirDestino  Directorio destino (se crea si no existe).
 * @param string $nombreBase  Nombre sin extensión (ej. el código del animal).
 * @param int    $maxDim      Lado mayor máximo en px (0 = usar BOVS_IMAGEN_MAX_DIM).
 * @param int    $calidad     Calidad 1-100 (0 = usar BOVS_IMAGEN_CALIDAD).
 * @return string|null        Nombre del archivo guardado (ej. "BOVS-01-01.webp") o null si falla.
 */
function bovs_imagen_procesar($origen, $dirDestino, $nombreBase, $maxDim = 0, $calidad = 0) {
    if (!function_exists('imagecreatefromstring')) {
        return null; // GD no disponible
    }
    $maxDim  = $maxDim  > 0 ? $maxDim  : BOVS_IMAGEN_MAX_DIM;
    $calidad = $calidad > 0 ? $calidad : BOVS_IMAGEN_CALIDAD;

    // --- Leer el binario (desde archivo o desde data URI) ---
    if (is_string($origen) && strpos($origen, 'data:') === 0) {
        $coma = strpos($origen, ',');
        if ($coma === false) {
            return null;
        }
        $bin = base64_decode(substr($origen, $coma + 1), true);
    } elseif (is_string($origen) && $origen !== '' && is_file($origen)) {
        $bin = @file_get_contents($origen);
    } else {
        return null;
    }
    if (!$bin) {
        return null;
    }

    $src = @imagecreatefromstring($bin);
    if (!$src) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $escala = min(1.0, $maxDim / max($w, $h));
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));

    $dst = imagecreatetruecolor($nw, $nh);
    // Preservar transparencia (logos/PNG y salida WebP).
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transp = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transp);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    ob_start();
    if (function_exists('imagewebp')) {
        $ext = 'webp';
        $ok  = imagewebp($dst, null, $calidad);
        imagedestroy($dst);
    } else {
        // JPEG no soporta canal alfa: aplanar sobre fondo blanco.
        $flat  = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $nw, $nh, $white);
        imagecopy($flat, $dst, 0, 0, 0, 0, $nw, $nh);
        imagedestroy($dst);
        $ext = 'jpg';
        $ok  = imagejpeg($flat, null, min(100, $calidad + 2));
        imagedestroy($flat);
    }
    $out = ob_get_clean();
    if (!$ok || !$out) {
        return null;
    }

    if (!is_dir($dirDestino)) {
        @mkdir($dirDestino, 0775, true);
    }

    $archivo = $nombreBase . '.' . $ext;
    $ruta    = rtrim($dirDestino, '/') . '/' . $archivo;
    if (@file_put_contents($ruta, $out) === false) {
        return null;
    }
    return $archivo;
}

/**
 * Elimina del disco una imagen previamente guardada (por su nombre almacenado).
 * Usa basename() para evitar atravesar rutas.
 */
function bovs_imagen_eliminar($dirDestino, $archivo) {
    if (empty($archivo)) {
        return false;
    }
    $ruta = rtrim($dirDestino, '/') . '/' . basename($archivo);
    if (is_file($ruta)) {
        return @unlink($ruta);
    }
    return false;
}

/**
 * Nombre base seguro a partir del nombre original de un archivo subido,
 * sin extensión. Sirve para las cargas que conservan el nombre original.
 */
function bovs_imagen_nombre_base($nombreOriginal, $prefijo = '') {
    $nombre = pathinfo((string) $nombreOriginal, PATHINFO_FILENAME);
    $nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre);
    $nombre = trim($nombre, '_');
    if ($nombre === '') {
        $nombre = 'imagen';
    }
    return $prefijo . $nombre;
}
