# Despliegue — Modo offline MULTIFUNDO (v4.2)

Guía para pasar a producción el cambio que permite que **un mismo usuario use la app
offline con varios de sus fundos** (antes el modo offline asumía un fundo por usuario)
y que **el administrador decida, usuario por usuario, quién puede usar el modo offline**.
Todo lo de este documento se probó en QAS (13-10-2026 multifundo, 16-10-2026 permiso
por usuario).

---

## 1. Qué cambia (para saber qué estamos moviendo)

| Capa | Antes | Ahora |
|---|---|---|
| Token del API | Atado a `(usuario, fundo)` | Token del **usuario**; cada petición indica el fundo y el servidor lo valida |
| Datos locales | Una sola base IndexedDB | **Una base por fundo** (`bovs_offline_f<id>`) + una global (sesión, credencial, fundos descargados) |
| Credencial del equipo | Bloqueaba activar un 2.º fundo del mismo usuario | Bloquea solo otro **usuario**; guarda la lista de fundos |
| Sincronización | Un fundo | Recorre **todos los fundos descargados**, con rangos de códigos y catálogos por fundo |
| Panel web de dispositivos | Una fila por (usuario, fundo) | **Una fila por equipo**, con sus fundos como etiquetas |
| Permiso de acceso offline | Todos los usuarios podían activar la app | **Del productor**: casilla "Acceso sin conexión" en registrar/editar (solo productores, solo el plan DIAMANTE la trae activa); sus usuarios la heredan; los roles Administrador/Super Usuario no usan offline |

Además se corrigió un **bug preexistente** de la librería de Active Record que rompía
*rutas del offline*: resolver códigos provisionales, alta de servicios y períodos de palpación.

**Permiso de acceso offline (nuevo).** El permiso es del **PRODUCTOR** (rol 2), dueño del
fundo, y se administra en *Usuarios → Registrar / Editar usuario* con la casilla
**"Acceso sin conexión (offline)"** (el plan **DIAMANTE** es el único que la trae activa;
con los demás planes nace apagada y el administrador puede activarla a mano).

* La casilla aparece **solo para el rol PRODUCTOR**.
* Los usuarios que trabajan en su fundo (**rol 3, ENCARGADO**) **heredan** el permiso: el
  servidor lo resuelve en vivo (si tiene varios fundos, se exige que *todos* sus
  productores lo tengan activo).
* Los roles **1 (Administrador)** y **4 (Super Usuario)** no usan la app sin conexión:
  solo administran (usuarios, dispositivos, web), así que quedan sin acceso.
* Toda cuenta nueva nace **sin acceso**.

Si la cuenta no tiene el permiso, al iniciar sesión (o al validarse el equipo) el servidor
responde `403 · sin_acceso_offline` y la app muestra *"Tu cuenta no tiene acceso al modo
sin conexión. Contacta al administrador del sistema."* El bloqueo vive en
`musuarios.acceso_offline`; la web y la app **con internet** siguen funcionando normal.

---

## 2. Antes de empezar

- [ ] **Respaldo de la base de datos** (obligatorio: se toca la tabla `api_tokens`).
- [ ] Respaldo (o tag en git) de los archivos que se van a reemplazar.
- [ ] Confirmar que hay una ventana corta: durante el despliegue, la sincronización
      offline puede fallar unos minutos; **no se pierde nada** (los cambios quedan en el
      `outbox` del equipo hasta que la sincronización funcione).
- [ ] Verificar PHP y PostgreSQL (los mismos que ya usa la app). La API ya exige:
      `php -v` (8.x) y extensión `pdo_pgsql`.

---

## 3. Archivos a desplegar

Copiar respetando rutas:

