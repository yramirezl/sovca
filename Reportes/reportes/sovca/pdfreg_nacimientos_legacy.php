<?php
/* ============================================================
   Compatible con PHP 7.4 y PHP 8.3
   ============================================================ */

date_default_timezone_set('America/Caracas');

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() { return false; }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($v) { return true; }
}
if (!function_exists('__pdf_txt')) {
    function __pdf_txt($str) {
        if ($str === null || $str === '') return '';
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return utf8_decode($str);
    }
}

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_NOTICE);

require_once('../../lib/general/fpdf/fpdf.php');
require_once('../../lib/bd/basedatosAdoIbase.php');
require_once('../../lib/general/cabecera.php');
require_once('../../lib/general/Herramientas.class.php');
include_once('../../lib/utiles.php');
include_once('../../lib/funciones.php');

#[\AllowDynamicProperties]
class pdfreporte extends fpdf
{
    /* Constructor dual: PHP 5.3.3 → 8.3 */
    function __construct()
    {
        $this->pdfreporte();
    }

    var $reg;
    var $p = 0;

    function pdfreporte()
    {
        $this->fpdf( 'l', 'mm', 'legal' );
        $this->bd = new baseClases();
        $this->cab = new Cabecera();

        $parametros = base64_decode( getvalue( '%$%' ) );
        // H::PrintR( $parametros );
        // exit;

        $cod_bovs = '';
        $filtrarXsexo = '';
        $f_inicio = '';
        $f_hasta = '';
        $cod_arete = '';
        $buscar_x_cod_arete = '';

        $valores = explode( '&', $parametros );
        $cad0 = $valores[ 0 ];

        $cad1 = $valores[ 1 ];
        $cad2 = $valores[ 2 ];
        $cad3 = $valores[ 3 ];
        $cad4 = $valores[ 4 ];
        $cad5 = $valores[ 5 ];

        $valores_cad0 = explode( '=', $cad0 );

        $cad_0 = $valores_cad0[ 0 ];
        $cad_00 = $valores_cad0[ 1 ];

        if ( $cad_0 == 'cod_bovs' ) {
            $cod_bovs = $cad_00;
        } else if ( $cad_0 == 'filtrarXsexo' ) {
            $filtrarXsexo = $cad_00;
        } else if ( $cad_0 == 'fecha_inicio' ) {
            $f_inicio = $cad_00;
        } else if ( $cad_0 == 'fecha_hasta' ) {
            $f_hasta = $cad_00;
        } else if ( $cad_0 == 'codigo_erete' ) {
            $buscar_x_cod_arete = $cad_00;
        }
        /////////////////////////////////
        $valores_cad1 = explode( '=', $cad1 );

        $cad_1 = $valores_cad1[ 0 ];
        $cad_2 = $valores_cad1[ 1 ];

        if ( $cad_1 == 'cod_bovs' ) {
            $cod_bovs = $cad_2;
        } else if ( $cad_1 == 'filtrarXsexo' ) {
            $filtrarXsexo = $cad_2;
        } else if ( $cad_1 == 'fecha_inicio' ) {
            $f_inicio = $cad_2;
        } else if ( $cad_1 == 'fecha_hasta' ) {
            $f_hasta = $cad_2;
        } else if ( $cad_1 == 'codigo_erete' ) {
            $buscar_x_cod_arete = $cad_2;
        }
        /////////////////////////////////

        $valores_cad2 = explode( '=', $cad2 );

        $cad_3 = $valores_cad2[ 0 ];
        $cad_4 = $valores_cad2[ 1 ];

        if ( $cad_3 == 'cod_bovs' ) {
            $cod_bovs = $cad_4;
        } else if ( $cad_3 == 'filtrarXsexo' ) {
            $filtrarXsexo = $cad_4;
        } else if ( $cad_3 == 'fecha_inicio' ) {
            $f_inicio = $cad_4;
        } else if ( $cad_3 == 'fecha_hasta' ) {
            $f_hasta = $cad_4;
        } else if ( $cad_3 == 'codigo_erete' ) {
            $buscar_x_cod_arete = $cad_4;
        }

        ////////////////////////////////
        $valores_cad3 = explode( '=', $cad3 );

        $cad_5 = $valores_cad3[ 0 ];
        $cad_6 = $valores_cad3[ 1 ];

        if ( $cad_5 == 'cod_bovs' ) {
            $cod_bovs = $cad_6;
        } else if ( $cad_5 == 'filtrarXsexo' ) {
            $filtrarXsexo = $cad_6;
        } else if ( $cad_5 == 'fecha_inicio' ) {
            $f_inicio = $cad_6;
        } else if ( $cad_5 == 'fecha_hasta' ) {
            $f_hasta = $cad_6;
        } else if ( $cad_5 == 'codigo_erete' ) {
            $buscar_x_cod_arete = $cad_6;
        }
        ////////////////////////////////
        $valores_cad4 = explode( '=', $cad4 );

        $cad_7 = $valores_cad4[ 0 ];
        $cad_8 = $valores_cad4[ 1 ];

        if ( $cad_7 == 'cod_bovs' ) {
            $cod_bovs = $cad_8;
        } else if ( $cad_7 == 'filtrarXsexo' ) {
            $filtrarXsexo = $cad_8;
        } else if ( $cad_7 == 'fecha_inicio' ) {
            $f_inicio = $cad_8;
        } else if ( $cad_7 == 'fecha_hasta' ) {
            $f_hasta = $cad_8;
        } else if ( $cad_7 == 'codigo_erete' ) {
            $buscar_x_cod_arete = $cad_8;
        }
        ////////////////////////////////

        $this->fecha_desde = $f_inicio;
        $this->fecha_hasta = $f_hasta;

        $this->cod_bovs = $cod_bovs;

        $this->filtrar_sexo = $filtrarXsexo;

        $this->mfundos_id = 0;
        session_start();
        $this->mfundos_id = $_SESSION[ 'mfundos_id' ];

        $fecha_de = $this->fecha_desde;
        $fecha_fi = $this->fecha_hasta;

        $sql_fundo = 'SELECT nombre, cid_rif  from mfundos where id=' . $this->mfundos_id . '; ';
        $this->arrp_fundo = $this->bd->select( $sql_fundo );
        $this->nombre_fundo = $this->arrp_fundo[ 0 ][ 'nombre' ];
        $this->cid_rif_fundo = $this->arrp_fundo[ 0 ][ 'cid_rif' ];
        $cad_order = '';

        $cadena_sexo = '';

        if ( $this->filtrar_sexo != '' ) {
            $cadena_sexo = 'and sexo_animal=' . $this->filtrar_sexo . ' ';
        }

        if ( ( $fecha_de != '' ) && ( $fecha_fi != '' ) ) {

            // //////////////********************//////////////////////
            // /////////////CAMBIANDO FORMATO DE FECHA ////////////
            $date_desde = new DateTime( $fecha_de );
            $fecha_desde  = $date_desde->format( 'Y-m-d' );

            $date_hasta = new DateTime( $fecha_fi );
            $fecha_hasta  = $date_hasta->format( 'Y-m-d' );

            // //////////////********************//////////////////////

            $this->cadena_rango_fecha = 'Desde el ' . $this->fecha_desde . ' Hasta el ' . $this->fecha_hasta;
            $cad_order = 'order by id_animal';
            $this->cadena_fecha = " and f_servicio        
            BETWEEN CAST ('$fecha_desde' AS DATE) AND CAST ('$fecha_hasta' AS DATE)";

        } else {
            $this->cadena_fecha = '';
            $this->cadena_rango_fecha = '';
        }
        if ( $this->cod_bovs != '' ) {

            if ( $buscar_x_cod_arete == 1 ) {

                $rgb_animal = $this->cod_bovs;
  

                $cadena = "and codigo_rgb_id_animal='$rgb_animal' ";

            } else   if ( $buscar_x_cod_arete == 2 ) {

                $nro_arete = $this->cod_bovs;

                $cadena = "and nro_arete='$nro_arete' ";

            }

            $sql_id = 'SELECT  id  from manimales where mfundos_id=' . $this->mfundos_id . " $cadena; ";

            $this->arrp_id = $this->bd->select( $sql_id );
            $cod_animal = $this->arrp_id[ 0 ][ 'id' ];
            $cod_animal = intval( $cod_animal );

            if ( $cod_animal > 0 ) {
                $cadena_codigo = ' and id_animal= ' . $cod_animal . '  ';
                $cad_order = 'order by id_animal';
            } else {

                $sql_id_desde = 'SELECT  min(id) as minimo  from manimales where mfundos_id=' . $this->mfundos_id . " and mestatus_id<>3 $cadena_sexo; ";
                $sql_id_hasta = 'SELECT  max(id) as maximo  from manimales where mfundos_id=' . $this->mfundos_id . " and mestatus_id<>3 $cadena_sexo; ";
                $this->arrp_id_desde = $this->bd->select( $sql_id_desde );
                $this->arrp_id_hasta = $this->bd->select( $sql_id_hasta );
                $cod_animal_desde = $this->arrp_id_desde[ 0 ][ 'minimo' ];
                $cod_animal_desde = intval( $cod_animal_desde );
                $cod_animal_hasta = $this->arrp_id_hasta[ 0 ][ 'maximo' ];
                $cod_animal_hasta = intval( $cod_animal_hasta );
                $cadena_codigo = ' and id_animal>= ' . $cod_animal_desde . '  and id_animal<= ' . $cod_animal_hasta . ' ';
                $cad_order = 'order by id_animal';

            }
        }

        $sql_principal = " SELECT codigo_servicio,codigo_rgb_id_animal_hembra,
                                    nro_arete,sexo_animal,nombre_h
                                    from v_rep_regservicios  
                                     where mfundos_id=" . $this->mfundos_id . " $this->cadena_fecha $cadena_codigo  $cadena_sexo order by codigo_servicio desc limit 1;  ";

        //    H::PrintR( $sql_principal );
        //    exit;

        $this->arrp = $this->bd->select( $sql_principal );

        $this->reg = 0;
    }

