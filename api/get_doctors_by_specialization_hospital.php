<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$specialization_id = intval($_GET['specialization_id'] ?? 0);
$hospital_id = intval($_GET['hospital_id'] ?? 0);

if (!$specialization_id || !$hospital_id) {
    echo json_encode(['error' => 'Both specialization_id and hospital_id are required']);
    exit;
}

try {
    // Получаем врачей указанной специализации в указанной больнице
    $stmt = $pdo->prepare("
        SELECT d.*, s.name as speciality_name
        FROM doctors d
        LEFT JOIN specialities s ON d.speciality_id = s.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE d.speciality_id = ? 
        AND dept.hospital_id = ?
        ORDER BY d.rating DESC, d.name
    ");
    
    $stmt->execute([$specialization_id, $hospital_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($doctors ?: []);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>