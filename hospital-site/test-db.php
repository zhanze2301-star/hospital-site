<?php
include 'config.php';

// Пробуем взять все специальности (пока пусто, но проверим соединение)
$stmt = $pdo->query("SELECT * FROM specialities");
$specialities = $stmt->fetchAll();

echo "<pre>";
echo "Подключение работает! Найдено специальностей: " . count($specialities) . "\n";
echo "Пример: если добавишь данные вручную в phpMyAdmin, они здесь появятся.";
echo "</pre>";
?>