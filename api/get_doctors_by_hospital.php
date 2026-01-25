<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$hospital_id = intval($_GET['hospital_id'] ?? 0);
$specialization_id = intval($_GET['specialization_id'] ?? 0);
$date = $_GET['date'] ?? null;
$time_preference = $_GET['time_preference'] ?? '';

if (!$hospital_id || !$specialization_id) {
    echo json_encode(['error' => 'Hospital ID and Specialization ID are required']);
    exit;
}

try {
    // Базовый запрос врачей
    $sql = "
        SELECT d.*, s.name as speciality_name,
               (SELECT COUNT(*) FROM appointments a 
                WHERE a.doctor_id = d.id 
                AND DATE(a.appointment_datetime) = ? 
                AND a.status != 'cancelled') as busy_slots,
               (SELECT COUNT(*) FROM doctor_schedule ds 
                WHERE ds.doctor_id = d.id 
                AND ds.is_working = 1) as working_days
        FROM doctors d
        LEFT JOIN specialities s ON d.speciality_id = s.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE dept.hospital_id = ?
        AND d.speciality_id = ?
        ORDER BY d.rating DESC, d.name
    ";
    
    $params = [$date ?: date('Y-m-d'), $hospital_id, $specialization_id];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Добавляем информацию о доступности
    foreach ($doctors as &$doctor) {
        // Рассчитываем примерное количество свободных слотов
        $doctor['available_slots'] = max(0, 10 - $doctor['busy_slots']); // Примерно 10 слотов в день
        
        // Фильтр по времени
        if ($time_preference) {
            // Можно добавить логику фильтрации по времени
        }
    }
    
    echo json_encode($doctors ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>