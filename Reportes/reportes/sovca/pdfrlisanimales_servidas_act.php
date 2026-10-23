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

        $sql_principal = " SELECT    codigo_servicio,
                                   codigo_rgb_id_animal_hembra,
                                   imagen,nro_chapeta,
                                   fecha_servicio,
                                   nro_servicio,
                                   fecha_probable_palpacion,                                   
                                   estado_fisiologico,
                                   medofisiologicos_id,
                                   nombre_h,nombre_m,servicio,
                                   meses,dias
         from v_list_hembras_servidas where mfundos_id=" . $this->mfundos_id . "  order by  to_date(fecha_probable_palpacion,'DD-MM-YYYY') asc   ";
 

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
        if($this->nombre_fundo!=$this->cid_rif_fundo){
            $this->datos_fundo=$this->nombre_fundo . '  ' . $this->cid_rif_fundo;
        }else{
            $this->datos_fundo=$this->nombre_fundo;
        }
        $this->RowM( array( $this->datos_fundo, 'Fecha: ' . $fecha ) );

        $this->SetXY( $x + 35, $y - 5 );

        $this->SetJump( 6 );
        $this->SetWidths( array( 120 ) );
        $this->RowM( array( 'LISTADO DE HEMBRAS SERVIDAS' ) );

    }

    function  Pie_Pagina() {
        $this->SetFillColor( 230, 230, 230 );

        $this->SetTextColor( 0, 0, 100 );

        $this->SetFont( 'ARIAL', 'B', 7 );
        $this->SetXY( $x+10, 330 );
        $this->SetWidths( array( 190 ) );
        $this->SetAligns( array( 'R' ) );
        $this->RowM( array( utf8_decode (utf8_decode( 'Sistema SOVCA (https://global.ruted.net)' ) ) ) );
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

            $this->SetBorder( 0 );
            $this->SetFillTable( 0 );
            $this->SetFont( 'ARIAL', '', '10' );
            $this->SetTextColor( 0, 0, 0 );

            $this->SetWidths( array( 100 ) );
            $this->SetAligns( array( 'C') );
            $this->SetFillColor( 200, 250, 216 );
            //Fondo verde de celda
            $this->SetBorder( true );
            $this->SetJump( 8 );
            $this->RowMFillColor( array( utf8_decode( 'DATOS DE LA HEMBRA' ) ),true );
        
            $x = 160;
            $y = $this->GetY();
            $y1 = $this->GetY();

            $dias=$data[ 'dias' ];
            $meses=$data[ 'meses' ];

            if ( $meses>0 ) {
                if ( $meses == 1 ) {
                    $dias_pendientes = $meses.'Mes y '.$data[ 'dias' ].' Días';
                } else {
                    $dias_pendientes = $meses.'Meses y '.$data[ 'dias' ].' Días';

                }
            } else {

             if ($meses == 0){
                if($data[ 'dias' ]>0){
                    $dias_pendientes = $data[ 'dias' ].' Días';
                }else{
                    $dias=$data[ 'dias' ]*(-1);
                    $dias_pendientes = 'Presenta un ATRASO de '.$dias.' Días';

                }

             }else{
                $mes=$data[ 'meses' ]*(-1);
                $dia=$data[ 'dias' ]*(-1);

                if ( $mes == 1 ) {
                    if($dia==1){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Mes y '.$dia.' Día';
                    }else   if($dia==0){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Mes';
                    }else   if($dia>1){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Mes y '.$dia.' Días';
                    } 
                } else {

                    if($dia==1){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Meses y '.$dia.' Día';
                    }else   if($dia==0){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Meses';
                    }else   if($dia>1){
                        $dias_pendientes ='Presenta un ATRASO de '.$mes.' Meses y '.$dia.' Días';
                    } 

                }



             }
            
            }

            if ( $data[ 'medofisiologicos_id' ] == 2 || $data[ 'medofisiologicos_id' ] == 3 ) {
                $fecha_p_palpacion = $data[ 'fecha_probable_palpacion' ];
            } else {
                $fecha_p_palpacion = 'No Aplica';
            }


            $this->SetBorder( true );
            $this->Ln( 2 );
            $this->SetWidths( array( 50, 50,90 ) );
            $this->SetAligns( array( 'C', 'C' , 'C' ) );
            $this->SetJump( 5 );
            $this->SetBorder( 1 );
            $this->SetFillColor( 100, 200, 116 );

            $this->RowMFillColor( array( utf8_decode( 'CÓDIGO' ), utf8_decode( 'N° ARETE' ), 'NOMBRE' ),true );
            $this->SetTextColor( 0, 0, 150 );
            $this->SetAligns( array( 'C', 'C' , 'C' ) );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );

            $this->RowMFillColor( array( $data[ 'codigo_rgb_id_animal_hembra' ], $data[ 'nro_chapeta' ],$data[ 'nombre_h' ] ),true );
            $this->SetTextColor( 0, 0, 0 );
            $this->SetWidths( array( 35,20,70,65 ) );

            $this->SetAligns( array( 'C', 'C' , 'C', 'C' ) );
            $this->SetBorder( true );

            $this->SetFillColor( 100, 200, 116 );
            $this->SetJump( 5 );

            $this->SetTextColor( 0, 0, 0 );
            $this->RowMFillColor( array(utf8_decode( 'Fecha Servicio' ), utf8_decode( 'N° Servicio' ), utf8_decode( 'Tipo de Servicio' ), utf8_decode( 'Estado Fisiológico ') ),true );

            $this->SetTextColor( 0, 0, 150 );
            // $this->SetAligns( array( 'C', 'C' , 'C' ) );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );

            $this->RowMFillColor( array($data[ 'fecha_servicio' ],$data[ 'nro_servicio' ],$data[ 'servicio' ], $data[ 'estado_fisiologico' ] ),true );
            $this->SetWidths( array( 50,140 ) );

            $this->SetAligns( array( 'C', 'C' ) );
            $this->SetBorder( true );
            $this->SetFillColor( 100, 200, 116 );
            $this->SetJump( 5 );

            $this->SetTextColor( 0, 0, 0 );
            $this->RowMFillColor( array( utf8_decode( 'Fecha Probable de Palpación' ), utf8_decode( 'Tiempo estimado para Palpar' ) ),true );
            $this->SetAligns( array( 'C', 'C' ) );

            $this->SetTextColor( 0, 0, 150 );
            $this->SetFillColor( 200, 250, 216 );
            $this->SetJump( 6 );

            $this->RowMFillColor( array( $fecha_p_palpacion, utf8_decode( $dias_pendientes ) ),true );

            $this->SetTextColor( 0, 0, 0 );

  
            $this->SetJump( 5 );

            if ( $this->GetY() > 320 ) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY( 10, 35 );

            }
            $this->Ln( 15 );
            $contador++;
        }
        $this->SetWidths( array( 140 ) );
        $this->SetAligns( array( 'C') );
        $this->SetFillColor( 200, 250, 216 );
        $this->SetFont( 'ARIAL', 'B', '13' );   
        $this->SetBorder( true );
        $this->SetJump( 8 );
        $this->RowMFillColor(array(utf8_decode('Nro Total de Hembras Servidas Pendientes por Palpar').': '.$contador),true);

    }
    //end cuerpo

}
