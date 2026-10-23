<?php
function limpiar($valor){
    $valor = str_replace('"','',$valor);
    $valor = str_replace("'","",$valor);
    $valor = strip_tags($valor);
    $valor = stripslashes($valor);
    $valor = htmlentities($valor);
    $valor = addslashes($valor);     
    $valor = caracteres($valor);
    
    
    return $valor;
}

// Permitidos @#%&+*-/(){}.,;: Aa-Zz 0..9

function limpiarclaves($valor){
    $valor = str_replace('"','',$valor);
    $valor = str_replace("'","",$valor);
    $valor = str_replace("%","",$valor);
    $valor = strip_tags($valor);
    $valor = stripslashes($valor);
    $valor = htmlentities($valor);
    $valor = addslashes($valor);     
    $valor = caracteres($valor);
    
    
    return $valor;
}

function caracteres($valor){
    $valor=str_replace( 
                            array ( 
                                    ' * ',
                                    '*',
                                    ' SELECT ', 
                                    ' UPDATE ', 
                                    ' DELETE ', 
                                    ' INSERT ', 
                                    ' INTO ', 
                                    ' VALUES ', 
                                    ' FROM ', 
                                    ' LEFT ', 
                                    ' JOIN ', 
                                    ' WHERE ', 
                                    ' LIMIT ', 
                                    ' ORDER BY ', 
                                    ' AND ', 
                                    ' OR ', //[dv] note the space. otherwise you match to 'COLOR' ;-)
                                    ' DESC ', 
                                    ' ASC ', 
                                    ' ON ',
                                    ' LIKE ',
                                    ';'
                                  ),'',$valor);
    return $valor;
}

?>
