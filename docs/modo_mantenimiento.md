# Modo mantenimiento (solo administradores)

Permite dejar el servidor de **producción** en modo de pruebas de mantenimiento: los
usuarios **productores (perfil 2)** y **ayudantes (perfil 3)** no pueden entrar al
sistema, mientras que los perfiles **1 (Administrador)** y **4 (Super Usuario)** siguen
operando y pueden entrar a cualquier fundo con **Modo soporte** para revisar todo el
sistema.

---

## 1. Cómo funciona

> **Nota SOVCA**: el helper `lib/mantenimiento_helper.php` usa el prefijo
> interno `bovs_` en las funciones heredadas y se controla con la variable de
> entorno `SOVCA_MANTENIMIENTO` (o `SOVCA_MANTENIMIENTO_DIR` para la carpeta).
> La carpeta de estado es `file/mantenimiento/`.

El interruptor es un **archivo de estado**, no una columna de base de datos, para que
pueda consultarse en el login, en los layouts, en los endpoints AJAX y en el API sin
depender de ActiveRecord. Se resuelve en este orden:

| Prioridad | Origen | Uso |
|---|---|---|
| 1 | Variable de entorno `SOVCA_MANTENIMIENTO` (`1`/`on`/`true`/`si`/`yes`) | Bloqueo inmediato desde Apache, sin tocar archivos |
| 2 | `file/mantenimiento/estado.json` | Lo escribe el **panel** o el **script CLI** |
| 3 | `config/mantenimiento.php` (opcional, `<?php return array(...)`) | Despliegue manual / respaldo |
| — | Nada configurado | **Apagado** (falla abierto: el sistema funciona) |

El archivo de estado tiene esta forma:

```json
{
  "activo": true,
  "motivo": "Pruebas de rendimiento",
  "mensaje": "Volvemos a las 3:00 p. m.",
  "desde": "2026-10-23 13:37:03",
  "hasta": "2026-10-23 14:07:03",
  "duracion_min": 30,
  "usuario": "ADMIN",
  "usuario_id": 1,
  "actualizado": "2026-10-23 13:37:03"
}
```

- **Cierre automático:** si `hasta` está definido y ya pasó, el modo se considera
  terminado aunque el archivo siga con `"activo": true`. Así una prueba no deja a los
  usuarios fuera por un olvido. `duracion_min: 0` = sin límite (hay que desactivarlo a mano).
- **Bitácora:** cada activación/desactivación se agrega como una línea JSON en
  `file/mantenimiento/bitacora.log` (fecha, acción, usuario, motivo, origen, IP).
- **Roles permitidos:** por defecto `1` y `4`. Se pueden cambiar con la clave `roles`
  en `config/mantenimiento.php` si algún día se necesita otro perfil.

---

## 2. Dónde se aplica el bloqueo

El control se aplica en **cinco capas**, para que no quede un camino abierto:

| Capa | Archivo | Efecto para perfiles 2 y 3 |
|---|---|---|
| Login web | `controllers/musuarios_controller.php` (`case 'login'`) | Redirige a `views/login?stat=mantenimiento` |
| Navegación web | `lib/validar_session.php` (122 vistas) | Cierra la sesión y redirige al login con el aviso |
| AJAX / controladores | `lib/sanitizar.php` | Responde **403 JSON** `{codigo: "mantenimiento"}` |
| Red de seguridad global | `config/conexion.php` | Cubre `data_json/*`, reportes y cualquier script con modelos |
| Reportes PDF (ADOdb) | `Reportes/lib/bd/basedatosAdoIbase.php` | Responde **403** con un aviso HTML y cierra la ventana |
| API / app offline | `api/lib/ApiAuth.php` (`requerirAutenticacion`) y `api/auth_controller.php` (`login`) | **403** `{codigo: "mantenimiento"}`; la PWA conserva los pendientes sin marcarlos como error |

Además:

- **Acceso demostrativo:** el botón se oculta en el login y `controllers/demo_controller.php`
  rechaza `crear_cuenta`, `verificar_codigo` y `reenviar_codigo` mientras el modo esté activo.
- **Login:** muestra el aviso *“Mantenimiento programado”* y, al intentar entrar, el
  mensaje del motivo.
- **Barra superior:** el administrador ve un chip rojo **Mantenimiento → Gestionar**.
- **Inicio:** tarjeta con el motivo, la hora de cierre y los botones
  **Entrar con Modo soporte** y **Gestionar mantenimiento**.

---

## 3. Cómo se activa y se desactiva

### 3.1 Desde el panel (recomendado)

`Administración → Mantenimiento` (roles 1 y 4) → `views/mantenimiento/index.php`.

