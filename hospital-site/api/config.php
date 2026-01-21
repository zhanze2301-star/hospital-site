<?php
// config.php — настройки подключения к БД

$host = 'localhost';          // Обычно localhost в WAMP
$dbname = 'hospital_db';      // Имя твоей базы
$username = 'root';           // По умолчанию в WAMP
$password = '';               // По умолчанию пустой в WAMP (потом смени на реальный пароль!)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // echo "Подключение к БД успешно!";  // Раскомменти для теста
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>