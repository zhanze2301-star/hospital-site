<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if (!$doctor_id) {
    echo json_encode(['error' => 'Doctor ID is required']);
    exit;
}

try {
    // Получаем расписание врача на этот день недели
    $day_of_week = date('N', strtotime($date));
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_schedule 
        WHERE doctor_id = ? AND day_of_week = ? AND is_working = 1
    ");
    $stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        echo json_encode([]);
        exit;
    }
    
    // Получаем существующие записи
    $stmt = $pdo->prepare("
        SELECT TIME(appointment_datetime) as time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_datetime) = ?
        AND status != 'cancelled'
        ORDER BY appointment_datetime
    ");
    $stmt->execute([$doctor_id, $date]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Генерируем слоты (по 30 минут)
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 минут в секундах
    
    $slots = [];
    $current = $start;
    
    while ($current + $interval <= $end) {
        $slot_time = date('H:i', $current);
        $slot_end = date('H:i', $current + $interval);
        
        // Проверяем доступность
        $available = true;
        foreach ($existing as $booked_time) {
            $booked_start = strtotime($booked_time);
            $booked_end = $booked_start + 30 * 60;
            
            if ($current < $booked_end && $current + $interval > $booked_start) {
                $available = false;
                break;
            }
        }
        
        // Пропускаем прошедшее время если сегодня
        if ($date == date('Y-m-d') && $current < time()) {
            $available = false;
        }
        
        $slots[] = [
            'time' => $slot_time,
            'end' => $slot_end,
            'available' => $available
        ];
        
        $current += $interval;
    }
    
    echo json_encode($slots);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>