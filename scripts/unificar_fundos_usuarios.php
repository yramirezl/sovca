<?php
/**
 * scripts/unificar_fundos_usuarios.php
 * =========================================================================
 * PLAN DE MIGRACIÓN — Unificación de usuarios con varios fundos
 * =========================================================================
 *
 * PROBLEMA
 *   Cuando una misma persona necesitaba administrar varios fundos, el sistema
 *   la obligaba a crear UN USUARIO POR FUNDO (mismo email, distinto username).
 *   Ejemplo real: enelay29@gmail.com -> DONVILLABOVINOS (fundo DON VILLA
 *   BOVINOS) y DONVILLABUFALOS (fundo EL TOCO).
 *
 * OBJETIVO
 *   Que un único usuario (multifundo) tenga acceso a todos esos fundos y se
 *   puedan retirar las cuentas sobrantes.
 *
 * QUÉ HACE LA UNIFICACIÓN
 *   1. Da acceso del usuario DESTINO a cada fundo seleccionado
 *      (mfundosxusuarios: estado 1).
 *   2. Marca al destino como multifundo (es_multifundo = true) y sube su
 *      max_fundos al número de fundos que administrará.
 *   3. Retira el acceso de las cuentas ORIGEN a esos fundos.
 *   4. (Opcional) Desactiva las cuentas ORIGEN que quedan sin ningún fundo.
 *   5. (Opcional) Transfiere la propiedad de los fundos al destino
 *      (mfundos.user_propietario).
 *
 * QUÉ **NO** HACE (nada de esto se toca)
 *   - NO mueve animales, controles, producciones ni ningún dato de negocio:
 *     todo sigue colgado de su fundo (manimales.mfundos_id = fundo).
 *   - NO borra registros. Solo cambia quién tiene acceso al fundo.
 *
 * REVERSIBILIDAD
 *   Volver atrás es manual y sencillo: reactivar el usuario (status = 1),
 *   devolverle el acceso al fundo (mfundosxusuarios.status = 1), devolver la
 *   propiedad (mfundos.user_propietario) y bajar es_multifundo/max_fundos.
 *
 * USO (desde la raíz del proyecto)
 *   # 1) Ver el plan y los candidatos detectados (no modifica nada)
 *   php scripts/unificar_fundos_usuarios.php --plan
 *   php scripts/unificar_fundos_usuarios.php
 *
 *   # 2) Simular una unificación concreta (no modifica nada)
 *   php scripts/unificar_fundos_usuarios.php --objetivo=79 --fusionar=80
 *
 *   # 3) Aplicar
 *   php scripts/unificar_fundos_usuarios.php --objetivo=79 --fusionar=80 --aplicar
 *
 *   # Con opciones:
 *   php scripts/unificar_fundos_usuarios.php --objetivo=79 --fusionar=80 \
 *        --aplicar --desactivar --propietario
 *
 * OPCIONES
 *   --objetivo=ID|USERNAME   Usuario que conserva la cuenta (destino).
 *   --fusionar=ID,USERNAME   Usuario(s) cuyos fundos se pasan al destino.
 *   --aplicar                Ejecuta los cambios (sin esto solo simula).
 *   --desactivar             Inhabilita las cuentas origen que queden sin fundos.
 *   --propietario            Deja al destino como propietario de los fundos.
 *   --reajustar-plan=ID|USER Deja TODOS los fundos de esa cuenta en su plan
 *                            de mayor cobertura (para cuentas ya unificadas).
 *   --plan                   Muestra este plan y sale.
 *   --help
 *
 * RECOMENDACIÓN ANTES DE APLICAR EN PRODUCCIÓN
 *   - Respaldo de la base de datos.
 *   - Ejecutar primero SIN --aplicar y revisar el resumen.
 *   - Hacerlo fuera del horario de uso: el usuario origen no debe estar
 *     trabajando (su sesión abierta seguirá activa hasta que cierre sesión).
 *   - Verificar luego con el propio script (vuelve a mostrar el estado).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);

// ─────────────────────────────  Argumentos  ─────────────────────────────
$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

$PLAN = <<<TXT

═══════════════════════════════════════════════════════════════════════════
 PLAN DE MIGRACIÓN — Unificación de usuarios con varios fundos
═══════════════════════════════════════════════════════════════════════════
 1. DETECTAR   grupos de usuarios ACTIVOS con el mismo email (misma persona
               que tuvo que crear un usuario por fundo).
 2. ELEGIR     la cuenta que se conserva (destino) y las cuentas a fusionar.
 3. SIMULAR    (sin --aplicar) para revisar exactamente qué cambiará.
 4. APLICAR    con --aplicar: el destino recibe acceso a todos los fundos,
               se marca multifundo y las cuentas origen pierden el acceso.
 5. OPCIONAL   --desactivar  : da de baja las cuentas origen que queden vacías.
               --propietario : el destino queda como propietario de los fundos.
 6. VERIFICAR  volver a ejecutar el script: el grupo ya no aparece y el destino
               muestra todos los fundos.
═══════════════════════════════════════════════════════════════════════════
 No se mueve ni se borra ningún dato de negocio (animales, controles, etc.):
 los registros siguen perteneciendo a su fundo; solo cambia el ACCESO.

TXT;

if (isset($opts['help']) || isset($opts['plan'])) {
    echo $PLAN;
    echo "Uso:\n";
    echo "  php scripts/unificar_fundos_usuarios.php [--objetivo=ID|USERNAME]\n";
    echo "                                          [--fusionar=ID,USERNAME,...]\n";
    echo "                                          [--aplicar] [--desactivar] [--propietario]\n";
    exit(0);
}

// ─────────────────── Bootstrap de ActiveRecord + helper ───────────────────
// Las rutas de config/conexion.php son relativas a /controllers, por eso se
// cambia el CWD durante la carga (así se reutiliza el MISMO motor que usa la
// aplicación: mf_unificar_fundos()).
chdir($ROOT . '/controllers');
require_once $ROOT . '/config/conexion.php';
require_once $ROOT . '/lib/multifundo_helper.php';

if (!function_exists('mf_unificar_fundos')) {
    fwrite(STDERR, "ERROR: no se pudo cargar lib/multifundo_helper.php\n");
    exit(1);
}

/** Resuelve un usuario dado su id o su username. */
function uf_usuario($valor)
{
    $v = trim((string) $valor);
    if ($v === '') {
        return null;
    }
    if (ctype_digit($v)) {
        return _mf_q1("SELECT id, username, name, email, status, es_multifundo, max_fundos
                         FROM musuarios WHERE id = " . (int) $v . " LIMIT 1");
    }
    $safe = addslashes($v);
    return _mf_q1("SELECT id, username, name, email, status, es_multifundo, max_fundos
                     FROM musuarios WHERE upper(username) = upper('$safe') LIMIT 1");
}

function uf_fundos_txt($usuario_id)
{
    $rows = mf_fundos_para_unificar($usuario_id);
    if (!$rows) {
        return '(sin fundos)';
    }
    $out = array();
    foreach ($rows as $f) {
        $gan = ((int) $f['mtipoganaderias_id'] === 1) ? 'ovina'
             : (((int) $f['mtipoganaderias_id'] === 2) ? 'caprina' : 'ovina y caprina');
        $out[] = $f['nombre'] . " (id {$f['mfundos_id']}, $gan, {$f['animales']} animales)";
    }
    return implode(' | ', $out);
}

$aplicar     = isset($opts['aplicar']);
$desactivar  = isset($opts['desactivar']);
$propiedad   = isset($opts['propietario']);
$objetivoRaw = isset($opts['objetivo']) ? $opts['objetivo'] : '';
$fusionarRaw = isset($opts['fusionar']) ? $opts['fusionar'] : '';

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo ' Unificación de usuarios con varios fundos' . ($aplicar ? '   [APLICAR]' : '   [SIMULACIÓN]') . "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";

// ─────────────────────  1) Candidatos detectados  ─────────────────────
$grupos = mf_grupos_usuarios_duplicados();

echo "\n── Grupos detectados (usuarios ACTIVOS con el mismo email) ────────────────\n";
if (!$grupos) {
    echo "   (ninguno)\n";
} else {
    foreach ($grupos as $g) {
        echo "   • {$g['email']}\n";
        foreach ($g['usuarios'] as $u) {
            printf("       id %-6d %-22s %-26s multifundo=%s  fundos: %s\n",
                $u['id'], $u['username'], $u['name'],
                ($u['es_multifundo'] ? 'sí' : 'no'), uf_fundos_txt($u['id']));
        }
    }
}

// ─────────────────  1 bis) Reajustar el plan de una cuenta  ─────────────────
// Sirve para las cuentas que se unificaron con la versión anterior del
// proceso: quedaron con fundos en planes distintos aunque el plan de la
// ficha fuera mayor. Deja TODOS sus fundos en el plan de mayor cobertura.
$reajustarRaw = isset($opts['reajustar-plan']) ? $opts['reajustar-plan'] : '';
if ($reajustarRaw !== '') {
    $ucuenta = uf_usuario($reajustarRaw);
    if (!$ucuenta) {
        fwrite(STDERR, "\nERROR: no existe el usuario '{$reajustarRaw}'.\n");
        exit(1);
    }
    $fundosCuenta = mf_fundos_para_unificar((int) $ucuenta['id']);
    if (!$fundosCuenta) {
        fwrite(STDERR, "\nERROR: el usuario '{$ucuenta['username']}' no tiene fundos.\n");
        exit(1);
    }

    echo "\n── Reajuste de plan ────────────────────────────────────────────────────────\n";
    printf("   Cuenta: id %d · %s · %s\n", $ucuenta['id'], $ucuenta['username'], $ucuenta['email']);
    $planesC = array(mf_plan_vigente_usuario((int) $ucuenta['id']));
    foreach ($fundosCuenta as $f) {
        $pl = mf_plan_detalle(mf_plan_vigente_fundo((int) $f['mfundos_id']));
        $planesC[] = $pl['id'];
        printf("      - %-28s (id %d) plan actual: %s\n",
            $f['nombre'], $f['mfundos_id'], ($pl['nombre'] !== '' ? $pl['nombre'] : 'sin plan'));
    }
    $mayor = mf_plan_detalle(mf_plan_mayor($planesC));
    if ($mayor['id'] <= 0) {
        fwrite(STDERR, "\nERROR: no se pudo determinar el plan de mayor cobertura.\n");
        exit(1);
    }
    printf("   Plan que prevalecerá: %s (%d registros por fundo)\n", $mayor['nombre'], $mayor['max_reg']);

    if (!$aplicar) {
        echo "\n [SIMULACIÓN] No se cambió nada. Agrega --aplicar para ejecutar.\n\n";
        exit(0);
    }

    $fallos = 0;
    foreach ($fundosCuenta as $f) {
        if (!mf_aplicar_plan_fundo((int) $f['mfundos_id'], (int) $ucuenta['id'], $mayor['id'])) {
            $fallos++;
        }
    }
    _mf_exec("UPDATE musuarios SET mplanes_id = {$mayor['id']} WHERE id = " . (int) $ucuenta['id']);

    echo "\n── Resultado ────────────────────────────────────────────────────────────\n";
    printf("   Fundos en %s: %d de %d\n", $mayor['nombre'], count($fundosCuenta) - $fallos, count($fundosCuenta));
    echo '   ' . uf_fundos_txt((int) $ucuenta['id']) . "\n";
    echo "\n ✔ Reajuste completado.\n\n";
    exit(0);
}

// ─────────────────────  2) Unificación solicitada  ─────────────────────
if ($objetivoRaw === '' || $fusionarRaw === '') {
    echo "\n (No se indicó --objetivo / --fusionar: solo informativo.)\n";
    echo " Ejemplo:  php scripts/unificar_fundos_usuarios.php --objetivo=79 --fusionar=80\n";
    echo " Reajustar plan: php scripts/unificar_fundos_usuarios.php --reajustar-plan=79 --aplicar\n\n";
    exit(0);
}

$destino = uf_usuario($objetivoRaw);
if (!$destino) {
    fwrite(STDERR, "\nERROR: no existe el usuario destino '{$objetivoRaw}'.\n");
    exit(1);
}
if ((int) $destino['status'] !== 1) {
    fwrite(STDERR, "\nERROR: el usuario destino '{$destino['username']}' está inactivo.\n");
    exit(1);
}

$origenes   = array();
$fundoIds   = array();
$noHallados = array();
foreach (explode(',', $fusionarRaw) as $tok) {
    $tok = trim($tok);
    if ($tok === '') {
        continue;
    }
    $u = uf_usuario($tok);
    if (!$u) {
        $noHallados[] = $tok;
        continue;
    }
    if ((int) $u['id'] === (int) $destino['id']) {
        continue; // el destino no puede fusionarse consigo mismo
    }
    $origenes[] = (int) $u['id'];
    foreach (mf_fundos_para_unificar((int) $u['id']) as $f) {
        $fundoIds[(int) $f['mfundos_id']] = true;
    }
}
$fundoIds = array_keys($fundoIds);

if ($noHallados) {
    fwrite(STDERR, "\nAVISO: no se encontraron los usuarios: " . implode(', ', $noHallados) . "\n");
}
if (!$origenes) {
    fwrite(STDERR, "\nERROR: no se indicó ningún usuario origen válido en --fusionar.\n");
    exit(1);
}
if (!$fundoIds) {
    fwrite(STDERR, "\nERROR: los usuarios origen no tienen fundos que traspasar.\n");
    exit(1);
}

// ─────────────────────  3) Vista previa  ─────────────────────
echo "\n── Unificación solicitada ────────────────────────────────────────────────\n";
printf("   Destino : id %d · %s · %s\n", $destino['id'], $destino['username'], $destino['email']);
echo "   Fundos que se asignarán al destino (y se quitarán a los orígenes):\n";
$planesPrevios = array(mf_plan_vigente_usuario((int) $destino['id']));
foreach ($fundoIds as $fid) {
    $f = _mf_q1("SELECT f.id, f.nombre, f.mtipoganaderias_id, f.user_propietario
                   FROM mfundos f WHERE f.id = " . (int) $fid . " LIMIT 1");
    $gan = ($f && (int) $f['mtipoganaderias_id'] === 1) ? 'ovina'
         : (($f && (int) $f['mtipoganaderias_id'] === 2) ? 'caprina' : 'ovina y caprina');
    $prop = ($f && $f['user_propietario']) ? ('propietario actual: id ' . $f['user_propietario']) : 'sin propietario';
    $pl   = mf_plan_detalle(mf_plan_vigente_fundo((int) $fid));
    $planesPrevios[] = $pl['id'];
    printf("      - %-28s (id %d, %s, plan %s, %s)\n",
        $f ? $f['nombre'] : "fundo $fid", $fid, $gan, ($pl['nombre'] !== '' ? $pl['nombre'] : 'sin plan'), $prop);
}
$planMayor = mf_plan_detalle(mf_plan_mayor($planesPrevios));
echo "   Orígenes que perderán esos fundos:\n";
foreach ($origenes as $ori) {
    $o = uf_usuario($ori);
    printf("      - id %d · %s   (fundos actuales: %s)\n", $ori, $o['username'], uf_fundos_txt($ori));
}
printf("   Opciones: inhabilitar orígenes = %s · transferir propiedad = %s\n",
    ($desactivar ? 'SÍ' : 'no'), ($propiedad ? 'SÍ' : 'no'));
if ($planMayor['id'] > 0) {
    printf("   Plan que prevalecerá (mayor cobertura): %s (%d registros por fundo)\n",
        $planMayor['nombre'], $planMayor['max_reg']);
}

if (!$aplicar) {
    echo "\n [SIMULACIÓN] No se cambió nada. Agrega --aplicar para ejecutar.\n\n";
    exit(0);
}

// ─────────────────────  4) Aplicar  ─────────────────────
$res = mf_unificar_fundos(
    (int) $destino['id'],
    $fundoIds,
    $origenes,
    array('desactivar_origenes' => $desactivar, 'transferir_propiedad' => $propiedad)
);

echo "\n── Resultado ────────────────────────────────────────────────────────────\n";
if (!empty($res['errores'])) {
    foreach ($res['errores'] as $e) {
        echo "   [ERROR] $e\n";
    }
}
printf("   Fundos asignados al destino: %d\n", count($res['fundos']));
printf("   Destino: es_multifundo=%d  max_fundos=%d\n", $res['es_multifundo'], $res['max_fundos']);
if (!empty($res['plan']['id'])) {
    printf("   Plan aplicado a los fundos: %s (%d registros por fundo)\n",
        $res['plan']['nombre'], $res['plan']['max_reg']);
}
if ($res['desactivados']) {
    echo '   Cuentas desactivadas: ' . implode(', ', $res['desactivados']) . "\n";
}
if ($res['con_fundos']) {
    foreach ($res['con_fundos'] as $cf) {
        echo "   (no se desactivó) id {$cf['id']}: todavía conserva {$cf['fundos']} fundo(s)\n";
    }
}

echo "\n── Verificación (estado final del destino) ───────────────────────────────\n";
echo '   ' . $destino['username'] . ' · fundos: ' . uf_fundos_txt((int) $destino['id']) . "\n";
foreach ($origenes as $ori) {
    $o = uf_usuario($ori);
    printf("   %s (id %d): status=%s · fundos: %s\n", $o['username'], $ori, $o['status'], uf_fundos_txt($ori));
}

echo "\n" . ($res['ok'] ? ' ✔ Unificación completada.' : ' ⚠ Terminó con errores (revisar arriba).') . "\n\n";
exit($res['ok'] ? 0 : 1);
