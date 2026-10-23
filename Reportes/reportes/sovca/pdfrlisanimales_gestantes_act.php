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
        $this->fpdf( 'p', 'mm', 'legal' );
        $this->bd = new baseClases();
        $this->cab = new Cabecera();

        $this->mfundos_id = 0;
        session_start();
        $this->mfundos_id = $_SESSION[ 'mfundos_id' ];
        $sql_fundo = 'SELECT nombre, cid_rif  from mfundos where id=' . $this->mfundos_id . '; ';
        $this->arrp_fundo = $this->bd->select( $sql_fundo );
        $this->nombre_fundo = $this->arrp_fundo[ 0 ][ 'nombre' ];
        $this->cid_rif_fundo = $this->arrp_fundo[ 0 ][ 'cid_rif' ];
        $sql_principal = " SELECT  codigo_animal,nombre_animal,
                                    nro_chapeta,imagen,fecha_servicio,
                                    fecha_p_parto,servicio,meses,dias,nro_servicio
        
         from v_list_gestantes_activas where mfundos_id=" . $this->mfundos_id . "  order by to_date(fecha_p_parto,'DD-MM-YYYY') asc  ";
            //   H::PrintR($sql_principal);exit;
      

        $this->arrp = $this->bd->select( $sql_principal );

        $this->reg = 0;
    }

    function getEncabezado( $x, $y )
 {
        $this->SetFillColor( 230, 230, 230 );
        $this->SetFont( 'ARIAL', 'B', 10 );
        $this->SetXY( $x + 0, $y - 20 );
        $this->SetWidths( array( 190 ) );
        $this->SetAligns( array( 'C' ) );
        $this->SetBorder( 1 );
        $this->SetJump( 12 );
        $this->RowM( array( '' ) );
        $this->SetXY( $x + 60, $y - 15 );
        $this->SetWidths( array( 160 ) );
        $this->SetAligns( array( 'C' ) );

        $fecha = date( 'd/m/Y' );

        $this->SetWidths( array( 90, 40 ) );
        $this->SetAligns( array( 'C', 'R' ) );
        // $this->SetTextColor( 100, 100, 255 );

        $this->RowM( array( $this->nombre_fundo . '  ' . $this->cid_rif_fundo, 'Fecha: ' . $fecha ) );

        $this->SetXY( $x + 35, $y - 5 );

        $this->SetJump( 6 );
        $this->SetWidths( array( 120 ) );
        $this->RowM( array( 'LISTADO DE HEMBRAS GESTANTES ACTIVAS' ) );

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

    function RotatedText( $x, $y, $txt, $angle ) {
        $this->Rotate( $angle, $x, $y );
        $this->Text( $x, $y, $txt );
        $this->Rotate( 0 );
    }

    function  Footer()
 {
        $this->y2 = 257;
        $this->SetFillColor( 230, 230, 230 );
        $this->SetFont( 'ARIAL', 'B', '8' );
    }

    function Header()
 {
        $this->cab->poner_cabecera( $this, $this->titulo, 'p', '', 'n' );
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado( $x, $y );
        $this->SetWidths( array( 60 ) );
        $this->SetAligns( array( 'L' ) );
        $this->SetXY( 10, 23 );

        ///

    }
    //end header

    function Cuerpo()
 {
        $puntero = 0;
        $contador = 0;
        $this->Pie_Pagina();

        $this->SetXY( 10, 35 );

        foreach ( $this->arrp as $data ) {

            if ( $this->GetY() > 290 ) {
                $this->AddPage();
                $this->Pie_Pagina();
                $this->SetXY( 10, 35 );
            }
            $this->SetFont( 'ARIAL', '', '10' );
            $this->SetWidths( array( 100 ) );
            $this->SetAligns( array( 'C') );
            $this->SetFillColor( 200, 250, 216 );
            //Fondo verde de celda
            $this->SetBorder( true );
            $this->SetJump( 8 );
            $this->RowMFillColor( array( utf8_decode( 'DATOS DE LA HEMBRA' ) ),true );
            $this->SetWidths( array( 90, 60 ) );
            $this->SetAligns( array( 'L', 'L' ) );
            $this->SetJump( 5 );
            $this->SetBorder( 1 );
            $x = 160;
            $y = $this->GetY();
            $y1 = $this->GetY();

  

            $this->SetTextColor( 0, 0, 0 );
            $this->SetFont( 'ARIAL', 'B', '9' );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetWidths( array( 50, 50,90 ) );
            $this->SetAligns( array( 'C', 'C' , 'C' ) );
            $this->SetBorder( true );

            $this->RowMFillColor( array( utf8_decode( 'CÓDIGO' ), utf8_decode( 'N° ARETE' ), 'NOMBRE' ),true );
            $this->SetTextColor( 0, 0, 150 );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );
            $this->SetTextColor( 0, 0, 150 );

            $this->RowMFillColor( array( '   '.$data[ 'codigo_animal' ], '   '.$data[ 'nro_chapeta' ],$data[ 'nombre_animal' ] ) ,true);
            $this->SetTextColor( 0, 0, 0 );
            $this->SetFillColor( 100, 200, 116 );

            $this->RowMFillColor( array( utf8_decode( 'N° SERVICIO' ),utf8_decode( 'FECHA DEL SERVICIO' ), utf8_decode( 'DIAS DE GESTACIÓN' ) ) ,true);
       
            $fecha = '';
            if ( $data[ 'meses' ]>0 ) {
                $fecha = $data[ 'meses' ].' Meses y '.$data[ 'dias' ].' Dias';
            } else {
                $fecha = $data[ 'dias' ].' Dias';

            }

            $this->SetJump( 6 );
            $this->SetTextColor( 0, 0, 150 );
            $this->SetFillColor( 200, 250, 216 );

            $this->RowMFillColor( array($data[ 'nro_servicio' ] ,$data[ 'fecha_servicio' ], '   '.$fecha ),true );
            $this->SetWidths( array( 100,90) );

            $this->SetAligns( array( 'C', 'C' ) );

            $this->SetTextColor( 0, 0, 0 );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetBorder( true );

            $this->RowMFillColor( array( utf8_decode( 'TIPO DE SERVICIO' ), utf8_decode( 'FECHA PROBABLE DEL PARTO' ) ),true );
            $this->SetAligns( array( 'C', 'C' ) );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );
            $this->SetTextColor( 0, 0, 150 );

            $this->RowMFillColor( array( '   '.$data[ 'servicio' ], '   '.$data[ 'fecha_p_parto' ] ) ,true);

            $this->SetTextColor( 0, 0, 0 );
            $this->SetJump( 5 );

            if ( $this->GetY() > 320 ) {
                $this->AddPage();
                $this->SetXY( 10, 35 );
            }
            $this->Ln( 15 );
            $contador++;
        }
        $this->SetWidths( array( 120 ) );
        $this->SetAligns( array( 'L' ) );
        $this->SetFont( 'Arial', '', 15 );
        $this->SetFillColor( 100, 200, 116 );
        $this->SetBorder( true );
        $this->SetJump( 6 );

        $this->SetTextColor( 0, 0, 0 );
        $this->RowMFillColor( array( utf8_decode( 'Nro Total de Hembras Gestantes ' ).': '.$contador ),true );

    }
    //end cuerpo

}
