<?php
session_start();
require_once '../config.php';

// Устанавливаем заголовок ДО любого вывода
header('Content-Type: application/json; charset=utf-8');

// Отключаем вывод ошибок в ответе
ini_set('display_errors', 0);
error_reporting(0);

// Получаем данные
$data = json_decode(file_get_contents('php://input'), true);

// Если не получили JSON, пробуем из POST
if ($data === null && !empty($_POST)) {
    $data = $_POST;
}

// Проверяем обязательные поля
$required = ['doctor_id', 'date', 'time', 'patient_name', 'patient_phone'];
$missing = [];

foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Не заполнены обязательные поля: ' . implode(', ', $missing)
    ]);
    exit;
}

try {
    // Подготовка данных
    $doctor_id = (int)$data['doctor_id'];
    $date = trim($data['date']);
    $time = trim($data['time']);
    $patient_name = trim($data['patient_name']);
    $patient_phone = trim($data['patient_phone']);
    $patient_notes = trim($data['patient_notes'] ?? '');
    
    // Формируем datetime
    $appointment_datetime = $date . ' ' . $time . ':00';
    
    // Проверяем, что дата в будущем
    if (strtotime($appointment_datetime) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Нельзя записаться на прошедшую дату']);
        exit;
    }
    
    // Проверяем врача
    $stmt = $pdo->prepare("SELECT id, name FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Врач не найден']);
        exit;
    }
    
    // Проверяем день недели
    $day_of_week = date('N', strtotime($date)); // 1=пн, 7=вс
    
    // Проверяем расписание врача
    $stmt = $pdo->prepare("
        SELECT is_working, start_time, end_time 
        FROM doctor_schedule 
        WHERE doctor_id = ? AND day_of_week = ?
    ");
    $stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Врач не работает в этот день']);
        exit;
    }
    
    if ($schedule['is_working'] == 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Врач не работает в этот день']);
        exit;
    }
    
    // Проверяем время в пределах рабочего дня
    if ($time < $schedule['start_time'] || $time >= $schedule['end_time']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => "Врач работает с {$schedule['start_time']} до {$schedule['end_time']}"
        ]);
        exit;
    }
    
    // Получаем интервал приема (по умолчанию 30 минут)
    $appointment_interval = 30; // минут
    
    // Проверяем, не занято ли время (с учетом интервала)
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_datetime,
            TIME(a.appointment_datetime) as appointment_time,
            a.status
        FROM appointments a
        WHERE a.doctor_id = ? 
          AND DATE(a.appointment_datetime) = ?
          AND a.status != 'cancelled'
          AND (
            -- Проверяем перекрытие по времени с учетом интервала
            (
                TIMESTAMP(?, ?) 
                BETWEEN 
                a.appointment_datetime 
                AND DATE_ADD(a.appointment_datetime, INTERVAL ? MINUTE)
            )
            OR
            (
                DATE_ADD(TIMESTAMP(?, ?), INTERVAL ? MINUTE)
                BETWEEN 
                a.appointment_datetime 
                AND DATE_ADD(a.appointment_datetime, INTERVAL ? MINUTE)
            )
            OR
            (
                a.appointment_datetime 
                BETWEEN 
                TIMESTAMP(?, ?) 
                AND DATE_ADD(TIMESTAMP(?, ?), INTERVAL ? MINUTE)
            )
          )
    ");
    
    // Выполняем проверку
    $stmt->execute([
        $doctor_id,
        $date,
        $date, $time,
        $appointment_interval,
        $date, $time,
        $appointment_interval,
        $appointment_interval,
        $date, $time,
        $date, $time,
        $appointment_interval
    ]);
    
    $existing_appointments = $stmt->fetchAll();
    
    if (!empty($existing_appointments)) {
        http_response_code(409);
        
        // Формируем список занятых времен
        $busy_times = [];
        foreach ($existing_appointments as $app) {
            $busy_times[] = date('H:i', strtotime($app['appointment_time']));
        }
        
        $busy_times_str = implode(', ', array_unique($busy_times));
        
        echo json_encode([
            'success' => false, 
            'error' => 'Это время уже занято. Занятые слоты: ' . $busy_times_str,
            'busy_slots' => $busy_times,
            'suggested_times' => $this->getAvailableTimes($pdo, $doctor_id, $date, $schedule)
        ]);
        exit;
    }
    
    // Проверяем отсутствие врача
    $stmt = $pdo->prepare("
        SELECT id FROM doctor_unavailable 
        WHERE doctor_id = ? 
          AND ? BETWEEN start_datetime AND end_datetime
    ");
    $stmt->execute([$doctor_id, $appointment_datetime]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Врач отсутствует в это время']);
        exit;
    }
    
    // Создаем запись
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status, notes, created_at) 
        VALUES (?, ?, ?, ?, 'pending', 'unpaid', ?, NOW())
    ");
    
    $success = $stmt->execute([
        $patient_name,
        $patient_phone,
        $doctor_id,
        $appointment_datetime,
        $patient_notes
    ]);
    
    if ($success) {
        $appointment_id = $pdo->lastInsertId();
        
        // Получаем информацию о враче для ответа
        $stmt = $pdo->prepare("SELECT name, speciality_id FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor_info = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => '✅ Запись успешно создана!',
            'appointment_id' => $appointment_id,
            'appointment_datetime' => $appointment_datetime,
            'formatted_datetime' => date('d.m.Y H:i', strtotime($appointment_datetime)),
            'patient_name' => $patient_name,
            'doctor_name' => $doctor_info['name'] ?? '',
            'doctor_id' => $doctor_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Не удалось создать запись']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error in create_appointment.php: ' . $e->getMessage());
    // echo json_encode([
    //     'success' => false,
    //     'error' => 'Ошибка базы данных',
    //     'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : null
    // ]);
    echo json_encode([
    'success' => false,
    'error' => 'Ошибка базы данных'
    // Уберите debug информацию временно
]);
}



/**
 * Функция для получения доступных времен
 */
function getAvailableTimes($pdo, $doctor_id, $date, $schedule) {
    $interval = 30; // минут между записями
    $start_time = strtotime($schedule['start_time']);
    $end_time = strtotime($schedule['end_time']);
    
    $available_times = [];
    
    // Получаем занятые времена
    $stmt = $pdo->prepare("
        SELECT TIME(appointment_datetime) as time 
        FROM appointments 
        WHERE doctor_id = ? 
          AND DATE(appointment_datetime) = ?
          AND status != 'cancelled'
        ORDER BY appointment_datetime
    ");
    $stmt->execute([$doctor_id, $date]);
    $busy_times = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Преобразуем занятые времена в timestamp
    $busy_slots = [];
    foreach ($busy_times as $busy_time) {
        $busy_slots[] = strtotime($busy_time);
    }
    
    // Генерируем доступные времена
    $current = $start_time;
    while ($current < $end_time) {
        $time_str = date('H:i', $current);
        
        // Проверяем, не занято ли это время
        $is_busy = false;
        foreach ($busy_slots as $busy_slot) {
            // Проверяем перекрытие с интервалом
            if (abs($current - $busy_slot) < ($interval * 60)) {
                $is_busy = true;
                break;
            }
        }
        
        if (!$is_busy) {
            $available_times[] = $time_str;
        }
        
        $current += ($interval * 60); // добавляем интервал в секундах
    }
    
    return array_slice($available_times, 0, 5); // возвращаем первые 5 доступных слотов
}
?>