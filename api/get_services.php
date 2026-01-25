<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$doctor_id = intval($_GET['doctor_id'] ?? 0);

if (!$doctor_id) {
    echo json_encode(['error' => 'Doctor ID is required']);
    exit;
}

try {
    // Получаем услуги, доступные для врача (через его специализацию)
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM services s
        WHERE s.is_active = 1 
        AND (s.specialization_id IS NULL OR s.specialization_id = (
            SELECT speciality_id FROM doctors WHERE id = ?
        ))
        ORDER BY s.name
    ");
    $stmt->execute([$doctor_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($services ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>