# N° de arete — formato y unicidad por fundo

## Formato canónico

El N° de arete es **números + letras + guiones**, en el orden que prefiera el
productor (`9321A`, `A9321`, `322-JR`, `SN-2-1`). El sistema solo lo deja en esta
forma:

- Todo en **MAYÚSCULAS**; los acentos se convierten a su letra (`Ñ→N`, `Á→A`).
- Cada **símbolo o espacio** se reemplaza por un **guion**.
- **Nunca dos guiones seguidos**.
- **Sin guion al inicio** (al final sí se permite: es el ajuste de duplicados).
- **Máximo 15 caracteres** (si se pasa, se recorta y se avisa en la migración).

Ejemplos: ` 322  jr ` → `322-JR` · `SN 2-1` → `SN-2-1` ·
`NRO. PIERNA: 22/25` → `NRO-PIERNA-22-2`.

Se aplica en la web (al salir del campo y al guardar), en la app offline (al salir
del campo, al validar y al guardar) y en el servidor (API e importación), así que
no depende del navegador.

## Regla de unicidad

Un animal **activo** de un fundo no puede tener el mismo N° de arete que otro
animal activo del mismo fundo.

- Comparación **canónica** (`322 JR` y `322-JR` son el mismo arete).
- Solo contra animales con `status = 1`: un animal dado de baja libera su arete.
- El arete vacío nunca bloquea y la comparación es **dentro del fundo**.
- En la base existe además el índice único
  `manimales_fundo_arete_normalizado_unico_idx` sobre
  `(mfundos_id, upper(btrim(nro_arete)))` con `status = 1 AND mestatus_id = 1`:
es la última red de seguridad ante escrituras concurrentes.

Implementación única: **`lib/arete_helper.php`**

| Función | Para qué |
|---|---|
| `bovs_arete_formatear($valor)` | formato canónico (máx. 15) |
| `bovs_arete_variante($base, $n)` | variante para resolver un duplicado (`-`, `-2`, `-3`…) |
| `bovs_arete_normalizar($valor)` | alias histórico de `formatear` |
| `bovs_arete_existente($fundo, $arete, $opciones)` | animal activo que ya lo usa (`excluir_id`, `excluir_codigo`) |
| `bovs_arete_mensaje($existente)` | texto para el usuario con código y nombre del animal |
| `bovs_arete_duplicados($fundo)` | auditoría: aretes repetidos exactos |
| `bovs_arete_base_compartida($fundo)` | auditoría: "332" y "332 JR" |

## Dónde se valida

| Módulo | Archivo | Momento |
|---|---|---|
| Alta de animal | `controllers/manimales_controller.php` (`add`) | al guardar |
| Ficha / alta desde nacimiento | `controllers/manimales_controller.php` (`save_ficha`) | al guardar |
| Edición de animal | `controllers/manimales_controller.php` (`edit`) | al guardar |
| Aviso en el formulario | `controllers/manimales_controller.php` (`search_nro_arete`) | al salir del campo |
| Importación Excel | `lib/importer/AnimalesImporter.php` | por fila, contra el fundo y contra el propio CSV |
| Ficha de la cría | `controllers/mnacimientos_controller.php` | al registrar el parto |
| Migración entre fundos | `controllers/migracion_controller.php` | antes de copiar, contra el fundo destino |
| App offline (dispositivo) | `app_offline/js/animales.js` (`existeArete`) | al escribir y al guardar |
| API de sincronización | `api/catalogos_controller.php` (`validar_arete`) y `api/sync_controller.php` (`validar_campos_unicos`) | al validar y al recibir el push |

### Corrección aplicada en la edición

La edición (y la validación del formulario) excluía el propio animal comparando
**por valor de arete**:

```sql
nro_arete NOT IN (SELECT nro_arete FROM manimales
                   WHERE nro_arete = 'X' AND codigo_rgb_id_animal = 'SOVCA-...')
```

Si otro animal ya usaba ese arete, el subselect devolvía el mismo texto y la
condición descartaba **todas** las filas: la edición nunca detectaba el duplicado.
Ahora se excluye **por `id` y por código**, que es lo que corresponde.

## Lo que NO se bloquea (a propósito)

Aretes que comparten el número inicial pero no son iguales: `332` y `332 JR`,
`011` y `011MACHO`. Son dos chapetas físicas distintas; bloquearlas impediría
registrar animales reales. El sistema los **informa** en la auditoría para que el
productor decida.

## Corregir duplicados existentes

Los duplicados los corrige el **productor**, no el sistema:

1. Corregir el N° de arete desde la ficha del animal (Editar), o
2. dar de baja uno de los dos registros en **Desincorporaciones** (la baja libera el arete).

### Migración de los aretes ya cargados

```bash
php scripts/normalizar_aretes.php                          # informe (no escribe)
php scripts/normalizar_aretes.php --aplicar                # aplica
php scripts/normalizar_aretes.php --fundo=17 --aplicar     # un solo fundo
```

Deja **todos** los aretes en el formato canónico y, si al formatearlos aparecen
duplicados entre animales activos del mismo fundo, conserva el arete del **más
antiguo** y a los demás les agrega un guion final (`322JR` → `322JR-`); si ese ya
está usado, `-2`, `-3`… También informa los aretes que hubo que recortar a 15
caracteres. Las filas modificadas suben `version`, así los equipos offline reciben
el arete nuevo en la próxima sincronización.

Aplicado en `bovs_server` el 02-11-2026: **388 aretes normalizados, 3 duplicados
ajustados** (fundo 17: `SN-1`, `SN-2`, `SN-3`).

## Auditoría (solo lectura)

```bash
php scripts/auditar_aretes_duplicados.php                  # todos los fundos
php scripts/auditar_aretes_duplicados.php --fundo=63
php scripts/auditar_aretes_duplicados.php --fundo=63 --solo=exactos
php scripts/auditar_aretes_duplicados.php --json           # para soporte / hoja de cálculo
```

Informa dos bloques: **duplicados exactos** (lo que el sistema ya bloquea) y
**aretes que comparten número inicial**. No modifica nada.

## Por qué la regla se aplica en la aplicación

La unicidad depende de `status` (una baja libera el arete) y de un formato
(mayúsculas, guiones, 15 caracteres). El índice único parcial de la base cubre el
caso concurrente con `upper(btrim())`, y la capa de aplicación aplica además el
formato canónico completo mediante una sola función compartida por web, API y app
offline.
