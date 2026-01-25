<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, name, description 
        FROM specialities 
        ORDER BY name
    ");
    $specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($specializations ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>