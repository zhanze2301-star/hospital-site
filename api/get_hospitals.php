<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Проверяем, существует ли таблица hospitals
    $table_exists = $pdo->query("SHOW TABLES LIKE 'hospitals'")->rowCount() > 0;
    
    if (!$table_exists) {
        // Возвращаем тестовые данные
        $hospitals = [
            [
                'id' => 1,
                'name' => 'Главная больница',
                'address' => 'ул. Чуй 123, Бишкек',
                'phone' => '+996 312 123456',
                'latitude' => 42.8746,
                'longitude' => 74.5698
            ],
            [
                'id' => 2,
                'name' => 'Филиал №1',
                'address' => 'ул. Советская 45, Бишкек',
                'phone' => '+996 312 654321',
                'latitude' => 42.8800,
                'longitude' => 74.5800
            ],
            [
                'id' => 3,
                'name' => 'Детский медицинский центр',
                'address' => 'ул. Манаса 67, Бишкек',
                'phone' => '+996 312 987654',
                'latitude' => 42.8700,
                'longitude' => 74.5900
            ]
        ];
        
        echo json_encode($hospitals);
        exit;
    }
    
    // Простой запрос без колонки is_active
    $stmt = $pdo->query("
        SELECT id, name, address, phone, latitude, longitude 
        FROM hospitals 
        ORDER BY name
    ");
    
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$hospitals) {
        // Если таблица пустая, возвращаем тестовые данные
        $hospitals = [
            [
                'id' => 1,
                'name' => 'Главная больница',
                'address' => 'ул. Чуй 123, Бишкек',
                'phone' => '+996 312 123456'
            ]
        ];
    }
    
    echo json_encode($hospitals);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'test_data' => [
            [
                'id' => 1,
                'name' => 'Главная больница (тест)',
                'address' => 'ул. Чуй 123, Бишкек'
            ]
        ]
    ]);
}
?>