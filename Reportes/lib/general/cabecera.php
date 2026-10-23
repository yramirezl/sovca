<?php
require_once("../../lib/bd/basedatosAdoIbase.php");

class Cabecera
{

  protected $bd;
  protected $config = array();

  function __construct()
  {
    $this->bd=new basedatosAdoIbase();
  }
 function poner_cabecera($objeto,$rep,$configuracion,$pagina,$sw='')
  {

    if($configuracion=="p")
    {
 
	if ($sw=='')
	{
	      $objeto->setFont("Arial","B",8);
	      //Logo de la Empresa
	     $objeto->Image("../../img/logo_univictimas.png",10,11,50,19);
       
	      //fecha actual
	      $fecha=date("d/m/Y");
	      $objeto->Cell(350,10,'Fecha: '.$fecha,0,0,'C');
	      $objeto->ln(5);

	      //Paginas
	      if($pagina=="s")
	      {
	        $objeto->Cell(190,10,utf8_decode('Página ').$objeto->PageNo().' de {nb}',0,0,'R');
	      }
	        //Titulo Descripcion de la Empresa
	        $objeto->Ln(-8);
	        $objeto->setX(30);
	        $objeto->Cell(180,5,$nombre,0,0,'L');
	        $objeto->Ln(3);
	        $objeto->setX(30);
	        $objeto->Cell(180,5,$direccion,0,0,'L');

	      $objeto->setFont("Arial","B",12);
	      $objeto->setY(30);
	      $objeto->Cell(180,5,$rep,0,0,'C',0);
	      $objeto->ln(10);
	      $objeto->Line(10,35,200,35);

    	}else
    	{
		      $objeto->setFont("Arial","B",8);
		      //Logo de la Empresa
		      $objeto->Image("../../img/logo_sirelog.png",10,11,60,12);
   		      $objeto->ln(11);
		      $objeto->setFont("Arial","B",12);
		      $objeto->Cell(180,10,$rep,0,0,'C',0);
		      $objeto->ln();
		     // $objeto->Line(10,$objeto->GetY(),200,$objeto->GetY());

    	}

    }
    else
    {

      $objeto->setFont("Arial","B",8);
      //Logo de la Empresa
      $objeto->Image("../../img/logo_univictimas.png",10,8,30);
      //fecha actual
      $fecha=date("d/m/Y");
      $objeto->Cell(470,10,'Fecha: '.$fecha,0,0,'C');
      $objeto->ln(5);
      //Paginas
      if($pagina=="s")
      {
        $objeto->Cell(470,10,'Página '.$objeto->PageNo().' de {nb}',0,0,'C');
      }
        //Titulo Descripcion de la Empresa
        $tab = 45;
      $objeto->setFont("Arial","B",12);
      $objeto->Ln(-8);
      $objeto->setX($tab);
      $objeto->Cell(270,5,$nombre,0,0,'L');
      $objeto->Ln(3);
      $objeto->setX($tab);
      $objeto->setFont("Arial","B",6);
      $objeto->Cell(270,5,$direccion,0,0,'L');
      $objeto->Ln(3);
      $objeto->setX($tab);
      $objeto->Cell(270,5,'Tlf: '.$telef,0,0,'L');
      $objeto->Ln(3);
      $objeto->setX($tab);
      $objeto->Cell(270,5,'Fax: '.$fax,0,0,'L');
      $objeto->Ln(10);
      $objeto->setFont("Arial","B",12);
      $objeto->Cell(270,10,$rep,0,0,'C',0);
      $objeto->ln(10);
      //$objeto->Line(10,35,270,35);
    }

  }

  function poner_cabecera2($objeto, $titulo, $orden, $x, $y,$dia,$mes,$anio) {
        $objeto->Image("../../img/logo_univictimas.png", $x + 3, $y + 3, 45);
        $objeto->SetFont("ARIAL", "B", 8);
        $objeto->SetWidths(array(30, 130));
        $objeto->SetAligns(array('C', 'C'));
        $objeto->SetBorder(true);
        $objeto->SetJump(18);
        $objeto->RowM(array('', 'ACTA CONVENIO'));

        $objeto->SetXY($x + 30 + 130, $objeto->GetY() - 18);
        $objeto->SetWidths(array(30));
        $objeto->SetAligns(array('L'));
        $objeto->SetBorder(true);
        $objeto->RowM(array('N° '.$orden));

        $objeto->SetX($x + 30 + 130);
        $objeto->SetWidths(array(30));
        $objeto->SetAligns(array('C'));
        $objeto->SetBorder(true);
        $objeto->RowM(array('FECHA'));

        $objeto->SetX($x + 30 + 130);
        $objeto->SetWidths(array(10, 10, 10));
        $objeto->SetAligns(array('C', 'C', 'C'));
        $objeto->SetBorder(true);
        $objeto->RowM(array('DIA', 'MES', 'AÑO'));

        $objeto->SetX($x + 30 + 130);
        $objeto->SetWidths(array(10, 10, 10));
        $objeto->SetAligns(array('C', 'C', 'C'));
        $objeto->SetBorder(true);
        $objeto->SetJump(6);
        $objeto->RowM(array($dia, $mes, $anio));
    }


}
?>
