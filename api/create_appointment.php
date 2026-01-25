<?php
// api/create_appointment.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Проверяем, что запрос POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Получаем JSON данные
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Проверяем обязательные поля
$required = ['patient_name', 'doctor_id', 'date', 'time'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Валидация данных
    $patient_name = trim($input['patient_name']);
    $patient_phone = isset($input['patient_phone']) ? trim($input['patient_phone']) : null;
    $doctor_id = intval($input['doctor_id']);
    $date = $input['date'];
    $time = $input['time'];
    
    // Формируем полную дату и время
    $appointment_datetime = $date . ' ' . $time . ':00';
    
    // Проверяем, что дата в будущем
    if (strtotime($appointment_datetime) <= time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Appointment time must be in the future']);
        exit;
    }
    
    // Проверяем, существует ли врач
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Doctor not found']);
        exit;
    }
    
    // Проверяем, свободно ли время
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_datetime = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$doctor_id, $appointment_datetime]);
    
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'This time slot is already booked']);
        exit;
    }
    
    // Создаем запись
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', 'unpaid', NOW())
    ");
    
    $stmt->execute([$patient_name, $patient_phone, $doctor_id, $appointment_datetime]);
    
    $appointment_id = $pdo->lastInsertId();
    
    // Формируем ответ
    echo json_encode([
        'success' => true,
        'message' => 'Запись успешно создана!',
        'appointment_id' => $appointment_id,
        'appointment' => [
            'id' => $appointment_id,
            'patient_name' => $patient_name,
            'patient_phone' => $patient_phone,
            'doctor_id' => $doctor_id,
            'appointment_datetime' => $appointment_datetime,
            'status' => 'pending'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>