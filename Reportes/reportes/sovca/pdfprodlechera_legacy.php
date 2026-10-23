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
  
        $parametros = base64_decode(getvalue('%$%'));

          $cod_bovs_desde="";
          $cod_bovs_hasta="";
          $f_inicio="";
          $f_hasta="";
          $lote_desde="";
          $lote_hasta="";
   
          $valores= explode("&",$parametros);
          $cad0=$valores[0];       
          $cad1=$valores[1];
          $cad2=$valores[2];
          $cad3=$valores[3];
          $cad4=$valores[4];
          $cad5=$valores[5];
   
          $valores_cad0= explode("=",$cad0);
   
          $cad_0=$valores_cad0[0];
          $cad_00=$valores_cad0[1];
         

          if($cad_0=='cod_bovs_inicio'){
           $cod_bovs_desde=$cad_00;
          }else if($cad_0=='cod_bovs_final'){
           $cod_bovs_hasta=$cad_00;
          }else if($cad_0=='fecha_inicio'){
           $f_inicio=$cad_00;
          }else if($cad_0=='fecha_final'){
           $f_hasta=$cad_00;
          }else if($cad_0=='lote_unico'){
           $lote_desde=$cad_00;
          }else if($cad_0=='lote_unico'){
            $lote_hasta=$cad_00;
           }
   /////////////////////////////////
          $valores_cad1= explode("=",$cad1);
   
          $cad_1=$valores_cad1[0];
          $cad_2=$valores_cad1[1];
   
          if($cad_1=='cod_bovs_inicio'){
            $cod_bovs_desde=$cad_2;
           }else if($cad_1=='cod_bovs_final'){
            $cod_bovs_hasta=$cad_2;
           }else if($cad_1=='fecha_inicio'){
            $f_inicio=$cad_2;
           }else if($cad_1=='fecha_final'){
            $f_hasta=$cad_2;
           }else if($cad_1=='lote_unico'){
            $lote_desde=$cad_2;
           }else if($cad_1=='lote_unico'){
             $lote_hasta=$cad_2;
            }
   /////////////////////////////////
   
          $valores_cad2= explode("=",$cad2);
   
          $cad_3=$valores_cad2[0];
          $cad_4=$valores_cad2[1];
   
          if($cad_3=='cod_bovs_inicio'){
            $cod_bovs_desde=$cad_4;
           }else if($cad_3=='cod_bovs_final'){
            $cod_bovs_hasta=$cad_4;
           }else if($cad_3=='fecha_inicio'){
            $f_inicio=$cad_4;
           }else if($cad_3=='fecha_final'){
            $f_hasta=$cad_4;
           }else if($cad_3=='lote_unico'){
            $lote_desde=$cad_4;
           }else if($cad_3=='lote_unico'){
             $lote_hasta=$cad_4;
            }
   /////////////////////////////////
   
   ////////////////////////////////
          $valores_cad3= explode("=",$cad3);
   
          $cad_5=$valores_cad3[0];
          $cad_6=$valores_cad3[1];
         
   
          if($cad_5=='cod_bovs_inicio'){
            $cod_bovs_desde=$cad_6;
           }else if($cad_5=='cod_bovs_final'){
            $cod_bovs_hasta=$cad_6;
           }else if($cad_5=='fecha_inicio'){
            $f_inicio=$cad_6;
           }else if($cad_5=='fecha_final'){
            $f_hasta=$cad_6;
           }else if($cad_5=='lote_unico'){
            $lote_desde=$cad_6;
           }else if($cad_5=='lote_unico'){
             $lote_hasta=$cad_6;
            }
   ////////////////////////////////
           $valores_cad4= explode("=",$cad4);
   
           $cad_7=$valores_cad4[0];
           $cad_8=$valores_cad4[1];
      
   
                 if($cad_7=='cod_bovs_inicio'){
            $cod_bovs_desde=$cad_8;
           }else if($cad_7=='cod_bovs_final'){
            $cod_bovs_hasta=$cad_8;
           }else if($cad_7=='fecha_inicio'){
            $f_inicio=$cad_8;
           }else if($cad_7=='fecha_final'){
            $f_hasta=$cad_8;
           }else if($cad_7=='lote_unico'){
            $lote_desde=$cad_8;
           }else if($cad_7=='lote_unico'){
             $lote_hasta=$cad_8;
            }
   ////////////////////////////////
        
        $this->fecha_desde = $f_inicio;
        $this->fecha_hasta = $f_hasta;

        $this->cod_bovs_inicio = $cod_bovs_desde;
        $this->cod_bovs_final = $cod_bovs_hasta;

        $this->lote_unico = $lote_desde;
        // $this->lote_final = $lote_hasta;

        $this->mfundos_id = 0;
        session_start();
        $this->mfundos_id = $_SESSION['mfundos_id'];


        $fecha_de = $this->fecha_desde;
        $fecha_fi = $this->fecha_hasta;


              //////////////********************//////////////////////
        /////////////CAMBIANDO FORMATO DE FECHA ////////////
        $date_desde = new DateTime( $fecha_de );
        $f_desde  = $date_desde->format( 'Y-m-d' );

        $date_hasta = new DateTime( $fecha_fi );
        $f_hasta  = $date_hasta->format( 'Y-m-d' );
        //////////////********************//////////////////////

        $fecha_prod =  substr($fecha_de, 0, 10);

        $partes = explode("-", $fecha_prod);
        $aa = $partes[0];
        $mes = $partes[1];
        $dd = $partes[2];
        $fecha_ini = $dd . '-' . $mes . '-' . $aa;

        $fecha_fin =  substr($fecha_fi, 0, 10);

        $partes_f = explode("-", $fecha_fin);
        $aa1 = $partes_f[0];
        $mes1 = $partes_f[1];
        $dd1 = $partes_f[2];
        $fecha_fin = $dd1 . '-' . $mes1 . '-' . $aa1;


        $sql_fundo = "SELECT nombre, cid_rif  from mfundos where id=" . $this->mfundos_id . "; ";
        $this->arrp_fundo = $this->bd->select($sql_fundo);
        $this->nombre_fundo = $this->arrp_fundo[0]['nombre'];
        $this->cid_rif_fundo = $this->arrp_fundo[0]['cid_rif'];
        $cad_order = "";
        $cadena_lote = "";
        $this->filtro_lote=false;

        if (($fecha_prod != '') && ($fecha_fin != '')) {
            $this->cadena_rango_fecha = 'Desde el ' . $this->fecha_desde . ' Hasta el ' . $this->fecha_hasta;
            $cad_order = "order by id_animal";
            $this->cadena_fecha = " and fecha_produccion        
            BETWEEN CAST ('$f_desde' AS DATE) AND CAST ('$f_hasta' AS DATE)";

        } else {
            $this->cadena_fecha = "";
            $this->cadena_rango_fecha = "";
        }
        if ($this->cod_bovs_inicio != '' && $this->cod_bovs_final != '') {
            $sql_id_desde = "SELECT  id  from manimales where mfundos_id=" . $this->mfundos_id . " and codigo_rgb_id_animal='$this->cod_bovs_inicio'; ";
            $sql_id_hasta = "SELECT  id  from manimales where mfundos_id=" . $this->mfundos_id . " and codigo_rgb_id_animal='$this->cod_bovs_final'; ";
            $this->arrp_id_desde = $this->bd->select($sql_id_desde);
            $this->arrp_id_hasta = $this->bd->select($sql_id_hasta);
            $cod_animal_desde = $this->arrp_id_desde[0]['id'];
            $cod_animal_desde = intval($cod_animal_desde);
            $cod_animal_hasta = $this->arrp_id_hasta[0]['id'];
            $cod_animal_hasta = intval($cod_animal_hasta);

            if ($cod_animal_desde > 0 && $cod_animal_hasta > 0) {
                $cadena_codigo = " and id_animal>= " . $cod_animal_desde . "  and id_animal<= " . $cod_animal_hasta . " ";
                $cad_order = "order by id_animal";
            }else{

            $sql_id_desde = "SELECT  min(id) as minimo  from manimales where mfundos_id=" . $this->mfundos_id . " and mestatus_id=1 and sexo=1; ";
            $sql_id_hasta = "SELECT  max(id) as maximo  from manimales where mfundos_id=" . $this->mfundos_id . " and mestatus_id=1 and sexo=1; ";
            $this->arrp_id_desde = $this->bd->select($sql_id_desde);
            $this->arrp_id_hasta = $this->bd->select($sql_id_hasta);
            $cod_animal_desde = $this->arrp_id_desde[0]['minimo'];
            $cod_animal_desde = intval($cod_animal_desde);
            $cod_animal_hasta = $this->arrp_id_hasta[0]['maximo'];
            $cod_animal_hasta = intval($cod_animal_hasta);
            $cadena_codigo = " and id_animal>= " . $cod_animal_desde . "  and id_animal<= " . $cod_animal_hasta . " ";
            $cad_order = "order by id_animal";

            }
        }

        if ($this->lote_unico != '' ) {

            $cadena_lote = " and id_lote= " . $this->lote_unico . " ";
            $cad_order = "order by id_lote,id_animal";
            $this->filtro_lote=true;
            $this->cadena_rango_lote = "AGRUPADO POR LOTES";
        }

        $sql_principal = " SELECT distinct codigo_animal,id_animal,nombre_animal,id_lote,nombre_lote from v_rep_prolecheras  where mfundos_id=" . $this->mfundos_id . " and estatus_animal NOT IN (3,5) $this->cadena_fecha $cadena_codigo $cadena_lote $cad_order;  ";
         

        $this->arrp = $this->bd->select($sql_principal);

                    //    H::PrintR($sql_fecha);exit;




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

        if ($this->cadena_rango_fecha != "") {
            $this->SetXY($x + 15, $y - 5);

            $this->SetJump(6);
            $this->SetWidths(array(150));

            $this->RowM(array('REPORTE DE PRODUCCION DE LECHE ' . $this->cadena_rango_fecha));
        } else   if ($this->filtro_lote) {
            $this->SetXY($x + 15, $y - 5);

            $this->SetJump(6);
            $this->SetWidths(array(150));

            $this->RowM(array('REPORTE DE PRODUCCION DE LECHE ' . $this->cadena_rango_lote));
        }      
        else        
        {

            $this->SetJump(6);
            $this->SetWidths(array(120));
            $this->RowM(array('REPORTE GENERAL DE PRODUCCION DE LECHE'));
        }
    }
    function  Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", "8");
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

        // $this->getEncabezado($x, $this->y1+5);
    } //end header

    function Cuerpo()
    {
        $puntero = 0;
        $contador = 0;



        $this->SetXY(10, 35);

        foreach ($this->arrp as $data) {

            if ($this->GetY() > 290) {
                $this->AddPage();
                $this->SetXY(10, 35);
            }

            if ($this->filtro_lote){

                $this->SetWidths(array(60));
                $this->SetAligns(array('L'));
                $this->SetJump(5);
    
                $this->SetBorder(0);
                $this->SetFillTable(0);
                $fecha = date("d/m/Y");
                // $this->SetTextColor(50,0, 255);
    
                $this->RowM(array(utf8_decode('LOTE : '.$data['nombre_lote']))); 


            }
            $this->SetWidths(array(40, 40, 40, 70));
            $this->SetAligns(array('C', 'C', 'C', 'C'));
            $this->SetJump(5);

            $this->SetBorder(true);
            $this->SetFillTable(0);
            $fecha = date("d/m/Y");
            // $this->SetTextColor(50,0, 255);

            $this->RowM(array(utf8_decode('Código del Animal'), $data['codigo_animal'], 'Nombre del Animal', utf8_decode($data['nombre_animal'])));

            $this->SetWidths(array(40, 150));
            $this->SetAligns(array('C', 'L'));
            $this->SetJump(5);
            $cod_animal = $data['codigo_animal'];
            $sql_fecha = "SELECT  to_char(fecha_produccion, 'DD-MM-YYYY') as fecha_produccion ,cantidad_leche_m,cantidad_leche_t from v_rep_prolecheras  where codigo_animal='$cod_animal' $this->cadena_fecha order by id_animal,fecha_produccion asc ;  ";

            $sql_max = "SELECT  max (cantidad_leche_m+cantidad_leche_t) as maximo from v_rep_prolecheras  where codigo_animal='$cod_animal' $this->cadena_fecha ;  ";
            $this->arrp_sql_max = $this->bd->select($sql_max);
            $maximo = $this->arrp_sql_max[0]['maximo'];

            // MINIMO

            $sql_min = "SELECT  min (cantidad_leche_m+cantidad_leche_t) as minimo from v_rep_prolecheras  where codigo_animal='$cod_animal' $this->cadena_fecha ;  ";
            $this->arrp_sql_min = $this->bd->select($sql_min);
            $minimo = $this->arrp_sql_min[0]['minimo'];

            //    H::PrintR($sql_fecha);exit;
            $this->arrp_prod_leche = $this->bd->select($sql_fecha);
            $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
            $this->SetWidths(array(40, 30, 30, 30, 60));
            $this->SetBorder(true);

            $this->RowM(array(utf8_decode('Fecha de Producción'), utf8_decode('Kilos (Mañana)'), 'Kilos (Tarde)', 'Kilos TOTAL', 'Kilos Promedio por Dia'));
            $total = 0;
            $cant_total = 0;

            $can_reg = 0;
            foreach ($this->arrp_prod_leche as $data_prod) {


                $this->SetAligns(array('C', 'R', 'R', 'R'));
                $this->SetWidths(array(40, 30, 30, 30));
                $total += $data_prod['cantidad_leche_m'] + $data_prod['cantidad_leche_t'];
                $cant_total = $data_prod['cantidad_leche_m'] + $data_prod['cantidad_leche_t'];
                $this->SetTextColor(0, 0, 0);

                if ($minimo == $cant_total) {
                    $this->SetBorder(true);
                    $this->SetFillTable(0);
                    $this->SetTextColor(255, 0, 0);

                    $this->RowM(array($data_prod['fecha_produccion'], $data_prod['cantidad_leche_m'], $data_prod['cantidad_leche_t'], $cant_total));
                    $this->SetBorder(true);
                    $this->SetTextColor(0, 0, 0);
                } else if ($maximo == $cant_total) {
                    $this->SetBorder(true);
                    $this->SetFillTable(0);
                    $this->SetTextColor(0, 0, 255);

                    $this->RowM(array($data_prod['fecha_produccion'], $data_prod['cantidad_leche_m'], $data_prod['cantidad_leche_t'], $cant_total));
                    $this->SetBorder(true);
                    $this->SetTextColor(0, 0, 0);
                } else {
                    $this->SetBorder(true);
                    $this->SetFillTable(0);
                    $this->RowM(array($data_prod['fecha_produccion'], $data_prod['cantidad_leche_m'], $data_prod['cantidad_leche_t'], $cant_total));
                    $this->SetBorder(true);
                    // $this->SetFillTable(1);

                }



                if ($this->GetY() > 320) {
                    $this->AddPage();
                    $this->SetXY(10, 35);
                }
                $can_reg++;
            }


            $this->SetX(140);

            $this->SetWidths(array(60));
            // $this->SetFillTable(1);
            $this->SetBorder(true);
            $promedio = ($can_reg > 0) ? ($total / $can_reg) : 0;
            $this->RowM(array(H::FormatoMonto($promedio) . ' Kgs'));

            $this->Ln(5);
        }
    } //end cuerpo


}
