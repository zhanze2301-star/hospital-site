<?php
// api/get_doctors.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../config.php';

try {
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    $specialization_id = isset($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : null;
    
    if ($service_id) {
        // Врачи, оказывающие конкретную услугу
        $stmt = $pdo->prepare("
            SELECT DISTINCT d.*, s.name as speciality_name
            FROM doctors d
            LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
            LEFT JOIN specialities s ON d.speciality_id = s.id
            WHERE ds.service_id = :service_id
            ORDER BY d.rating DESC, d.name
        ");
        $stmt->execute([':service_id' => $service_id]);
    } elseif ($specialization_id) {
        // Врачи конкретной специальности
        $stmt = $pdo->prepare("
            SELECT d.*, s.name as speciality_name
            FROM doctors d
            LEFT JOIN specialities s ON d.speciality_id = s.id
            WHERE d.speciality_id = :specialization_id
            ORDER BY d.rating DESC, d.name
        ");
        $stmt->execute([':specialization_id' => $specialization_id]);
    } else {
        // Все врачи
        $stmt = $pdo->prepare("
            SELECT d.*, s.name as speciality_name
            FROM doctors d
            LEFT JOIN specialities s ON d.speciality_id = s.id
            ORDER BY d.rating DESC, d.name
        ");
        $stmt->execute();
    }
    
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'doctors' => $doctors,
        'count' => count($doctors)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных'
    ]);
}
?>