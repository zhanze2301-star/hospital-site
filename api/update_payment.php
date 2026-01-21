<?php
require_once 'db_connect.php';

$admin_key = $_POST['admin_key'] ?? '';
$valid_key = 'hospital_admin_2025';

if ($admin_key !== $valid_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Неверный ключ администратора']);
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