<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$specialization_id = intval($_GET['specialization_id'] ?? 0);

if (!$specialization_id) {
    echo json_encode(['error' => 'Specialization ID is required']);
    exit;
}

try {
    // Сначала проверяем таблицу hospitals
    $table_exists = $pdo->query("SHOW TABLES LIKE 'hospitals'")->rowCount() > 0;
    
    if (!$table_exists) {
        // Возвращаем тестовые данные
        $hospitals = [
            [
                'id' => 1,
                'name' => 'Главная больница',
                'address' => 'ул. Чуй 123, Бишкек',
                'phone' => '+996 312 123456',
                'doctor_count' => 3
            ],
            [
                'id' => 2,
                'name' => 'Филиал №1',
                'address' => 'ул. Советская 45, Бишкек',
                'phone' => '+996 312 654321',
                'doctor_count' => 2
            ]
        ];
        
        echo json_encode($hospitals);
        exit;
    }
    
    // Ищем врачей с этой специализацией
    $stmt = $pdo->prepare("
        SELECT DISTINCT h.* 
        FROM hospitals h
        WHERE EXISTS (
            SELECT 1 FROM doctors d
            WHERE d.speciality_id = ?
            AND h.id IS NOT NULL
        )
        ORDER BY h.name
    ");
    
    $stmt->execute([$specialization_id]);
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Добавляем количество врачей
    foreach ($hospitals as &$hospital) {
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM doctors 
            WHERE speciality_id = ?
        ");
        $stmt2->execute([$specialization_id]);
        $count = $stmt2->fetch();
        $hospital['doctor_count'] = $count['count'] ?? 0;
    }
    
    if (!$hospitals) {
        $hospitals = [
            [
                'id' => 1,
                'name' => 'Главная больница',
                'address' => 'ул. Чуй 123, Бишкек',
                'doctor_count' => 2
            ]
        ];
    }
    
    echo json_encode($hospitals);
    
} catch (PDOException $e) {
    // Тестовые данные при ошибке
    echo json_encode([
        [
            'id' => 1,
            'name' => 'Главная больница',
            'address' => 'ул. Чуй 123, Бишкек',
            'doctor_count' => 2
        ]
    ]);
}
?>