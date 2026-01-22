<?php
// debug_api.php - для отладки
echo "<h3>Отладка реального API</h3>";

// 1. Проверяем подключение к БД
echo "<p>1. Проверка подключения к БД...</p>";
try {
    require_once 'config.php';
    echo "<p style='color:green'>✅ Подключение к БД успешно</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Ошибка БД: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Проверяем таблицу appointments
echo "<p>2. Проверка таблицы appointments...</p>";
try {
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM appointments")->fetch()['cnt'];
    echo "<p style='color:green'>✅ Таблица существует. Записей: $count</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Ошибка: " . $e->getMessage() . "</p>";
}

// 3. Проверяем запись данных
echo "<p>3. Тест записи в БД...</p>";
$test_data = [
    'patient_name' => 'Тест из debug',
    'patient_phone' => '+996 555 999999',
    'doctor_id' => 1,
    'datetime' => date('Y-m-d H:i:s', strtotime('+1 day'))
];

try {
    $stmt = $pdo->prepare("INSERT INTO appointments 
                          (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status) 
                          VALUES (?, ?, ?, ?, 'pending', 'unpaid')");
    
    if ($stmt->execute([
        $test_data['patient_name'],
        $test_data['patient_phone'],
        $test_data['doctor_id'],
        $test_data['datetime']
    ])) {
        $id = $pdo->lastInsertId();
        echo "<p style='color:green'>✅ Тестовая запись создана! ID: $id</p>";
        
        // Показываем созданную запись
        $created = $pdo->query("SELECT * FROM appointments WHERE id = $id")->fetch();
        echo "<pre>" . print_r($created, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Ошибка записи: " . $e->getMessage() . "</p>";
}

// 4. Проверяем JSON ответ
echo "<p>4. Тест JSON ответа...</p>";
$test_json = json_encode([
    'success' => true,
    'test' => 'JSON работает'
]);

if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color:green'>✅ JSON кодирование работает</p>";
    echo "<pre>$test_json</pre>";
} else {
    echo "<p style='color:red'>❌ Ошибка JSON: " . json_last_error_msg() . "</p>";
}
?>