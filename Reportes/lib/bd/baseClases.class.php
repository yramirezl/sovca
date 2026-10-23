<?php

 @require_once("basedatosAdoIbase.php");

 class baseClases
 {
 	protected $bd="";

     function __construct()
     {
        $this->bd=new basedatosAdoIbase();
     }

   function __destruct() {
          $this->bd->closed();
   }

   function select($sql)
	{
		 $rs=$this->bd->select($sql);
		 if ($rs)
		 {
		 	return $rs->GetArray();
		 }else{
		 	return array();
		 }
	}

   function actualizar($sql)
    {
      $rs=$this->bd->actualizar($sql);
    }
 }