```
scripts/migracion_offline_multifundo.sql      (NUEVO — no se ejecuta desde la web)
scripts/migracion_acceso_offline.sql           (NUEVO — no se ejecuta desde la web)
scripts/migracion_acceso_offline_productores.sql (NUEVO — reasigna permisos, ver punto 4)

api/lib/ApiAuth.php
api/auth_controller.php
api/sync_controller.php
api/rangos_controller.php
api/catalogos_controller.php

php-activerecord/lib/Connection.php           (parche de librería — ver punto 5)

app_offline/index.html
app_offline/versiones.json
app_offline/service-worker.js
app_offline/css/estilos.css
app_offline/js/db.js
app_offline/js/api.js
app_offline/js/sync.js
app_offline/js/app.js
app_offline/js/credenciales.js

controllers/mdispositivos_controller.php
views/dispositivos/index.php
views/dispositivos/historico.php

controllers/musuarios_controller.php              (permiso de acceso offline + fix de get_roles)
views/usuarios/frm_registrar.php                  (casilla "Acceso sin conexión (offline)")
views/usuarios/frm_editar.php                     (misma casilla, precargada con el valor del usuario)
views/usuarios/index.php                          (columna y filtro "Modo offline")
data_json/data_musuarios.php                      (estado efectivo del permiso en cada fila)
lib/acceso_offline_helper.php                     (NUEVO — regla del permiso en un solo lugar)
lib/herencia_productor_helper.php                 (NUEVO — herencia productor → ayudantes)
lib/normativa_fundos_helper.php                   (NUEVO — reglas de vínculo usuario ↔ fundo)
scripts/normalizar_normativa_fundos.php           (NUEVO — audita y corrige los vínculos)
views/login.php                                   (aviso "Acceso suspendido" del ayudante heredado)
```

> `app_offline/version.php` calcula la versión con la fecha de modificación de esos
> archivos, así que **no hay que tocarlo**: al subir los `.js` cambia solo.
> Tampoco hay build ni `npm install`.

### Si se despliega por git

```bash
# En el servidor de producción
cd /ruta/al/sovca
git fetch --all
git checkout <rama-o-tag-del-cambio>
```

### Si se despliega por FTP/sincronizado

Subir **cada archivo de la lista** (ojo con `app_offline/js/*`, son los que más cambian).
Después, confirmar que no quedaron a medias:

```bash
php -l api/lib/ApiAuth.php
php -l api/auth_controller.php
php -l api/sync_controller.php
php -l api/rangos_controller.php
php -l api/catalogos_controller.php
php -l controllers/mdispositivos_controller.php
php -l controllers/musuarios_controller.php
php -l views/usuarios/frm_registrar.php
php -l views/usuarios/frm_editar.php
php -l views/usuarios/index.php
php -l data_json/data_musuarios.php
php -l lib/acceso_offline_helper.php
php -l lib/herencia_productor_helper.php
php -l lib/normativa_fundos_helper.php
php -l scripts/normalizar_normativa_fundos.php
php -l views/login.php
node --check app_offline/js/db.js   # si no hay node, abrir la app y revisar la consola
```

> `app_offline/service-worker.js` sube a `bovs-shell-v50` y `app_offline/versiones.json`
> a **4.2**: eso es lo que hace que los equipos de campo descarguen el JavaScript nuevo
> (sin ese cambio, un equipo con la app abierta seguiría usando el JS viejo desde la caché).

---

## 4. Migración de base de datos

```bash
cd /ruta/al/sovca
export PGPASSWORD='<clave-del-usuario-de-la-app>'
psql -h 127.0.0.1 -U <usuario> -d <base> -P pager=off -v ON_ERROR_STOP=1 \
     -f scripts/migracion_offline_multifundo.sql

psql -h 127.0.0.1 -U <usuario> -d <base> -P pager=off -v ON_ERROR_STOP=1 \
     -f scripts/migracion_acceso_offline.sql

psql -h 127.0.0.1 -U <usuario> -d <base> -P pager=off -v ON_ERROR_STOP=1 \
     -f scripts/migracion_acceso_offline_productores.sql
```

Qué hace la primera (es idempotente, se puede correr dos veces):

1. `api_tokens.mfundos_id` pasa a **NULLABLE** → NULL significa "token del usuario
   (multifundo)"; un valor significa "token de ese fundo" (clientes viejos).
2. Crea dos índices de apoyo (`mfundosxusuarios`, `usuario_dispositivo_offline`).
3. Documenta la columna con un `COMMENT`.

Qué hace la segunda (también idempotente en estructura):

1. Agrega `musuarios.acceso_offline boolean NOT NULL DEFAULT true`.
2. Índice parcial `musuarios_acceso_offline_idx` para encontrar rápido los bloqueados.
3. **No cambia el permiso de nadie**: todos los usuarios existentes quedan con el
   acceso **permitido**, así nadie pierde el acceso con el despliegue. El bloqueo se
   aplica después, usuario por usuario, desde la web.

