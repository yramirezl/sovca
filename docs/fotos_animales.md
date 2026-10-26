# Fotos de los animales (app sin conexión)

Versión **4.39** · `service-worker` **v87** · base del fundo **v4**

## 1. Cómo lo hace la web (y por qué se copia igual)

La app web **no guarda el binario en la base de datos**. Guarda:

| Dónde | Qué |
|---|---|
| `dist/img/fotos_animales/<archivo>` | La imagen (redimensionada a 800 px, WebP o JPEG) |
| `manimales.imagen` (`varchar(100)`) | **Solo el nombre del archivo** |

El flujo de la web está en `controllers/manimales_controller.php` y usa
`lib/imagen_helper.php`:

```php
$img = bovs_imagen_procesar($_FILES['file']['tmp_name'],
                            '../dist/img/fotos_animales/',
                            $codigo_rgb_id_animal);
$reg_animal->imagen = $img;   // ej. "BOVS-05-044.webp"
```

Al editar, si llega una imagen nueva se borra la anterior
(`bovs_imagen_eliminar`). La foto se muestra con
`../../dist/img/fotos_animales/<imagen>`.

La app offline hace **exactamente lo mismo**, así que una foto tomada en el
corral se ve igual desde el navegador de escritorio y viceversa.

## 2. Veredicto de viabilidad

| Caso | Viable | Cómo |
|---|---|---|
| **Mostrar fotos existentes** | Sí, sin tocar la base | `manimales.imagen` baja en el pull (la fila completa), así que la app arma la URL `../dist/img/fotos_animales/<imagen>` |
| **Mostrarlas sin señal** | Sí | Copia del binario en IndexedDB (`fotos`), 10 por sincronización |
| **Cargar fotos nuevas** | Sí | Endpoint `action: subir_foto` + cola local `fotos_pendientes` |
| **Cargarlas sin señal** | Sí | Se guardan como data URI en el equipo y suben solas al recuperar señal |

Capacidad del servidor verificada: **GD con soporte WebP activo**,
`dist/img/fotos_animales/` escribible por `www-data`.

> Nota sobre el alcance: la app solo pide foto cuando `manimales.imagen` tiene
> texto. En `sovca_qas` eso son **169 animales** (ver más abajo), así que el
> volumen real de descargas es pequeño, no de miles.

### El problema de los datos heredados

Los números reales (medidos con `scripts/importar_fotos_animales.php` sobre
`sovca_qas`):

| Dato | Valor |
|---|---|
| Animales en la base | 4.640 |
| `imagen` NULL | 3 |
| `imagen` con cadena vacía | 4.468 |
| **`imagen` con valor real** | **169** (169 nombres distintos) |
| De esos 169, archivo presente en disco | **0** |
| Archivos en la carpeta que nadie referencia | 1 (`BOVS-059-031.jpeg`) |

Es decir: la columna está llena de **cadena vacía**, no de nombres. Solo 169
animales tienen foto registrada, y de esos **ninguno tiene el archivo**. Los
respaldos del servidor (`/var/www/html/Respaldos/*.zip`) **no incluyen la
carpeta de fotos**, así que aquí no hay nada que copiar: los archivos hay que
pedírselos al cliente o sacarlos del servidor de producción.

Los 169 nombres siguen tres patrones, y ninguno coincide con el disco:

| Patrón | Cantidad | Ejemplo |
|---|---|---|
| `<CODIGO>_<apodo>.ext` | 29 | `BOVS-013-010_Dante.jpeg` |
| `<CODIGO>_<hash32>.ext` | — | `SOVCA-02-011_a270dd1e3cc64a6b9052862982bfeb3e.jpg` |
| `<CODIGO>.ext` | 140 | `BOVS-02-043.jpg` |

Formatos: 144 `.jpg`, 22 `.jpeg`, 3 `.png`. Hay nombres delicados como
`BOVS-02-02_50% BOVS.jpg` (espacio y `%`), que `Fotos.rutaDe()` resuelve con
`encodeURIComponent`.

Como la app solo intenta foto cuando `imagen` tiene texto, el costo real es de
**169 intentos**, no de 4.000.

### Cómo se resuelve: `scripts/importar_fotos_animales.php`

```bash
# 1) Inventario: qué falta, por fundo
php scripts/importar_fotos_animales.php

# 2) Listado para pedirle las fotos al cliente
php scripts/importar_fotos_animales.php --csv=/tmp/faltan.csv

# 3) Simular la carga desde la carpeta recibida (no escribe nada)
php scripts/importar_fotos_animales.php --origen=/ruta/fotos

# 4) Cargar de verdad
sudo -u www-data php scripts/importar_fotos_animales.php --origen=/ruta/fotos --aplicar

# 5) Igual, pero convirtiendo a WebP 800 px (actualiza manimales.imagen)
sudo -u www-data php scripts/importar_fotos_animales.php --origen=/ruta/fotos --webp --aplicar
```

Empareja por **nombre exacto** → **mismo nombre con otra extensión** →
**por código**, sin distinguir mayúsculas ni acentos, y guarda el archivo con
**el nombre exacto que espera la base de datos** (no modifica ninguna fila).
Con `--webp` sí actualiza `manimales.imagen` y avanza la versión, para que los
equipos reciban el cambio en el próximo pull.

⚠️ Ejecutarlo con `sudo -u www-data` (o con un usuario del grupo de Apache):
`dist/img/fotos_animales/` es de `www-data` y sin eso las copias fallan.

