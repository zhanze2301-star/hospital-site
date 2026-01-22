<?php
// api/get_available_slots.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

// Устанавливаем заголовок JSON ДО любого вывода
header('Content-Type: application/json; charset=utf-8');

// Подключаем конфиг
require_once __DIR__ . '/../config.php';

// Получаем параметры
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : null;
$date = isset($_GET['date']) ? $_GET['date'] : null;

// Проверяем параметры
if (!$doctor_id || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указаны врач или дата']);
    exit;
}

// Проверяем валидность даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат даты']);
    exit;
}

try {
    // Проверяем день недели (1-понедельник, 7-воскресенье)
    $day_of_week = date('N', strtotime($date));
    
    // Проверяем, существует ли таблица doctor_schedule
    $table_exists = $pdo->query("SHOW TABLES LIKE 'doctor_schedule'")->rowCount() > 0;
    
    if ($table_exists) {
        // Получаем рабочее время врача на этот день
        $stmt = $pdo->prepare("
            SELECT start_time, end_time 
            FROM doctor_schedule 
            WHERE doctor_id = ? AND day_of_week = ? AND is_working = 1
            LIMIT 1
        ");
        $stmt->execute([$doctor_id, $day_of_week]);
        $schedule = $stmt->fetch();
    }
    
    // Если нет расписания или таблицы, используем стандартное
    if (!$table_exists || !$schedule) {
        $schedule = ['start_time' => '09:00:00', 'end_time' => '18:00:00'];
    }
    
    // Получаем занятые слоты на эту дату
    $stmt = $pdo->prepare("
        SELECT TIME(appointment_datetime) as time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_datetime) = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$doctor_id, $date]);
    $busy_times = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Генерируем доступные слоты (каждые 30 минут)
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 минут в секундах
    
    $available_slots = [];
    
    // Проверяем, не прошедшая ли дата
    $selected_date = strtotime($date);
    $today = strtotime(date('Y-m-d'));
    $now = time();
    
    for ($time = $start; $time < $end; $time += $interval) {
        $time_str = date('H:i', $time);
        
        // Проверяем, не прошедшее ли это время для сегодняшней даты
        if ($selected_date == $today) {
            $slot_time_today = strtotime(date('Y-m-d ') . $time_str);
            if ($slot_time_today <= $now) {
                continue; // Пропускаем прошедшее время
            }
        }
        
        // Проверяем, не занят ли слот
        $time_with_seconds = $time_str . ':00';
        if (!in_array($time_with_seconds, $busy_times)) {
            $available_slots[] = [
                'time' => $time_str,
                'formatted' => date('H:i', $time),
                'available' => true
            ];
        }
    }
    
    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'date' => $date,
        'day_of_week' => $day_of_week,
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'slots' => $available_slots,
        'count' => count($available_slots),
        'busy_times' => $busy_times // Для отладки
    ]);
    
} catch (Exception $e) {
    // Ловим любые исключения
    http_response_code(500);
    echo json_encode([
        'error' => 'Внутренняя ошибка сервера',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

exit; 
?>