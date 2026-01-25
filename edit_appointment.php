<?php
// ========================
// EDIT APPOINTMENT (только статус и оплата). Работает полностью, чтобы изменения были видны, нужно обновлять страницу
// ========================
session_start();
require 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$appointment = null;

if ($appointment_id > 0) {
    $appointment = $pdo->query("
        SELECT a.*, d.name as doctor_name
        FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.id = $appointment_id
    ")->fetch(PDO::FETCH_ASSOC);
}

if (!$appointment) {
    die('Запись не найдена');
}

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    
    $sql = "UPDATE appointments SET status = ?, payment_status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $payment_status, $appointment_id]);
    
    $message = "✅ Данные обновлены";
    
    // Обновляем запись
    $appointment = $pdo->query("
        SELECT a.*, d.name as doctor_name 
        FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.id = $appointment_id
    ")->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Редактирование записи #<?= $appointment_id ?></title>
    <style>
        .info-box { background: #f0f8ff; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .form-group { margin: 15px 0; }
        label { display: inline-block; width: 150px; font-weight: bold; }
        select, input { padding: 8px; font-size: 16px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        .save-btn { background: #4CAF50; color: white; border: none; }
        .back-btn { background: #ddd; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <h1>Редактирование записи #<?= $appointment_id ?></h1>
    
    <!-- Информация о записи (только для просмотра) -->
    <div class="info-box">
        <h3>Информация о записи:</h3>
        <p><strong>Пациент:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
        <p><strong>Телефон:</strong> <?= $appointment['patient_phone'] ?></p>
        <p><strong>Врач:</strong> <?= $appointment['doctor_name'] ?></p>
        <p><strong>Дата и время:</strong> <?= date('d.m.Y H:i', strtotime($appointment['appointment_datetime'])) ?></p>
    </div>
    
    <?php if (isset($message)) echo "<p style='color:green;'>$message</p>"; ?>
    
    <!-- Форма изменения статуса и оплаты -->
    <form method="POST">
        <div class="form-group">
            <label>Статус приёма:</label>
            <select name="status">
                <option value="pending" <?= $appointment['status']=='pending'?'selected':'' ?>>⏳ Ожидает</option>
                <option value="completed" <?= $appointment['status']=='completed'?'selected':'' ?>>✅ Завершён</option>
                <option value="cancelled" <?= $appointment['status']=='cancelled'?'selected':'' ?>>❌ Отменён</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Статус оплаты:</label>
            <select name="payment_status">
                <option value="unpaid" <?= $appointment['payment_status']=='unpaid'?'selected':'' ?>>💵 Не оплачено</option>
                <option value="paid" <?= $appointment['payment_status']=='paid'?'selected':'' ?>>💳 Оплачено</option>
            </select>
        </div>
        
        <button type="submit" class="save-btn">💾 Сохранить изменения</button>
        <a href="admin1.php?tab=appointments">
            <button type="button" class="back-btn">← Назад к списку</button>
        </a>
    </form>
</body>
</html>