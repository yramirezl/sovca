<?php
require_once '../models/Maptitude.php';
date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_aptitud"]);

@$numero ='+584127579701';// ($_POST["numero"]);
@$mensaje = 'Esta es un aprueba';//($_POST["mensaje"]);
/***********************************/
@$nombre_aptitud = ($_POST["nombre_aptitud"]);
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.ultramsg.com/instance13079/messages/chat",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_SSL_VERIFYHOST => 0,
  CURLOPT_SSL_VERIFYPEER => 0,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "token=clh2jlfv930u8wcp&to=$numero&body=$mensaje&priority=1&referenceId=",
  CURLOPT_HTTPHEADER => array(
    "content-type: application/x-www-form-urlencoded"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}