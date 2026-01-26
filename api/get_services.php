<?php
// api/get_services.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../config.php';

try {
    $specialization_id = isset($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : null;
    
    if ($specialization_id) {
        $stmt = $pdo->prepare("
            SELECT s.*, sp.name as specialization_name
            FROM services s
            LEFT JOIN specialities sp ON s.specialization_id = sp.id
            WHERE (s.specialization_id = :specialization_id OR s.specialization_id IS NULL)
            AND s.is_active = 1
            ORDER BY s.specialization_id, s.name
        ");
        $stmt->execute([':specialization_id' => $specialization_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, sp.name as specialization_name
            FROM services s
            LEFT JOIN specialities sp ON s.specialization_id = sp.id
            WHERE s.is_active = 1
            ORDER BY s.specialization_id, s.name
        ");
        $stmt->execute();
    }
    
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>