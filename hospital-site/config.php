<?php
// config.php - с отладкой

// ВРЕМЕННО включаем отладку
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'hospital_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Проверка подключения
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    // Для API скриптов
    if (isset($api_mode) && $api_mode) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    // Для HTML страниц
    die('Ошибка подключения к БД: ' . $e->getMessage());
}
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // echo "Подключение к БД успешно!";  // Раскомменти для теста
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>