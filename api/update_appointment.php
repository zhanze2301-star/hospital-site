<?php
require_once 'db_connect.php';

$admin_key = $_POST['admin_key'] ?? '';
$valid_key = 'hospital_admin_2025'; // Должен совпадать с ключом в admin.php

if ($admin_key !== $valid_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Неверный ключ администратора']);
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