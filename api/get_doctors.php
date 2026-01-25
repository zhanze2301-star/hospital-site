<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$hospital_id = intval($_GET['hospital_id'] ?? 0);
$specialization_id = intval($_GET['specialization_id'] ?? 0);

if (!$hospital_id) {
    echo json_encode(['error' => 'Hospital ID is required']);
    exit;
}

try {
    $sql = "
        SELECT d.*, s.name as speciality_name
        FROM doctors d
        LEFT JOIN specialities s ON d.speciality_id = s.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE dept.hospital_id = ? AND d.speciality_id > 0
    ";
    
    $params = [$hospital_id];
    
    if ($specialization_id) {
        $sql .= " AND d.speciality_id = ?";
        $params[] = $specialization_id;
    }
    
    $sql .= " ORDER BY d.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($doctors ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>