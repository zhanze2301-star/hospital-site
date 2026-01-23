<?php
// doctor_schedule.php - Расписание конкретного врача
// добавить кнопку что врач будет отсутствовать какое время во время дня на короткое время, и чтобы человеку который записывался пришло оповещение 
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$doctor_id = $_GET['id'] ?? null;
if (!$doctor_id) {
    header('Location: admin_doctors.php');
    exit;
}

// Получаем информацию о враче
$stmt = $pdo->prepare("SELECT d.*, s.name as speciality_name 
                       FROM doctors d 
                       LEFT JOIN specialities s ON d.speciality_id = s.id 
                       WHERE d.id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Location: admin_doctors.php');
    exit;
}

// Получаем расписание врача на текущую неделю
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$schedule = $pdo->prepare("
    SELECT a.*, 
           TIME(a.appointment_datetime) as time_only,
           DATE(a.appointment_datetime) as date_only
    FROM appointments a
    WHERE a.doctor_id = ?
    AND DATE(a.appointment_datetime) BETWEEN ? AND ?
    ORDER BY a.appointment_datetime
");
$schedule->execute([$doctor_id, $week_start, $week_end]);
$appointments = $schedule->fetchAll();

// Группируем по дням
$schedule_by_day = [];
foreach ($appointments as $app) {
    $day = $app['date_only'];
    if (!isset($schedule_by_day[$day])) {
        $schedule_by_day[$day] = [];
    }
    $schedule_by_day[$day][] = $app;
}

// Получаем рабочие часы врача (если есть таблица doctor_schedule)
$work_hours = [];
try {
    $hours_stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = ? ORDER BY day_of_week");
    $hours_stmt->execute([$doctor_id]);
    $work_hours = $hours_stmt->fetchAll();
} catch (Exception $e) {
    // Таблицы может не быть - это нормально
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расписание врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .schedule-day {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .time-slot {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            transition: all 0.3s;
        }
        .time-slot:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .time-slot.pending { border-left: 4px solid #ffc107; }
        .time-slot.completed { border-left: 4px solid #198754; }
        .time-slot.cancelled { border-left: 4px solid #6c757d; }
        .work-hours-table th {
            background: #e9ecef;
        }
        .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <!-- Заголовок -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>
                    <i class="bi bi-clock"></i> Расписание врача
                </h2>
                <p class="text-muted mb-0">
                    <strong><?php echo htmlspecialchars($doctor['name']); ?></strong> - 
                    <?php echo htmlspecialchars($doctor['speciality_name']); ?>
                </p>
            </div>
            <div class="btn-group">
                <a href="admin_doctors.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Назад
                </a>
                <a href="book.php?doctor_id=<?php echo $doctor_id; ?>" 
                   target="_blank" class="btn btn-primary">
                    <i class="bi bi-calendar-plus"></i> Записать пациента
                </a>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="bi bi-plus-circle"></i> Добавить в расписание
                </button>
            </div>
        </div>
        
        <!-- Недельная навигация -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            Неделя: <?php echo date('d.m.Y', strtotime($week_start)); ?> - 
                            <?php echo date('d.m.Y', strtotime($week_end)); ?>
                        </h5>
                    </div>
                    <div class="btn-group">
                        <a href="?id=<?php echo $doctor_id; ?>&week=prev" class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i> Предыдущая
                        </a>
                        <a href="?id=<?php echo $doctor_id; ?>" class="btn btn-outline-secondary">
                            Текущая
                        </a>
                        <a href="?id=<?php echo $doctor_id; ?>&week=next" class="btn btn-outline-primary">
                            Следующая <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Основное расписание -->
            <div class="col-md-8">
                <?php if (empty($schedule_by_day)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        На эту неделю нет записей на приём.
                    </div>
                <?php else: ?>
                    <?php foreach ($schedule_by_day as $day => $day_appointments): 
                        $day_name = [
                            'Monday' => 'Понедельник',
                            'Tuesday' => 'Вторник',
                            'Wednesday' => 'Среда',
                            'Thursday' => 'Четверг',
                            'Friday' => 'Пятница',
                            'Saturday' => 'Суббота',
                            'Sunday' => 'Воскресенье'
                        ][date('l', strtotime($day))];
                    ?>
                    <div class="schedule-day">
                        <div class="day-header">
                            <h5 class="mb-0">
                                <?php echo $day_name; ?>, <?php echo date('d.m.Y', strtotime($day)); ?>
                                <span class="badge bg-light text-dark float-end">
                                    <?php echo count($day_appointments); ?> записей
                                </span>
                            </h5>
                        </div>
                        
                        <?php foreach ($day_appointments as $app): ?>
                        <div class="time-slot <?php echo $app['status']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-primary"><?php echo substr($app['time_only'], 0, 5); ?></strong>
                                    <span class="ms-3">
                                        <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong>
                                    </span>
                                    <?php if ($app['patient_phone']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-telephone"></i> <?php echo $app['patient_phone']; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $app['status'] == 'pending' ? 'warning' : ($app['status'] == 'completed' ? 'success' : 'secondary'); ?> me-2">
                                        <?php echo $app['status']; ?>
                                    </span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_appointment.php?id=<?php echo $app['id']; ?>" 
                                           class="btn btn-outline-info" title="Просмотр">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($app['status'] == 'pending'): ?>
                                            <a href="change_status.php?id=<?php echo $app['id']; ?>&status=completed&return=doctor_schedule" 
                                               class="btn btn-outline-success" title="Завершить">
                                                <i class="bi bi-check-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Боковая панель -->
            <div class="col-md-4">
                <!-- Рабочие часы -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Рабочие часы</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($work_hours)): ?>
                            <p class="text-muted">Рабочие часы не установлены</p>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editWorkHoursModal">
                                <i class="bi bi-pencil"></i> Установить
                            </button>
                        <?php else: ?>
                            <table class="table table-sm work-hours-table">
                                <thead>
                                    <tr>
                                        <th>День</th>
                                        <th>Начало</th>
                                        <th>Конец</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($work_hours as $hour): 
                                        $day_names = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                    ?>
                                    <tr>
                                        <td><?php echo $day_names[$hour['day_of_week']] ?? $hour['day_of_week']; ?></td>
                                        <td><?php echo substr($hour['start_time'], 0, 5); ?></td>
                                        <td><?php echo substr($hour['end_time'], 0, 5); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editWorkHoursModal">
                                <i class="bi bi-pencil"></i> Изменить
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Статистика врача -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Статистика</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats_stmt = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
                            FROM appointments 
                            WHERE doctor_id = ?
                            AND DATE(appointment_datetime) >= CURDATE()
                        ");
                        $stats_stmt->execute([$doctor_id]);
                        $stats = $stats_stmt->fetch();
                        ?>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Всего записей:</span>
                                <strong><?php echo $stats['total'] ?? 0; ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Ожидают:</span>
                                <span class="badge bg-warning"><?php echo $stats['pending'] ?? 0; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Завершены:</span>
                                <span class="badge bg-success"><?php echo $stats['completed'] ?? 0; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Оплачено:</span>
                                <span class="badge bg-success"><?php echo $stats['paid'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно добавления записи -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="api/create_appointment.php" method="POST">
                    <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить запись на приём</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Пациент *</label>
                            <input type="text" name="patient_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="patient_phone" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Дата *</label>
                                <input type="date" name="date" class="form-control" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Время *</label>
                                <input type="time" name="time" class="form-control" 
                                       value="09:00" step="1800" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно редактирования рабочих часов -->
    <div class="modal fade" id="editWorkHoursModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="api/save_work_hours.php" method="POST">
                    <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Рабочие часы врача</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Установите рабочие часы для каждого дня недели</p>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>День недели</th>
                                    <th>Работает</th>
                                    <th>Начало</th>
                                    <th>Конец</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $days = [
                                    1 => 'Понедельник',
                                    2 => 'Вторник', 
                                    3 => 'Среда',
                                    4 => 'Четверг',
                                    5 => 'Пятница',
                                    6 => 'Суббота',
                                    7 => 'Воскресенье'
                                ];
                                
                                foreach ($days as $day_num => $day_name):
                                    $existing = array_filter($work_hours, fn($h) => $h['day_of_week'] == $day_num);
                                    $hour = $existing ? reset($existing) : null;
                                ?>
                                <tr>
                                    <td><?php echo $day_name; ?></td>
                                    <td>
                                        <input type="checkbox" name="work[<?php echo $day_num; ?>][is_working]" 
                                               value="1" <?php echo $hour ? 'checked' : ''; ?> class="form-check-input">
                                    </td>
                                    <td>
                                        <input type="time" name="work[<?php echo $day_num; ?>][start_time]" 
                                               class="form-control" value="<?php echo $hour ? substr($hour['start_time'], 0, 5) : '09:00'; ?>">
                                    </td>
                                    <td>
                                        <input type="time" name="work[<?php echo $day_num; ?>][end_time]" 
                                               class="form-control" value="<?php echo $hour ? substr($hour['end_time'], 0, 5) : '18:00'; ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Автоматическое обновление каждые 2 минуты
        setInterval(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 120000);
    </script>
</body>
</html>