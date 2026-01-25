<?php
// ========================
// RATE DOCTOR (админ добавляет оценку)
// ========================
session_start();
require 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Функция обновления рейтинга врача (объявляем ДО использования)
function updateDoctorRating($pdo, $doctor_id) {
    $sql = "SELECT AVG(r.score) as avg_score 
            FROM ratings r
            JOIN appointments a ON r.appointment_id = a.id
            WHERE a.doctor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $avg_score = round($result['avg_score'] ?? 0, 1);
    
    $update_sql = "UPDATE doctors SET rating = ? WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$avg_score, $doctor_id]);
}

// Получаем завершённые записи без оценок
$appointments = $pdo->query("
    SELECT a.id, a.patient_name, a.doctor_id, d.name as doctor_name, 
           a.appointment_datetime
    FROM appointments a
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN ratings r ON a.id = r.appointment_id
    WHERE a.status = 'completed' 
    AND r.id IS NULL
    ORDER BY a.appointment_datetime DESC
")->fetchAll();

// Добавление оценки
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_rating'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $score = (int)$_POST['score'];
    $comment = $_POST['comment'] ?? '';
    $doctor_id = (int)$_POST['doctor_id'];
    
    // Проверяем, нет ли уже оценки
    $check = $pdo->query("SELECT id FROM ratings WHERE appointment_id = $appointment_id")->fetch();
    
    if ($check) {
        $error = "❌ Для этой записи уже есть оценка";
    } else {
        // Добавляем оценку
        $sql = "INSERT INTO ratings (appointment_id, score, comment) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$appointment_id, $score, $comment]);
        
        // Обновляем средний рейтинг врача
        updateDoctorRating($pdo, $doctor_id);
        
        $message = "✅ Оценка добавлена";
        
        // Обновляем список
        $appointments = $pdo->query("
            SELECT a.id, a.patient_name, a.doctor_id, d.name as doctor_name, 
                   a.appointment_datetime
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN ratings r ON a.id = r.appointment_id
            WHERE a.status = 'completed' 
            AND r.id IS NULL
            ORDER BY a.appointment_datetime DESC
        ")->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Добавление оценок врачам</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background-color: #f2f2f2; }
        .form-container { background: #f9f9f9; padding: 20px; margin: 20px 0; }
        select, textarea { padding: 8px; margin: 5px; }
        button { padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <h1>⭐ Добавление оценок врачам</h1>
    
    <?php if (isset($message)) echo "<p style='color:green;'>$message</p>"; ?>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    
    <!-- Список записей без оценок -->
    <h2>Завершённые записи без оценок</h2>
    <?php if (empty($appointments)): ?>
        <p>Нет записей для оценки</p>
    <?php else: ?>
        <table>
            <tr><th>Пациент</th><th>Врач</th><th>Дата приёма</th><th>Оценка</th></tr>
            <?php foreach ($appointments as $app): ?>
            <tr>
                <td><?= htmlspecialchars($app['patient_name']) ?></td>
                <td><?= $app['doctor_name'] ?></td>
                <td><?= date('d.m.Y H:i', strtotime($app['appointment_datetime'])) ?></td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="add_rating" value="1">
                        <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                        <input type="hidden" name="doctor_id" value="<?= $app['doctor_id'] ?>">
                        
                        <select name="score" required>
                            <option value="">Выберите оценку</option>
                            <option value="1">1 ★</option>
                            <option value="2">2 ★★</option>
                            <option value="3">3 ★★★</option>
                            <option value="4">4 ★★★★</option>
                            <option value="5">5 ★★★★★</option>
                        </select>
                        
                        <textarea name="comment" placeholder="Комментарий (необязательно)" rows="1" style="width: 200px;"></textarea>
                        
                        <button type="submit">✅ Добавить оценку</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>