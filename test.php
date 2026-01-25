<?php
require_once 'config.php';

echo "<h3>Структура таблицы ratings:</h3>";

$stmt = $pdo->query("DESCRIBE ratings");
$columns = $stmt->fetchAll();

echo "<table border='1'>";
echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Ключ</th><th>Значение по умолчанию</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Проверим данные
echo "<h3>Первые 5 записей в ratings:</h3>";
$data = $pdo->query("SELECT * FROM ratings LIMIT 5")->fetchAll();
echo "<pre>";
print_r($data);
echo "</pre>";
?>