Qué hace la tercera (**¡solo se corre UNA vez!**):

1. Deja el **valor por defecto en `false`** → todo usuario nuevo nace sin acceso.
2. Cambia el índice parcial a `musuarios_con_acceso_offline_idx` (`WHERE acceso_offline = true`).
3. **Reasigna el permiso de todas las cuentas**: queda activo **únicamente en los
   PRODUCTORES (rol 2) con plan DIAMANTE**; el resto de usuarios queda sin acceso.
   Efecto en los equipos de campo: los productores que **no** tengan plan DIAMANTE
   dejarán de poder sincronizar hasta que el administrador les habilite la casilla.

> ⚠️ La parte de datos de la tercera migración **reinicia los permisos**: si se vuelve a
> ejecutar después de haber habilitado cuentas a mano, esas asignaciones se pierden.

Verificación:

```sql
\d api_tokens                     -- mfundos_id debe salir SIN "not null"
SELECT count(*) FROM api_tokens WHERE mfundos_id IS NULL;   -- 0 al inicio

\d musuarios                      -- acceso_offline | boolean | not null | default false

-- Solo productores (2) con plan DIAMANTE deben quedar con acceso
SELECT mroles_id, mplanes_id, acceso_offline, count(*)
  FROM musuarios GROUP BY 1,2,3 ORDER BY 1,2,3;
```

> Nota: **no hay que tocar ninguna tabla de datos**. Ningún animal, peso ni servicio se
> mueve: lo único que cambia es **quién puede sincronizar qué fundo** y cómo se resuelve
> el fundo de cada petición.

---

## 5. Parche de la librería de Active Record (importante)

`php-activerecord/lib/Connection.php` declaraba `query($sql, &$values)` **por
referencia**. Cualquier llamada con un arreglo literal (`->query($sql, [$a, $b])`)
moría con *"Argument #2 ($values) could not be passed by reference"*.

- El cuerpo de la función **solo lee** el arreglo, así que se quitó el `&` en `query()`
  y en `query_and_fetch_one()`.
- Si alguna vez se **actualiza php-activerecord**, hay que volver a aplicar el cambio
  (o volver a comprobar que ninguna ruta use arreglos literales).

Verificación (debe imprimir dos veces el nombre de un usuario):

```bash
cd /ruta/al/sovca/controllers
php -r '
require_once "../config/conexion.php";
$c = Musuario::connection();
$v = [1];
echo $c->query("SELECT username FROM musuarios WHERE id = ?", $v)->fetch()["username"], "\n";
echo $c->query("SELECT username FROM musuarios WHERE id = ?", [1])->fetch()["username"], "\n";
'
```

---

## 6. Verificación del API (5 minutos)

Reemplazar `<USUARIO>` y `<CLAVE>` por una cuenta real (idealmente un productor
multifundo). El token se guarda en una variable.

```bash
API=http://<host>/sovca/api

TOKEN=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"action":"login","usuario":"<USUARIO>","password":"<CLAVE>","dispositivo":"Prueba despliegue"}' \
  $API/auth_controller.php | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["token"] ?? "";')

echo "token: ${TOKEN:0:10}..."
```

**a) El login debe traer la lista de fundos**

```bash
curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"action\":\"validar\",\"token\":\"$TOKEN\"}" $API/auth_controller.php \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); $u=$d["usuario"]??[];
            echo "fundos con acceso: ".count($u["fundos"]??[])."\n";
            echo "es_multifundo: ".json_encode($u["es_multifundo"]??null)."\n";'
```

Esperado: tantos fundos como tenga el usuario (si es administrador: todos los fundos activos).

**b) Vincular el equipo a dos de esos fundos** (usar dos ids reales; el primero es el
principal del usuario)

```bash
DISP=$(php -r 'printf("%s-%s-4%s-8%s-%s", str_repeat("a",8), str_repeat("b",4), str_repeat("c",3), str_repeat("d",3), str_repeat("e",12));')
curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"action\":\"vincular_dispositivo\",\"dispositivo_id\":\"$DISP\",\"nombre_dispositivo\":\"Prueba despliegue\",\"modo_offline\":\"gestion\",\"fundos\":[<FUNDO_A>,<FUNDO_B>],\"token\":\"$TOKEN\"}" \
  $API/auth_controller.php
```

