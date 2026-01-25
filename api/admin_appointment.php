<?php
require_once 'db_connect.php'; 

// Простейшая проверка авторизации (в реальном проекте используйте сессии/JWT)
// Для MVP можно пропустить, но добавьте хотя бы проверку по секретному ключу
$admin_key = $_GET['admin_key'] ?? '';
if ($admin_key !== 'ваш_секретный_ключ_для_админки') { // Замените на сложный ключ!
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен']);
    exit;
}

// GET-запрос: получить список записей
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
        SELECT a.*, d.name as doctor_name, d.specialty_id 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $appointments = $stmt->fetchAll();
    echo json_encode($appointments);
    exit;
}

// PATCH-запрос: изменить статус записи (например, на 'completed')
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = $input['id'] ?? null;
    $new_status = $input['status'] ?? null;

    if (!$appointment_id || !in_array($new_status, ['pending', 'completed', 'cancelled'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверные данные для обновления']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $appointment_id]);
    echo json_encode(['success' => true, 'message' => 'Статус обновлен']);
    exit;
}

http_response_code(405); // Method Not Allowed
?>