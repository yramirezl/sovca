-- =============================================================================
-- SOVCA · Normalizar las categorías de animales (rangos de edad)
-- -----------------------------------------------------------------------------
-- PROBLEMA
--   Las categorías se definen por sexo y rango de edad (mínima/máxima en
--   meses) y hasta ahora no se validaba nada. Cada fundo creó su propia lista
--   y quedaron cadenas rotas: en el fundo 002 «OVEJAS» va de 0 a 5000 meses y
--   convive con NOVILLA 10–14 y NOVILLA DE VIENTRE 14–24, y hay dos NOVILLO
--   (8–24 y 8–36). El mismo animal cae en varias categorías a la vez.
--
--   En toda la base: 31 cruces y 13 nombres repetidos.
--
-- QUÉ HACE ESTE SCRIPT (no borra nada: solo ajusta y desactiva)
--   PASO 1 · Las categorías personalizadas (`id_padre` > 0) que SE CRUZAN con
--            otra o que ENGLOBAN a otra vuelven al rango del catálogo general.
--            Se conserva el nombre que usa el fundo; solo se corrigen las
--            edades. Las copias que no molestan a nadie se dejan como están
--            (así no se abren huecos nuevos).
--   PASO 2 · Si quedan dos categorías idénticas en el mismo fundo (mismo sexo,
--            ganadería, nombre y rango), se desactiva la de mayor id.
--   PASO 3 · Se resuelven los cruces que quedan:
--            3a) dos categorías con el MISMO rango pero distinto nombre → se
--                conserva la de menor id, que es la que el sistema ya está
--                mostrando (el desempate de la categoría actual es id asc);
--            3b) una categoría propia del fundo (`id_padre` = 0) que se cruza
--                con DOS O MÁS categorías de la cadena general: no encaja en
--                la cadena, se desactiva.
--   PASO 4 · Informe final: no debe quedar ningún cruce.
--
--   Todo queda en `status = 0` / rangos ajustados: no se elimina ninguna fila
--   y se puede revisar (o volver atrás con status = 1).
--
-- RESULTADO ESPERADO (medido en la copia local con los datos de producción)
--   · categorías repetidas:      13 → 0
--   · pares que se cruzan:       31 → 0
--   · animales sin categoría:    48 → 48  (ningún animal pierde su categoría)
--   · se desactivan 12 filas: los duplicados de los pasos 2 y 3, más
--     «NOVILLA DE VIENTRE» (fundo 002) que cruzaba con dos pasos de la cadena.
--     Ninguna se borra: quedan en `status = 0` y se pueden reactivar.
--
-- Aplicar:
--   psql -d sovca -f scripts/migracion_categorias_normalizar.sql
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- Informe PREVIO
-- -----------------------------------------------------------------------------
SELECT 'ANTES · categorías repetidas (mismo fundo, sexo, nombre y rango)' AS paso,
       mfundos_id, sexo, nombre,
       edadminima || '–' || edadmaxima AS rango,
       count(*) AS veces,
       string_agg(id::text, ', ' ORDER BY id) AS ids
  FROM mcategorias
 WHERE status = 1
 GROUP BY mfundos_id, sexo, mtipoganaderias_id, nombre, edadminima, edadmaxima
HAVING count(*) > 1
 ORDER BY mfundos_id, sexo, nombre;

SELECT 'ANTES · pares de categorías que se cruzan' AS paso, count(*) AS pares
  FROM mcategorias a
  JOIN mcategorias b
    ON a.mfundos_id = b.mfundos_id
   AND a.sexo = b.sexo
   AND a.mtipoganaderias_id = b.mtipoganaderias_id
   AND a.id < b.id
   AND a.status = 1 AND b.status = 1
   AND a.edadminima < b.edadmaxima
   AND b.edadminima < a.edadmaxima;

-- -----------------------------------------------------------------------------
-- PASO 1 · Las copias que se cruzan (o que engloban a otra categoría) vuelven
--          al rango del catálogo general.
--          Se repite porque al corregir una copia puede quedar al descubierto
--          otro cruce; se detiene cuando ya no cambia nada.
-- -----------------------------------------------------------------------------
DO $$
DECLARE
    afectadas integer;
    vueltas   integer := 0;
BEGIN
    LOOP
        UPDATE mcategorias c
           SET edadminima = p.edadminima,
               edadmaxima = p.edadmaxima,
               updated    = now()
          FROM mcategorias p
         WHERE c.id_padre = p.id
           AND c.mfundos_id > 0
           AND p.mfundos_id = 0
           AND c.status = 1
           AND (c.edadminima <> p.edadminima OR c.edadmaxima <> p.edadmaxima)
           AND (
                /* se cruza con otra categoría del mismo fundo y sexo */
                EXISTS (SELECT 1
                          FROM mcategorias o
                         WHERE o.mfundos_id         = c.mfundos_id
                           AND o.sexo               = c.sexo
                           AND o.mtipoganaderias_id = c.mtipoganaderias_id
                           AND o.id <> c.id
                           AND o.status = 1
                           AND o.edadminima < c.edadmaxima
                           AND c.edadminima < o.edadmaxima)
                /* o engloba a otra (el caso «OVEJAS 0–5000») */
                OR EXISTS (SELECT 1
                             FROM mcategorias o
                            WHERE o.mfundos_id         = c.mfundos_id
                              AND o.sexo               = c.sexo
                              AND o.mtipoganaderias_id = c.mtipoganaderias_id
                              AND o.id <> c.id
                              AND o.status = 1
                              AND c.edadminima <= o.edadminima
                              AND c.edadmaxima >= o.edadmaxima)
           );

        GET DIAGNOSTICS afectadas = ROW_COUNT;
        vueltas := vueltas + 1;
        EXIT WHEN afectadas = 0 OR vueltas >= 5;
    END LOOP;
