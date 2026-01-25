<?php
// api/get_hospital_departments.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Проверяем, что запрос GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hospital_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid hospital ID']);
    exit;
}

try {
    // Подключаемся к БД
    require_once '../config.php';
    
    // Получаем информацию о больнице
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        http_response_code(404);
        echo json_encode(['error' => 'Hospital not found']);
        exit;
    }
    
    // Получаем отделения больницы
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COUNT(DISTINCT doc.id) as doctors_count,
               GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as specialities
        FROM departments d
        LEFT JOIN doctors doc ON d.id = doc.department_id
        LEFT JOIN specialities s ON doc.speciality_id = s.id
        WHERE d.hospital_id = ?
        GROUP BY d.id
        ORDER BY d.name
    ");
    $stmt->execute([$hospital_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Формируем ответ
    $response = [
        'success' => true,
        'hospital' => $hospital,
        'departments' => $departments
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
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