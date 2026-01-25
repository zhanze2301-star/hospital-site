<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, name, address, phone, latitude, longitude 
        FROM hospitals 
        WHERE is_active = 1 
        ORDER BY name
    ");
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($hospitals ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>