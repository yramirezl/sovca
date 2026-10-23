<?php
require_once 'Spreadsheet/Excel/Writer.php';
include_once("./include_php/conexion.php");
$conn = conexion();

$SELECT_SECTOR_NINO = "SELECT fu_obtener_edad(fecha_nac_nino,CURRENT_DATE) as edad, nino.id_nino, nino.nombre_nino, nino.apellido_paterno_nino, apellido_materno_nino, nino.direccion_nino, nino.sector_nino, nino.fono_fijo, nino.fono_movil, nino.fono_referencia, educacion.nombre_escuela_nino, educacion.curso_nino, educacion.repeticion_u_ano_nino, educacion.motivo_repitencia_nino, salud.estado_salud_nino, salud.discapacidad_nino, salud.tipo_discapacidad_nino
 FROM nino left join educacion on nino.id_nino = educacion.id_nino left join salud on nino.id_nino = salud.id_nino
     where nino.sector_nino = 'ANITA'";
 
 $RES_SECTOR=pg_query($conn, $SELECT_SECTOR_NINO);


//visualizar como archivo

$NombreArchivo ="Listado De Ninos Por Sectores.xls";

//creo el libro para el archivo

$libro = new Spreadsheet_Excel_Writer();

// Estableceremos nuestro formato Negrita e Italica para usarlo en el documento
            $negrita =& $libro->addFormat();
            $negrita->setBold();
            $negrita->setPattern(1);
$negrita->setFgColor(0);
            
            
                
            $italica =& $libro->addFormat();
            $italica->setItalic();
            $italica->setColor('black');
            $italica->setPattern(1);
            $italica->setFgColor(0);
            
            
            
            $formato =& $libro->addFormat();
            $formato->setItalic();
            $formato->setColor('black');
$formato->setPattern(1);
$formato->setFgColor(26);//amarillo claro

//$formato->SetMargins(0.75);
        
            // Creamos una hoja en la cual poner nuestros datos
            $hoja =& $libro->addWorksheet();
            //$hoja =& $libro->SetMargins(1);
                    
            // Ancho de columnas
            $hoja->setColumn(0,0,10);
            $hoja->setColumn(1,1,30);
            $hoja->setColumn(1,2,30);
            $hoja->setColumn(1,3,10);
            $hoja->setColumn(1,4,30);
            $hoja->setColumn(1,5,30);
            $hoja->setColumn(1,6,30);
            $hoja->setColumn(1,7,30);
            $hoja->setColumn(1,8,30);
            $hoja->setColumn(1,9,30);
            $hoja->setColumn(1,10,30);
            $hoja->setColumn(1,11,10);
            $hoja->setColumn(1,12,30);
            $hoja->setColumn(1,13,30);
            $hoja->setColumn(1,14,10);
            $hoja->setColumn(1,15,30);
            
            
            // Verificamos que la hoja se haya generado correctamente
            if (PEAR::isError($hoja)) 
                die($hoja->getMessage());
                
            // Titulos
            $hoja->writeString(0, 2, "LISTADO DE NINOS POR SECTOR", $negrita, $formato);
                
              // Encabezado de columnas
            $hoja->write(2, 0, "C.I.P", $italica);
            $hoja->write(2, 1, "NOMBRE", $italica);
            $hoja->write(2, 2, "APELLIDOS", $italica);
            $hoja->write(2, 3, "EDAD", $italica);
            $hoja->write(2, 4, "DIRECCION", $italica);
            $hoja->write(2, 5, "SECTOR", $italica);
            $hoja->write(2, 6, "F. FIJO", $italica);
            $hoja->write(2, 7, "F. MOVIL", $italica);
            $hoja->write(2, 8, "F. REFERENCIA", $italica);
            $hoja->write(2, 9, "ESCUELA/LICEO", $italica);
            $hoja->write(2, 10, "CURSO", $italica);
            $hoja->write(2, 11, "REPITENTE", $italica);
            $hoja->write(2, 12, "MOTIVO REPITENCIA", $italica);
            $hoja->write(2, 13, "ESTADO DE SALUD", $italica);
            $hoja->write(2, 14, "DISCAPACITADO", $italica);
            $hoja->write(2, 15, "TIPO DISCAPACIDAD", $italica);
            
            
            $i = 3;
            while ($fila = pg_fetch_assoc($RES_SECTOR)) {
                    //$ts=$fila["ts"];
                    $id_nin = $fila['id_nino'];
                    $nomb = $fila['nombre_nino'];
                    $ape_paterno_nino = $fila['apellido_paterno_nino'];
                    $ape_materno_nino = $fila['apellido_materno_nino'];
                    $edad_nino = $fila['edad']. " ANOS";
                    $direccion = $fila['direccion_nino'];
                    $sector = $fila['sector_nino'];
                    $f_fijo = $fila['fono_fijo'];
                    $f_movil = $fila['fono_movil'];
                    $f_referencia = $fila['fono_referncia'];
                    $escuela_liceo = $fila['nombre_escuela_nino'];
                    $curso = $fila['curso_nino'];
                    $repeticion = $fila['repeticion_u_ano_nino'];
                    $motiv_repitencia = $fila['motivo_repitencia'];
                    $estado_salu = $fila['estado_salud'];
                    $discapacidad = $fila['discapacidad_nino'];
                    $tip_discapacidad = $fila['tipo_discapacidad_nino'];
                        
                
                $hoja->write($i, 0, $id_nin, $formato);
                $hoja->write($i, 1, $nomb, $formato);
                $hoja->write($i, 2, $ape_paterno_nino." ".$ape_materno_nino, $formato);
                $hoja->write($i, 3, $edad_nino, $formato);
                $hoja->write($i, 4, $direccion, $formato);
                $hoja->write($i, 5, $sector, $formato);
                $hoja->write($i, 6, $f_fijo, $formato);
                $hoja->write($i, 7, $f_movil, $formato);
                $hoja->write($i, 8, $f_referencia, $formato);
                $hoja->write($i, 9, $escuela_liceo, $formato);
                $hoja->write($i, 10, $curso, $formato);
                $hoja->write($i, 11, $repeticion, $formato);
                $hoja->write($i, 12, $motiv_repitencia, $formato);
                $hoja->write($i, 13, $estado_salu, $formato);
                $hoja->write($i, 14, $discapacidad, $formato);
                $hoja->write($i, 15, $tip_discapacidad, $formato);
                
                 
                                 
                $i++;
            }
            $libro->send($NombreArchivo);
            $libro->close();
?>
