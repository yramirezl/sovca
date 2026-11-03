# Modo offline — página de inicio y acceso directo en el dispositivo

> Resuelve un problema concreto del campo: el productor tenía que **recordar la
> dirección** del modo offline (`/app_offline/index.html`) para volver a entrar
> cuando se le cerraba el navegador. Ahora hay una página de inicio con URL corta,
> y al instalar la app queda el **ícono en el dispositivo**.

---

## 1. Cómo entra el productor (tres caminos, todos válidos)

| Camino | Cuándo se usa |
|---|---|
| **Ícono SOVCA** en la pantalla de inicio / escritorio | Uso diario. Es lo que crea la instalación. |
| Escribir `<servidor>/sovca/offline` | Si se le cerró el navegador y no tiene el ícono a mano. |
| Sistema web → login → **Modo offline · Página de inicio** | Si está frente a la web y quiere instalar o revisar el equipo. |

`/sovca/offline` es una redirección (`.htaccess`, regla `offline/?$`) a
`app_offline/inicio.html`. Se redirige en vez de reescribir a propósito: el
manifest de la PWA resuelve `scope` y `start_url` de forma **relativa** a
`/app_offline/`, así que la página tiene que servirse desde ahí para que la
instalación siga funcionando.

---

## 2. La página de inicio (`app_offline/inicio.html`)

Es la `start_url` del manifest: lo primero que se abre al tocar el ícono.

Muestra el estado real del equipo leyendo IndexedDB con las mismas funciones que
la app (`js/db.js`), nunca con datos inventados:

| Estado | Qué dice | Qué ofrece |
|---|---|---|
| Sin sesión ni credencial local | «Este equipo todavía no está preparado» | Instalar la app + enlace al sistema web para activar el modo offline |
| Con credencial/sesión | «Este equipo está listo para trabajar sin conexión» + usuario y fundos | Entrar a la app |
| Ya instalada y preparada | — | **Pasa directo a la app** (ver §3) |
| Instalada pero sin datos | «Falta descargar los datos del fundo» | Entrar a la app para iniciar sesión una vez |

Botones:

1. **Entrar a SOVCA Offline** → `index.html`.
2. **Instalar en este dispositivo** → aparece cuando el navegador dispara
   `beforeinstallprompt`; al aceptar, el navegador **crea el ícono**.
3. **Cómo crear el acceso en este dispositivo** → pasos por plataforma
   (Android, iPhone/iPad, Windows/Mac), resaltando la del equipo en uso.

---

## 3. «Que no estorbe» en el uso diario

Si `display-mode: standalone` (abierta desde el ícono) **y** el equipo ya tiene
datos, la portada hace `location.replace('index.html')`: el productor no ve
ninguna pantalla intermedia.

Para entrar a la portada a propósito (por ejemplo, para reinstalar o revisar el
estado) se usa `?inicio=1`, que es justo lo que ponen:

- el acceso directo del manifest (`shortcuts`),
- el enlace **Modo Offline** del login del sistema,
- el atajo `/sovca/offline` (redirige con `?inicio=1`),
- el enlace al pie de las dos pantallas de entrada de la app (login y reconexión
  local), que además resuelve «me equivoqué de usuario / quiero instalar».

### Un solo acceso por módulo

El login del sistema tiene **un único** enlace al módulo:

```
Login  →  "Modo Offline"  →  app_offline/inicio.html?inicio=1
```

La portada es la que muestra las opciones, sin duplicar botones en el login:

| Opción de la portada | Para qué |
|---|---|
| **Entrar a SOVCA Offline** | Abre la app (`index.html`). |
| **Instalar en este dispositivo** | Aparece cuando el navegador ofrece instalarla. |
| **Cómo crear el acceso en este dispositivo** | Pasos por plataforma. |
| **Activar este equipo** → `../views/login?activar=1` | Solo si el equipo no está preparado: abre el asistente de precarga (usuario, contraseña y captcha) directamente en el login. |

Ese `?activar=1` es la única forma de abrir el asistente desde la interfaz: el
login ya no tiene un botón aparte, justamente para que no haya dos entradas al
mismo módulo.

Dentro de la app, el enlace a la portada (`.pie-inicio`) se oculta en teléfonos
y tablets con `app_offline/js/acceso-modo-offline.js`: ahí la app se abre desde
el ícono instalado.

