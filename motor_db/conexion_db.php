<?php
header('Content-Type: application/json; charset=utf-8');

/*
  CONFIGURACIÓN PARA MYSQL EN HEROKU (JAWSDB)
  URL original:
  mysql://fy51zvw646k90l2u:qmmaieojdaj0azb2@tk3mehkfmmrhjg0b.cbetxkdyhwsb.us-east-1.rds.amazonaws.com:3306/vi68zaznnp5539d5
*/

$host = "tk3mehkfmmrhjg0b.cbetxkdyhwsb.us-east-1.rds.amazonaws.com";
$user = "fy51zvw646k90l2u";
$pass = "qmmaieojdaj0azb2";
$dbname = "vi68zaznnp5539d5";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "Error de conexión a la BD: " . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset("utf8mb4");

// Helpers JSON
function j_ok($data = []) { echo json_encode(["ok" => true] + $data); exit; }
function j_err($msg, $code = 400) { 
  http_response_code($code); 
  echo json_encode(["ok" => false, "error" => $msg]); 
  exit; 
}

// CORS para peticiones desde apps y front
if (php_sapi_name() !== 'cli') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: Content-Type');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}
?>