Esperado: `"resultado":"ok"` y `"fundos"` con **una entrada por fundo**.

**c) Bajar animales de cada fundo (deben ser datos distintos)**

```bash
for F in <FUNDO_A> <FUNDO_B>; do
  curl -s -X POST -H "Content-Type: application/json" \
    -d "{\"action\":\"pull\",\"modulo\":\"animales\",\"fundo_id\":$F,\"dispositivo_id\":\"$DISP\",\"token\":\"$TOKEN\"}" \
    $API/sync_controller.php \
    | php -r '$d=json_decode(stream_get_contents(STDIN),true);
              echo "fundo ".($d["fundo_id"]??"?").": ".($d["cantidad"]??"?")." animales\n";'
done
```

**d) Un fundo NO vinculado debe rechazarse** (probar con un id que el usuario tenga
pero que no se haya vinculado en el paso b, o con un id inexistente):

```bash
curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"action\":\"pull\",\"modulo\":\"animales\",\"fundo_id\":999999,\"dispositivo_id\":\"$DISP\",\"token\":\"$TOKEN\"}" \
  $API/sync_controller.php
```

Esperado: `"codigo":"fundo_no_permitido"`.

**e) Limpiar la prueba**

```sql
DELETE FROM usuario_dispositivo_offline WHERE dispositivo_id = '<DISP>';
DELETE FROM api_tokens WHERE dispositivo = 'Prueba despliegue';
```

**f) Permiso de acceso offline del productor** (usar un productor DIAMANTE, un productor de
 otro plan y un usuario ayudante de cada uno)

```sql
-- referencia: quién tiene el permiso
SELECT id, username, mroles_id, mplanes_id, acceso_offline
  FROM musuarios WHERE mroles_id = 2 ORDER BY mplanes_id, id LIMIT 20;

-- apagar/encender el permiso de un productor
UPDATE musuarios SET acceso_offline = false WHERE username = '<PRODUCTOR>';
UPDATE musuarios SET acceso_offline = true  WHERE username = '<PRODUCTOR>';
```

```bash
curl -s -X POST -H "Content-Type: application/json" \
  -d '{"action":"login","usuario":"<PRODUCTOR>","password":"<CLAVE>","dispositivo":"Prueba permiso"}' \
  $API/auth_controller.php
```

Esperado: `resultado: ok` si es productor DIAMANTE (o si el administrador le activó la
casilla) y, si no tiene el permiso:

```json
{"resultado":"error","mensaje":"Tu cuenta no tiene acceso al modo sin conexión. Contacta al administrador del sistema.","codigo":"sin_acceso_offline"}
```

Casos a comprobar (probados en QAS el 13-10-2026):

| Cuenta | Resultado esperado |
|---|---|
| Productor con plan DIAMANTE | acceso permitido |
| Productor con otro plan (sin reparto manual) | `sin_acceso_offline` |
| Ayudante (rol 3) cuyo productor tiene el permiso | acceso permitido (lo hereda) |
| Ayudante cuyo productor **no** tiene el permiso | `sin_acceso_offline` |
| Administrador y Super Usuario | `sin_acceso_offline` (no usan offline) |

```sql
DELETE FROM api_tokens WHERE dispositivo = 'Prueba permiso';
```

Lo mismo debe pasar en `validar` y en `vincular_dispositivo` mientras el permiso esté
apagado: eso es lo que protege a un equipo que ya tenía la app abierta (en la siguiente
validación o sincronización se cierra la sesión y se muestra el aviso).

---

## 7. Verificación en la web (2 minutos)

1. Entrar como administrador → **Dispositivos**.
2. Un equipo vinculado a varios fundos debe verse como **una sola fila**, con sus fundos
   como etiquetas y el texto "N fundos en este equipo".
3. Ordenar por **Última sinc.** (debe ordenar por fecha real, no por texto).
4. Seleccionar un equipo de prueba y **Desvincular**: el diálogo debe listar los fundos
   afectados y el mensaje debe decir "…en sus N fundos".
