<?php
// api/get_appointment_details.php
session_start();
require_once __DIR__ . '/../config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указан ID записи']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, d.name as doctor_name,
               DATE_FORMAT(a.appointment_datetime, '%d.%m.%Y %H:%i') as formatted_datetime
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        echo json_encode(['success' => true, 'appointment' => $appointment]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Запись не найдена']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>