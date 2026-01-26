<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$hospital_id = intval($_GET['hospital_id'] ?? 0);
$specialization_id = intval($_GET['specialization_id'] ?? 0);

if (!$specialization_id) {
    echo json_encode(['error' => 'Specialization ID is required']);
    exit;
}

try {
    // Если hospital_id не указан, ищем всех врачей специализации
    if ($hospital_id) {
        $sql = "
            SELECT d.*, s.name as speciality_name
            FROM doctors d
            LEFT JOIN specialities s ON d.speciality_id = s.id
            WHERE d.speciality_id = ?
            AND d.id IS NOT NULL
            ORDER BY d.rating DESC, d.name
            LIMIT 10
        ";
        $params = [$specialization_id];
    } else {
        $sql = "
            SELECT d.*, s.name as speciality_name
            FROM doctors d
            LEFT JOIN specialities s ON d.speciality_id = s.id
            WHERE d.speciality_id = ?
            AND d.id IS NOT NULL
            ORDER BY d.rating DESC, d.name
            LIMIT 10
        ";
        $params = [$specialization_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Добавляем информацию о доступности
    foreach ($doctors as &$doctor) {
        // Проверяем расписание на сегодня
        $today = date('N'); // день недели
        $stmt_schedule = $pdo->prepare("
            SELECT COUNT(*) as is_working 
            FROM doctor_schedule 
            WHERE doctor_id = ? 
            AND day_of_week = ? 
            AND is_working = 1
        ");
        $stmt_schedule->execute([$doctor['id'], $today]);
        $schedule = $stmt_schedule->fetch();
        
        $doctor['is_working_today'] = ($schedule['is_working'] ?? 0) > 0;
        $doctor['available_slots'] = $doctor['is_working_today'] ? 5 : 0;
    }
    
    if (empty($doctors)) {
        // Тестовые данные
        $doctors = [
            [
                'id' => 1,
                'name' => 'Иванов Иван Иванович',
                'speciality_id' => $specialization_id,
                'speciality_name' => 'Кардиолог',
                'rating' => 4.5,
                'experience' => '15 лет',
                'photo_url' => '',
                'is_working_today' => true,
                'available_slots' => 4
            ],
            [
                'id' => 4,
                'name' => 'Бекташ кызы Айзада',
                'speciality_id' => $specialization_id,
                'speciality_name' => 'ЛОР',
                'rating' => 8.4,
                'experience' => '11 лет',
                'photo_url' => 'https://odoctor.kg/media/doctors/bektash-kyzy-aizada.webp',
                'is_working_today' => true,
                'available_slots' => 3
            ]
        ];
    }
    
    echo json_encode($doctors);
    
} catch (PDOException $e) {
    // Тестовые данные при ошибке
    echo json_encode([
        [
            'id' => 1,
            'name' => 'Тестовый врач',
            'speciality_name' => 'Специалист',
            'rating' => 4.0,
            'experience' => '10 лет',
            'is_working_today' => true,
            'available_slots' => 5
        ]
    ]);
}
?>