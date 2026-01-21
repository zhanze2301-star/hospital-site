<?php
require_once 'api/config.php';

echo "<h1>Тест всех подключений</h1>";
echo "<p>Подключение к БД: <span style='color:green'>УСПЕШНО</span></p>";

// Проверяем таблицы
echo "<h2>Проверка таблиц:</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN); // Используем FETCH_COLUMN
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>" . htmlspecialchars($table) . "</li>";
}
echo "</ul>";

// Проверяем данные в таблицах
echo "<h2>Количество записей:</h2>";
$tables_to_check = ['specialities', 'doctors', 'appointments', 'ratings'];
foreach ($tables_to_check as $table) {
    if (in_array($table, $tables)) {
        $count = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch()['cnt'];
        echo "<p>$table: $count записей</p>";
    } else {
        echo "<p style='color:orange'>$table: таблица не существует</p>";
    }
}

// Проверяем API endpoints
echo "<h2>Проверка API endpoints:</h2>";
$endpoints = [
    'api/check_time.php',
    'api/create_appointment.php',
    'api/update_appointment.php',
    'api/update_payment.php'
];

echo "<ul>";
foreach ($endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        echo "<li><span style='color:green'>✓</span> $endpoint существует</li>";
    } else {
        echo "<li><span style='color:red'>✗</span> $endpoint не найден</li>";
    }
}
echo "</ul>";

// Проверка структуры таблицы appointments
echo "<h2>Структура таблицы appointments:</h2>";
if (in_array('appointments', $tables)) {
    $columns = $pdo->query("DESCRIBE appointments")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Ключ</th><th>По умолчанию</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Таблица appointments не существует!</p>";
}

// Проверка внешних ключей
echo "<h2>Внешние ключи:</h2>";
$foreign_keys = $pdo->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'hospital_db' 
    AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll();

if (!empty($foreign_keys)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Таблица</th><th>Колонка</th><th>Ссылается на</th><th>Колонка</th></tr>";
    foreach ($foreign_keys as $fk) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fk['TABLE_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Внешние ключи не настроены</p>";
}
?>