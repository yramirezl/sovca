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


        $sql_fundo = "SELECT nombre, cid_rif  from mfundos where id=" . $this->mfundos_id . "; ";
        $this->arrp_fundo = $this->bd->select($sql_fundo);
        $this->nombre_fundo = $this->arrp_fundo[0]['nombre'];
        $this->cid_rif_fundo = $this->arrp_fundo[0]['cid_rif'];

    


        $sql_principal = " SELECT  distinct(nro_arete),sexo,fecha,anios,meses,dias  
        
         from v_grupos_etarios where mfundos_id=" . $this->mfundos_id . "  and fecha is not Null
                                    order by sexo,anios,meses,dias ";
        // H::PrintR($sql_principal); exit;


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

        $this->SetXY($x + 15, $y + 5);

        $this->SetJump(8);
        $this->SetWidths(array(150));
        $this->SetFont("ARIAL", "B", 16);


        $this->RowM(array('REPORTE GENERAL POR GRUPOS ETARIOS '));
        
    }
    function  Pie_Pagina() {
        $this->SetFillColor(230,230,230);
       
        $this->SetTextColor( 0, 0, 100 );

        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY(10,330);
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
        $nro_hembras_menor_3_meses=0;
        $nro_hembras_de_3_9_meses=0;
        $nro_hembras_de_9_12_meses=0;
        $nro_hembras_de_1_2_anios=0;
        $nro_hembras_de_2_3_anios=0;
        $nro_hembras_de_3_5_anios=0;
        $nro_hembras_mayor_5_anios=0;
        $total_hembras=0;

        $nro_machos_menor_3_meses=0;
        $nro_machos_de_3_9_meses=0;
        $nro_machos_de_9_12_meses=0;
        $nro_machos_de_1_2_anios=0;
        $nro_machos_de_2_3_anios=0;
        $nro_machos_mayor_3_anios=0;
        $total_machos=0;
        $total_animales_activos=0;



        $this->Pie_Pagina();


        $this->SetXY(30, 50);
        $this->SetBorder( 0 );
        $this->SetFillTable( 0 );
        $this->SetFont( 'ARIAL', '', '14' );
        $this->SetTextColor( 0, 0, 0 );

        $this->SetWidths( array(125 ) );
        $this->SetAligns( array( 'C') );
        $this->SetFillColor( 200, 250, 216 );
        //Fondo verde de celda
        $this->SetBorder( true );
        $this->SetJump( 9 );

        $this->RowMFillColor(array(utf8_decode('CATEGORIAS ETARIAS')),true);
      
    
        foreach ($this->arrp as $data) {
          
              // sexo,fecha,anios,meses,dias
            if ($data['fecha']!=''){

                if ($data['sexo']==1){

                    if ($data['anios']==0){                 
                        if ($data['meses']<=3){   
                            
                            $nro_hembras_menor_3_meses++;


                        }else if ( ($data['meses']>3) && ( $data['meses']<=9) ){
                                $nro_hembras_de_3_9_meses++;

                            }   else if  ($data['meses']>9) {
                                $nro_hembras_de_9_12_meses++;

                                    }            

                      

                    }else{

                        if (($data['anios']>=1) && ($data['anios']<=2) ){
                            $nro_hembras_de_1_2_anios++;

                        }else if (($data['anios']>2) && ($data['anios']<=3) ){
                            $nro_hembras_de_2_3_anios++;

                        } else if (($data['anios']>3) && ($data['anios']<=5) ){
                            $nro_hembras_de_3_5_anios++;

                        } else if ($data['anios']>5){
                            $nro_hembras_mayor_5_anios++;

                        } 
                    }
                    
                    $total_hembras++;

                }else  if ($data['sexo']==2){

                    if ($data['anios']==0){  

                        if ($data['meses']<=3){   
                            
                            $nro_machos_menor_3_meses++;


                        }else  if ( ($data['meses']>3) && ( $data['meses']<=9) ){
                                $nro_machos_de_3_9_meses++;

                            }    else if  ($data['meses']>9) {
                                $nro_machos_de_9_12_meses++;

                                    }            


                    

                    }else  if (($data['anios']>=1) && ($data['anios']<=2) ){
                            $nro_machos_de_1_2_anios++;

                        }else if (($data['anios']>2) && ($data['anios']<=3) ){
                            $nro_machos_de_2_3_anios++;

                        }  else if ($data['anios']>3){
                            $nro_machos_mayor_3_anios++;

                        } 
                    

                    $total_machos++;

                }


            





            }
  
        

            
        }
        $total_animales_activos=$total_machos+$total_hembras;


        $this->SetWidths(array(50,25,50,25));
        $this->SetAligns(array('C', 'C', 'C', 'C'));

        $this->SetX(30);
        $this->SetJump( 9 );

        $this->SetFont( 'ARIAL', 'B', '12' );
        $this->SetFillColor( 100, 200, 116 );
        $this->SetBorder( true );
        $this->RowMFillColor(array(utf8_decode('HEMBRAS'), utf8_decode('Total'),'MACHOS', utf8_decode('Total')), true);
        $this->SetAligns(array('L', 'C', 'L', 'C'));
        $this->SetFillColor( 200, 250, 216 );
        $this->SetX(30);
        $this->SetFont( 'ARIAL', '', '11' );

        $this->RowMFillColor(array(utf8_decode('MENOR A 3 MESES'),     $nro_hembras_menor_3_meses,'MENOR A 3 MESES',     $nro_machos_menor_3_meses), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('DE 3 HASTA 9 MESES'),  $nro_hembras_de_3_9_meses,'DE 3 HASTA 9 MESES',   $nro_machos_de_3_9_meses), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('DE 9 HASTA 12 MESES'), $nro_hembras_de_9_12_meses,'DE 9 HASTA 12 MESES', $nro_machos_de_9_12_meses), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('DE 1 HASTA 2 AÑOS'),   $nro_hembras_de_1_2_anios,utf8_decode('DE 1 HASTA 2 AÑOS'),    $nro_machos_de_1_2_anios), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('DE 2 HASTA 3 AÑOS'),   $nro_hembras_de_2_3_anios,utf8_decode('DE 2 HASTA 3 AÑOS'),    $nro_machos_de_2_3_anios), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('DE 3 HASTA 5 AÑOS'),   $nro_hembras_de_3_5_anios,utf8_decode('MAYOR A 3 AÑOS'),       $nro_machos_mayor_3_anios), true);
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('MAYOR A 5 AÑOS'),      $nro_hembras_mayor_5_anios,'',                   ''), true);
        $this->SetX(30);

        $this->SetAligns(array('C', 'C', 'C', 'C'));
        $this->SetFillColor( 100, 200, 116 );
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('TOTAL'), $total_hembras,'TOTAL', $total_machos), true);


     
       
        $this->SetWidths( array( 150 ) );
        $this->SetAligns( array( 'L') );
        $this->SetFillColor( 100, 250, 216 );
        $this->SetFont( 'ARIAL', 'B', '12' );   
        $this->SetBorder( true );
        $this->SetJump( 8 );
        $this->SetX(30);

        $this->RowMFillColor(array(utf8_decode('Nro Total de Animales Activos ').': '.$total_animales_activos),true);
    } //end cuerpo
}
