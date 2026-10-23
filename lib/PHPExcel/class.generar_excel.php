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
	
	
	public function print_excel($nombre_documento,$titulo_documento,$columnas,$filas){
		
############################################ ESTILOS ############################################
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

############################################ ESTILOS ############################################
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


$titulo_documento2 = strtoupper(substr_replace($titulo_documento, ' DE ', 7, 1));
		$this->getActiveSheet()->insertNewRowBefore(1, 1);
		$this->getActiveSheet()->setCellValue('A1', $titulo_documento2);
		$this->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$this->getActiveSheet()->getStyle('A1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

		$this->getActiveSheet()->getRowDimension('1')->setRowHeight(50);
		$this->getActiveSheet()->getStyle('A1')->getAlignment()->setWrapText(true);
		
		$this->getActiveSheet()->setTitle($titulo_documento);

		    foreach (range(0, $nro_columnas) as $col) {
		        $this->getActiveSheet()->getColumnDimensionByColumn($col)->setAutoSize(true);                
		    }

		$this->setActiveSheetIndex(0);

		switch ($nro_columnas) {

		    case 10:
				$this->getActiveSheet()->getStyle('A2:J2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:I1');
				$this->getActiveSheet()->getStyle('A2:J'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('J1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('J1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('J1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('J1')->applyFromArray($styleArray2);
		    break;

		    case 9:

				$this->getActiveSheet()->getStyle('A2:I2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:H1');
				$this->getActiveSheet()->getStyle('A2:I'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('I1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('I1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('I1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('I1')->applyFromArray($styleArray2);		
		    break;

		    case 8:
				$this->getActiveSheet()->getStyle('A2:H2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:G1');
				$this->getActiveSheet()->getStyle('A2:H'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('H1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('H1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('H1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('H1')->applyFromArray($styleArray2);		    
		    break;

		    case 7:
				$this->getActiveSheet()->getStyle('A2:G2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:F1');
				$this->getActiveSheet()->getStyle('A2:G'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('G1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('G1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('G1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('G1')->applyFromArray($styleArray2);			    
		    break;

		    case 6:
				$this->getActiveSheet()->getStyle('A2:F2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:E1');
				$this->getActiveSheet()->getStyle('A2:F'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('F1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('F1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('F1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('F1')->applyFromArray($styleArray2);			    
		    break;       

		    case 5:
				$this->getActiveSheet()->getStyle('A2:E2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:D1');
				$this->getActiveSheet()->getStyle('A2:E'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('E1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('E1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('E1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('E1')->applyFromArray($styleArray2);	
		    break;

		    case 4:
				$this->getActiveSheet()->getStyle('A2:D2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:C1');
				$this->getActiveSheet()->getStyle('A2:D'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('D1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('D1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('D1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('D1')->applyFromArray($styleArray2);	
		    break;

		    case 3:
				$this->getActiveSheet()->getStyle('A2:C2')->applyFromArray($styleArray);
				$this->getActiveSheet()->mergeCells('A1:B1');
				$this->getActiveSheet()->getStyle('A2:C'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('C1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('C1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('C1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('C1')->applyFromArray($styleArray2);	
		    break;	

		    case 2:
				$this->getActiveSheet()->getStyle('A2:B2')->applyFromArray($styleArray);
				$this->getActiveSheet()->getStyle('A2:B'.$nro_filasa)->applyFromArray($borders);
				$this->getActiveSheet()->setCellValue('B1','Fecha: '.$hoy);	
				$this->getActiveSheet()->getStyle('B1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		        $this->getActiveSheet()->getStyle('B1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		        $this->getActiveSheet()->getStyle('B1')->applyFromArray($styleArray2);	
		    break;		    	    		    

		}

		$objDrawing = new PHPExcel_Worksheet_Drawing();  
		$objDrawing->setPath("images/cabecera.png");
		$objDrawing->setCoordinates('A1');      
		$objDrawing->setHeight(60);  
		$objDrawing->setOffsetX(2);
		$objDrawing->setOffsetY(2);
		$objDrawing->setWorksheet($this->getActiveSheet());

			    header('Content-Type: application/vnd.openxmlformatsofficedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment;filename="'.$nombre_documento.'.xlsx"');
				ob_end_clean();
				header('Cache-Control: max-age=0');
				$objWriter = PHPExcel_IOFactory::createWriter($this, 'Excel2007');
				$objWriter->save('php://output');
		
		exit;
		
	}
	

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