5. **Histórico**: debe seguir mostrando una fila por (equipo, fundo) con la fecha de
   desvinculación.
6. **Usuarios → Registrar usuario**: la casilla **"Acceso sin conexión (offline)"** solo
   aparece al elegir rol **PRODUCTOR** (con otros roles queda oculta). Al elegir el plan
   **DIAMANTE** se marca sola; con otro plan queda apagada y el administrador puede
   marcarla a mano.
7. **Usuarios → Editar un productor**: la casilla debe reflejar lo guardado (marcada si
   tiene el permiso) y el cambio debe persistir al guardar. Para un usuario con rol
   ENCARGADO, Administrador o Super Usuario la casilla **no** se muestra.
8. En el equipo (app offline): iniciar sesión con una cuenta sin permiso →
   debe verse el mensaje "Tu cuenta no tiene acceso al modo sin conexión. Contacta al
   administrador del sistema." y el aviso modal **Sin acceso al modo sin conexión**; la
   pantalla principal **no** debe abrirse. Con el permiso activo, el acceso es normal.
9. **Listado de usuarios → columna "Modo offline"**: cada fila debe mostrar una etiqueta
   **Activo** (productor con el permiso), **Heredado** (usuario del fundo de un productor
   que lo tiene activo) o **Inactivo**, con el motivo en el tooltip. Arriba de la tabla, el
   control segmentado **Modo offline** (Todos / Con acceso / Sin acceso) filtra el listado
   y muestra el conteo de cada estado (en QAS: 93 / 67 / 26).
   El filtro es interno del listado: se aplica en memoria y **no viaja en la URL**, así que
   no hace falta un módulo ni un menú aparte para ver estas cuentas.

---

## 8. Qué pasará con los equipos que **ya** tienen la app offline abierta

Nada de esto requiere que el usuario haga algo especial:

1. El service worker está en `bovs-shell-v50`; al abrir la app se actualiza sola
   (`skipWaiting` + recarga) y borra la caché vieja. Si un equipo sigue mostrando el
   comportamiento viejo, cerrar la app del todo y volverla a abrir (o Ctrl+Shift+R).
2. La base local migra **automáticamente** de v9 a v10:
   - Los animales/pesos/servicios que ya estaban en el equipo se **mueven a la base del
     fundo** que estaba activo. **No se pierde nada**, ni siquiera lo que esté pendiente
     de sincronizar.
   - El fundo migrado queda registrado como "descargado en este equipo".
3. En la cabecera aparece el nombre del fundo y, si el usuario tiene más de un fundo
   descargado, el **selector de fundo** y el botón **⚙** para agregar o quitar fundos.
4. Los usuarios que todavía usen la app **4.0** (sin el selector) siguen funcionando:
   el servidor les resuelve el fundo principal, igual que antes.

> Si un equipo muestra "No hay un fundo activo en este dispositivo": significa que el
> usuario no tiene fundos activos asignados (`mfundosxusuarios` con `status = 1`) o que
> el fundo está inactivo. Revisar la asignación en la web; la app se recupera al volver
> a iniciar sesión.

---

## 9. Prueba funcional recomendada (productor real)

Con el usuario multifundo real (por ejemplo el caso de cuentas duplicadas ya unificadas):

- [ ] Activar modo offline eligiendo **2 fundos**.
- [ ] Verificar que el selector de fundo aparece y que al cambiar de fundo **cambian los
      animales listados** (no se mezclan).
- [ ] Registrar un animal en cada fundo (idealmente con códigos provisionales) y
      sincronizar: en cada fundo debe quedar con su código definitivo correcto
      (`SOVCA-0<fundo>-…`).
- [ ] En la web, comprobar que cada animal quedó en su fundo.
- [ ] Abrir la app sin conexión (modo avión) y confirmar que se ven los datos de ambos
      fundos.

---

## 10. Rollback

Si algo sale mal:

1. Restaurar los archivos anteriores (git checkout / copia de respaldo).
2. Para volver la migración atrás hay que **primero** eliminar los tokens multifundo,
   porque la columna no puede quedar `NOT NULL` con valores NULL:

```sql
-- OJO: cierra las sesiones offline de los equipos; tendrán que volver a iniciar sesión
DELETE FROM api_tokens WHERE mfundos_id IS NULL;

ALTER TABLE api_tokens ALTER COLUMN mfundos_id SET NOT NULL;
```

