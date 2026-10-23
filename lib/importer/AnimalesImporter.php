<?php
/* ============================================================
   Importador de animales por CSV — logica pura sin HTTP.
   ============================================================ */

require_once __DIR__ . '/NormalizadorNombres.php';
require_once __DIR__ . '/../estatus_animal_helper.php';

class AnimalesImporter {

    private $mfundos_id;
    private $t_ganaderia;
    private $usuario_id;
    private $maximo_registros;

    private $catalogo_razas       = null;
    private $catalogo_aptitudes   = null;
    private $catalogo_categorias  = null;
    private $catalogo_lotes       = null;
    private $catalogo_servicios   = null;

    private $nros_arete_existentes = array();
    private $nombres_existentes    = array();
    private $cantidad_actual       = 0;
    private $proximo_codigo        = 0;

    const LIMITE_FILAS = 500;

    public function __construct($mfundos_id, $t_ganaderia, $usuario_id, $maximo_registros = 999999) {
        $this->mfundos_id       = intval($mfundos_id);
        $this->t_ganaderia      = intval($t_ganaderia);
        $this->usuario_id       = intval($usuario_id);
        $this->maximo_registros = intval($maximo_registros);
        $this->cargarCatalogos();
    }

    /* ============================================================
       CARGA DE CATALOGOS
       ------------------------------------------------------------
       - mrazas       → propias + globales (mfundos_id = 0)
       - mcategorias  → propias + globales (mfundos_id = 0)
       - mlotes       → solo del fundo
       - maptitudes   → globales (sin mfundos_id)
       - mtiposervicios → globales (sin mfundos_id)
       ============================================================ */
    private function cargarCatalogos() {
        require_once __DIR__ . '/../../models/Mraza.php';
        require_once __DIR__ . '/../../models/Maptitude.php';
        require_once __DIR__ . '/../../models/Mcategoria.php';
        require_once __DIR__ . '/../../models/Mlote.php';
        require_once __DIR__ . '/../../models/Mtiposervicio.php';
        require_once __DIR__ . '/../../models/Manimale.php';

        $mf = $this->mfundos_id;
        $tg = $this->t_ganaderia;

        /* RAZAS: propias del fundo + globales */
        $this->catalogo_razas = $this->catalogoComoArray(
            Mraza::find_by_sql("
                SELECT id, nombre FROM mrazas
                WHERE status = 1
                  AND (mfundos_id = {$mf} OR mfundos_id = 0)
                ORDER BY mfundos_id DESC, nombre ASC
            ")
        );

        /* APTITUDES: global, sin mfundos_id */
        $this->catalogo_aptitudes = $this->catalogoComoArray(
            Maptitude::find_by_sql("
                SELECT id, nombre FROM maptitudes
                WHERE status = 1
                ORDER BY nombre ASC
            ")
        );

        /* LOTES: solo del fundo */
        $this->catalogo_lotes = $this->catalogoComoArray(
            Mlote::find_by_sql("
                SELECT id, nombre FROM mlotes
                WHERE status = 1
                  AND mfundos_id = {$mf}
                ORDER BY nombre ASC
            ")
        );

        /* SERVICIOS: global, sin mfundos_id */
        $this->catalogo_servicios = $this->catalogoComoArray(
            Mtiposervicio::find_by_sql("
                SELECT id, nombre FROM mtiposervicios
                WHERE status = 1
                ORDER BY nombre ASC
            ")
        );

        /* CATEGORIAS: propias + globales, filtrado por tipo de ganaderia */
        $cats = Mcategoria::find_by_sql("
            SELECT id, nombre, sexo FROM mcategorias
            WHERE mtipoganaderias_id = {$tg}
              AND status = 1
              AND (mfundos_id = {$mf} OR mfundos_id = 0)
            ORDER BY mfundos_id DESC, nombre ASC
        ");
        $this->catalogo_categorias = array();
        if (is_array($cats)) {
            foreach ($cats as $c) {
                $this->catalogo_categorias[] = array(
                    'id'     => intval($c->id),
                    'nombre' => (string)$c->nombre,
                    'sexo'   => intval($c->sexo)
                );
            }
        }

        /* Nros arete existentes */
        $rows = Manimale::find_by_sql("
            SELECT nro_arete FROM manimales
            WHERE status = 1 AND mfundos_id = {$mf}
        ");
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $k = NormalizadorNombres::normalizar($r->nro_arete);
                if ($k !== '') $this->nros_arete_existentes[$k] = true;
            }
        }

        /* Nombres existentes */
        $rows = Manimale::find_by_sql("
            SELECT nombre FROM manimales
            WHERE status = 1 AND mfundos_id = {$mf}
        ");
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $k = NormalizadorNombres::normalizar($r->nombre);
                if ($k !== '') $this->nombres_existentes[$k] = true;
            }
        }

        /* Cantidad de animales activos (para validar el plan) */
        $rows = Manimale::find_by_sql("
            SELECT COUNT(*) AS c FROM manimales
            WHERE status = 1 AND mfundos_id = {$mf}
        ");
        if (is_array($rows) && count($rows) > 0) {
            $this->cantidad_actual = intval($rows[0]->c);
        }

        /* Proximo codigo = MAX sufijo numerico + 1 (de todos los codigos) */
        $rows = Manimale::find_by_sql("
            SELECT COALESCE(
                MAX(CAST(SUBSTRING(codigo_rgb_id_animal FROM '[0-9]+$') AS INTEGER)),
                0
            ) AS max_sufijo
            FROM manimales
            WHERE mfundos_id = {$mf}
        ");
        if (is_array($rows) && count($rows) > 0) {
            $this->proximo_codigo = intval($rows[0]->max_sufijo);
        } else {
            $this->proximo_codigo = 0;
        }
    }

    /**
     * Convierte un array de objetos ActiveRecord a array plano
     * con solo 'id' y 'nombre'.
     */
    private function catalogoComoArray($items) {
        $out = array();
        if (!is_array($items)) return $out;
        foreach ($items as $it) {
            $out[] = array(
                'id'     => intval($it->id),
                'nombre' => (string)$it->nombre
            );
        }
        return $out;
    }

    public function getCatalogoPublico($tipo) {
        switch ($tipo) {
            case 'razas':      return $this->catalogo_razas;
            case 'aptitudes':  return $this->catalogo_aptitudes;
            case 'categorias': return $this->catalogo_categorias;
            case 'lotes':      return $this->catalogo_lotes;
            case 'servicios':  return $this->catalogo_servicios;
        }
        return array();
    }

    /* ============================================================
       PARSEO DEL CSV
       ============================================================ */
    public function parsearCSV($ruta) {
        if (!is_readable($ruta)) {
            throw new Exception('No se puede leer el archivo CSV');
        }

        $handle = fopen($ruta, 'r');
        if (!$handle) throw new Exception('No se puede abrir el CSV');

        /* Detectar BOM UTF-8 */
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        /* Detectar delimitador */
        $primera = fgets($handle);
        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        $delim = (substr_count($primera, ';') > substr_count($primera, ',')) ? ';' : ',';

        $headers = fgetcsv($handle, 0, $delim);
        if (!$headers) { fclose($handle); throw new Exception('CSV vacio o invalido'); }
        $headers = array_map(function($h) {
            return strtolower(trim($h));
        }, $headers);

        $filas = array();
        $num = 1;
        while (($fila = fgetcsv($handle, 0, $delim)) !== false) {
            $num++;
            if (count($fila) === 1 && trim((string)$fila[0]) === '') continue;

            $registro = array('_fila' => $num);
            foreach ($headers as $i => $h) {
                $registro[$h] = isset($fila[$i]) ? $this->limpiarUTF8(trim($fila[$i])) : '';
            }
            $filas[] = $registro;
        }
        fclose($handle);
        return $filas;
    }

    private function limpiarUTF8($s) {
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        return $s;
    }

    /* ============================================================
       VALIDACION DE UNA FILA
       ============================================================ */
    public function validarFila($fila, &$indices) {
        $errores    = array();
        $decisiones = array();
        $datos      = array();

        $get = function($k) use ($fila) {
            return isset($fila[$k]) ? trim($fila[$k]) : '';
        };

        /* Obligatorios */
        $obligatorios = array(
            'nombre_animal', 'nro_arete', 'sexo', 'fecha_nacimiento',
            'raza', 'aptitud', 'categoria_ingreso', 'categoria_futura',
            'lote', 'servicio'
        );
        foreach ($obligatorios as $campo) {
            if ($get($campo) === '') {
                $errores[] = "Falta {$campo}";
            }
        }
        if (!empty($errores)) {
            return array('ok' => false, 'errores' => $errores, 'decisiones' => array(), 'datos' => array());
        }

        $nombre    = $get('nombre_animal');
        $nro_arete = $get('nro_arete');

        /* Sexo */
        $sexo_norm = NormalizadorNombres::normalizar($get('sexo'));
        $sexo = null;
        if (in_array($sexo_norm, array('HEMBRA', 'H', 'F', 'FEMENINO'))) $sexo = 1;
        elseif (in_array($sexo_norm, array('MACHO', 'M', 'MASCULINO'))) $sexo = 2;
        else $errores[] = "Sexo invalido '{$get('sexo')}'. Use HEMBRA o MACHO";

        /* Fecha nacimiento */
        $fecha_nac = NormalizadorNombres::parsearFecha($get('fecha_nacimiento'));
        if ($fecha_nac === null) {
            $errores[] = "Fecha de nacimiento invalida: '{$get('fecha_nacimiento')}'";
        }

        /* Duplicados nro_arete */
        $nro_norm = NormalizadorNombres::normalizar($nro_arete);
        if (isset($this->nros_arete_existentes[$nro_norm])) {
            $errores[] = "Nro de arete '{$nro_arete}' ya existe en el sistema";
        }
        if (isset($indices['aretes'][$nro_norm])) {
            $errores[] = "Nro de arete '{$nro_arete}' duplicado dentro del CSV (fila {$indices['aretes'][$nro_norm]})";
        }

        /* Duplicados nombre */
        $nombre_norm = NormalizadorNombres::normalizar($nombre);
        if (isset($this->nombres_existentes[$nombre_norm])) {
            $errores[] = "El nombre '{$nombre}' ya existe en el sistema";
        }
        if (isset($indices['nombres'][$nombre_norm])) {
            $errores[] = "El nombre '{$nombre}' esta duplicado en el CSV (fila {$indices['nombres'][$nombre_norm]})";
        }

        /* Resolver cada catalogo con fuzzy match */
        $resolver = function($campo, $texto, $catalogo, $id_destino) use (&$decisiones, &$datos) {
            $res = NormalizadorNombres::buscarConSimilares($texto, $catalogo);
            if ($res['match']) {
                $datos[$id_destino] = $res['match']->id;
                return;
            }

            $cands = array();
            if (!empty($res['similares'])) {
                foreach ($res['similares'] as $s) {
                    $cands[] = array(
                        'id'        => $s['item']->id,
                        'nombre'    => $s['item']->nombre,
                        'similitud' => $s['similitud']
                    );
                }
            }

            $decisiones[] = array(
                'campo'           => $campo,
                'valor_ingresado' => $texto,
                'candidatos'      => $cands,
                'sin_match'       => empty($cands)
            );
        };

        if (empty($errores)) {
            $resolver('raza',              $get('raza'),              $this->catalogo_razas,      'mrazas_id');
            $resolver('aptitud',           $get('aptitud'),           $this->catalogo_aptitudes,  'aptitud');
            $resolver('categoria_ingreso', $get('categoria_ingreso'), $this->catalogo_categorias, 'categoria_ingreso_id');
            $resolver('categoria_futura',  $get('categoria_futura'),  $this->catalogo_categorias, 'categoria_futura_id');
            $resolver('lote',              $get('lote'),              $this->catalogo_lotes,      'mlotes_id');
            $resolver('servicio',          $get('servicio'),          $this->catalogo_servicios,  'servicio');
        }

        /* Estatus */
        $estatus_map = array(
            'ACTIVO'         => 1,
            'DONADOR ACTIVO' => 2,
            'DONADOR'        => 3,
            'DONADORA ACTIVO' => 4,
            'DONADORA ACTIVA' => 4,
            'DONADORA'        => 5
        );
        $estatus_txt  = $get('estatus') !== '' ? $get('estatus') : 'Activo';
        $estatus_norm = NormalizadorNombres::normalizar($estatus_txt);
        $estatus      = isset($estatus_map[$estatus_norm]) ? $estatus_map[$estatus_norm] : 1;

        /* El estatus debe corresponder al sexo (2/3 machos · 4/5 hembras) */
        $estatus = bovs_estatus_animal_valido($estatus, $sexo) ? $estatus : 1;

        /* Datos finales */
        $datos['nombre']          = $nombre;
        $datos['nro_arete']       = $nro_arete;
        $datos['sexo']            = $sexo;
        $datos['f_nacimiento']    = $fecha_nac;
        $datos['estatus_animal']  = $estatus;
        $datos['peso_nacimiento'] = $get('peso_nacimiento');
        $datos['alza']            = $get('alza');
        $datos['fecha_ingreso']   = NormalizadorNombres::parsearFecha($get('fecha_ingreso'));
        $datos['rena']            = $get('rena');
        $datos['arete_madre']     = $get('arete_madre');
        $datos['arete_padre']     = $get('arete_padre');
        $datos['procedencia']     = $get('procedencia');
        $datos['observaciones']   = $get('observaciones');
        $datos['_fila']           = $fila['_fila'];

        /* Indices para duplicados internos */
        $indices['aretes'][$nro_norm]     = $fila['_fila'];
        $indices['nombres'][$nombre_norm] = $fila['_fila'];

        $ok = empty($errores) && empty($decisiones);
        return array(
            'ok'         => $ok,
            'errores'    => $errores,
            'decisiones' => $decisiones,
            'datos'      => $datos
        );
    }

    /* ============================================================
       VALIDACION MASIVA
       ============================================================ */
    public function validarArchivo($ruta_csv) {
        $filas = $this->parsearCSV($ruta_csv);

        if (count($filas) === 0) {
            return array('ok' => false, 'error_global' => 'El archivo no tiene filas de datos', 'filas' => array());
        }
        if (count($filas) > self::LIMITE_FILAS) {
            return array(
                'ok' => false,
                'error_global' => 'Maximo ' . self::LIMITE_FILAS . ' filas por importacion. Tu archivo tiene ' . count($filas),
                'filas' => array()
            );
        }

        $disponibles = $this->maximo_registros - $this->cantidad_actual;
        $aviso_plan  = null;
        if (count($filas) > $disponibles) {
            $aviso_plan = "Tu plan permite {$this->maximo_registros} animales. Tienes {$this->cantidad_actual}. "
                        . "Solo puedes importar {$disponibles} mas, pero el CSV tiene " . count($filas) . " filas.";
        }

        $indices   = array('aretes' => array(), 'nombres' => array());
        $resultado = array();
        $contadores = array('ok' => 0, 'errores' => 0, 'decisiones' => 0);

        foreach ($filas as $fila) {
            $val = $this->validarFila($fila, $indices);
            $val['_fila'] = $fila['_fila'];
            $resultado[] = $val;

            if (!empty($val['errores'])) $contadores['errores']++;
            elseif (!empty($val['decisiones'])) $contadores['decisiones']++;
            else $contadores['ok']++;
        }

        return array(
            'ok'         => true,
            'aviso_plan' => $aviso_plan,
            'resumen'    => $contadores,
            'filas'      => $resultado,
            'catalogos'  => array(
                'razas'      => $this->catalogo_razas,
                'aptitudes'  => $this->catalogo_aptitudes,
                'categorias' => $this->catalogo_categorias,
                'lotes'      => $this->catalogo_lotes,
                'servicios'  => $this->catalogo_servicios
            )
        );
    }

    /* ============================================================
       CONFIRMAR IMPORTACION
       ============================================================ */
    public function confirmarImportacion($filas_validadas) {
        require_once __DIR__ . '/../../models/Manimale.php';
        require_once __DIR__ . '/../../models/Manimalxfundo.php';

        $insertados = 0;
        $errores    = array();
        $cod_actual = $this->proximo_codigo;
        $hoy        = date('d-m-Y');
        $nuevos_ids = array();

        foreach ($filas_validadas as $fila) {
            if (empty($fila['datos'])) continue;

            $datos    = $fila['datos'];
            $fila_num = isset($datos['_fila']) ? $datos['_fila'] : '?';

            try {
                $datos = $this->resolverNuevos($datos, $nuevos_ids);

                $cod_actual++;
                $cod_rgb = 'SOVCA-0' . $this->mfundos_id . '-0' . $cod_actual;

                /* Madre/padre por nro_arete */
                $madre_rgd = '';
                $madre_nom = '';
                if (!empty($datos['arete_madre'])) {
                    $m = $this->buscarAnimalPorArete($datos['arete_madre']);
                    if ($m) { $madre_rgd = $m->codigo_rgb_id_animal; $madre_nom = $m->nombre; }
                }
                $padre_rgd = '';
                $padre_nom = '';
                if (!empty($datos['arete_padre'])) {
                    $p = $this->buscarAnimalPorArete($datos['arete_padre']);
                    if ($p) { $padre_rgd = $p->codigo_rgb_id_animal; $padre_nom = $p->nombre; }
                }

                $animal = new Manimale();
                $animal->codigo_rgb_id_animal = $cod_rgb;
                $animal->nombre               = $datos['nombre'];
                $animal->sexo                 = $datos['sexo'];
                $animal->nro_arete            = $datos['nro_arete'];
                $animal->categoria_ingreso_id = $datos['categoria_ingreso_id'];
                $animal->categoria_futura_id  = $datos['categoria_futura_id'];
                $animal->aptitud              = $datos['aptitud'];
                $animal->mrazas_id            = $datos['mrazas_id'];
                $animal->f_nacimiento         = $datos['f_nacimiento'];
                $animal->f_ingreso            = !empty($datos['fecha_ingreso']) ? $datos['fecha_ingreso'] : null;
                $animal->peso_nacimiento      = !empty($datos['peso_nacimiento']) ? $datos['peso_nacimiento'] : '';
                $animal->alza                 = !empty($datos['alza']) ? $datos['alza'] : '';
                $animal->rena                 = !empty($datos['rena']) ? $datos['rena'] : '';
                $animal->estatus_animal       = $datos['estatus_animal'];
                $animal->servicio             = $datos['servicio'];
                $animal->mlotes_id            = $datos['mlotes_id'];
                $animal->rgd_madre            = $madre_rgd;
                $animal->nombre_madre         = $madre_nom;
                $animal->rgd_padre            = $padre_rgd;
                $animal->nombre_padre         = $padre_nom;
                $animal->procedencia          = !empty($datos['procedencia']) ? $datos['procedencia'] : '';
                $animal->observaciones        = !empty($datos['observaciones']) ? $datos['observaciones'] : '';
                $animal->imagen               = '';
                $animal->user_create          = $this->usuario_id;
                $animal->mfundos_id           = $this->mfundos_id;
                $animal->mtipoganaderias_id   = $this->t_ganaderia;
                $animal->created              = $hoy;

                if (!$animal->save()) {
                    $errores[] = "Fila {$fila_num}: error al guardar el animal";
                    $cod_actual--;
                    continue;
                }

                $relacion = new Manimalxfundo();
                $relacion->manimales_id = $animal->id;
                $relacion->mfundos_id   = $this->mfundos_id;
                $relacion->user_create  = $this->usuario_id;
                $relacion->save();

                $insertados++;
            } catch (Exception $e) {
                $errores[] = "Fila {$fila_num}: " . $e->getMessage();
                $cod_actual--;
            }
        }

        return array('insertados' => $insertados, 'errores' => $errores);
    }

    /* ============================================================
       HELPERS PRIVADOS
       ============================================================ */
    private function buscarAnimalPorArete($nro) {
        $nro = str_replace("'", "''", $nro);
        $rows = Manimale::find_by_sql("
            SELECT codigo_rgb_id_animal, nombre FROM manimales
            WHERE mfundos_id = {$this->mfundos_id}
              AND status = 1
              AND UPPER(TRIM(nro_arete)) = UPPER(TRIM('{$nro}'))
            LIMIT 1
        ");
        return (!empty($rows) && is_array($rows)) ? $rows[0] : null;
    }

    private function resolverNuevos($datos, &$nuevos_ids) {
        require_once __DIR__ . '/../../models/Mraza.php';
        require_once __DIR__ . '/../../models/Mcategoria.php';
        require_once __DIR__ . '/../../models/Mlote.php';

        /* Nueva raza */
        if (!empty($datos['nueva_raza'])) {
            $key = NormalizadorNombres::normalizar($datos['nueva_raza']);
            if (!isset($nuevos_ids['raza'][$key])) {
                $r = new Mraza();
                $r->nombre             = $datos['nueva_raza'];
                $r->mfundos_id         = $this->mfundos_id;
                $r->mtipoganaderias_id = $this->t_ganaderia;
                $r->status             = 1;
                $r->save();
                $nuevos_ids['raza'][$key] = $r->id;
            }
            $datos['mrazas_id'] = $nuevos_ids['raza'][$key];
        }

        /* Nueva categoria ingreso */
        if (!empty($datos['nueva_categoria_ingreso'])) {
            $key = NormalizadorNombres::normalizar($datos['nueva_categoria_ingreso']);
            if (!isset($nuevos_ids['cat'][$key])) {
                $c = new Mcategoria();
                $c->nombre             = $datos['nueva_categoria_ingreso'];
                $c->sexo               = $datos['sexo'];
                $c->edadminima         = 0;
                $c->edadmaxima         = 300;
                $c->mfundos_id         = $this->mfundos_id;
                $c->mtipoganaderias_id = $this->t_ganaderia;
                $c->status             = 1;
                $c->save();
                $nuevos_ids['cat'][$key] = $c->id;
            }
            $datos['categoria_ingreso_id'] = $nuevos_ids['cat'][$key];
        }

        /* Nueva categoria futura */
        if (!empty($datos['nueva_categoria_futura'])) {
            $key = NormalizadorNombres::normalizar($datos['nueva_categoria_futura']);
            if (!isset($nuevos_ids['cat'][$key])) {
                $c = new Mcategoria();
                $c->nombre             = $datos['nueva_categoria_futura'];
                $c->sexo               = $datos['sexo'];
                $c->edadminima         = 0;
                $c->edadmaxima         = 300;
                $c->mfundos_id         = $this->mfundos_id;
                $c->mtipoganaderias_id = $this->t_ganaderia;
                $c->status             = 1;
                $c->save();
                $nuevos_ids['cat'][$key] = $c->id;
            }
            $datos['categoria_futura_id'] = $nuevos_ids['cat'][$key];
        }

        /* Nuevo lote */
        if (!empty($datos['nuevo_lote'])) {
            $key = NormalizadorNombres::normalizar($datos['nuevo_lote']);
            if (!isset($nuevos_ids['lote'][$key])) {
                $l = new Mlote();
                $l->nombre     = $datos['nuevo_lote'];
                $l->mfundos_id = $this->mfundos_id;
                $l->status     = 1;
                $l->save();
                $nuevos_ids['lote'][$key] = $l->id;
            }
            $datos['mlotes_id'] = $nuevos_ids['lote'][$key];
        }

        return $datos;
    }
}