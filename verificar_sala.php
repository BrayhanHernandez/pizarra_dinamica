<?php
// verificar_sala.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

// Opcional: en desarrollo puedes ver errores en el log pero no en la salida del fetch
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // Lee JSON del body
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data['code'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'msg' => 'Falta parámetro code'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $code = strtoupper(trim($data['code']));

    // Busca la sala
    $stmt = $conn->prepare("SELECT id_room, code FROM rooms WHERE code = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'msg' => 'Sala no encontrada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $room = $res->fetch_assoc();
    // Ajusta el nombre de columna si usas otro (ej. 'activa', 'cerrada', etc.)
    if (isset($room['status']) && $room['status'] !== 'activa') {
        http_response_code(410); // Gone
        echo json_encode([
            'ok' => false,
            'msg' => 'La sala está cerrada o inactiva'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // OK
    http_response_code(200);
    echo json_encode([
        'ok'   => true,
        'code' => $code
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Errores inesperados -> 500 + JSON
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'msg'    => 'DB error',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