3. Los datos locales de los equipos ya migrados quedan en la base por fundo: la app 4.0
   no las leería, pero **no se borran** (se recuperan al volver a desplegar esta versión).
   Si hiciera falta, en el equipo se puede hacer "Olvidar este equipo" y volver a activar.

---

## 11. Checklist final

- [ ] Respaldo de base de datos hecho
- [ ] Archivos subidos completos (API + app_offline + dispositivo/views)
- [ ] `php -l` sin errores en los archivos PHP tocados
- [ ] Migración `.sql` ejecutada (`mfundos_id` nullable)
- [ ] Migración `scripts/migracion_acceso_offline.sql` ejecutada (`musuarios.acceso_offline`)
- [ ] Migración `scripts/migracion_acceso_offline_productores.sql` ejecutada (una sola vez) y revisada la lista de productores que quedan con acceso
- [ ] Parche de `php-activerecord/lib/Connection.php` aplicado y verificado
- [ ] Login del API devuelve `fundos[]`
- [ ] `vincular_dispositivo` con 2 fundos y `pull` independiente por fundo
- [ ] Rechazo correcto de un fundo no permitido
- [ ] Permiso de acceso offline: casilla visible solo para PRODUCTOR (DIAMANTE la trae activa), herencia del rol 3 verificada y login del API bloqueado con `sin_acceso_offline` cuando no tiene permiso
- [ ] Usuarios: columna "Modo offline" (Activo / Heredado / Inactivo) y filtro segmentado "Todos / Con acceso / Sin acceso" con sus contadores, funcionando y sin parámetros en la URL
- [ ] Panel web: un equipo = una fila, con sus fundos; desvincular afecta a todos
- [ ] Prueba funcional con un productor multifundo real
- [ ] Equipos de campo: abrieron la app actualizada (v4.2) y migraron sus datos
- [ ] Datos de prueba del despliegue borrados (dispositivos, tokens y permisos de prueba)

---

## 12. Corrección incluida: selector de Rol vacío en Editar usuario

`controllers/musuarios_controller.php` tenía la acción `get_roles` **sin `break`**: caía en
`get_roles_id`, consultaba con un `rol_id` vacío y devolvía el selector **Rol vacío** (y un
error 500) al editar usuarios sin fundo asociado (por ejemplo un productor recién creado).
Se corrigió agregando la respuesta propia de `get_roles`. Sin este arreglo, un productor sin
fundo no se podía editar y, por lo tanto, tampoco se le podía asignar el acceso offline.

---

## 13. Corrección incluida: editar usuarios INACTIVOS

La vista `vusuarios` trae **`WHERE a.status = 1`**, así que cualquier consulta del módulo de
usuarios que pasara por ella devolvía **nada** para las cuentas inactivas. Se corrigieron los
dos puntos afectados en `controllers/musuarios_controller.php`:

1. **Acción `search`** (la que llena el formulario de edición): consultaba
   `Musuarios ... WHERE status=1 and id = …`, por lo que al editar un usuario inactivo el
   formulario salía **completamente en blanco**. Ahora consulta por `id` sin filtrar por
   estado y devuelve además `status`; si la cuenta no existe, responde un JSON con `error`
   en vez de una respuesta vacía (y el formulario avisa y vuelve al listado).
2. **Acción `edit`** (guardado): para el rol ENCARGADO resolvía el fundo con la vista, así
   que con una cuenta inactiva el fundo quedaba vacío y la consulta del plan terminaba en
   `WHERE mfundos_id= and mplanes_id is not Null` → **PDOException y error 500** (el guardado
   no se completaba). Ahora la consulta del plan solo se ejecuta si `$fundo > 0` y `$plan_x`
   se inicializa, de modo que la edición de una cuenta inactiva se guarda con normalidad.

Además, al abrir la edición de una cuenta inactiva el formulario muestra un aviso
**“Cuenta inactiva”** recordando que debe reactivarse con el botón **Reactivar** del listado
(el formulario no cambia el estado).

> Ojo: un PRODUCTOR sin fila en `mplanesxfundo` sigue exigiendo plan, tipo de suscripción y
> fecha de activación al guardar (regla preexistente, no tiene que ver con el estado de la
> cuenta): esos tres campos están en el propio formulario.

