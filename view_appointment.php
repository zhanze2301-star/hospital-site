<?php
// view_appointment.php - ПРОСТАЯ ВЕРСИЯ для просмотра записи
// Поставить кнопку редактировать запись , чтобы можно было откорректировать если нажали не туда
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: admin_appointments.php');
    exit;
}

// Получаем данные записи (упрощённый запрос)
$stmt = $pdo->prepare("
    SELECT a.*, d.name as doctor_name, d.photo_url, d.workplace,
           s.name as speciality_name
    FROM appointments a
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN specialities s ON d.speciality_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    echo "<div class='alert alert-danger'>Запись не найдена</div>";
    exit;
}

// Получаем оценку, если есть
$rating_stmt = $pdo->prepare("SELECT score, comment FROM ratings WHERE appointment_id = ?");
$rating_stmt->execute([$id]);
$rating = $rating_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Запись #<?php echo $id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .info-card {
            border-left: 4px solid #3498db;
            border-radius: 5px;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="admin.php">Админ-панель</a>
            <a href="admin_appointments.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Назад
            </a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="bi bi-calendar-check"></i> Запись на приём #<?php echo $id; ?>
                </h4>
                <div>
                    <?php
                    $status_badge = [
                        'pending' => ['class' => 'warning', 'text' => 'Ожидает'],
                        'completed' => ['class' => 'success', 'text' => 'Завершена'],
                        'cancelled' => ['class' => 'secondary', 'text' => 'Отменена']
                    ][$appointment['status']];
                    ?>
                    <span class="badge bg-<?php echo $status_badge['class']; ?> me-2">
                        <?php echo $status_badge['text']; ?>
                    </span>
                    
                    <span class="badge bg-<?php echo $appointment['payment_status'] == 'paid' ? 'success' : 'danger'; ?>">
                        <?php echo $appointment['payment_status'] == 'paid' ? 'Оплачено' : 'Не оплачено'; ?>
                    </span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <!-- Информация о пациенте -->
                    <div class="col-md-6">
                        <div class="card info-card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Информация о пациенте</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">ФИО пациента:</th>
                                        <td><strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Телефон:</th>
                                        <td>
                                            <?php if ($appointment['patient_phone']): ?>
                                                <a href="tel:<?php echo $appointment['patient_phone']; ?>">
                                                    <?php echo $appointment['patient_phone']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">не указан</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Дата создания:</th>
                                        <td><?php echo date('d.m.Y H:i', strtotime($appointment['appointment_datetime'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Информация о приёме -->
                    <div class="col-md-6">
                        <div class="card info-card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Информация о приёме</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Дата и время:</th>
                                        <td>
                                            <strong><?php echo date('d.m.Y', strtotime($appointment['appointment_datetime'])); ?></strong>
                                            <br>
                                            <span class="text-primary"><?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Статус:</th>
                                        <td>
                                            <span class="badge bg-<?php echo $status_badge['class']; ?>">
                                                <?php echo $status_badge['text']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Оплата:</th>
                                        <td>
                                            <span class="badge bg-<?php echo $appointment['payment_status'] == 'paid' ? 'success' : 'danger'; ?>">
                                                <?php echo $appointment['payment_status'] == 'paid' ? 'Оплачено' : 'Не оплачено'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Информация о враче -->
                <div class="row">
                    <div class="col-12">
                        <div class="card info-card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Информация о враче</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <?php if ($appointment['photo_url']): ?>
                                        <img src="<?php echo htmlspecialchars($appointment['photo_url']); ?>" 
                                             class="rounded-circle me-4" 
                                             width="80" height="80" 
                                             style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-4" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-person" style="font-size: 36px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-grow-1">
                                        <h5><?php echo htmlspecialchars($appointment['doctor_name']); ?></h5>
                                        <p class="mb-1">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($appointment['speciality_name']); ?></span>
                                            <?php if ($appointment['workplace']): ?>
                                                <span class="badge bg-info ms-2"><?php echo $appointment['workplace']; ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <!-- <a href="book.php?doctor_id=<?php echo $appointment['doctor_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="bi bi-calendar-plus"></i> Записаться к этому врачу
                                        </a> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Оценка приёма -->
                <?php if ($rating): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card info-card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-star"></i> Оценка приёма</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="rating-stars me-3">
                                        <?php
                                        $score = intval($rating['score']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $score ? '★' : '☆';
                                        }
                                        ?>
                                    </div>
                                    <div>
                                        <strong><?php echo $score; ?>/5</strong>
                                        <?php if ($rating['comment']): ?>
                                            <div class="mt-2">
                                                <p class="mb-0"><strong>Комментарий:</strong></p>
                                                <p class="text-muted"><?php echo htmlspecialchars($rating['comment']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Действия -->
                <div class="mt-4">
                    <div class="btn-group">
                        <a href="admin_appointments.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Назад к списку
                        </a>
                        <?php if ($appointment['status'] == 'pending'): ?>
                            <a href="change_status.php?id=<?php echo $id; ?>&status=completed" 
                               class="btn btn-success">
                                <i class="bi bi-check-lg"></i> Завершить приём
                            </a>
                            <a href="change_status.php?id=<?php echo $id; ?>&status=cancelled" 
                               class="btn btn-danger">
                                <i class="bi bi-x-lg"></i> Отменить запись
                            </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer"></i> Распечатать
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>