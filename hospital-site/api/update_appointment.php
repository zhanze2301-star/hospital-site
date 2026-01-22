<?php
session_start();
require_once __DIR__ . '/../config.php';

// Проверяем авторизацию через сессию
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён. Требуется авторизация.']);
    exit;
}

$appointment_id = $_POST['id'] ?? null;
$new_status = $_POST['status'] ?? null;
 
if (!$appointment_id || !in_array($new_status, ['pending', 'completed', 'cancelled'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные данные для обновления']);
    exit;
}

// Обновляем статус
$stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $appointment_id]);

echo json_encode(['success' => true, 'message' => 'Статус обновлён']);
?>