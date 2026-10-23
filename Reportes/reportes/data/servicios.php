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

$sql = "select codigo_servicio, codigo_rgb_id_animal_hembra,f_servicio,
											nombre_h,nombre_m,
											estado_fisiologico,
											case when (medofisiologicos_id=1 and parto=0)
													then 'Gestación Activa'
												when (medofisiologicos_id=1 and parto=1 and 
													date_part('month', age(now(),date(fecha_parto))) < 1.5 )
													then 'Periodo de Descanso'
												when (medofisiologicos_id=1 and parto=1 and 
													date_part('month', age(now(),date(fecha_parto))) >= 1.5 )
													then 'Disponible para Servico'	  
												when (medofisiologicos_id in (2,3) and parto=0)
													then 'Servicio Activo'	
												when (medofisiologicos_id in (4,5) and parto=0)
													then 'Disponible para Servico' end  condicion,
												case when estado_servicio=1 then 'ABIERTO'
													when estado_servicio=2 then 'CERRADO' end estadoservicio,                                              
                                                  
													estado_servicio,nro_arete,nro_servicio,
													(select a.nombre from mfundos a where mfundos_id=a.id) as fundo
											from Vregservicios  
											where  mfundos_id=" . $mfundos_id . " and estatus_animal>0 
                                            order by f_servicio asc  ;  ";

$arrp = $bd->select($sql);



$documento = new Spreadsheet();
$documento
    ->getProperties()
    ->setCreator("Ing Yonny Ramírez (BOVS)")
    ->setLastModifiedBy('BOVS')
    ->setTitle('Archivo exportado desde Postgres')
    ->setDescription('Un archivo de Excel exportado desde Postgres por BOVS');

# Como ya hay una hoja por defecto, la obtenemos, no la creamos
$hojaDeProductos = $documento->getActiveSheet();
$hojaDeProductos->setTitle("Servicio Hembras");

# Escribir encabezado de los productos
$encabezado = ["ID SERVICIO", "Código/ Hembra", "Nro Arete/ Microchip", "Nombre/ Hembra", "Fecha del Servicio", "Estado Fisiológico", "Estatus Animal", "Nro de Servicio", "Nombre/ MACHO"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaDeProductos->fromArray($encabezado, null, 'A1');

# Comenzamos en la 2 porque la 1 es del encabezado
$numeroDeFila = 3;


$styleArray = [
    'alignment' => [
        'horizontal' => 'center', // Centrado horizontal
        'vertical' => 'center'   // Centrado vertical
    ]
];

$hojaDeProductos->getStyle('C')->applyFromArray($styleArray);
$hojaDeProductos->getStyle('H')->applyFromArray($styleArray);

foreach ($arrp as $data) {
    # Obtener los datos de la base de datos

    $timestamp = strtotime($data['f_servicio']);
    $formattedDate = date("d-m-Y", $timestamp);

    $fecha_servicio = $formattedDate;

    $hojaDeProductos->getColumnDimension('A')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('B')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('C')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('D')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('E')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('F')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('G')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('H')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('I')->setAutoSize(true);
    $hojaDeProductos->getColumnDimension('J')->setAutoSize(true);


    # Escribirlos en el documento
    $hojaDeProductos->setCellValueByColumnAndRow(1, $numeroDeFila, $data['codigo_servicio']);
    $hojaDeProductos->setCellValueByColumnAndRow(2, $numeroDeFila,  $data['codigo_rgb_id_animal_hembra']);
    $hojaDeProductos->setCellValueByColumnAndRow(3, $numeroDeFila,  $data['nro_arete']);
    $hojaDeProductos->setCellValueByColumnAndRow(4, $numeroDeFila,  $data['nombre_h']);

    $hojaDeProductos->setCellValueByColumnAndRow(5, $numeroDeFila,  $fecha_servicio);
    $hojaDeProductos->setCellValueByColumnAndRow(6, $numeroDeFila,  $data['estado_fisiologico']);
    $hojaDeProductos->setCellValueByColumnAndRow(7, $numeroDeFila,  $data['condicion']);
    $hojaDeProductos->setCellValueByColumnAndRow(8, $numeroDeFila,  $data['nro_servicio']);
    $hojaDeProductos->setCellValueByColumnAndRow(9, $numeroDeFila,  $data['nombre_m']);

    $numeroDeFila++;
    $fundo = $data['fundo'];
}


$timestamp = date('m-d-Y');
$detalle = $fundo . ' ' . $timestamp;



$nombreDelDocumento = "Servicio Animal $detalle.xlsx";
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
