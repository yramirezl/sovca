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
        $this->fpdf("p", "mm", "legal");
        $this->bd = new baseClases();
        $this->cab = new Cabecera();  

        $this->mfundos_id = 0;
        session_start();
        $this->mfundos_id = $_SESSION['mfundos_id'];
        $this->t_ganaderia = $_SESSION['tipo_ganaderia'];

        $parametros = base64_decode(getvalue('%$%'));
       

        $valores= explode("=",$parametros);
        $cad0=$valores[0];       



        $this->lote = $cad0;

        $sql_fundo = "SELECT nombre, cid_rif  from mfundos where id=" . $this->mfundos_id . "; ";
        $this->arrp_fundo = $this->bd->select($sql_fundo);
        $this->nombre_fundo = $this->arrp_fundo[0]['nombre'];
        $this->cid_rif_fundo = $this->arrp_fundo[0]['cid_rif'];

        $sql_lote = "SELECT nombre  from mlotes where id=" . $this->lote . "; ";
        $this->arrp_lote = $this->bd->select($sql_lote);
        $this->nombre_lote = function_exists('mb_strtoupper')
            ? mb_strtoupper($this->arrp_lote[0]['nombre'], 'UTF-8')
            : strtoupper($this->arrp_lote[0]['nombre']);


        $sql_principal = " SELECT  codigo_animal,id_animal,nombre_animal,nombre_lote,estatus_animal,
                                    categoria_ingreso,categoria_futura,aptitud,raza,sexo,sexo_animal,
                                    to_char(f_nacimiento, 'DD-MM-YYYY') as f_nacimiento ,
                                    peso_nacimiento,nro_chapeta,anios,meses,dias   
        
         from v_list_animales_x_lotes where mfundos_id=" . $this->mfundos_id . " and lote=" . $this->lote . "  ";
    //   H::PrintR($sql_principal); exit;


        $this->arrp = $this->bd->select($sql_principal);

        $this->reg = 0;
    }


    function getEncabezado($x, $y)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", 10);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(160));
        $this->SetAligns(array('C'));

        $fecha = date("d/m/Y");

        $this->SetWidths(array(90, 40));
        $this->SetAligns(array('C', 'R'));
        // $this->SetTextColor(100,100, 255);
        if($this->nombre_fundo!=$this->cid_rif_fundo){
            $this->datos_fundo=$this->nombre_fundo . '  ' . $this->cid_rif_fundo;
        }else{
            $this->datos_fundo=$this->nombre_fundo;
        }
        $this->RowM( array( $this->datos_fundo, 'Fecha: ' . $fecha ) );

        $this->SetXY($x + 35, $y - 5);


            $this->SetJump(6);
            $this->SetWidths(array(120));
            $this->RowM(array('Listado de Animales en el Lote : '.$this->nombre_lote));
        
    }
    function  Pie_Pagina() {
        $this->SetFillColor(230,230,230);
       
        $this->SetTextColor( 0, 0, 100 );

        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY($x+10,330);
        $this->SetWidths(array(190));
        $this->SetAligns(array('R'));
        $this->RowM(array(utf8_decode (utf8_decode('Sistema SOVCA (https://global.ruted.net)'))));
    }
    function  Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", "8");
    }

    
    function RotatedText($x, $y, $txt, $angle){
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(0);
        }

    function Header()
    {
         $this->cab->poner_cabecera($this, $this->titulo, "p", "", "n");
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(60));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);

        /// 

        // $this->getEncabezado($x, $this->y1+5);
    } //end header

    function Cuerpo()
    {
        $puntero = 0;
        $contador = 0;

        $this->Pie_Pagina();


        $this->SetXY(10, 35);

        foreach ($this->arrp as $data) {

            if ($this->GetY() > 290) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY(10, 35);
            }

            if (($data['f_nacimiento']!='')&&($data['sexo']!='')){
                $sexo=$data['sexo'];                
                $cat_actual=$this->Cat_Actual($sexo,$data['f_nacimiento'],$this->mfundos_id,$this->t_ganaderia);
        
            }
         
            $this->SetBorder( 0 );
            $this->SetFillTable( 0 );
            $this->SetFont( 'ARIAL', '', '10' );
            $this->SetTextColor( 0, 0, 0 );

            $this->SetWidths( array(125 ) );
            $this->SetAligns( array( 'C') );
            $this->SetFillColor( 200, 250, 216 );
            //Fondo verde de celda
            $this->SetBorder( true );
            $this->SetJump( 8 );

            $this->RowMFillColor(array(utf8_decode('DATOS DEL ANIMAL')),true);
            $this->SetWidths(array(60, 30));
            $this->SetAligns(array('L', 'L'));
            $this->SetJump(5);
            $this->SetBorder(1);
             $x=160;
             $y=$this->GetY();
             $y1=$this->GetY();

            $this->SetBorder(true);

            $this->SetTextColor(0, 0, 0);
            if($data['nro_chapeta']!=""){
                $nro_arete=$data['nro_chapeta'];
            }else{
                $nro_arete='No Indicado';

            }
            $this->SetWidths(array(30,25,70,25,40));
            $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));


            $this->SetFont( 'ARIAL', 'B', '9' );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetBorder( true );
            $this->RowMFillColor(array(utf8_decode('CÓDIGO'), utf8_decode('N° ARETE'),'NOMBRE', 'SEXO','ESTATUS'), true);
            $this->SetAligns(array('C', 'C', 'C', 'C'));
            $this->SetTextColor( 50, 50, 150 );
             $this->SetFillColor( 200, 250, 216 );
            $this->RowMFillColor(array( $data['codigo_animal'],  $nro_arete,$data['nombre_animal'], $data['sexo_animal'],$data['estatus_animal']),true);
            $this->SetTextColor(0, 0, 0);
         
            $this->SetFont( 'ARIAL', 'B', '9' );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetBorder( true );

            $this->SetWidths(array(40,40,40,70));

            $this->SetAligns(array('C', 'C','C','C'));
            $this->SetJump(5);
            $this->SetBorder(true);

            $this->SetTextColor(0, 0, 0);
            $this->SetFillColor( 100, 200, 116 );

            $this->RowMFillColor(array('F. NACIMIENTO','EDAD','APTITUD', 'RAZA'),true);

            $this->SetTextColor( 0, 0, 150 );
           
            $edad="";
            $f_nac="";
            $cat_futura="";
            $cat_ingreso="";

            $aptitud="";
            if($data['aptitud']!=""){
                $aptitud=$data['aptitud'];
            }else{
                $aptitud='No Definida';

            }
            if($data['categoria_ingreso']!=""){
                $cat_ingreso=$data['categoria_ingreso'];
            }else{
                $cat_ingreso='No Definida';

            }
            if($data['categoria_futura']!=""){
                $cat_futura=$data['categoria_futura'];
            }else{
                $cat_futura='No Definida';

            }
            if($data['f_nacimiento']!=""){
                $f_nac=$data['f_nacimiento'];
            if($data['anios']>0){
                if($data['meses']>0){
                    if($data['meses']>1){
                        $edad=$data['anios'].utf8_decode(' Años y ').$data['meses'].' Meses';

                    }else{
                        $edad=$data['anios'].utf8_decode(' Años y ').$data['meses'].' Mes';

                    }
                }else{
                    $edad=$data['anios'].utf8_decode(' Años');
                }
                
            }else{
                $edad=$data['meses'].' Meses';

            }
        }else{
            $f_nac='No Definida';
            $edad= 'No Definida';
           }

           

           $this->SetBorder(true);
           $this->SetJump(5);
           $this->SetAligns(array('C', 'C','C','C'));

           $this->SetTextColor( 50, 50, 150 );
           $this->SetFillColor( 200, 250, 216 );
           $this->RowMFillColor(array($f_nac,$edad,$aptitud,$data['raza']),true);

            $this->SetAligns(array('C', 'C', 'C'));
            $this->SetWidths(array(63, 63, 64));
            $this->SetBorder(true);
            $this->SetJump(5);

            $this->SetTextColor(0, 0, 0);
            $this->SetFillColor( 100, 200, 116 );

            $this->RowMFillColor(array('CATEGORIA INGRESO','CATEGORIA ACTUAL', 'CATEGORIA FUTURA'),true);

            $this->SetTextColor( 50, 50, 150 );
            $this->SetFillColor( 200, 250, 216 );
            $this->RowMFillColor(array($cat_ingreso,$cat_actual,  $cat_futura),true);
            $this->SetTextColor(0, 0, 0);

            $this->SetWidths(array(40, 150));
            $this->SetAligns(array('C', 'L'));
            $this->SetJump(5);

                if ($this->GetY() > 320) {
                    $this->AddPage();
                    $this->SetXY(10, 35);
                }
            $this->Ln(10);
            $contador++;

        }

        $this->SetWidths( array( 160 ) );
        $this->SetAligns( array( 'L') );
        $this->SetFillColor( 200, 250, 216 );
        $this->SetFont( 'ARIAL', 'B', '12' );   
        $this->SetBorder( true );
        $this->SetJump( 8 );
        $this->RowMFillColor(array(utf8_decode('Nro Total de Animales en el Lote '.$this->nombre_lote).': '.$contador),true);
    } //end cuerpo



    
