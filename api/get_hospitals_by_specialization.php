<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$specialization_id = intval($_GET['specialization_id'] ?? 0);
$with_coords = isset($_GET['with_coords']);

if (!$specialization_id) {
    echo json_encode(['error' => 'Specialization ID is required']);
    exit;
}

try {
    $sql = "
        SELECT DISTINCT h.*, 
               (SELECT COUNT(*) FROM doctors d 
                LEFT JOIN departments dept ON d.department_id = dept.id 
                WHERE dept.hospital_id = h.id 
                AND d.speciality_id = ?) as doctor_count
        FROM hospitals h
        LEFT JOIN departments dept ON h.id = dept.hospital_id
        LEFT JOIN doctors d ON d.department_id = dept.id
        WHERE h.is_active = 1
        AND (d.speciality_id = ? OR dept.specialization_id = ?)
        GROUP BY h.id
        ORDER BY h.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$specialization_id, $specialization_id, $specialization_id]);
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Фильтруем координаты если не нужны
    if (!$with_coords) {
        foreach ($hospitals as &$hospital) {
            unset($hospital['latitude'], $hospital['longitude']);
        }
    }
    
    echo json_encode($hospitals ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>