    function getEncabezado($x, $y)
    {
        $this->SetBorder(1);
        $this->SetDrawColor(2, 157, 116);
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', 14);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(330));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(16);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(230));
        $this->SetAligns(array('C'));

        $fecha = date('d/m/Y');

        $this->SetWidths(array(200, 60));
        $this->SetAligns(array('C', 'R'));
        // $this->SetTextColor( 100, 100, 255 );

        $this->RowM(array($this->nombre_fundo . '  ' . $this->cid_rif_fundo, 'Fecha: ' . $fecha));

        $this->SetXY($x + 5, $y);

        if ($this->cadena_rango_fecha != '') {
            $this->SetXY($x, $y);
            $this->SetJump(6);
            $this->SetWidths(array(250));
            $this->SetAligns(array('C'));
            $this->RowM(array('REPORTE DE NACIMIENTOS ' . $this->cadena_rango_fecha));
        } else {

            $this->SetJump(6);
            $this->SetWidths(array(250));
            $this->SetAligns(array('C'));
            $this->RowM(array('INFORME DE NACIMIENTOS'));
        }
    }

    function  Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', '8');
    }
    function  Pie_Pagina()
    {
        $this->SetFillColor(230, 230, 230);

        $this->SetTextColor(0, 0, 100);

        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY(10, 330);
        $this->SetWidths(array(190));
        $this->SetAligns(array('R'));
        $this->RowM(array(utf8_decode(utf8_decode('Sistema SOVCA (https://global.ruted.net)'))));
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, 'p', '', 'n');
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(200));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }
    //end header

    function Cuerpo()
    {
        $puntero = 0;
        $contador = 0;
        $this->Pie_Pagina();

        $this->SetXY(10, 40);

        foreach ($this->arrp as $data) {


            $cod_animal = $data['codigo_animal'];

            if ($this->GetY() > 250) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY(10, 35);
            }

            $this->SetBorder(0);


            $this->SetFont('ARIAL', '', '10');
            $this->SetWidths(array(80));
            $this->SetAligns(array('C'));
            $this->SetFillColor(100, 200, 156);
            //Fondo verde de celda
            $this->SetBorder(true);
            $this->SetJump(8);

            $this->RowMFillColor(array(utf8_decode('DATOS DEL ANIMAL')), true);


            $this->SetTextColor(0, 0, 0);
            $this->SetFont('ARIAL', 'B', '9');
            $this->SetFillColor(100, 200, 116);
            $this->SetWidths(array(40, 35, 20,20,20,20,20));
            $this->SetAligns(array('C', 'C', 'C','C', 'C', 'C','C'));
            $this->SetBorder(true);

            $this->RowMFillColor(array(utf8_decode('Código SOVCA'), utf8_decode('N° Arete'), 'Nombre','Total Nacimientos','% Vivos','% hembras','% machos'), true);
            $this->SetTextColor(0, 0, 150);
            $this->SetFillColor(200, 250, 216);
            $this->SetJump(6);
            $this->SetTextColor(0, 0, 150);
            $this->SetAligns(array('C', 'C', 'C','C', 'C', 'C','C'));

            $this->RowMFillColor(array('   ' . $data['codigo_animal'], '   ' . $data['nro_arete'], $data['nombre_animal'],$data[''],$data[''],$data[''],$data['']), true);
            $this->SetFont('ARIAL', '', '10');
            $this->SetWidths(array(80));
            $this->SetAligns(array('C'));
            $this->SetFillColor(200, 220, 216);
            $this->SetBorder(true);
            $this->SetJump(6);


            $sql_detalle = " SELECT id_consecutivo_peso,peso,                                           
                                            to_char(fecha_pesaje, 'DD-MM-YYYY') AS fecha_pesaje                                           
                                            from v_consecutivo_peso  
                                         where codigo_rgb_id_animal='$cod_animal' and   mfundos_id=" . $this->mfundos_id . ";  ";


            //   H::PrintR( $sql_detalle );
            //  exit;

            $this->arrp_deta = $this->bd->select($sql_detalle);
             $can_reg_det=count($this->arrp_deta);
            if (count($this->arrp_deta) > 0) {     
                $this->SetTextColor(0, 0, 0);

                $this->SetFont('ARIAL', 'B', '9');
                $this->SetFillColor(100, 200, 156);
                $this->SetWidths(array(40));
                $this->SetAligns(array('C'));
                $this->SetBorder(true);

                $this->RowMFillColor(array(utf8_decode('Datos Especificos')), true);
                    $this->SetTextColor(0, 0, 0);
                    $this->SetFont('ARIAL', 'B', '9');
                    $this->SetFillColor(100, 200, 116);
                    $this->SetWidths(array(40, 50, 30, 25,30));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
                    $this->SetBorder(true);
    
                    $this->RowMFillColor(array(utf8_decode('Fecha '), 'Parto Nro', 'Tipo de servicio',utf8_decode('Nació'), 'Sexo', 'Peso al nacer', 'ID o nro. arete','Aptitud del animal','IEP'), true);
    
                         
                    $fecha_aux="";
                    $fecha_pesaje="";
                    $peso_aux="";
                    $peso_actual="";
                    $gdp="";
                    $gddp="";
                    $acum=false;
                    $gtp=0;
                    $dias_t=0;
                    $peso_inicial=0;
                    $peso_actual=0;
                    $min_gddp=10000;
                    $max_gddp=0;
                    $conta_det=0;
                    $media_gddp=0;
                    $gddp_acum=0;
            foreach ($this->arrp_deta as $data_deta) {

    
                if ($this->GetY() > 300) {
                    $this->AddPage();
                    $this->Pie_Pagina();

                    $this->SetXY(10, 35);
                }

                ////////////////////////////

                $this->SetTextColor(0, 0, 0);
                $this->SetFont('ARIAL', '', '8');
                $this->SetFillColor(200, 250, 216);

                $this->SetWidths(array(40, 50, 30, 25,30));
                $this->SetAligns(array('C','C', 'C', 'C', 'C'));
                $this->SetBorder(true);


                /////////////////////////
                $fecha_pesaje=$data_deta['fecha_pesaje'];
                $peso_actual=$data_deta['peso'];
                if ($fecha_aux!=""){
                    $periodo=$this->tiempo_ies($fecha_aux, $fecha_pesaje);
                }else{
                    $periodo=0;
                }
                if ($peso_aux!=""){
                    $gdp=$peso_actual-$peso_aux;

                }else{
                    $gdp="-";
                }
                if ($periodo>0){
                    $gddp=$gdp/$periodo;
                    $gddp=H::FormatoMonto($gddp);
                    $acum=true;
                    if ($gddp<$min_gddp){
                        $min_gddp=$gddp;
                       } 
                       if ($gddp>$max_gddp){
                        $max_gddp=$gddp;
                       } 

                       $gddp_acum=$gddp_acum+$gddp;
                }else{
                    $gddp="-";                    
                }
                
     
     

                $this->RowMFillColor(array( $data_deta['fecha_pesaje'],  $data_deta['peso'],  $periodo,  $gdp,  $gddp), true);


             
                
                $fecha_aux=$data_deta['fecha_pesaje'];
                $peso_aux=$data_deta['peso'];


                $gtp+=$gdp;
                $dias_t+=$periodo;
                $conta_det++;
                if ($conta_det==1){
                    $peso_inicial=$data_deta['peso'];
                }
                if ($conta_det==$can_reg_det){
                    $peso_actual=$data_deta['peso'];
                }

            

                
                

            }
        if($acum){
            $media_gddp=$gddp_acum/$can_reg_det;
            $media_gddp=H::FormatoMonto($media_gddp);

            $this->SetTextColor(0, 0, 0);
            $this->SetFont('ARIAL', 'B', '9');
            $this->SetFillColor(100, 200, 156);
            $this->SetWidths(array(40));
            $this->SetAligns(array('C'));
            $this->RowMFillColor(array(utf8_decode('Datos Acumulados')), true);
  
            // $this->SetTextColor(0, 0, 0);

            $this->SetFillColor(100, 200, 116);
            $this->SetWidths(array(20, 30, 25, 25,25,25,25));
            $this->SetAligns(array('C','C', 'C', 'C', 'C', 'C', 'C'));
            $this->SetBorder(true);
            $this->RowMFillColor(array('GTP',utf8_decode('Días Totales'), 'Peso Inicial', 'Peso Actual', 'Media GDdP', 'Min GDdP','Max GDdP'), true);
            $this->SetBorder(true);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('ARIAL', '', '8');
            $this->SetFillColor(200, 250, 216);
        

            $this->RowMFillColor(array($gtp,$dias_t, $peso_inicial, $peso_actual, $media_gddp, $min_gddp, $max_gddp), true);
            $this->SetBorder(true);


        }
        

               
            

        }

           
            $this->SetX(140);

            $this->SetWidths(array(60));
            // $this->SetFillTable( 1 );
            $this->SetBorder(true);
            $this->Ln(5);
            $contador++;
        }
    }
    //end cuerpo

    ///////////////////FUNCIONES DE CALCULO DE FECHAS/////////////////////////////
// $fecha_aux, $fecha_pesaje
function tiempo_ies($fecha_pesaje,$fecha_aux)
{
$fecha_f = $fecha_aux;
$fecha_i =$fecha_pesaje;
// $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
$dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;

$dias = abs($dias);
$dias = floor($dias);
$dias = (int)$dias;
$this->dias=$dias;
return $this->dias;
}




}

