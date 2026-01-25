<?php
// api/get_available_time.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Проверяем, что запрос GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if ($doctor_id <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // Подключаемся к БД
    require_once '../config.php';
    
    // Проверяем, существует ли врач
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Doctor not found']);
        exit;
    }
    
    // Получаем рабочие часы врача на этот день недели
    $day_of_week = date('N', strtotime($date)); // 1-пн, 7-вс
    
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_schedule 
        WHERE doctor_id = ? 
        AND day_of_week = ? 
        AND is_working = 1
    ");
    $stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Если нет расписания на этот день
    if (!$schedule || !$schedule['start_time'] || !$schedule['end_time']) {
        echo json_encode([
            'success' => true,
            'date' => $date,
            'available_slots' => [],
            'message' => 'Врач не работает в этот день'
        ]);
        exit;
    }
    
    // Получаем существующие записи на эту дату
    $stmt = $pdo->prepare("
        SELECT TIME(appointment_datetime) as time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_datetime) = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$doctor_id, $date]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Генерируем доступные временные слоты (каждые 30 минут)
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $available_slots = [];
    
    // Пропускаем обеденный перерыв (13:00-14:00)
    $lunch_start = strtotime('13:00:00');
    $lunch_end = strtotime('14:00:00');
    
    for ($time = $start; $time < $end; $time += 1800) { // 1800 секунд = 30 минут
        // Пропускаем обеденный перерыв
        if ($time >= $lunch_start && $time < $lunch_end) {
            continue;
        }
        
        $time_str = date('H:i', $time);
        
        // Проверяем, не занят ли слот
        if (!in_array($time_str . ':00', $booked_slots)) {
            $available_slots[] = $time_str;
        }
    }
    
    // Формируем ответ
    echo json_encode([
        'success' => true,
        'date' => $date,
        'working_hours' => [
            'start' => substr($schedule['start_time'], 0, 5),
            'end' => substr($schedule['end_time'], 0, 5)
        ],
        'available_slots' => $available_slots,
        'total_slots' => count($available_slots)
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