---

## 14. Herencia: productor → usuarios ayudantes (rol 3)

Regla: **todo lo que le pasa al productor lo heredan los usuarios ayudantes de su fundo**.
Está centralizada en `lib/herencia_productor_helper.php` y se aplica en tres puntos.

### a) Al inhabilitar (botón *Inactivar* del listado)

Cuando la cuenta inhabilitada es un PRODUCTOR (rol 2), sus ayudantes (rol 3) se inhabilitan
también, pero con dos salvaguardas:

* **Fundo compartido**: si el fundo queda con otro productor activo, los ayudantes siguen
  trabajando (no se toca a nadie).
* **Ayudante multifundo**: si al ayudante le queda algún fundo con productor activo,
  conserva su acceso.

> Corrección importante: antes esta cascada se hacía con la vista `vusuarios` (que trae
> `WHERE status = 1`), así que inactivaba a **todos** los usuarios del fundo (incluidos
> otros productores del mismo fundo) y **solo del último fundo leído**: un productor con
> varios fundos dejaba ayudantes con acceso. Ahora se recorren todos sus fundos y solo sus
> ayudantes.

### b) Al reactivar (botón *Reactivar*)

Vuelven los ayudantes que se habían inhabilitado **junto con el productor**: se reconocen
por la firma que deja la cascada (mismo `user_modify` y misma fecha `updated` que tenía el
productor), así no se reactivan cuentas inhabilitadas por otros motivos. El mensaje de
confirmación indica qué usuarios volvieron.

### c) Red de seguridad en el login

Aunque los datos queden desincronizados, un AYUDANTE no puede entrar si **todos** los fundos
donde trabaja se quedaron sin productor activo: el login lo detecta y responde
`../views/login?stat=sin_productor` con el aviso *“Acceso suspendido. El productor dueño
del fundo donde trabajas está inactivo…”*. Si comparte trabajo con un fundo que sí tiene
dueño activo, entra normalmente.

### d) Visibilidad en el listado

La columna **“Modo offline”** ahora muestra el estado REAL:

| Situación | Se muestra |
|---|---|
| Productor activo con el permiso | `Activo` |
| Ayudante de un productor activo con el permiso | `Heredado` |
| Ayudante cuyo productor está **inactivo** | `Inactivo` — “El productor X está inactivo: sin un productor activo en el fundo no hay acceso sin conexión que heredar” |
| Cuenta inhabilitada (cualquier rol) | `Inactivo` — “La cuenta está inhabilitada…” |

Por eso los contadores del filtro bajaron (en QAS: de 67 a 21 cuentas “con acceso”): antes
se contaban como activas las cuentas **inhabilitadas** cuyo flag estaba encendido.

### Pruebas realizadas (datos temporales, ya eliminados)

| Caso | Resultado |
|---|---|
| Inhabilitar productor con un solo fundo | Productor `0` y **ambos ayudantes**: el del fundo `0`, el multifundo conserva `1` |
| Reactivar el productor | Vuelve el ayudante heredado (`1`) sin revivir otras cuentas |
| Fundo compartido (otro productor activo) | Al inhabilitar a uno, los ayudantes **no** se tocan |
| Login del ayudante sin productor activo | `stat=sin_productor` + “Acceso suspendido”, sin sesión |

---

## 15. Normativa de vínculos usuario ↔ fundo

Modalidad vigente:

1. Un **PRODUCTOR** (rol 2) puede tener **varios fundos** (multifundo).
2. Un **FUNDO** solo puede tener **un productor**.
3. Un fundo puede tener **varios AYUDANTES** (rol 3).
4. Un **AYUDANTE** solo puede pertenecer a **un fundo**.

### Dónde se aplica

* `lib/normativa_fundos_helper.php` — valida (`norm_validar_vinculo`) y audita
  (`norm_auditar`) los vínculos. Decide además qué productor conserva un fundo
  (`norm_productor_a_conservar`).
* `controllers/musuarios_controller.php` (alta de usuario) — al crear un AYUDANTE se valida el
  vínculo antes de guardarlo y se usa el **id del usuario recién creado** (antes se buscaba
  con un `max(id)` por fecha de creación, que podía enlazar a otra cuenta).
