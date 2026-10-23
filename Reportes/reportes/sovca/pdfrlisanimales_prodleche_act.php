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
        $sql_principal = " SELECT  codigo_animal,nombre_animal,
                                    nro_chapeta,imagen,prod_minima,prod_max   
        
         from v_list_prodleche_activos where mfundos_id=" . $this->mfundos_id . "    ";
        //   H::PrintR($sql_principal);
        //   exit; 

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

        $this->RowM(array($this->nombre_fundo . '  ' . $this->cid_rif_fundo, 'Fecha: ' . $fecha));

        $this->SetXY($x + 35, $y - 5);


            $this->SetJump(6);
            $this->SetWidths(array(120));
            $this->RowM(array('LISTADO DE PRODUCTORAS DE LECHE ACTIVAS'));
        
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
        function  Pie_Pagina() {
          
            $this->SetTextColor( 0, 0, 100 );
    
            $this->SetFont("ARIAL", "B", 7);
            $this->SetXY($x+10,330);
            $this->SetWidths(array(190));
            $this->SetAligns(array('R'));
            $this->RowM(array(utf8_decode (utf8_decode('Sistema SOVCA (https://global.ruted.net)'))));
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
            $cod_animal=$data['codigo_animal'];
            $sql_promedio = "SELECT   sum(cantidad_leche_m+cantidad_leche_t) as cantidad_leche,count (fecha_produccion) as cantidad_dias from v_rep_prolecheras where codigo_animal='$cod_animal' ";
     
            $this->arrp_sql_promedio = $this->bd->select($sql_promedio);
            $cant_dias=$this->arrp_sql_promedio[0]['cantidad_dias'];
            $cant_leche=$this->arrp_sql_promedio[0]['cantidad_leche'];

            $promedio=0;
            if ($cant_dias>0){
                $promedio =$cant_leche/$cant_dias ;

            }
         
            $this->SetFont( 'ARIAL', '', '10' );
            $this->SetWidths( array( 100 ) );
            $this->SetAligns( array( 'C') );
            $this->SetFillColor( 200, 250, 216 );
            //Fondo verde de celda
            $this->SetBorder( true );
            $this->SetJump( 8 );

            $this->RowMFillColor( array( utf8_decode( 'DATOS DE LA HEMBRA' ) ),true );
         
          
            $this->SetTextColor( 0, 0, 0 );
            $this->SetFont( 'ARIAL', 'B', '9' );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetWidths(array(50,50, 90));
            $this->SetAligns( array( 'C', 'C' , 'C' ) );
            $this->SetBorder( true );

            $this->RowMFillColor( array( utf8_decode( 'CÓDIGO' ), utf8_decode( 'N° ARETE' ), 'NOMBRE' ),true );
            $this->SetTextColor( 0, 0, 150 );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );
            $this->SetTextColor( 0, 0, 150 );

            $this->RowMFillColor( array( '   '.$data[ 'codigo_animal' ], '   '.$data[ 'nro_chapeta' ],$data[ 'nombre_animal' ] ) ,true);
            $this->SetBorder( true );

            $this->SetFillColor( 100, 200, 116 );

            $this->SetWidths(array(40,40,30,80));
            $this->SetAligns(array('C','C', 'C', 'C'));
            $this->SetBorder(1);
            $this->SetJump(5);
            $this->RowMFillColor(array(utf8_decode('Producción Mínima'),utf8_decode('Producción Máxima'),'Promedio',utf8_decode('TOTAL PRODUCCIÓN')),true);

            $this->SetFillColor( 200, 250, 216 );
            $this->SetTextColor( 0, 0, 150 );
            $this->SetWidths(array(40,40,30,40,40));
            $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
            $this->SetBorder( true );
            $this->SetJump(6);
            $this->RowMFillColor(array($data['prod_minima'].' Kgs',$data['prod_max'].' Kgs',H::FormatoMonto($promedio).' Kgs',$cant_dias.utf8_decode(' Días'),$cant_leche.'Kgs. '),true);

    
            $this->SetJump(5);

                if ($this->GetY() > 320) {
                    $this->AddPage();
                    $this->SetXY(10, 35);
                }
            $this->Ln(15);
        
            $contador++;
        }

        $this->SetWidths(array(190));
        $this->SetAligns(array('L'));
        $this->SetFont('Arial','',15);      
        $this->SetTextColor(0, 0, 0);
        $this->SetJump(10);

        $this->RowMFillColor(array(utf8_decode('Nro Total de Productoras de Leche Activas ').': '.$contador),true);
        
    } //end cuerpo


}
