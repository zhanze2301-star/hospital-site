<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$hospital_id = intval($_GET['hospital_id'] ?? 0);

if (!$hospital_id) {
    echo json_encode(['error' => 'Hospital ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT d.*, s.name as specialization_name
        FROM departments d
        LEFT JOIN specialities s ON d.specialization_id = s.id
        WHERE d.hospital_id = ? AND d.is_active = 1
        ORDER BY s.name
    ");
    $stmt->execute([$hospital_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departments ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>