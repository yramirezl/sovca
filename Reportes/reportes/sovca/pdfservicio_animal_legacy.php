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

    function getEncabezado( $x, $y )
 {
        $this->SetFillColor( 230, 230, 230 );
        $this->SetBorder( 1 );
        $this->SetDrawColor( 2, 157, 116 );
        //Fondo verde de celda

        $this->SetFont( 'ARIAL', 'B', 12 );
        $this->SetXY( $x + 0, $y - 20 );
        $this->SetWidths( array( 320 ) );
        $this->SetAligns( array( 'C' ) );
        $this->SetBorder( 1 );
        $this->SetJump( 12 );
        $this->RowM( array( '' ) );
        $this->SetXY( $x + 60, $y - 15 );
        // $this->SetWidths( array( 250 ) );
        // $this->SetAligns( array( 'C' ) );

        $fecha = date( 'd/m/Y' );

        $this->SetWidths( array( 200, 50 ) );
        $this->SetAligns( array( 'C', 'R' ) );
        // $this->SetTextColor( 100, 100, 255 );

        $this->RowM( array( $this->nombre_fundo . '  ' . $this->cid_rif_fundo, 'Fecha: ' . $fecha ) );

        $this->SetXY( $x + 35, $y  );

        if ( $this->cadena_rango_fecha != '' ) {
            $this->SetXY( $x + 15, $y );

            $this->SetJump( 6 );
            $this->SetWidths( array( 250 ) );

            $this->RowM( array( 'REPORTE DE SERVICIO ANIMAL ' . $this->cadena_rango_fecha ) );
        } else {

            $this->SetJump( 6 );
            $this->SetWidths( array( 250 ) );
            $this->RowM( array( 'INFORME DETALLADO DE SERVICIO ANIMAL' ) );
        }
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

        $this->SetXY( 10, 50 );

        foreach ( $this->arrp as $data ) {

            $sexo_animal = $data[ 'sexo_animal' ];
            $filtrar_sexo = $this->filtrar_sexo;

            if ( $this->GetY() > 290 ) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY( 10, 35 );
            }

            $this->SetBorder( 0 );
            // $this->SetFillTable( 0 );

            if ( $filtrar_sexo != '' ) {

                if ( $sexo_animal == 1 ) {
                    $cod_hembra = $data[ 'codigo_rgb_id_animal_hembra' ];
                    $this->SetFont( 'ARIAL', 'B', '14' );
                    $this->SetWidths( array( 180 ) );
                    $this->SetAligns( array( 'C' ) );
                    $this->SetFillTable( 1 );
                    $this->SetFillColor(  2, 223, 173  );

                    //Fondo verde de celda
                    $this->SetBorder( true );
                    $this->SetJump( 10 );
                    $this->RowM( array( utf8_decode( 'Datos de la Hembra' ) ) );

                    $this->SetDrawColor( 200, 157, 116 );
                    //Fondo verde de celda
                    $this->SetTextColor( 0, 0, 0 );

                    $this->SetWidths( array( 40, 40, 100, 40, 40, 30, 30 ) );

                    $this->SetAligns( array( 'C', 'C', 'C', 'C', 'C', 'C', 'C' ) );
                    $this->SetDrawColor( 2, 157, 116 );
                    //Fondo verde de celda

                    //Fondo verde de celda
                    $this->SetBorder( true );
                    $this->SetFont( 'ARIAL', 'B', '11' );
                    $this->SetJump( 6 );
                    // $this->SetFillColor( 250, 250, 200 );
                    $this->SetFillColor( 250, 180, 180 );

                    $this->SetDrawColor( 0, 0, 0 );
                    $this->SetBorder( true );
                    $this->SetTextColor( 0, 0, 0 );
                    $this->SetJump( 8 );

                    $this->RowMFillColor( array( utf8_decode( 'Código' ), utf8_decode( 'N° Arete' ), 'Nombre', 'Total Servicios', 'Total Gestaciones', 'Total Abortos', utf8_decode( 'Total Vacías' ) ), true );
                    $this->SetFillColor( 200, 250, 216 );
                    $this->SetAligns( array( 'L', 'C', 'L', 'C', 'C', 'C', 'C' ) );
                    $this->SetFont( 'ARIAL', 'B', '10' );

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    // CALCULO DE TOTALES
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_max = "SELECT  COUNT(codigo_servicio) as nro_total from mregservicios  
                    where codigo_rgb_id_animal_hembra='$cod_hembra' and status=1;  ";
                    $this->arrp_sql_max = $this->bd->select( $sql_max );
                    $total_servicio = $this->arrp_sql_max[ 0 ][ 'nro_total' ];

                    $sql_max_1 = "SELECT  COUNT(codigo_servicio) as nro_total from mregservicios  
                    where codigo_rgb_id_animal_hembra='$cod_hembra' and
			                medofisiologicos_id=1 and status=1;  ";
                    $this->arrp_sql_max_1 = $this->bd->select( $sql_max_1 );
                    $total_gestaciones = $this->arrp_sql_max_1[ 0 ][ 'nro_total' ];

                    $sql_max_2 = "SELECT  COUNT(codigo_servicio) as nro_total from mregservicios  
                    where codigo_rgb_id_animal_hembra='$cod_hembra' and
			                medofisiologicos_id=4 and status=1;  ";
                    $this->arrp_sql_max_2 = $this->bd->select( $sql_max_2 );
                    $total_aborto = $this->arrp_sql_max_2[ 0 ][ 'nro_total' ];

                    $sql_max_3 = "SELECT  COUNT(codigo_servicio) as nro_total from mregservicios  
                    where codigo_rgb_id_animal_hembra='$cod_hembra' and
			                medofisiologicos_id=5 and status=1;  ";
                    $this->arrp_sql_max_3 = $this->bd->select( $sql_max_3 );
                    $total_vacia = $this->arrp_sql_max_3[ 0 ][ 'nro_total' ];

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $this->SetFillColor( 148, 247, 224  );

                    //Fondo verde de celda
                    $this->RowMFillColor( array( $data[ 'codigo_rgb_id_animal_hembra' ], $data[ 'nro_chapeta' ], $data[ 'nombre_h' ], $total_servicio, $total_gestaciones, $total_aborto, $total_vacia ), true );
                    $this->SetTextColor( 0, 0, 0 );

                    $this->SetWidths( array( 30, 50, 40, 50, 40, 30, 50, 30 ) );

                    $this->SetAligns( array( 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C' ) );
                    //Fondo verde de celda
                    $this->SetFillColor( 250, 180, 180 );

                    //Fondo verde de celda
                    $this->SetBorder( true );
                    $this->SetFont( 'ARIAL', 'B', '10' );
                    $this->SetJump( 6 );

                    $this->RowMFillColor( array( '', utf8_decode( 'Efectividad Monta/ Gest' ), utf8_decode( 'Efectividad IA/ Gest' ), 'Efectividad TE/ Gest', utf8_decode( 'DNP Óptimo' ), utf8_decode( 'IEP Óptimo' ), utf8_decode( '% Gestación/Parió' ), utf8_decode( '% Aborto' ) ), true );

                    $sql_meta = "	SELECT  nombre,periodo,condicion,codmeta_bov,uni_medida                                    
                                from mmetaservicios		
                                where
                                mfundos_id=".$this->mfundos_id." and status>0
                                        union					   
                                        SELECT  nombre,periodo,condicion,codmeta_bov,uni_medida                                    
                                            from mmetaservicios		
                                            where
                                            mfundos_id=0 and status>0 and
                                        codmeta_bov	NOT in (select DISTINCT b.codmeta_bov 
                                            from mmetaservicios b where b.mfundos_id=5 and b.status>0);  ";

                    $this->arrp_sql_sql_meta = $this->bd->select( $sql_meta );

                    foreach ( $this->arrp_sql_sql_meta as $data_meta ) {
                        if ( $data_meta[ 'codmeta_bov' ] == 'MMG' ) {
                            // Meta Monta/ Gestación
                            $per_mmg = $data_meta[ 'periodo' ];
                            $condicion_mmg = $data_meta[ 'condicion' ];
                            $uni_medida_mmg = $data_meta[ 'uni_medida' ];
                            $valores_MMG = $condicion_mmg.' '.$per_mmg;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'DNPOG' ) {
                            // Días No Productivos Óptimos

                            $per_dnp = $data_meta[ 'periodo' ];
                            $condicion_dnp = $data_meta[ 'condicion' ];
                            $uni_medida_dnp = $data_meta[ 'uni_medida' ];
                            $valores_DNPOG = $condicion_dnp.' '.$per_dnp.' '.$uni_medida_dnp;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'PMA' ) {
                            //  Porcentaje Maximo de Abortos

                            $per_pma = $data_meta[ 'periodo' ];
                            $condicion_pma = $data_meta[ 'condicion' ];
                            $uni_medida_pma = $data_meta[ 'uni_medida' ];
                            $valores_PMA = $condicion_pma.' '.$per_pma.' '.$uni_medida_pma;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'MTEG' ) {
                            //  Meta Tranferencia de Embriones/ Gestación

                            $per_mteg = $data_meta[ 'periodo' ];
                            $condicion_mteg = $data_meta[ 'condicion' ];
                            $uni_medida_mteg = $data_meta[ 'uni_medida' ];
                            $valores_MTEG = $condicion_mteg.' '.$per_mteg;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'POGP' ) {
                            //  Porcentaje Óptimo de Gestación/ Parió

                            $per_pogp = $data_meta[ 'periodo' ];
                            $condicion_pogp = $data_meta[ 'condicion' ];
                            $uni_medida_pogp = $data_meta[ 'uni_medida' ];
                            $valores_POGP = $condicion_pogp.' '.$per_pogp.' '.$uni_medida_pogp;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'IEPO' ) {
                            //  Intervalo Entre Partos Óptimo

                            $per_iepo = $data_meta[ 'periodo' ];
                            $condicion_iepo = $data_meta[ 'condicion' ];
                            $uni_medida_iepo = $data_meta[ 'uni_medida' ];
                            $valores_IEPO = $condicion_iepo.' '.$per_iepo.' '.$uni_medida_iepo;

                        } else if ( $data_meta[ 'codmeta_bov' ] == 'MIAG' ) {
                            //  Meta Inseminación Artificial/ Gestación
                            $per_miag = $data_meta[ 'periodo' ];
                            $condicion_miag = $data_meta[ 'condicion' ];
                            $uni_medida_miag = $data_meta[ 'uni_medida' ];
                            $valores_MIAG = $condicion_miag.' '.$per_miag;
                        }

                    }

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    // CALCULO DE DETALLES
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
       
                    $sql_EMG = "SELECT 
                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=1 and  status=1) as num_monta,
                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=1 and  medofisiologicos_id=1 ) as num_gesta

                                        from mregservicios
                                            where 
                                            codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=1 and  status=1;  ";
                        //  H::PrintR( $sql_EMG );
                        // exit;    
                    $this->arrp_sql_EMG = $this->bd->select( $sql_EMG );
                    $total_emg_monta = $this->arrp_sql_EMG[ 0 ][ 'num_monta' ];
                    $total_emg_gesta = $this->arrp_sql_EMG[ 0 ][ 'num_gesta' ];


                    $efec_emg="";
                    if ( $total_emg_monta>0 ) {
                        if( $total_emg_gesta>0){
                            $efec_emg = number_format( ($total_emg_monta/ $total_emg_gesta),1 );

                        }else{
                            $efec_emg = number_format( ( $total_emg_gesta),1 );
                        }
                    }

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_EIAG = "SELECT 

                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=2 and  status=1) as num_monta,
                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=2 and  medofisiologicos_id=1 ) as num_gesta

                                from mregservicios
                                where 
                                codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=2 and  status=1;  ";

                        // H::PrintR( $sql_EIAG );
                        // exit;   
                    $this->arrp_sql_EIAG = $this->bd->select( $sql_EIAG );
                    $total_eiag_monta = $this->arrp_sql_EIAG[ 0 ][ 'num_monta' ];
                    $total_eiag_gesta = $this->arrp_sql_EIAG[ 0 ][ 'num_gesta' ];

                     $efec_eiag = "";


                    if ( $total_eiag_monta>0 ) {
                        if($total_eiag_gesta>0){
                            $efec_eiag = number_format( ($total_eiag_monta/$total_eiag_gesta),1 );

                        }else{
                            $efec_eiag = number_format( ($total_eiag_gesta),1 );
                        }
                    } 

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_TEG = "SELECT 
                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=3 and  status=1) as num_monta,
                                    (select count (codigo_servicio)  from mregservicios where codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=3 and  medofisiologicos_id=1 ) as num_gesta
                                     from mregservicios
                                        where 
                                        codigo_rgb_id_animal_hembra='$cod_hembra' and mservicios_id=3 and  status=1;  ";
                    $this->arrp_sql_TEG = $this->bd->select( $sql_TEG );
                    $total_teg_monta = $this->arrp_sql_TEG[ 0 ][ 'num_monta' ];
                    $total_teg_gesta = $this->arrp_sql_TEG[ 0 ][ 'num_gesta' ];

                     $efec_teg = "";
                    if ( $total_teg_monta>0 ) {
                        if($total_teg_gesta>0){
                            $efec_teg = number_format(($total_teg_monta/$total_teg_gesta),1 );
                        }else{
                            $efec_teg = number_format(($$total_teg_gesta),1 );
                        }
                    } 


                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_DNP = "SELECT 
                                sum(dnp) as dnp ,
                                (select count(codigo_servicio) from mregservicios where  codigo_rgb_id_animal_hembra='$cod_hembra' and  status=1 and dnp>0) as cant_reg
                                from mregservicios
                                where 
                                codigo_rgb_id_animal_hembra='$cod_hembra' and  status=1;  ";

                  
                        //  H::PrintR( $sql_DNP );
                        //  exit;  

                    $this->arrp_sql_DNP = $this->bd->select( $sql_DNP );
                    $total_dnp = $this->arrp_sql_DNP[ 0 ][ 'dnp' ];
                    $total_cant_reg = $this->arrp_sql_DNP[ 0 ][ 'cant_reg' ];

                    if ( $total_cant_reg>0 ) {
                        $promedio_dnp = number_format(( $total_dnp/$total_cant_reg),0);
                    } else {
                        $promedio_dnp = "";
                    }


                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_IEP = "SELECT
                                a.codigo_rgb_id_animal_hembra,
                                to_char(a.f_servicio, 'DD-MM-YYYY') AS fecha_servicio,
                                to_char(a.f_ultimo_parto, 'DD-MM-YYYY') AS fecha_ultimo_parto
                            
                                from mregservicios a
                                where 
                                a.parto= 1 and
                                a.codigo_rgb_id_animal_hembra='$cod_hembra' and  
                                a.status=1 order by a.codigo_rgb_id_animal_hembra; ";

                        // H::PrintR( $sql_IEP );
                        // exit;           

                    $this->arrp_sql_IEP = $this->bd->select( $sql_IEP );

                    //////////// SE RECORRE EL ARREGLO EN BUSCA DE VARIOS PARTOS ///////////
                    $tiempo=0;
                    $nro_parto=0;
                    $IEP="";
                    $k=0;
                    foreach ( $this->arrp_sql_IEP as $data_iep ) {
                        $fecha_ultimo_parto= $data_iep[ 'fecha_ultimo_parto' ] ;
                        if($k>0){
                            $fecha_parto_anterior= $data_iep[$k-1][ 'fecha_ultimo_parto' ] ;
                            
                            
                            if($fecha_ultimo_parto!=$fecha_parto_anterior){
                                $tiempo+= $this->tiempo_t($fecha_ultimo_parto,$fecha_parto_anterior);
    
                            }

                        }
                     
                        $nro_parto++;                   
                        $k++;
                    }
                    // H::PrintR( $tiempo );
                    // exit;   
                    if($nro_parto>1){
                        $IEP=$tiempo/$nro_parto;

                    }
           

                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $sql_Gesta_P_A = " SELECT 
                    (select count (codigo_servicio)    from mregservicios c where c.codigo_rgb_id_animal_hembra='$cod_hembra' and c.status=1 and c.medofisiologicos_id=1 and c.parto=1) as cant_gesta_parto,
                    (select count (codigo_servicio)    from mregservicios d where d.codigo_rgb_id_animal_hembra='$cod_hembra' and  d.medofisiologicos_id=4 and  d.status=1 ) as cant_gesta_aborto
                    
                                from mregservicios a
                                    where 
                                      a.codigo_rgb_id_animal_hembra='$cod_hembra' and a.medofisiologicos_id in (1,4) and  a.status=1 ";
                    //       H::PrintR( $sql_Gesta_P_A );
                    // exit;   
                    $this->arrp_sql_Gesta_P_A = $this->bd->select( $sql_Gesta_P_A );
                    $cant_gesta_parto = $this->arrp_sql_Gesta_P_A[ 0 ][ 'cant_gesta_parto' ];
                    $cant_gesta_aborto = $this->arrp_sql_Gesta_P_A[ 0 ][ 'cant_gesta_aborto' ];

                    $tgp  = $cant_gesta_parto;
                    $tgp_a=$cant_gesta_parto+$cant_gesta_aborto;
  

                    $porcent_gest_part="";
                    $porcent_gest_aborto="";

                    if($tgp>0){
                        if($tgp_a>0){
                            $porcent_gest_part=number_format(( ($tgp/$tgp_a)*100),0);
                            $porcent_gest_part=$porcent_gest_part.' '.$uni_medida_pogp;

                        }
                        if($total_aborto>0){
                            $porcent_gest_aborto=number_format( ( $cant_gesta_aborto/($tgp_a)*100),0 );
                            $porcent_gest_aborto=$porcent_gest_aborto.' '.$uni_medida_pma;

                        }

                    }

                         
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    $this->SetFillColor(  148, 247, 224  );
                    $this->SetJump( 8 );

                    $this->RowMFillColor( array( 'ACTUAL', $efec_emg,  $efec_eiag, $efec_teg,$promedio_dnp, $IEP, $porcent_gest_part,  $porcent_gest_aborto), true );
                    $this->SetFillColor(  39, 224, 227  );
                    $this->SetJump( 6 );

                    $this->RowMFillColor( array( 'META', $valores_MMG, $valores_MIAG, $valores_MTEG, $valores_DNPOG, $valores_IEPO,  $valores_POGP, $valores_PMA ), true );
                    
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    $this->SetWidths( array( 30, 40, 40, 40, 50, 30, 30, 30, 30 ) );

                    $this->SetAligns( array( 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C' ) );
                    //Fondo verde de celda
                    $this->SetFillColor( 250, 180, 180 );

                    //Fondo verde de celda
                    $this->SetBorder( true );
                    $this->SetFont( 'ARIAL', 'B', '10' );
                    $this->SetJump( 6 );

                    $this->RowMFillColor( array(utf8_decode( 'Fecha' ), utf8_decode( '# Servicio' ), 'Tipo de Servicio', utf8_decode( 'Estado Fisiológico' ), utf8_decode( 'Fecha del Último Parto '), utf8_decode( 'DNP' ), utf8_decode( 'IES' ), utf8_decode( 'Parió?' ), utf8_decode( 'IEP' ) ), true );
                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                    $sql_Detalle_Servicio = " SELECT 
                                        to_char(a.f_servicio, 'DD-MM-YYYY') as   f_servicio,
                                        to_char(a.f_ultimo_parto, 'DD-MM-YYYY') as   f_ultimo_parto,
                                        to_char(a.fecha_parto, 'DD-MM-YYYY') as   fecha_parto,
                                        a.nro_servicio,  a.dnp,
                                        case when a.mservicios_id=1 then 'Monta'
                                            when a.mservicios_id=2 then 'Inse. Artif'
                                            when a.mservicios_id=3 then 'Transf. Embrio'
                                            else 'No Definida' end as tipo_servicio,
                                        (select b.nombre from mestadofisiologicos b where b.id=a.medofisiologicos_id and a.codigo_rgb_id_animal_hembra='$cod_hembra') as estado_fisiologico,                                       
                                        case when a.parto=1 then 'SI'
                                            else 'NO' end as pario
                                        from mregservicios a
                                        where 
                                        a.codigo_rgb_id_animal_hembra='$cod_hembra' and 
                                        a.status=1 
                                        order by  a.f_servicio asc ";

                                        //   H::PrintR( $sql_Detalle_Servicio ); exit;  
                        

                    $this->arrp_sql_Detalle_Servicio = $this->bd->select( $sql_Detalle_Servicio );
                //   H::PrintR( $this->arrp_sql_Detalle_Servicio[1] ); exit;  

                    $dnp=0;
                    $dnp_x_per=0;
                    $y=$this->GetY();
                    $k=0;

                    $tiempo_iep="";
                    $tiempo_ies="";

                    foreach ( $this->arrp_sql_Detalle_Servicio as $data_Deta_Serv ) {
                        $fecha_ultimo_parto   = $data_Deta_Serv[ 'f_ultimo_parto' ] ;                        
                        $f_servicio           = $data_Deta_Serv[ 'f_servicio' ] ;
                        $nro_servicio         = $data_Deta_Serv[ 'nro_servicio' ] ;
                        $estado_fisiologico   = $data_Deta_Serv[ 'estado_fisiologico' ] ;
                        $tipo_servicio        = $data_Deta_Serv[ 'tipo_servicio' ] ;
                        $pario                = $data_Deta_Serv[ 'pario' ] ;
                        $dnp                  = $data_Deta_Serv[ 'dnp' ] ;
                        $fecha_up_anterior    = $data_Deta_Serv[ 'fecha_parto' ] ;

                         if($k>0){
                            $f_servicio_anterior           = $this->arrp_sql_Detalle_Servicio[$k-1][ 'f_servicio' ] ;  

                            if($fecha_ultimo_parto!='' && $fecha_up_anterior!=''){
                                $tiempo_iep= $this->tiempo_iep($fecha_ultimo_parto,$fecha_up_anterior);

                            }

                            if($f_servicio_anterior!='' && $f_servicio!=''){
                                $tiempo_ies= $this->tiempo_ies($f_servicio,$f_servicio_anterior);               

                            }
                           
                         }
                      $k++;
                      $this->SetFillColor(  148, 247, 224  );
                      $this->SetJump( 8 );

                        $this->RowMFillColor(array( $f_servicio, $nro_servicio, $tipo_servicio, $estado_fisiologico, $fecha_ultimo_parto, $dnp, $tiempo_ies, $pario, $tiempo_iep ), true );
                       
                    }


                }

            }

            $this->SetTextColor( 0, 0, 0 );

            $this->SetWidths( array( 40, 150 ) );
            $this->SetAligns( array( 'C', 'L' ) );
            $this->SetJump( 5 );

            if ( $this->GetY() > 320 ) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY( 10, 35 );

            }
            $this->Ln( 15 );
            $contador++;

            if ( $this->GetY() > 290 ) {
                $this->AddPage();
                $this->SetXY( 10, 35 );
            }

            $this->SetX( 140 );

            $this->SetWidths( array( 60 ) );
            // $this->SetFillTable( 1 );
            $this->SetBorder( true );

            $this->Ln( 5 );
        }
    }
    //end cuerpo

    function tiempo_iep($fecha_ultimo_parto,$fecha_up_anterior )
    {
    $fecha_f = $fecha_up_anterior;
    $fecha_i = $fecha_ultimo_parto;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);
    $dias = (int)$dias;
    $this->dias=$dias;
    return $this->dias;
    }
    function tiempo_ies($f_servicio,$f_servicio_anterior)
    {
    $fecha_f = $f_servicio_anterior;
    $fecha_i = $f_servicio;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);
    $dias = (int)$dias;
    $this->dias=$dias;
    return $this->dias;
    }

    function tiempo_t($fecha_ultimo_parto,$fecha_parto_anterior )
    {
    $fecha_f = $fecha_parto_anterior;
    $fecha_i = $fecha_ultimo_parto;
    $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
    $dias = abs($dias);
    $dias = floor($dias);
    $dias = (int)$dias;
    $this->dias=$dias;
    return $this->dias;
    }
  

}