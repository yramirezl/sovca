# Conexiones: duración de cada sesión

El módulo `views/conexiones/` ya mostraba **quién entra, desde dónde y con qué
frecuencia**. Ahora también muestra **cuánto tiempo permanece conectado cada
usuario**: desde el login hasta la última transacción registrada en esa sesión.

---

## 1. Qué se guarda

Cada login sigue creando una fila en `conexiones_historial`. La migración
`scripts/migracion_conexiones_duracion.sql` (idempotente) agrega:

| Columna | Para qué |
|---|---|
| `session_id_hash` | sha256 del id de sesión PHP (nunca el id en claro) |
| `ultima_actividad` | última petición del usuario (la «última transacción») |
| `ultima_ruta` | última página o acción (informativo, aparece en el tooltip) |
| `fecha_fin` | cierre de la sesión (logout) |
| `duracion_seg` | segundos entre el login y el cierre / última actividad |
| `cerrada` / `cerrada_por` | si se cerró y por qué (`logout`, `timeout`, `admin`) |

Se aplica con:

```bash
psql -d <base> -v ON_ERROR_STOP=1 -f scripts/migracion_conexiones_duracion.sql
```

---

## 2. Cómo se mide

1. **Login** → `bovs_conexion_registrar()` (en `lib/geoip_helper.php`) inserta
   la fila con `ultima_actividad = fecha` y guarda en la sesión el id de esa fila
   (`$_SESSION['cx_historial_id']`).
2. **Navegación** → `lib/validar_session.php` (todas las páginas) llama a
   `bovs_cx_latido()`.
3. **Acciones/AJAX** → `lib/sanitizar.php` (todos los controladores) llama al
   mismo latido, añadiendo el nombre de la acción. Esto es lo que registra la
   «última transacción».
4. **Logout** → `views/logout.php` cierra la fila con `bovs_cx_cerrar_sesion('logout')`.

El latido es una sola consulta `UPDATE` por clave primaria y **se limita a una
escritura cada 20 segundos por sesión** (la condición va en el propio `WHERE`),
así que no agrega carga apreciable.

### Estados de la sesión

| Estado | Regla | Texto en pantalla |
|---|---|---|
| `activa` | abierta y con actividad en los últimos 15 min | **En línea** · `desde HH:MM` |
| `cerrada` | se hizo logout | **Cerró sesión** · `HH:MM` |
| `caducada` | abierta pero sin actividad hace más de 15 min (navegador cerrado o sesión vencida) | **Caducó** · `HH:MM` |
| `sin_datos` | filas anteriores a esta mejora (`ultima_actividad` NULL) | **Sin seguimiento** |

En las sesiones caducadas la duración se calcula hasta la **última actividad**
(no hay un `logout` que marque el fin). Cuando el usuario vuelve a entrar, el
barrido `bovs_cx_cerrar_caducadas()` las cierra con `cerrada_por = 'timeout'`
y la hora real de caducidad, así el historial no acumula sesiones «En línea»
fantasma.

### Una fila por conexión (2026-11-01)

El login podía ejecutarse más de una vez con la **misma** sesión PHP (reenvío
del formulario, flujo multifundo, reingreso sin cerrar) y cada ejecución
insertaba otra fila con el mismo `session_id_hash`. El historial mostraba
varias «En línea» de la misma conexión, casi todas con 0 s de duración.

Ahora `bovs_conexion_registrar()` busca antes una conexión **abierta** de la
misma sesión y equipo (hash de sesión, o misma IP + navegador con actividad
reciente) y la reutiliza: refresca la actividad y **acumula** la duración. Solo
inserta una fila nueva si la anterior estaba cerrada o caducada.

Las conexiones duplicadas que ya existían se consolidan con
`scripts/migracion_conexiones_consolidar.sql` (idempotente): conserva la fila
más antigua de cada sesión, le pone la última actividad y la duración
acumulada, y borra el resto.

Además, la pantalla se refresca sola cada 60 s (salvo que la pestaña esté
oculta o haya un diálogo abierto), así la duración «en curso» avanza sin
recargar.

---

## 3. Qué se ve en el módulo

### KPIs
- Usuarios con actividad.
- Conexiones.
- Promedio por usuario.
- **Duración promedio** de las sesiones medidas del periodo.
- Última conexión.

### Resumen por usuario
- Columna **Duración**: promedio del periodo y, debajo, la duración de la última
  sesión.
- En «Última conexión» aparece además `act. hace X min` cuando la última
  transacción es posterior al login.

### Historial completo
- **Inicio**, **Última transacción**, **Duración** y **Estado** por cada sesión.
  El estado indica **En línea** (con la hora desde la que está conectado),
  **Cerró sesión** o **Caducó** (con la hora en que ocurrió).
- El tooltip de la última transacción muestra la ruta o acción registrada.

### Detalle de un usuario (modal)
- Duración promedio, última transacción y sesiones en línea, además de los datos
  que ya existían (frecuencia, días activos, IPs, ubicaciones…).

---

## 4. Campos nuevos de los endpoints

`data_json/data_mconexiones.php` (historial):

```
inicio, ultima_actividad, fin, duracion_seg, duracion_txt,
estado, cerrada_por, ultima_ruta
```

`data_json/data_mconexiones_resumen.php` (resumen):

```
duracion_prom, duracion_prom_txt, duracion_total, duracion_total_txt,
sesiones_medidas, en_linea, ultima_actividad, ultima_duracion, ultima_duracion_txt
```

---

## 5. Notas

- **Solo sesiones web.** La app offline y el API usan token, no sesión PHP, así
  que no se suman como «conexión» aquí.
- Las sesiones de **demostración** (`$_SESSION['demo']`) se excluyen: trabajan
  contra `sovca_demo` y no deben mezclarse con el historial real.
- Un mismo usuario puede tener **varias sesiones abiertas** (varios equipos); cada
  login es una fila independiente.
- Las filas anteriores al despliegue muestran **Sin seguimiento**; no se
  inventan duraciones.
- Cerrar el navegador sin pulsar *Salir* deja la fila abierta hasta que expira
  (se muestra **Expirada** con la duración hasta la última actividad).

---

## 6. Verificación rápida

```bash
# Sesiones nuevas con actividad
psql -d <base> -c "select id, username, fecha, ultima_actividad, duracion_seg, cerrada, ultima_ruta
                     from conexiones_historial order by id desc limit 10"
```

1. Inicia sesión y navega un par de páginas: `ultima_actividad` debe moverse y
   `ultima_ruta` apuntar a la última página/acción.
2. Pulsa *Salir*: la fila queda con `cerrada = true`, `cerrada_por = 'logout'`,
   `fecha_fin` y `duracion_seg`.
3. En el módulo, la sesión debe salir como **En línea** / **Cerrada** con su
   duración.

---

## 7. Rollback

Las columnas nuevas son opcionales: si se eliminan, el registro de accesos sigue
funcionando (el código tiene un respaldo que inserta la fila como antes). Para
desactivar el seguimiento sin tocar la base, basta con no llamar al latido; no
hay procesos en segundo plano.
