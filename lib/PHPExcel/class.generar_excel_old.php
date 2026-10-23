<?php
/*
 * 
 * Copyright 2016 hdandrea <Hector D'Andrea - Telf: 0426-8093046>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 */
 
 
require_once 'PHPExcel.php';
require_once 'PHPExcel/Cell/AdvancedValueBinder.php';
require_once 'PHPExcel/Worksheet/Drawing.php';
date_default_timezone_set('America/Caracas');

class get_excel extends PHPExcel{
	
	
	private $abecedario_excel = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
	
	
	public function print_excel($nombre_documento,$titulo_documento,$columnas,$filas,$etq_votos,$condic){

$borders = array(
      'borders' => array(
        'allborders' => array(
          'style' => PHPExcel_Style_Border::BORDER_THIN,
          'color' => array('argb' => 'FF000000'),
        )
      ),
    );

$styleArray = array(
    'font'  => array(
        'bold'  => true,
        'color' => array('rgb' => '000000'),
        'size'  => 11,
        'name'  => 'Calibri'
    ));

$styleblanco = array(
    'font'  => array(
         'color' => array('rgb' => 'ffffff')
    ));

$styleArray2 = array(
    'font'  => array(
        'bold'  => true,
        'color' => array('rgb' => '000000'),
        'size'  => 8,
        'name'  => 'Calibri'
    ));


$styleArray3 =  array( 
            'code' => PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00
        );
       
//$this->calculateColumnWidths();

/*foreach(range('A','H') as $columnID)
{
    $this->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
}*/


//$this->getActiveSheet()->getStyle('A1:G1')->applyFromArray($borders);
	

		$hoy = date('d-m-Y');
		$nro_columnas = count($columnas);
		$nro_filas = count($filas);
		
		$nro_filasa =$nro_filas+2;



		for ($i = 0; $i <= ($nro_columnas-1); $i++) {
			
			$celda = $this->abecedario_excel[$i].'1';
			$valor_celda = $columnas[$i];
			
			$this->setActiveSheetIndex(0)->setCellValue($celda, $valor_celda);
			
		}
		
		
		for ($f = 0; $f <= ($nro_filas-1); $f++) {
			
			$linea = $filas[$f];
			
			$nro_celdas_x_fila = count($linea);
			
			for ($c = 0; $c <= ($nro_celdas_x_fila-1); $c++) {
				
				$index = $f+2;
				
				$celdax = $this->abecedario_excel[$c]."".$index;
				
				$valor_celdax = $linea[$c];
				
				$this->setActiveSheetIndex(0)->setCellValue($celdax, $valor_celdax);
				
			}
			
			
		}
		

		$this->getActiveSheet()->insertNewRowBefore(1, 1);
		$this->getActiveSheet()->setCellValue('A1',  $etq_votos);
		$this->getActiveSheet()->getStyle('A1')->getAlignment()->setWrapText(true);

		$this->getActiveSheet()->setTitle($titulo_documento);
		$this->setActiveSheetIndex(0);

switch ($nro_columnas) {

    case 8:
        $this->getActiveSheet()->getColumnDimension('A')->setWidth(18);
        $this->getActiveSheet()->getColumnDimension('B')->setWidth(44);
        $this->getActiveSheet()->getColumnDimension('C')->setWidth(31);
        $this->getActiveSheet()->getColumnDimension('D')->setWidth(9);
        $this->getActiveSheet()->getColumnDimension('E')->setWidth(35);
        $this->getActiveSheet()->getColumnDimension('F')->setWidth(14);
        $this->getActiveSheet()->getColumnDimension('G')->setWidth(6);
        $this->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);

		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(120);
		$this->getActiveSheet()->getStyle('A2:H2')->applyFromArray($styleArray);
		$this->getActiveSheet()->mergeCells('A1:G1');
		$this->getActiveSheet()->getStyle('A2:H'.$nro_filasa)->applyFromArray($borders);

		$this->getActiveSheet()->setCellValue('H1','Fecha: '.$hoy);
		$this->getActiveSheet()->getStyle('H1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		$this->getActiveSheet()->getStyle('H1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		$this->getActiveSheet()->getStyle('H1')->applyFromArray($styleArray2);
		$this->getActiveSheet()->setAutoFilter("A2:H2");
		//$this->getActiveSheet()->getStyle('H1')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH);
		$tit_gra="REGIONES";
    break;

    case 7:
        $this->getActiveSheet()->getColumnDimension('A')->setWidth(44);
        $this->getActiveSheet()->getColumnDimension('B')->setWidth(31);
        $this->getActiveSheet()->getColumnDimension('C')->setWidth(9);
        $this->getActiveSheet()->getColumnDimension('D')->setWidth(35);
        $this->getActiveSheet()->getColumnDimension('E')->setWidth(14);
        $this->getActiveSheet()->getColumnDimension('F')->setWidth(6);
        $this->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);

		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(130);
		$this->getActiveSheet()->getStyle('A2:G2')->applyFromArray($styleArray);
		$this->getActiveSheet()->mergeCells('A1:F1');
		$this->getActiveSheet()->getStyle('A2:G'.$nro_filasa)->applyFromArray($borders);
		$this->getActiveSheet()->setCellValue('G1', 'Fecha: '.$hoy);
		$this->getActiveSheet()->getStyle('G1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		$this->getActiveSheet()->getStyle('G1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		$this->getActiveSheet()->getStyle('G1')->applyFromArray($styleArray2);
		$this->getActiveSheet()->setAutoFilter("A2:G2");
		//$this->getActiveSheet()->getStyle('G1')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH);
   		$tit_gra="CENTROS DE TRABAJO";
    break;


    case 6:
        $this->getActiveSheet()->getColumnDimension('A')->setWidth(31);
        $this->getActiveSheet()->getColumnDimension('B')->setWidth(9);
        $this->getActiveSheet()->getColumnDimension('C')->setWidth(35);
        $this->getActiveSheet()->getColumnDimension('D')->setWidth(14);
        $this->getActiveSheet()->getColumnDimension('E')->setWidth(6);
        $this->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);

		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(140);
		$this->getActiveSheet()->getStyle('A2:F2')->applyFromArray($styleArray);
		$this->getActiveSheet()->mergeCells('A1:E1');
		$this->getActiveSheet()->getStyle('A2:F'.$nro_filasa)->applyFromArray($borders);
		$this->getActiveSheet()->setCellValue('F1', 'Fecha: '.$hoy);
		$this->getActiveSheet()->getStyle('F1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		$this->getActiveSheet()->getStyle('F1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		$this->getActiveSheet()->getStyle('F1')->applyFromArray($styleArray2);
		$this->getActiveSheet()->setAutoFilter("A2:F2");
		//$this->getActiveSheet()->getStyle('F1')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH);
    	$tit_gra="CARGOS";
    break;

     case 5:
        $this->getActiveSheet()->getColumnDimension('A')->setWidth(9);
        $this->getActiveSheet()->getColumnDimension('B')->setWidth(35);
        $this->getActiveSheet()->getColumnDimension('C')->setWidth(14);
        $this->getActiveSheet()->getColumnDimension('D')->setWidth(6);
        $this->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(150);
		$this->getActiveSheet()->getStyle('A2:E2')->applyFromArray($styleArray);
		$this->getActiveSheet()->mergeCells('A1:D1');
		$this->getActiveSheet()->getStyle('A2:E'.$nro_filasa)->applyFromArray($borders);
		$this->getActiveSheet()->setCellValue('E1','Fecha: '.$hoy);
		$this->getActiveSheet()->getStyle('E1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		$this->getActiveSheet()->getStyle('E1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		$this->getActiveSheet()->getStyle('E1')->applyFromArray($styleArray2);
		$this->getActiveSheet()->setAutoFilter("A2:E2");
		//$this->getActiveSheet()->getStyle('E1')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH);
   $tit_gra="EMPLEADOS";
    break;       

}

$this->getSheetCount();

$positionInExcel=1;

$this->createSheet($positionInExcel)->setTitle('graficas');	

		$subtotal='=SUBTOTAL(103,A:A)-2';
		$uno=1;//$nro_filas+4;		
		$dos=2;//$nro_filas+5;
		$tres=2;//$nro_filas+6;

		$this->getActiveSheet()->setCellValue('I'.$uno,'DATOS FILTRADOS: ');
		$this->getActiveSheet()->setCellValue('I'.$dos,  'TOTAL DE REGISTROS: ');
		
		
		$this->getActiveSheet()->setCellValue('J'.$uno,  $subtotal);
		$this->getActiveSheet()->setCellValue('J'.$dos,  '=COUNTA(A:A)-2');
		$this->getActiveSheet()->setCellValue('K'.$tres,  '=ROUND((J'.$uno.'/J'.$dos.'),2)');

		$this->getActiveSheet()->getStyle('K'.$tres)->getNumberFormat()->applyFromArray($styleArray3);

		$this->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);

//tabla apra graficas
		$l=$nro_filas+2;
	$this->getActiveSheet()->setCellValue('L2','CONDICIONES');	
	for ($j = 3; $j <= 98; $j++) {
	
		$k=$j-1;									
		$this->getActiveSheet()->setCellValue('L'.$j,'=IFERROR(INDEX($A$3:$A$'.$l.',MATCH(0,INDEX(COUNTIF($L$2:L'.$k.',$A$3:$A$'.$l.'),0,0),0)),"""")');
		$this->getActiveSheet()->setCellValue('M'.$j,'=COUNTIF($A$3:$A$'.$l.',""&L'.$j.'&"")');
		
	}

	$this->getActiveSheet()->setCellValue('N3','=SUM(M:M)');

$this->getActiveSheet()->getStyle('K1:N100')->applyFromArray($styleblanco);

//dibujar grafica de torta

//$this->getActiveSheet()->setCellValue('Z1',  '=COUNTIF(M:M,">0")');

if($condic==1){
	$ncondic=1;
	$condic=$condic+2;
}else{
	$ncondic=$condic-2;
}

//echo($ncondic);exit();
$dataseriesLabels = array( new PHPExcel_Chart_DataSeriesValues('String', 'elecciones!$A$2', null, 1)
);
						   
$xAxisTickValues = array( new PHPExcel_Chart_DataSeriesValues('String', 'elecciones!$L$3:$L$'.$condic, null, $ncondic)
);

$dataSeriesValues = array( new PHPExcel_Chart_DataSeriesValues('Number', 'elecciones!$M$3:$M$'.$condic, null,$ncondic) 
);						   


$series = new PHPExcel_Chart_DataSeries(PHPExcel_Chart_DataSeries::TYPE_PIECHART,
										null, 
										range(0, count($dataSeriesValues) - 1), 
										$dataseriesLabels, 
										$xAxisTickValues, 
										$dataSeriesValues
		);
        // Set up a layout object for the Pie chart
$layout1 = new PHPExcel_Chart_Layout();
$layout1->setShowVal(true);
$layout1->setShowPercent(true);
$layout1->setShowLeaderLines(true);

$series->setPlotDirection(PHPExcel_Chart_DataSeries::DIRECTION_COL);

$plotarea = new PHPExcel_Chart_PlotArea($layout1, array($series));

$legend = new PHPExcel_Chart_Legend(PHPExcel_Chart_Legend::POSITION_BOTTOM , null, false);

$title = new PHPExcel_Chart_Title($tit_gra);

$chart = new PHPExcel_Chart( 'chart1', $title, $legend, $plotarea, true, 0, null, null);

$chart->setTopLeftPosition('C2');

$chart->setBottomRightPosition('R35');
		$this->getActiveSheet()->getSheetView()->setZoomScale(75);
		$objDrawing = new PHPExcel_Worksheet_Drawing();  
		$objDrawing->setPath("images/cabecera.jpg");
		$objDrawing->setCoordinates('A1');      
		$objDrawing->setHeight(100);
		$objDrawing->setOffsetX(32); 
		
		$objDrawing->setWorksheet($this->getActiveSheet());

$this->setActiveSheetIndex( 1 );
$this->getActiveSheet()->addChart($chart);

		$this->getActiveSheet()->insertNewRowBefore(1, 1);
		$this->getActiveSheet()->mergeCells('A1:S1');
		/*$this->getActiveSheet()->setCellValue('A1',  $etq_votos);
		$this->getActiveSheet()->getStyle('A1')->getAlignment()->setWrapText(true);*/
		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(75);
		$this->getActiveSheet()->getSheetView()->setZoomScale(75);

		$objDrawing = new PHPExcel_Worksheet_Drawing();  
		$objDrawing->setPath("images/cabecera.jpg");
		$objDrawing->setCoordinates('B1');      
		$objDrawing->setHeight(100);  
		$objDrawing->setOffsetX(256);
		$objDrawing->setWorksheet($this->getActiveSheet());
$this->setActiveSheetIndex( 0 );

		header('Content-Type: application/vnd.openxmlformatsofficedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="'.$nombre_documento.'.xlsx"');
		ob_end_clean();
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($this, 'Excel2007');
		$objWriter->setIncludeCharts(true);
		$objWriter->save('php://output');

		exit;
	
		
		
	}// fin de la funcion 
	

}//fin de la clase





//EJEMPLO DE USO
/***********  valores de entrada ***************************************/
/*
$nombre_documento = "ejemplo_excel";

$titulo_documento = "usuariosx";

$columnas = array('nombre','email','twitter');

$filas = array();

array_push($filas,  array('David1','dvd@gmail.com',1));
array_push($filas, array('David2','dvd@gmail.com',2));
array_push($filas, array('David3','dvd@gmail.com',3));
array_push($filas, array('David4','dvd@gmail.com',4));
array_push($filas, array('David5','dvd@gmail.com',5));
array_push($filas, array('David6','dvd@gmail.com',6));


//print_r( $filas);


/************************************************************************/

 //$excel = new get_excel();
 
 //$excel->print_excel($nombre_documento,$titulo_documento,$columnas,$filas);
 
 
?>
