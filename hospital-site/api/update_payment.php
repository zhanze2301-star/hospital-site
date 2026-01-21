<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён. Требуется авторизация.']);
    exit;
}

$appointment_id = $_POST['id'] ?? null;
$payment_status = $_POST['payment_status'] ?? null;

if (!$appointment_id || !in_array($payment_status, ['unpaid', 'paid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные данные для обновления']);
    exit;
}

$stmt = $pdo->prepare("UPDATE appointments SET payment_status = ? WHERE id = ?");
$stmt->execute([$payment_status, $appointment_id]);

echo json_encode(['success' => true, 'message' => 'Статус оплаты обновлён']);
?>