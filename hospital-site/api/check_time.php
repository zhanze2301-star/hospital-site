<?php
// Устанавливаем флаг для JSON ответов
$api_mode = true;

// Подключаем основной конфиг из корня
require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$doctor_id = $data['doctor_id'] ?? null;
$date = $data['date'] ?? null;
$time = $data['time'] ?? null;

if (!$doctor_id || !$date || !$time) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указаны врач, дата или время']);
    exit;
}

// Объединяем дату и время
$datetime = $date . ' ' . $time . ':00';

// Проверяем, занято ли время
$stmt = $pdo->prepare("SELECT id FROM appointments 
                       WHERE doctor_id = ? AND appointment_datetime = ? 
                       AND status != 'cancelled' LIMIT 1");
$stmt->execute([$doctor_id, $datetime]);

if ($stmt->fetch()) {
    echo json_encode(['available' => false, 'message' => 'Это время уже занято']);
} else {
    echo json_encode(['available' => true, 'message' => 'Время свободно']);
}
?> 