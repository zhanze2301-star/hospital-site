<?php
// api/create_appointment_real.php - РАБОЧАЯ ВЕРСИЯ

// ВКЛЮЧАЕМ ВСЕ ОШИБКИ ДЛЯ ОТЛАДКИ
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ЗАГОЛОВОК JSON - САМОЕ ПЕРВОЕ!
header('Content-Type: application/json; charset=utf-8');

// Ответ по умолчанию
$response = ['success' => false, 'error' => 'Неизвестная ошибка'];

try { 
    // Подключаем БД
    require_once __DIR__ . '/../config.php';
    
    // Получаем данные
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        throw new Exception('Нет данных в запросе');
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Неверный JSON: ' . json_last_error_msg());
    }
    
    // Проверяем обязательные поля
    $required = ['patient_name', 'doctor_id', 'date', 'time'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Не заполнено поле: $field");
        }
    }
    
    // Подготавливаем данные
    $patient_name = trim($data['patient_name']);
    $patient_phone = trim($data['patient_phone'] ?? '');
    $doctor_id = intval($data['doctor_id']);
    $date = $data['date'];
    $time = $data['time'];
    
    // Проверяем врача
    $stmt = $pdo->prepare("SELECT id, name FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        throw new Exception("Врач с ID $doctor_id не найден");
    }
    
    // Формируем datetime
    $datetime = "$date $time:00";
    
    // Проверяем формат даты
    if (!strtotime($datetime)) {
        throw new Exception("Неверная дата или время");
    }
    
    // Проверяем, не занято ли время
    $stmt = $pdo->prepare("SELECT id FROM appointments 
                           WHERE doctor_id = ? AND appointment_datetime = ? 
                           AND status != 'cancelled'");
    $stmt->execute([$doctor_id, $datetime]);
    
    if ($stmt->fetch()) {
        throw new Exception("Это время уже занято другим пациентом");
    }
    
    // СОЗДАЁМ ЗАПИСЬ
    $stmt = $pdo->prepare("INSERT INTO appointments 
                          (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status) 
                          VALUES (?, ?, ?, ?, 'pending', 'unpaid')");
    
    $stmt->execute([$patient_name, $patient_phone, $doctor_id, $datetime]);
    $appointment_id = $pdo->lastInsertId();
    
    // УСПЕХ!
    $response = [
        'success' => true,
        'appointment_id' => $appointment_id,
        'doctor_name' => $doctor['name'],
        'datetime' => $datetime,
        'formatted_datetime' => date('d.m.Y H:i', strtotime($datetime)),
        'message' => "✅ Запись #$appointment_id успешно создана!"
    ];
    
} catch (Exception $e) {
    // ЛОВИМ ВСЕ ОШИБКИ
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString() // Только для отладки
    ];
}

// ВЫВОДИМ ОТВЕТ
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;