<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors','On');
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
require_once "vendor/autoload.php";

# Nuestra base de datos
require_once 'models/Vfacturado.php';


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

# Obtener base de datos

        
//$data_fac = Vfacturado::find('all',array('conditions' => array('id=?',1)));


$documento = new Spreadsheet();
$documento
    ->getProperties()
    ->setCreator("Luis Cabrera Benito (parzibyte)")
    ->setLastModifiedBy('Parzibyte')
    ->setTitle('Archivo exportado desde MySQL')
    ->setDescription('Un archivo de Excel exportado desde MySQL por parzibyte');

# Como ya hay una hoja por defecto, la obtenemos, no la creamos
$hojaDeProductos = $documento->getActiveSheet();
$hojaDeProductos->setTitle("Productos");

# Escribir encabezado de los productos
$encabezado = ["Código de barras", "Descripción", "Precio de compra", "Precio de venta", "Existencia"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaDeProductos->fromArray($encabezado, null, 'A1');

# Comenzamos en la 2 porque la 1 es del encabezado
$numeroDeFila = 2;
foreach($data_fac as $rs_fac){
    # Obtener los datos de la base de datos
    $codigo = $rs_fac->mrequerimientos_id;
    $descripcion = $rs_fac->mrequerimientos_id;
    $precioCompra = $rs_fac->mrequerimientos_id;
    $precioVenta = $rs_fac->mrequerimientos_id;
    $existencia = $rs_fac->mrequerimientos_id;
    # Escribirlos en el documento
    $hojaDeProductos->setCellValueByColumnAndRow(1, $numeroDeFila, $codigo);
    $hojaDeProductos->setCellValueByColumnAndRow(2, $numeroDeFila, $descripcion);
    $hojaDeProductos->setCellValueByColumnAndRow(3, $numeroDeFila, $precioCompra);
    $hojaDeProductos->setCellValueByColumnAndRow(4, $numeroDeFila, $precioVenta);
    $hojaDeProductos->setCellValueByColumnAndRow(5, $numeroDeFila, $existencia);
    $numeroDeFila++;
}

# Ahora los clientes
# Ahora sí creamos una nueva hoja
$hojaDeClientes = $documento->createSheet();
$hojaDeClientes->setTitle("Clientes");

# Escribir encabezado
$encabezado = ["Nombre", "Correo electrónico"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaDeClientes->fromArray($encabezado, null, 'A1');
# Obtener clientes de BD


# Comenzamos en la 2 porque la 1 es del encabezado
$numeroDeFila = 2;

# Crear un "escritor"
$writer = new Xlsx($documento);
# Le pasamos la ruta de guardado
$writer->save('Exportado.xlsx');