function Cat_Actual($sexo_categoria,$fecha_nac,$mfundos_id,$t_ganaderia){

    $edad = $this->tiempo_t($fecha_nac);

                            $sql_data_propia = "select nombre from mcategorias 
                            WHERE 
                            mtipoganaderias_id=".$t_ganaderia." and 
                            status=1 and 
                            sexo=".$sexo_categoria." and 
                            mfundos_id=".$mfundos_id." and
                            edadminima<=".$edad." and
                            edadmaxima> ".$edad."  order by id asc;";

                            // H::PrintR($sql_data_propia); exit;

                            $this->arrp_data_propia = $this->bd->select($sql_data_propia);

                            $sql_data_estandar = "select nombre from mcategorias 
                            WHERE 
                            mtipoganaderias_id=".$t_ganaderia." and 
                            status=1 and 
                            sexo=".$sexo_categoria." and 
                            mfundos_id=0 and
                            edadminima<=".$edad." and
                            edadmaxima> ".$edad."  order by id asc;";

                            $this->arrp_data_estandar = $this->bd->select($sql_data_estandar);        
       
  
  
        if ( $this->arrp_data_propia[0]['nombre'] != null ) {
  
               
            $this->cat_actual=$this->arrp_data_propia[0]['nombre'];
                
            
  
        }else if ( $this->arrp_data_estandar[0]['nombre'] != null ) {
  
               
            $this->cat_actual=$this->arrp_data_estandar[0]['nombre'];
        
        
  
        }
   return  $this->cat_actual;
  
  }
  
  function tiempo_t($fecha_nac)
  {
  $fecha_f = date("Y-m-d");
  $fecha_i = $fecha_nac;
  $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
  $dias = abs($dias);
  $dias = floor($dias);
  $meses = ($dias / 30);
  $meses = (int)$meses;
  $this->meses=$meses;
  return $this->meses;
  }
}



