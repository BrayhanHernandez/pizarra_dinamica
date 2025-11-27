<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['usuario'])){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit; }

require_once __DIR__.'/conexion.php'; // o conexión.php
$conn->set_charset('utf8mb4');

$body = json_decode(file_get_contents('php://input') ?: '[]', true);
$nombre = trim($body['nombre'] ?? 'Reunión');

function gen_code($len=6){ return strtoupper(substr(bin2hex(random_bytes(4)),0,$len)); }
$code = gen_code();

$tries=0;
do{
  $stmt = $conn->prepare("SELECT id_room FROM rooms WHERE code = ? LIMIT 1");
  $stmt->bind_param("s",$code); $stmt->execute(); $exists = $stmt->get_result()->num_rows>0;
  if($exists) $code = gen_code();
  $tries++;
} while($exists && $tries<5);

$stmt = $conn->prepare("INSERT INTO rooms (code, name, owner_user, is_active) VALUES (?,?,?,1)");
$owner = $_SESSION['usuario'];
$stmt->bind_param("sss",$code,$nombre,$owner);
$stmt->execute();

echo json_encode(['ok'=>true,'code'=>$code]);