# Auditoría: criterio de «animal activo» en SOVCA

Fecha: 2026-10-28 · Fase 7 del port desde BOVS.

## El problema

Cada pantalla contaba los animales a su manera. En el mismo fundo el menú
lateral podía decir 199, el selector de fundos otra cifra y la vista de animales
activos una tercera, porque cada consulta filtraba cosas distintas:

| Lugar | Filtro que tenía |
|---|---|
| `views/layouts/menu_lateral_izquierdo.php` | `status = 1` |
| `views/layouts/fundo_switcher.php` (2 sitios) | `status = 1` |
| `controllers/mfundos_controller.php` (listado) | `status = 1` |
| `controllers/mfundos_controller.php` (ficha del fundo) | `status = 1` |
| `v_list_animales_activos` (vista SQL) | `estatus_animal <> 3` |
| `lib/multifundo_helper.php` (cupo de registros) | `manimalxfundos.status = 1` |

`status = 1` solo dice «no está eliminado»: seguía contando vendidos, muertos,
descartados y extraviados.

## El criterio único

Igual que en BOVS, en `lib/animales_conteo_helper.php`:

```sql
a.status = 1              -- no eliminado
AND a.mestatus_id = 1     -- vivo en el hato (ACTIVO)
AND a.estatus_animal IN (1, 2, 4)  -- ACTIVO, DONADOR ACTIVO, DONADORA ACTIVO
```

No cuentan DONADOR (3) ni DONADORA (5) sin habilitar, ni DESCARTE (2),
MUERTO (3), VENDIDO (4), EXTRAVIADO (5) o MIGRADO (7) del estado del ganado.

## Dónde se aplicó

- Menú lateral, selector de fundos y listados de fundos: la misma condición SQL.
- Ficha del fundo (tarjetas de hembras, machos, donadores): ídem.
- `lib/multifundo_helper.php`: el cupo de registros del plan se mide con
  `bovs_contar_animales_activos()`/`bovs_animal_activo_sql()`; antes usaba
  `manimalxfundos`, que contaba también a los animales muertos.
- Vista `v_list_animales_activos`: pasa a `estatus_animal IN (1, 2, 4)` y
  muestra la descripción de DONADOR / DONADORA ACTIVO / DONADORA, que antes
  salían en blanco. Ver `scripts/migracion_vista_animales_activos.sql`.

No se tocaron:

- `data_json/data_dashboard_categorias.php` y los gráficos del tablero (en BOVS
  tampoco cambiaron: agrupan el hato registrado, no el hato activo).
- Los conteos de ocupación por potrero (`estatus_animal NOT IN (3,5)`): miden
  animales presentes en el potrero, otro concepto.
- `data_json/data_dashboard_modulos.php`: solo comprueba si el módulo tiene
  datos (> 0).

## Impacto en las cifras

Las cifras **bajan**, y es lo correcto: antes incluían animales que ya no están
en el hato. Ejemplo en la base local (`sovca`):

| Fundo | Antes (`status = 1`) | Ahora (activos) |
|---|---|---|
| CRIADERO SOFIA DOLORES (id 5) | 199 | 77 |
| USUARIO DEMO SOVCA (id 2) | 6 | 4 |
| HATO NUEVO | 8 | 8 |

Total de la vista `v_list_animales_activos`: 92 filas (antes 115).

Como el cupo de registros del plan usa el mismo criterio, una cuenta que
estuviera al límite **recupera** cupo en lugar de perderlo.

## Cómo verificar

```sql
-- Debe dar el mismo número en los tres casos
SELECT count(*) FROM manimales a
 WHERE a.mfundos_id = 5 AND a.status = 1 AND a.mestatus_id = 1
   AND a.estatus_animal IN (1, 2, 4);

SELECT count(*) FROM v_list_animales_activos WHERE mfundos_id = 5;
```

Y en la interfaz: el número del selector de fundos debe coincidir con el total
del listado de animales y con el que muestra la ficha del fundo.