1. Escribe el **motivo** (obligatorio, mínimo 5 caracteres).
2. Elige la **duración** (30 min, 1 h, 2 h, 4 h, 8 h o sin límite).
3. Opcional: un **mensaje para los usuarios** (aparece en el login y en la PWA).
4. **Activar mantenimiento** → confirmación → el estado se guarda y la página se recarga.

Con el modo activo, el panel ofrece **Desactivar y abrir el sistema** y
**Entrar con Modo soporte**.

### 3.2 Desde la consola

```bash
php scripts/mantenimiento.php --instalar                 # prepara file/mantenimiento (777/666)
php scripts/mantenimiento.php --estado                   # consulta el estado actual
php scripts/mantenimiento.php --on --motivo="Pruebas de carga" --minutos=120
php scripts/mantenimiento.php --off --nota="Terminaron las pruebas"
```

Opciones: `--motivo=`, `--mensaje=`, `--minutos=N` (0 = sin límite), `--nota=`,
`--usuario=`, `--json` (salida para automatizar).

### 3.3 Desde Apache (bloqueo inmediato)

En el vhost o en un `.conf` del sitio:

```apache
SetEnv SOVCA_MANTENIMIENTO 1
```

Requiere `sudo systemctl reload apache2`. Tiene prioridad sobre el archivo: mientras
exista esa variable, el panel no podrá abrir el sistema (lo advierte en pantalla).

---

## 4. Flujo recomendado para una ventana de pruebas en producción

1. **Activar** el mantenimiento (panel o CLI) con una duración acotada (p. ej. 60 min).
2. Verificar desde otro navegador que un productor o ayudante queda bloqueado en el login.
3. Entrar como administrador y abrir **Todos los Fundos → Modo soporte → Entrar** en el
   fundo que se va a probar. La sesión conserva el rol 1/4, así que se navegan todos los
   módulos del fundo como si fuera el productor.
4. Al terminar, **salir del Modo soporte** y **desactivar el mantenimiento**.
5. Revisar la **bitácora** del panel para confirmar quién lo abrió y lo cerró.

> El archivo `file/mantenimiento/estado.json` está dentro del webroot y contiene solo el
> motivo y el nombre de usuario (no guardes allí información sensible). Si prefieres no
> exponerlo, usa la variable de entorno de Apache en lugar del archivo.

---

## 5. Permisos

El panel y el script escriben en `file/mantenimiento/`:

- carpeta: `777` (Apache, `www-data`, debe poder crear el temporal);
- `estado.json` y `bitacora.log`: `666`.

Si Apache no puede escribir, el panel muestra **“Sin permiso de escritura”** y el script
`--estado` indica `Archivo escribible: NO`. Solución:

```bash
php scripts/mantenimiento.php --instalar
# o, si la carpeta ya existe y es de otro usuario:
sudo chown -R www-data:www-data file/mantenimiento
```

---

## 6. Comportamiento en la app sin conexión (PWA)

- `api/lib/ApiAuth.php` devuelve **403** con `codigo: "mantenimiento"`.
- `app_offline/js/api.js` emite `bovs:mantenimiento`; `app.js` muestra un modal
  (máximo una vez cada 5 minutos) y **no cierra la sesión**.
- `app_offline/js/sync.js` **no marca los registros como error**: corta el ciclo, los deja
  pendientes y los sube cuando el mantenimiento termina.
- La versión de la app subió a **4.41** (`bovs-shell-v89`) para que los equipos con caché
  vieja reciban estos cambios.

---

## 7. Pruebas realizadas

- Login real por HTTP de un productor con el modo activo → `302` a
  `views/login?stat=mantenimiento`; al desactivarlo → `302` a `views/inicio/`.
- `validar_session.php`: rol 2 bloqueado (sesión destruida), rol 1 pasa.
- `sanitizar.php`: rol 2 recibe `403 {"codigo":"mantenimiento"}`.
- `ApiAuth::exigirMantenimientoPermitido`: productor real → 403; administrador → pasa.
- Reportes PDF (conexión ADOdb aparte): rol 2 recibe el aviso 403; rol 1 genera el reporte.
- Panel: un productor no puede activar ni desactivar (`no_autorizado`); el administrador
  activa/desactiva y la bitácora registra ambos eventos.
- Render del panel y del inicio con el modo activo (chip, tarjeta y botón de soporte).
- `php -l` en todos los archivos tocados y `node --check` en `api.js`, `sync.js`, `app.js`
  y `service-worker.js`.

---

## 8. Rollback

- Desactivar desde el panel o con `php scripts/mantenimiento.php --off`.
- Si se usó la variable de entorno, quitarla del vhost y recargar Apache.
- Como último recurso, escribir `{"activo": false}` en `file/mantenimiento/estado.json`
  (o borrar el archivo: sin archivo ni variable, el modo queda apagado).
- El modo mantenimiento **no modifica la base de datos** ni los datos de los fundos.
