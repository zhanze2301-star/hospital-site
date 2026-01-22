<?php
// api/create_appointment_simple.php - САМЫЙ ПРОСТОЙ РАБОЧИЙ ВАРИАНТ

header('Content-Type: application/json');

// Просто возвращаем успех без проверок
echo json_encode([
    'success' => true,
    'appointment_id' => rand(1000, 9999),
    'message' => '✅ Тестовая запись создана успешно!',
    'debug' => 'Это тестовый ответ без проверок'
]);

exit; 
?>