* `lib/multifundo_helper.php` (`mf_unificar_fundos`) — al unificar cuentas, cada fundo se
  valida antes de asignarlo al destino; los usuarios que pierden el acceso en la misma
  operación (los orígenes) se excluyen de la validación, de modo que la consolidación
  legítima sigue funcionando.
* `scripts/normalizar_normativa_fundos.php` — informe (por defecto) y corrección (`--aplicar`).

```bash
# Informe (no modifica nada)
php scripts/normalizar_normativa_fundos.php

# Corregir
php scripts/normalizar_normativa_fundos.php --aplicar
```

Al corregir, un fundo con varios productores se queda con su `user_propietario` (o con el
primer productor activo) y se **desactivan** (`mfundosxusuarios.status = 0`) los demás
enlaces; un ayudante con varios fundos conserva el de menor id. No se borra ninguna fila y
no se toca ningún dato de producción.

### Corrección aplicada en QAS

Se encontraron 2 fundos con más de un productor (y ningún ayudante en varios fundos):

| Fundo | Productores | Se conserva | Enlaces desactivados |
|---|---|---|---|
| 2 · LOS POTRILLOS | USUARIODEMO (activo, propietario), FCUERNAVACA, PALOBLANCO | USUARIODEMO | FCUERNAVACA, PALOBLANCO |
| 9 · Abrego | RANCHOVIRGEN (propietario), FINCASANTEFE757 | RANCHOVIRGEN | FINCASANTEFE757 |

Tras la corrección, el informe queda en **“ninguno”** en los dos controles.

---

## 16. Migración de fundos (unificación de cuentas)

Es el mecanismo con el que un productor que tiene sus fundos repartidos en varias cuentas los
consolida en una sola. El criterio de siempre: **los fundos pasan a la cuenta destino y las
cuentas origen quedan inhabilitadas** (opción “Desactivar cuentas origen sin fundos”, activada
por defecto) y **pierden el acceso** a esos fundos. No se borra ningún dato: solo cambia el
acceso.

Con la normativa y la herencia ya implementadas, el flujo queda así:

| Paso | Resultado |
|---|---|
| Traslado de los fundos al destino | El destino queda como **único productor** de cada fundo (una fila por fundo) |
| Destino | Queda marcado como **multifundo** con su `max_fundos` ajustado |
| Cuenta origen | `status = 0` y sus enlaces a esos fundos en `status = 0` (pierde el derecho y el acceso) |
| Usuarios ayudantes del fundo | Conservan su fundo y **heredan al nuevo productor** (permiso offline incluido) |
| Auditoría posterior | `normalizar_normativa_fundos.php` → “ninguno ✔” |

### Integraciones añadidas

* **La normativa no bloquea la consolidación**: la validación “un fundo = un productor”
  excluye a los usuarios que están por perder el acceso en la misma operación (los orígenes).
* **Herencia**: al inhabilitar una cuenta origen se guarda la “firma” (`user_modify` +
  `updated`) y se ejecuta la cascada sobre sus ayudantes, de modo que el comportamiento sea
  el mismo que al inhabilitar desde el listado de usuarios (y que una futura reactivación
  pueda devolverlos).
* **UI**: el diálogo de confirmación explica el criterio y el mensaje final informa cuántas
  cuentas origen se inhabilitaron y qué ayudantes heredaron.

### Prueba realizada (datos temporales, ya eliminados)

Escenario: productor destino con su fundo; productor origen con **dos** fundos (uno de ellos
con ayudante) y un ayudante en cada fundo.

| Comprobación | Resultado |
|---|---|
| Fundos unificados | 2 ✔ |
| Destino | 3 fundos, `es_multifundo = 1`, `max_fundos` ajustado ✔ |
| Origen | `status = 0`, enlaces desactivados, sin fundos ✔ |
| Un productor por fundo tras la migración | Sí (destino + ayudante por fundo) ✔ |
| Ayudantes | Siguen activos y su permiso offline pasa a “Heredado” del nuevo productor ✔ |
| Normativa posterior | 0 violaciones ✔ |
| Reactivar el origen | Vuelve la cuenta, sin fundos, sin revivir a nadie por error ✔ |
