<?php
// api/get_doctor_appointments.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Проверяем, что запрос GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');

if ($doctor_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid doctor ID']);
    exit;
}

try {
    // Проверяем, существует ли врач
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Doctor not found']);
        exit;
    }
    
    // Получаем записи врача за период
    $stmt = $pdo->prepare("
        SELECT id, patient_name, patient_phone, appointment_datetime, status
        FROM appointments
        WHERE doctor_id = ? 
        AND DATE(appointment_datetime) BETWEEN ? AND ?
        AND status != 'cancelled'
        ORDER BY appointment_datetime
    ");
    $stmt->execute([$doctor_id, $start, $end]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Формируем ответ
    echo json_encode([
        'success' => true,
        'count' => count($appointments),
        'appointments' => $appointments
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>