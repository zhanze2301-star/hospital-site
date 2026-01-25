<?php
// api/get_calendar_events.php
session_start();
require_once __DIR__ . '/../config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

// Получаем параметры
$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+1 month'));
$doctor_id = $_GET['doctor_id'] ?? '';
$status = $_GET['status'] ?? '';

// Формируем запрос
$sql = "SELECT a.id, a.patient_name, a.patient_phone, a.appointment_datetime, 
               a.status, a.payment_status,
               d.name as doctor_name
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.appointment_datetime BETWEEN ? AND ?
        AND DATE(a.appointment_datetime) BETWEEN ? AND ?";

$params = [$start . ' 00:00:00', $end . ' 23:59:59', $start, $end];

if (!empty($doctor_id)) {
    $sql .= " AND a.doctor_id = ?";
    $params[] = $doctor_id;
}

if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.appointment_datetime";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    echo json_encode($appointments);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>