---

## 4. Instalación por plataforma (lo que crea el acceso directo)

| Plataforma | Cómo | Automático |
|---|---|---|
| Android (Chrome/Edge/Samsung) | Botón **Instalar** o menú `⋮` → *Instalar aplicación* | Sí (WebAPK) |
| Windows / Mac / Linux (Chrome/Edge) | Botón **Instalar** o ícono de instalar de la barra; dejar marcado *Crear acceso directo en el escritorio* | Sí |
| iPhone / iPad (Safari) | Compartir → *Agregar a inicio* | **No** — Apple no lo permite; la página lo explica |

En navegadores sin instalación (Firefox, por ejemplo) el botón de instalar no
aparece y el camino es el punto 3 del panel de instrucciones.

Referencia técnica:

- `manifest.json`: `"id": "./"` (evita instalaciones duplicadas al cambiar
  `start_url`), `start_url: "./inicio.html"`, `shortcuts` (Ir a la app / Instalar
  o preparar el equipo), `launch_handler: focus-existing`,
  `display_override`.
- `js/inicio.js`: registro del service worker, detección de estado,
  `beforeinstallprompt`, `appinstalled` y redirección de uso diario.
- `service-worker.js`: `inicio.html`, `css/inicio.css` y `js/inicio.js` agregados
  al shell (caché `sovca-shell-v96`) y nuevo `respaldoOffline()`, que busca en
  caché **ignorando la query** (`?inicio=1`) y, si la página pedida no está,
  devuelve la app en vez de una pantalla de error del navegador.

---

## 5. Despliegue

Subir respetando rutas:

```
.htaccess                                  (atajo /offline)
app_offline/inicio.html                    (NUEVO)
app_offline/css/inicio.css                 (NUEVO)
app_offline/js/inicio.js                   (NUEVO)
app_offline/css/estilos.css                (bloque .pie-inicio)
app_offline/index.html                     (enlaces a la página de inicio)
app_offline/manifest.json                  (id, start_url, shortcuts)
app_offline/service-worker.js              (v96 + respaldoOffline)
app_offline/version.php                    (archivos nuevos en el hash)
app_offline/versiones.json                 (4.50)
views/login.php                            (enlace «Página de inicio»)
```

Después del despliegue, en el teléfono del productor:

1. Abrir la app una vez **con internet** para que el service worker nuevo se
   instale (la versión aparece en el badge del encabezado; debe decir 4.50).
2. Si ya tenía el ícono instalado, este sigue funcionando y ahora abre la página
   de inicio (que pasa sola a la app).
3. Si nunca la instaló, entrar a `/offline` y usar **Instalar en este
   dispositivo**.

> `manifest.json` y `service-worker.js` se sirven desde Apache; si más adelante
> se pone un CDN o caché agresiva delante, excluir ambos archivos (y
> `version.php`).

> **Esquema de versión (desde V1.00053).** El badge del encabezado muestra
> `V1.nnnnn`: la **V** y el **1** identifican esta app (primera versión del modo
> offline) y lo que sigue es la **revisión** (`1.00053`, `1.00054`…). Antes se
> usaba el mismo número que la web (4.52), y daba la impresión de que la PWA ya
> iba por su cuarta versión. La fuente sigue siendo `app_offline/versiones.json`
> (`actual`) —solo cambió la numeración— y el badge la formatea con
> `etiquetaVersion()` en `app_offline/js/app.js`.

---

## 6. Pruebas hechas (2026-10-30, entorno local)

- Portada sin datos: muestra «Este equipo todavía no está preparado» y el aviso
  con el enlace al sistema web.
- Con credencial local simulada: pasa a «listo», muestra usuario y oculta el
  aviso.
- Botón **Entrar** → `index.html` (la app muestra la pantalla de reconexión local
  con el usuario detectado).
- Enlace «Página de inicio del modo offline» presente en el login de la app y en
  la pantalla de reconexión local.
- **Sin conexión** (contexto offline): la portada carga desde caché, con CSS
  aplicado.
- `/sovca/offline` → 302 a `app_offline/inicio.html?inicio=1`.
- Login del sistema: «Modo offline · Página de inicio» con el `href` correcto.
