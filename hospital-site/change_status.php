<?php
// change_status.php
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

if ($id && in_array($new_status, ['pending', 'completed', 'cancelled'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);
    
    // Если статус "completed", можно автоматически добавить оценку
    if ($new_status == 'completed') {
        // Получаем doctor_id для этой записи
        $stmt = $pdo->prepare("SELECT doctor_id FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        $appointment = $stmt->fetch();
        
        // Автоматически добавляем оценку 5 (для теста)
        try {
            // Проверяем, нет ли уже оценки
            $check_stmt = $pdo->prepare("SELECT id FROM ratings WHERE appointment_id = ?");
            $check_stmt->execute([$id]);
            
            if (!$check_stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO ratings (appointment_id, score) VALUES (?, ?)");
                $stmt->execute([$id, 5]);
            }
        } catch (Exception $e) {
            // Игнорируем ошибку
        }
    }
}

// Возвращаем на предыдущую страницу
$referer = $_SERVER['HTTP_REFERER'] ?? 'admin.php';
header("Location: $referer");
exit;
?>