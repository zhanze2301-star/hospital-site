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
        WHERE doctor_id = ? 
        AND day_of_week = ? 
        AND is_working = 1
    ");
    $stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $stmt->fetch();
    
    // Если нет расписания, используем стандартное
    if (!$schedule) {
        $schedule = [
            'start_time' => '09:00:00',
            'end_time' => '18:00:00'
        ];
    }
    
    // Получаем существующие записи
    $stmt = $pdo->prepare("
        SELECT TIME(appointment_datetime) as time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_datetime) = ?
        AND status != 'cancelled'
    ");
    $stmt->execute([$doctor_id, $date]);
    $booked_times = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Генерируем слоты по 30 минут
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 минут
    
    $slots = [];
    $current = $start;
    
    while ($current + $interval <= $end) {
        $time_str = date('H:i', $current);
        
        // Проверяем, не занято ли время
        $is_available = true;
        foreach ($booked_times as $booked) {
            $booked_time = strtotime($booked);
            if ($current >= $booked_time && $current < $booked_time + 30*60) {
                $is_available = false;
                break;
            }
        }
        
        // Не показываем прошедшее время если сегодня
        if ($date == date('Y-m-d') && $current < time()) {
            $is_available = false;
        }
        
        $slots[] = [
            'time' => $time_str,
            'available' => $is_available
        ];
        
        $current += $interval;
    }
    
    if (empty($slots)) {
        // Генерируем тестовые слоты
        for ($hour = 9; $hour < 18; $hour++) {
            $slots[] = [
                'time' => sprintf('%02d:00', $hour),
                'available' => true
            ];
            $slots[] = [
                'time' => sprintf('%02d:30', $hour),
                'available' => $hour < 17
            ];
        }
    }
    
    echo json_encode($slots);
    
} catch (PDOException $e) {
    // Тестовые слоты при ошибке
    $slots = [];
    for ($hour = 9; $hour < 18; $hour++) {
        $slots[] = ['time' => sprintf('%02d:00', $hour), 'available' => true];
        $slots[] = ['time' => sprintf('%02d:30', $hour), 'available' => true];
    }
    echo json_encode($slots);
}
?>