END $$;

-- -----------------------------------------------------------------------------
-- PASO 2 · Desactivar duplicados exactos (se conserva la de menor id)
-- -----------------------------------------------------------------------------
UPDATE mcategorias dup
   SET status  = 0,
       updated = now()
 WHERE dup.status = 1
   AND EXISTS (
        SELECT 1
          FROM mcategorias k
         WHERE k.mfundos_id          = dup.mfundos_id
           AND k.sexo                = dup.sexo
           AND k.mtipoganaderias_id  = dup.mtipoganaderias_id
           AND upper(btrim(k.nombre)) = upper(btrim(dup.nombre))
           AND k.edadminima          = dup.edadminima
           AND k.edadmaxima          = dup.edadmaxima
           AND k.status              = 1
           AND k.id < dup.id
   );

-- -----------------------------------------------------------------------------
-- PASO 3a · Mismo rango, distinto nombre: se conserva la de menor id
--           (es la que el sistema ya está mostrando; se puede renombrar con
--           Editar, que ahora avisa si el rango se cruza)
-- -----------------------------------------------------------------------------
UPDATE mcategorias dup
   SET status  = 0,
       updated = now()
 WHERE dup.status = 1
   AND dup.mfundos_id > 0
   AND EXISTS (
        SELECT 1
          FROM mcategorias k
         WHERE k.mfundos_id         = dup.mfundos_id
           AND k.sexo               = dup.sexo
           AND k.mtipoganaderias_id = dup.mtipoganaderias_id
           AND k.edadminima         = dup.edadminima
           AND k.edadmaxima         = dup.edadmaxima
           AND k.status             = 1
           AND k.id < dup.id
   );

-- -----------------------------------------------------------------------------
-- PASO 3b · Categoría propia que se cruza con dos o más de la cadena general:
--           no encaja (p. ej. «NOVILLA DE VIENTRE» 14–24 sobre MAUTA 7–18 y
--           NOVILLA 18–24). Se desactiva; el productor decide después.
-- -----------------------------------------------------------------------------
UPDATE mcategorias propia
   SET status  = 0,
       updated = now()
 WHERE propia.status = 1
   AND propia.mfundos_id > 0
   AND propia.id_padre = 0
   AND (
        SELECT count(*)
          FROM mcategorias otra
         WHERE otra.mfundos_id         = propia.mfundos_id
           AND otra.sexo               = propia.sexo
           AND otra.mtipoganaderias_id = propia.mtipoganaderias_id
           AND otra.id <> propia.id
           AND otra.status = 1
           AND otra.id_padre > 0
           AND otra.edadminima < propia.edadmaxima
           AND propia.edadminima < otra.edadmaxima
       ) >= 2;

-- -----------------------------------------------------------------------------
-- PASO 4 · Informe POSTERIOR
-- -----------------------------------------------------------------------------
SELECT 'DESPUES · categorías repetidas (debe quedar 0)' AS paso, count(*) AS grupos
  FROM (
        SELECT 1
          FROM mcategorias
         WHERE status = 1
         GROUP BY mfundos_id, sexo, mtipoganaderias_id, nombre, edadminima, edadmaxima
        HAVING count(*) > 1
       ) x;

SELECT 'DESPUES · categorías que aún se cruzan (las ajusta el productor)' AS paso,
       a.mfundos_id,
       CASE WHEN a.sexo = 1 THEN 'HEMBRA' ELSE 'MACHO' END AS sexo,
       a.nombre AS categoria_a, a.edadminima || '–' || a.edadmaxima AS rango_a,
       b.nombre AS categoria_b, b.edadminima || '–' || b.edadmaxima AS rango_b
  FROM mcategorias a
  JOIN mcategorias b
    ON a.mfundos_id = b.mfundos_id
   AND a.sexo = b.sexo
   AND a.mtipoganaderias_id = b.mtipoganaderias_id
   AND a.id < b.id
   AND a.status = 1 AND b.status = 1
   AND a.edadminima < b.edadmaxima
   AND b.edadminima < a.edadmaxima
 ORDER BY a.mfundos_id, a.sexo, a.edadminima;

-- -----------------------------------------------------------------------------
-- Informe complementario: meses que quedan sin ninguna categoría.
-- No es un cruce, pero deja animales sin categoría asignada; para cubrirlos el
-- administrador crea la categoría que falte.
-- -----------------------------------------------------------------------------
WITH cadena AS (
    SELECT mfundos_id, sexo, mtipoganaderias_id, nombre, edadminima, edadmaxima,
           LAG(edadmaxima) OVER (
               PARTITION BY mfundos_id, sexo, mtipoganaderias_id
               ORDER BY edadminima, edadmaxima
           ) AS fin_previo
      FROM mcategorias
     WHERE status = 1
)
SELECT 'DESPUES · huecos de edad sin categoría (informativo)' AS paso,
       mfundos_id,
       CASE WHEN sexo = 1 THEN 'HEMBRA' ELSE 'MACHO' END AS sexo,
       fin_previo AS sin_categoria_desde,
       edadminima AS siguiente_categoria_desde,
       nombre AS siguiente_categoria
  FROM cadena
 WHERE fin_previo IS NOT NULL
   AND edadminima > fin_previo
 ORDER BY mfundos_id, sexo, edadminima;

COMMIT;
