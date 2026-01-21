<?php
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$patient_name = trim($data['patient_name'] ?? '');
$patient_phone = trim($data['patient_phone'] ?? '');
$doctor_id = $data['doctor_id'] ?? null;
$date = $data['date'] ?? null;
$time = $data['time'] ?? null;

// Валидация
if (empty($patient_name) || !$doctor_id || !$date || !$time) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните обязательные поля: имя, врач, дата и время']);
    exit;
}

// Формируем DATETIME для вашей базы
$datetime = $date . ' ' . $time . ':00';

// Проверка занятости времени (двойная проверка)
$stmt = $pdo->prepare("SELECT id FROM appointments 
                       WHERE doctor_id = ? AND appointment_datetime = ? 
                       AND status != 'cancelled' LIMIT 1");
$stmt->execute([$doctor_id, $datetime]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'К сожалению, это время только что заняли. Выберите другое время.']);
    exit;
}

// Создание записи с телефоном и статусом оплаты
$stmt = $pdo->prepare("INSERT INTO appointments 
                      (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status) 
                      VALUES (?, ?, ?, ?, 'pending', 'unpaid')");
try {
    $stmt->execute([$patient_name, $patient_phone, $doctor_id, $datetime]);
    $appointment_id = $pdo->lastInsertId();
    
    // Возвращаем больше информации для пользователя
    echo json_encode([
        'success' => true, 
        'appointment_id' => $appointment_id,
        'datetime' => $datetime,
        'message' => 'Запись успешно создана! Наш администратор свяжется с вами для подтверждения.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при сохранении: ' . $e->getMessage()]);
}
?>