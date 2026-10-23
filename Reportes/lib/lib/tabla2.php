<?php
function complementostabla($nombregrid){
$head.='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta charset=utf-8 />

<title>'.$nombregrid.'</title>
<link rel="stylesheet" type="text/css" href="../estilos/tabla.css" />
<script language="JavaScript" type="text/javascript">
var consulta="todos";

function select_consulta(valor){
	consulta=valor;
}

function closeModal(){
window.location="../clear.html";
}

function Nuevo(href){	
window.location=href;

}

function Buscar()
{ 
  var campo=consulta;
  var valor= document.f_consulta.buscar.value;
  window.location="'.$nombregrid.'.php?campo="+campo+"&valor="+valor.toUpperCase();
  consulta="todos";
  
}

</script>
</head>';

return $head;

}


function tabla($path,$tabla,$campos,$encabezados,$nombregrid,$formulario,$titulo){
	
include_once ('../conexiones/interbase.php');
include_once ('../lib/utiles.php');
$postgres = new pgsql();

$campo=getvalue('campo');
$vlr=getvalue('valor');
$valor=strtoupper($vlr);	
$logCampos=count($campos);
$logEncab=count($encabezados);
 
    session_start();    
    $idgrupo=getVarSession('idgrupo');
    
$html='<body>
    <div id=menu>
<form id="f_consulta" name="f_consulta" target="GB_frame">
<table>
<thead>';
for ($z = 0; $z <= ($logEncab); $z++){
	
	if($z==1){		
		$html.='<th>'.$titulo.    '</th>';
    }else if($z==2){
		   $html.='<th><select name="consulta" onchange="select_consulta(value)"><option value="todos">*</option>';		
		   
		   for ($x = 0; $x <= ($logEncab-1); $x++){			
	                $html.='<option value="'.$campos[$x].'">'.$encabezados[$x].'</option>';

                 } 
		
		  $html.='</th>';
		  $html.='<th><input type="text" size="25" name="buscar" ></th>';
		  $html.='<th><button type="button" onclick="Buscar()" name="boton_buscar" >Buscar <img src="../img/magnifier.png" border="0"></button></th>';
		
		}else{
			     $html.='<th></th>';
			 }
}
$html.='</thead>
</table>
</form>






  
<table id="table1" border="0" cellpadding="2" cellspacing="0" align="left" width="78%" class="tabla">';
$html.='<thead>';

for ($x = 0; $x <= ($logEncab-1); $x++) {

     $html.='<th align="left">'.$encabezados[$x].'</th>';


}
$html.='
<th align="center" width="30">Editar</th>
</thead>'. "\n";





$registros = 5; 
$pagina = $_GET["pagina"];

if (!$pagina) {
$inicio = 0;
$pagina = 1;
 
}else {
$inicio = ($pagina - 1) * $registros;
}
if($tabla=='mgrupos'){
   if($idgrupo!='1'){
        $tira=" where id!=1 ";
        $tira2="and id!=1 ";
    }else{
        $tira="";
        $tira2=" ";
    } 
} else if($tabla=='vimenugrupo'){
   if($idgrupo!='1'){
        $tira=" where idmgrupos!=1 ";
        $tira2="and idmgrupos!=1 ";
    }else{
        $tira="";
        $tira2=" ";
    } 
}

if (($campo=="todos")||(!$campo)){
		
     $query= $postgres->query("SELECT *FROM $tabla $tira ;");
     $postgres->query("SELECT *FROM $tabla $tira ORDER BY id DESC LIMIT $registros OFFSET $inicio;");
     
}else if(($campo!="todos")&&(!$campo)){
     $query= $postgres->query("SELECT *FROM $tabla $tira;");
     $postgres->query("SELECT *FROM $tabla $tira ORDER BY id DESC LIMIT $registros OFFSET $inicio;");

  }else{
	
	if(is_numeric($valor)){
	 	 
		 $query= $postgres->query("SELECT *FROM $tabla WHERE $campo= '".$valor."' $tira2 ;");
		 $postgres->query("SELECT *FROM $tabla WHERE $campo= '".$valor."' $tira2 LIMIT $registros OFFSET $inicio;");
		  
	  }else{
		  
		 $query= $postgres->query("SELECT *FROM $tabla WHERE $campo LIKE '%$valor%' $tira2 ;");
		 $postgres->query("SELECT *FROM $tabla WHERE $campo LIKE '%$valor%' $tira2 LIMIT $registros OFFSET $inicio;"); 		  
		 
		 }	 
  
  }



$cont=0;
while ($postgres->movenext()){
	
	$html.='<tr bgcolor="#FFF" onMouseOver="this.style.backgroundColor=\'#dddddd\'; this.style.cursor=\'hand\';"  onMouseOut="this.style.backgroundColor=\'#FFF\';"   onClick="GoToLink(this);">';
   
		for ($i = 0; $i <= ($logCampos-1); $i++) {
						
			$html.= '<td align="left"><a class=\'ord\' face="sans-serif" href="'.$formulario.'.php?id='.$postgres->getfield($campos[0]).'" target="GB_frame">'.$postgres->getfield($campos[$i]).'</a></td>';
			
		}
	$html.='<td align="center"><a class=\'ord\' face="sans-serif" href="'.$formulario.'.php?id='.$postgres->getfield($campos[0]).'"   target="GB_frame"><img src="../img/page_white_edit.png" border="0"></a></td>';
				
	$html.='</tr>'. "\n";	
}


while(pg_fetch_row($query)){
  $cont++;
}
$total_registros= $cont;
$total_paginas = ceil($total_registros/$registros);



	
		
$html.='<tfoot>';		
for ($f = 0; $f <= ($logEncab); $f++) {

     //$html.='<th align="left"></th>';
     
     if($f==($logEncab)){
		 
		   $html.='<th align="left">Pag '.$pagina.'/'.$total_paginas.'</th>'. "\n";
		 
		 }else if($f==1){
		        $html.='<th align="left"><a href="'.$nombregrid.'.php?pagina='.($pagina-1).'"><img src="../img/back.gif" border="0"></a>
		        <a href="'.$nombregrid.'.php?pagina='.($pagina+1).'"><img src="../img/next.gif" border="0"></a>
		        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		        <button class="boton" onclick="Nuevo(\''.$formulario.'.php\')">Agregar</button>
		        &nbsp;&nbsp;
		        <button class="boton" onclick="closeModal()">Salir</button></th>
		        '. "\n";
		    }else{			 
			     $html.='<th align="left"></th>'. "\n";			 
			   }
     

}		
$html.='<tfoot>';	

$html.='
</table>
</div>
</body>
</html>';

return $html;  

}

?>
