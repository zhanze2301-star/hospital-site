<?php
// api/get_specializations.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../config.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM specialities 
        ORDER BY name
    ");
    $stmt->execute();
    
    $specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'specializations' => $specializations,
        'count' => count($specializations)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных'
    ]);
}
?>