### Comportamiento de la app cuando la foto no está

1. Al bajar una foto que no está, se guarda `{ sin_archivo: true }` y no se
   vuelve a pedir en cada sincronización.
2. Si aun así falla la carga, el `<img>` dispara `error`: se quita la miniatura
   y la tarjeta vuelve a su tamaño normal. **Nunca se ve un icono roto.**

## 3. Diseño implementado

### Servidor — `api/sync_controller.php`

`action: "subir_foto"` (requiere vínculo de dispositivo en modo **gestión**)

```
{ codigo_rgb_id_animal, imagen_base64: "data:image/jpeg;base64,..." }
   ↓
ManimalSync::find  (valida que el animal sea de ese fundo)
   ↓
bovs_imagen_procesar()  → 800 px de lado mayor, WebP (o JPEG), calidad 80
   ↓
UPDATE manimales SET imagen = ?, version = version + 1, updated = NOW()
   ↓
bovs_imagen_eliminar()  (borra la foto anterior, si tenía otra)
   ↓
{ imagen, version, updated }
```

El nombre del archivo es el **código del animal** (`BOVS-02-03.webp`), así que
subir otra foto reemplaza la anterior sin acumular archivos. La versión avanza
para que el resto de los equipos reciba la foto nueva en el próximo pull.

Controles: data URI obligatorio, tope de 8 MB de cadena (~5 MB de imagen),
imagen validada por GD (una imagen corrupta devuelve error y **no** toca la foto
anterior), y 404 si el animal no existe en el fundo.

### Equipo — `app_offline/js/fotos.js`

| Almacén (base del fundo) | Contenido |
|---|---|
| `fotos` | `{ codigo, imagen, blob, sin_archivo, descargado_en }` |
| `fotos_pendientes` | `{ uuid_local, data_uri, codigo, creado, intentos, ultimo_error }` |

- `comprimirArchivo(archivo)`: canvas a 1.000 px de lado mayor, JPEG 0.82
  (probado: 1.600×1.200 → 1.000×750, 22 KB → 10 KB). El servidor vuelve a
  redimensionar a 800 px; esto es solo para no gastar datos subiendo la foto
  entera de la cámara.
- `guardarPendiente(uuidLocal, dataUri)`: la cola se indexa por **`uuid_local`**,
  no por código, porque un animal creado sin conexión tiene código
  **provisional** y lo cambia al sincronizar. Mientras el código sea
  provisional, la foto espera.
- `enviarPendientes(sesion)`: sube una por una, actualiza el animal local
  (`imagen`, `version`, `updated`), guarda el blob en `fotos` y saca la fila de
  la cola. Un equipo en modo **consulta** no intenta subir nada.
- `descargarFaltantes()`: baja como máximo `DESCARGAS_POR_SYNC = 10` por pasada,
  priorizando el hato activo (`mestatus_id = 1`). Con 4.000 animales esto se
  completa solo, sin saturar la conexión del fundo.
- `mapaDeUrls(animales)` / `urlDe(animal)`: resuelven la foto de toda la lista
  con **una sola lectura** de IndexedDB (no una por tarjeta).

La cola va **aparte del outbox**: si una foto no sube, el registro del animal no
queda bloqueado (y al revés). Se procesa al final de cada `Sync.sincronizar()`,
dentro del bloque `try`, de modo que un fallo de fotos nunca afecta a los datos
del hato.

### Interfaz

- **Lista del hato**: miniatura de 40 px (34 px en pantallas ≤ 430 px) en la
  esquina superior derecha. Solo reserva espacio cuando hay foto
  (`.chapeta--con-foto`).
- **Ficha del animal**: sección "Foto del animal" con marco de 96 px y botón
  **«Tomar o subir foto» / «Cambiar foto»**. En el celular abre la cámara
  trasera (`capture="environment"`); en el escritorio, el selector de archivos.
  Si ya hay una foto pendiente, la ficha lo dice: *"Guardada en el equipo · se
  sube al sincronizar"*.

## 4. Pruebas

`php api/test/validar_foto_animal.php` → **21 comprobaciones** contra la API real:
subida, archivo en disco, imagen legible y redimensionada, `manimales.imagen`
actualizada, versión que avanza, el pull entrega la foto nueva, reemplazo sin
acumular, y los rechazos (contenido inválido, no data URI, animal inexistente,
sin código) sin tocar la foto anterior.

Verificado además en el navegador: actualización de la base del fundo de **v3 a
v4** creando los dos almacenes nuevos sin perder datos, compresión en el equipo,
cola local, resolución de URLs y bloqueo de rutas arbitrarias
(`Fotos.rutaDe('../../etc/passwd')` → `null`).

## 5. Pendiente

- **Conseguir los 169 archivos**: no están en este servidor ni en los respaldos
  (`/var/www/html/Respaldos/*.zip` no traen la carpeta de fotos). Cuando lleguen,
  cargarlos con `scripts/importar_fotos_animales.php` (ver §2). Mientras tanto la
  app no se rompe: muestra el marcador.
- **Decidir qué hacer con `BOVS-059-031.jpeg`**: está en la carpeta pero ninguna
  fila lo referencia (huérfano). Se puede borrar o asignar a mano.
- **Política de retención** del almacén `fotos` en equipos con poca memoria: hoy
  no se purga solo. Un `Fotos.limpiarAntiguas()` por fecha de descarga sería el
  siguiente paso si el hato crece mucho.
