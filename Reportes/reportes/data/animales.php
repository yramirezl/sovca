<?php
// ini_set( 'error_reporting', E_ALL );
// ini_set( 'display_errors', 'On' );

/**
 * Ejemplo de cómo usar PDO y PHPSpreadSheet para
 * exportar datos de MySQL a Excel de manera
 * fácil, rápida y segura
 *
 * @author parzibyte
 * @see https://parzibyte.me/blog/2019/02/14/leer-archivo-excel-php-phpspreadsheet/
 * @see https://parzibyte.me/blog/2018/02/12/mysql-php-pdo-crud/
 * @see https://parzibyte.me/blog/2019/02/16/php-pdo-parte-2-iterar-cursor-comprobar-si-elemento-existe/
 * @see https://parzibyte.me/blog/2018/11/08/crear-archivo-excel-php-phpspreadsheet/
 * @see https://parzibyte.me/blog/2018/10/11/sintaxis-corta-array-php/
 *
 */
require_once "../../../vendor/autoload.php";
require_once("../../lib/bd/basedatosAdoIbase.php");
include_once("../../lib/utiles.php");
date_default_timezone_set('America/Caracas');

session_start();
$mfundos_id = $_SESSION['mfundos_id'];


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


use PhpOffice\PhpSpreadsheet\IOFactory;


# Obtener base de datos

$bd = new baseClases();

$sql = "select   nombre,f_nacimiento,
											case when estatus_animal=1 then 'ACTIVO' 
											     when estatus_animal=2 then 'DONADOR ACTIVO' 
												 when estatus_animal=3 then 'DONADOR' 
												 when estatus_animal=4 then 'DONADORA ACTIVO' 
												when estatus_animal=5 then 'DONADORA' 
											     else 'No Definido' END as estatus,
												 case when sexo=1 then 'HEMBRA'
												 when sexo=2 then 'MACHO' 
												 else  'N/D' END as sexo_animal,
											codigo_rgb_id_animal,nro_chapeta,
											 case when lote is not Null  then lote
											         else  'No tiene Lote Asignado' END as animal_lote,
													 (select a.nombre from mfundos a where mfundos_id=a.id) as fundo
											from vanimales 
											WHERE status=1 and mestatus_id=1 and mfundos_id=" . $mfundos_id . "
											order by id desc; ";

$arrp = $bd->select($sql);



$documento = new Spreadsheet();
$documento
    ->getProperties()
    ->setCreator("Ing Yonny Ramírez (BOVS)")
    ->setLastModifiedBy('BOVS')
    ->setTitle('Archivo exportado desde Postgres')
    ->setDescription('Un archivo de Excel exportado desde Postgres por BOVS');
    

# Como ya hay una hoja por defecto, la obtenemos, no la creamos
$hojaDeServicios = $documento->getActiveSheet();
$hojaDeServicios->setTitle("Animales");

# Escribir encabezado de los productos
$encabezado = [ "Código Animale", "Nro Arete/ Microchip", "Nombre Animal", "Fecha de Nacimiento", "Sexo", "Estatus Animal", "Lote"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaDeServicios->fromArray($encabezado, null, 'A1');

# Comenzamos en la 2 porque la 1 es del encabezado
$numeroDeFila = 3;

$styleArray = [
    'alignment' => [
        'horizontal' => 'center', // Centrado horizontal
        'vertical' => 'center'   // Centrado vertical
    ]
];

$hojaDeServicios->getStyle('B')->applyFromArray($styleArray);
$hojaDeServicios->getStyle('D')->applyFromArray($styleArray);



foreach ($arrp as $data) {


    
    # Obtener los datos de la base de datos

    $timestamp = strtotime($data['f_nacimiento']);
    $formattedDate = date("d-m-Y", $timestamp);

    $f_nacimiento = $formattedDate;

    $hojaDeServicios->getColumnDimension('A')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('B')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('C')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('D')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('E')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('F')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('G')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('H')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('I')->setAutoSize(true);
    $hojaDeServicios->getColumnDimension('J')->setAutoSize(true);


    # Escribirlos en el documento
    $hojaDeServicios->setCellValueByColumnAndRow(1, $numeroDeFila, $data['codigo_rgb_id_animal']);
    $hojaDeServicios->setCellValueByColumnAndRow(2, $numeroDeFila,  $data['nro_chapeta']);
    $hojaDeServicios->setCellValueByColumnAndRow(3, $numeroDeFila,  $data['nombre']);
    $hojaDeServicios->setCellValueByColumnAndRow(4, $numeroDeFila,   $f_nacimiento);
    $hojaDeServicios->setCellValueByColumnAndRow(5, $numeroDeFila, $data['sexo_animal']);
    $hojaDeServicios->setCellValueByColumnAndRow(6, $numeroDeFila,  $data['estatus']);
    $hojaDeServicios->setCellValueByColumnAndRow(7, $numeroDeFila,  $data['animal_lote']);
    $numeroDeFila++;
    $fundo = $data['fundo'];
}

$timestamp = date('m-d-Y');
$detalle = $fundo . ' ' . $timestamp;

$nombreDelDocumento = "Listado de Animales  $detalle.xlsx";
/**
 * Los siguientes encabezados son necesarios para que
 * el navegador entienda que no le estamos mandando
 * simple HTML
 * Por cierto: no hagas ningún echo ni cosas de esas; es decir, no imprimas nada
 */

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nombreDelDocumento . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($documento, 'Xlsx');
$writer->save('php://output');
exit();
