<?php
header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1:3306'; // Ваш хост из дампа
$dbname = 'hospital_db';   // Имя вашей базы
$username = 'root';        // Стандартный пользователь WAMP
$password = '';            // Обычно пустой

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения: ' . $e->getMessage()]);
    exit;
}
?>