# Catálogo de estatus del animal (`manimales.estatus_animal`)

Los valores de `estatus_animal` estaban escritos a mano en cada formulario y
consulta del sistema, sin distinguir el sexo del animal: una hembra podía
quedar como «Donador» y el módulo de servicios buscaba donadoras con los
valores de los machos (por eso **nunca** encontraba una donadora).

Ahora el catálogo vive en la base de datos y las reglas en un solo archivo.

## Valores

| Valor | Nombre            | Sexo    | Significado |
|:-----:|-------------------|---------|-------------|
| 1     | ACTIVO            | ambos   | Animal normal del rebaño (valor genérico) |
| 2     | DONADOR ACTIVO    | macho   | Toro donante de semen habilitado (inseminación artificial) |
| 3     | DONADOR           | macho   | Toro donante de semen (sin habilitar) |
| 4     | DONADORA ACTIVO   | hembra  | Vaca donante de embriones habilitada (transferencia de embriones) |
| 5     | DONADORA          | hembra  | Vaca donante de embriones (sin habilitar) |

Reglas:

* **1** aplica a hembras y machos.
* **2 y 3** son válidos **solo para machos**.
* **4 y 5** son válidos **solo para hembras**.

## Instalación / migración

```bash
# 1) Catálogo + migración de los datos existentes (hembras 2→4 y 3→5)
psql -h 127.0.0.1 -U postgres -d sovca_qas -f scripts/migracion_estatus_animal.sql

# 2) Vistas que traducen el estatus a texto (v_list_animales_x_lotes,
#    v_list_animales_activos, v_consecutivo_peso, v_grupos_etarios)
php scripts/actualizar_vistas_estatus_animal.php            # --dry-run para revisar
```

Ambos pasos son **idempotentes** (se pueden volver a ejecutar).

## Dónde se aplica la regla

| Lugar | Regla |
|-------|-------|
| `lib/estatus_animal_helper.php` | **Fuente única**: `bovs_estatus_animal_lista()`, `..._sql()`, `..._donante_sql()`, `..._valido()`, `..._opciones_html()` |
| `views/animales/frm_registrar.php` y `frm_editar.php` | El select de **Estatus** se carga por sexo (AJAX `get_estatus_animal`) y se recalcula al cambiar el sexo |
| `controllers/manimales_controller.php` | Alta y edición: rechaza un estatus que no corresponda al sexo (mensaje con los valores permitidos). Búsquedas de madre (1/4/5) y padre (1/2/3) |
| `controllers/mservicios_controller.php` | Monta → macho **1**; inseminación → macho **2/3**; transferencia → donadora **4/5**; hembras receptoras **1/4/5** |
| `controllers/mnacimientos_controller.php` | Madre **1/4/5**, donadora **4/5**, padre (según tipo de servicio) **1** o **2/3** |
| `api/sync_controller.php` (app offline) | Mismas validaciones en el servidor antes de guardar un servicio |
| `app_offline/js/app.js` y `reportes.js` | Etiquetas y filtros de donadoras (4/5); versión de la PWA **4.3** |
| `data_json/data_manimales*.php`, `Reportes/reportes/data/animales.php`, `rcat.php`, `pdfprodlechera.php`, `mfundos`, `mreportes`, `movreb` | Etiquetas y conteos alineados al catálogo |

## Consultas útiles

```sql
-- Verificar que no haya estatus cruzados por sexo
SELECT estatus_animal, sexo, count(*) FROM manimales GROUP BY 1,2 ORDER BY 1,2;

-- Donadores y donadoras de un fundo
SELECT codigo_rgb_id_animal, nombre, sexo, estatus_animal
  FROM manimales
 WHERE mfundos_id = 30 AND status = 1 AND mestatus_id = 1
   AND estatus_animal IN (2,3,4,5)
 ORDER BY sexo, codigo_rgb_id_animal;
```

> Si en el futuro se agrega un estatus nuevo, se inserta en `mestatusanimales`
> con su sexo (`0` ambos, `1` hembra, `2` macho) y se actualiza
> `bovs_estatus_animal_base()` del helper: el resto del sistema lo toma de ahí.
