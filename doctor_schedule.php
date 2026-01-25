<?php // doctor_schedule.php - Расписание конкретного врача. Работатют все функции
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
$stmt = $pdo->prepare("
    SELECT d.*, s.name as speciality_name
    FROM doctors d
    LEFT JOIN specialities s ON d.speciality_id = s.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Location: admin_doctors.php');
    exit;
}

// Получаем расписание врача
$schedule = [];
$days = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

// Проверяем, есть ли таблица doctor_schedule
$table_exists = $pdo->query("SHOW TABLES LIKE 'doctor_schedule'")->rowCount() > 0;

if ($table_exists) {
    // Получаем расписание из таблицы
    $stmt = $pdo->prepare("
        SELECT day_of_week, start_time, end_time, is_working 
        FROM doctor_schedule 
        WHERE doctor_id = ? 
        ORDER BY day_of_week
    ");
    $stmt->execute([$doctor_id]);
    $schedule_data = $stmt->fetchAll();
    
    foreach ($schedule_data as $row) {
        $schedule[$row['day_of_week']] = $row;
    }
} else {
    // Стандартное расписание (9:00-18:00, пн-пт)
    for ($i = 1; $i <= 7; $i++) {
        $schedule[$i] = [
            'day_of_week' => $i,
            'start_time' => $i <= 5 ? '09:00:00' : null,
            'end_time' => $i <= 5 ? '18:00:00' : null,
            'is_working' => $i <= 5 ? 1 : 0
        ];
    }
}

// Получаем будущие записи врача
$future_appointments = $pdo->prepare("
    SELECT a.*, 
           DATE(a.appointment_datetime) as date_only,
           TIME(a.appointment_datetime) as time_only
    FROM appointments a
    WHERE a.doctor_id = ? 
    AND a.appointment_datetime >= NOW()
    AND a.status != 'cancelled'
    ORDER BY a.appointment_datetime
    LIMIT 50
");
$future_appointments->execute([$doctor_id]);
$appointments = $future_appointments->fetchAll();

// Группируем записи по датам
$appointments_by_date = [];
foreach ($appointments as $app) {
    $date = $app['date_only'];
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $app;
}

// Обработка формы изменения расписания
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    // Создаём таблицу doctor_schedule если её нет
    if (!$table_exists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `doctor_schedule` (
                `id` int NOT NULL AUTO_INCREMENT,
                `doctor_id` int NOT NULL,
                `day_of_week` tinyint NOT NULL,
                `start_time` time DEFAULT NULL,
                `end_time` time DEFAULT NULL,
                `is_working` tinyint(1) DEFAULT '1',
                PRIMARY KEY (`id`),
                UNIQUE KEY `doctor_day` (`doctor_id`,`day_of_week`),
                FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $table_exists = true;
    }
    
    // Обновляем расписание
    foreach ($_POST['schedule'] as $day => $data) {
        $is_working = isset($data['is_working']) ? 1 : 0;
        $start_time = $is_working ? ($data['start_time'] ?? '09:00:00') : null;
        $end_time = $is_working ? ($data['end_time'] ?? '18:00:00') : null;
        
        // Проверяем, существует ли запись
        $check_stmt = $pdo->prepare("SELECT id FROM doctor_schedule WHERE doctor_id = ? AND day_of_week = ?");
        $check_stmt->execute([$doctor_id, $day]);
        
        if ($check_stmt->fetch()) {
            // Обновляем существующую
            $stmt = $pdo->prepare("
                UPDATE doctor_schedule 
                SET start_time = ?, end_time = ?, is_working = ?
                WHERE doctor_id = ? AND day_of_week = ?
            ");
            $stmt->execute([$start_time, $end_time, $is_working, $doctor_id, $day]);
        } else {
            // Создаём новую
            $stmt = $pdo->prepare("
                INSERT INTO doctor_schedule (doctor_id, day_of_week, start_time, end_time, is_working)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$doctor_id, $day, $start_time, $end_time, $is_working]);
        }
    }
    
    header("Location: doctor_schedule.php?id=$doctor_id&success=1");
    exit;
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
        .schedule-card {
            border-radius: 10px;
            transition: all 0.3s;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .working-day {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        .non-working-day {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .time-slot {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .appointment-busy {
            background-color: #ffcccc;
            border-left: 3px solid #ff6b6b;
        }
        .appointment-available {
            background-color: #ccffcc;
            border-left: 3px solid #4CAF50;
        }
        .day-column {
            min-height: 150px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <!-- Заголовок -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-clock"></i> Расписание врача</h2>
                <h4 class="text-primary"><?php echo htmlspecialchars($doctor['name']); ?></h4>
                <p class="text-muted">
                    <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($doctor['speciality_name']); ?>
                    <?php if ($doctor['workplace']): ?>
                        | <i class="bi bi-building"></i> <?php echo $doctor['workplace']; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="admin_doctors.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Назад к списку врачей
                </a>
                <a href="book.php?doctor_id=<?php echo $doctor_id; ?>" target="_blank" class="btn btn-primary">
                    <i class="bi bi-calendar-plus"></i> Записать пациента
                </a>
            </div>
        </div>
        
        <!-- Вкладки -->
        <ul class="nav nav-tabs mb-4" id="scheduleTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#weekly-schedule">
                    <i class="bi bi-calendar-week"></i> Еженедельное расписание
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#upcoming-appointments">
                    <i class="bi bi-calendar-check"></i> Ближайшие записи
                </button>
            </li>
            <!-- <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar-view">
                    <i class="bi bi-calendar-month"></i> Календарь
                </button>
            </li> -->
        </ul>
        
        <div class="tab-content">
            <!-- Еженедельное расписание -->
            <div class="tab-pane fade show active" id="weekly-schedule">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Еженедельное рабочее расписание</h5>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editScheduleForm">
                            <i class="bi bi-pencil"></i> Редактировать
                        </button>
                    </div>
                    
                    <!-- Форма редактирования расписания -->
                    <div class="collapse" id="editScheduleForm">
                        <div class="card-body border-bottom">
                            <form method="POST">
                                <div class="row">
                                    <?php for ($i = 1; $i <= 7; $i++): 
                                        $day_schedule = $schedule[$i] ?? [
                                            'day_of_week' => $i,
                                            'start_time' => '09:00:00',
                                            'end_time' => '18:00:00',
                                            'is_working' => $i <= 5 ? 1 : 0
                                        ];
                                    ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card schedule-card <?php echo $day_schedule['is_working'] ? 'working-day' : 'non-working-day'; ?>">
                                            <div class="card-body">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="schedule[<?php echo $i; ?>][is_working]" 
                                                           id="day<?php echo $i; ?>" 
                                                           value="1" 
                                                           <?php echo $day_schedule['is_working'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-bold" for="day<?php echo $i; ?>">
                                                        <?php echo $days[$i-1]; ?>
                                                    </label>
                                                </div>
                                                
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small">Начало</label>
                                                        <input type="time" 
                                                               name="schedule[<?php echo $i; ?>][start_time]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $day_schedule['start_time'] ? substr($day_schedule['start_time'], 0, 5) : ''; ?>"
                                                               <?php echo !$day_schedule['is_working'] ? 'disabled' : ''; ?>
                                                               onchange="toggleTimeInput(this, <?php echo $i; ?>)">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small">Конец</label>
                                                        <input type="time" 
                                                               name="schedule[<?php echo $i; ?>][end_time]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $day_schedule['end_time'] ? substr($day_schedule['end_time'], 0, 5) : ''; ?>"
                                                               <?php echo !$day_schedule['is_working'] ? 'disabled' : ''; ?>
                                                               onchange="toggleTimeInput(this, <?php echo $i; ?>)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" name="update_schedule" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить расписание
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editScheduleForm">
                                        Отмена
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Просмотр расписания -->
                    <div class="card-body">
                        <div class="row">
                            <?php for ($i = 1; $i <= 7; $i++): 
                                $day_schedule = $schedule[$i] ?? [
                                    'day_of_week' => $i,
                                    'start_time' => '09:00:00',
                                    'end_time' => '18:00:00',
                                    'is_working' => $i <= 5 ? 1 : 0
                                ];
                            ?>
                            <div class="col mb-3">
                                <div class="card schedule-card <?php echo $day_schedule['is_working'] ? 'working-day' : 'non-working-day'; ?> day-column">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo $days[$i-1]; ?></h6>
                                        
                                        <?php if ($day_schedule['is_working'] && $day_schedule['start_time'] && $day_schedule['end_time']): ?>
                                            <p class="mb-2">
                                                <i class="bi bi-clock"></i> 
                                                <?php echo substr($day_schedule['start_time'], 0, 5); ?> - 
                                                <?php echo substr($day_schedule['end_time'], 0, 5); ?>
                                            </p>
                                            <small class="text-success">
                                                <i class="bi bi-check-circle"></i> Рабочий день
                                            </small>
                                        <?php else: ?>
                                            <p class="mb-2 text-muted">
                                                <i class="bi bi-clock"></i> Не работает
                                            </p>
                                            <small class="text-danger">
                                                <i class="bi bi-x-circle"></i> Выходной
                                            </small>
                                        <?php endif; ?>
                                        
                                        <!-- Записи на этот день недели в ближайшие 2 недели -->
                                        <?php
                                        $next_two_weeks = [];
                                        $today = new DateTime();
                                        for ($j = 0; $j < 14; $j++) {
                                            $date = clone $today;
                                            $date->modify("+$j days");
                                            if ($date->format('N') == $i) { // 'N' возвращает день недели (1-пн, 7-вс)
                                                $next_two_weeks[] = $date->format('Y-m-d');
                                            }
                                        }
                                        ?>
                                        
                                        <?php if (!empty($next_two_weeks)): ?>
                                            <hr class="my-2">
                                            <small class="text-muted d-block mb-1">Ближайшие приёмы:</small>
                                            <?php foreach ($next_two_weeks as $date): 
                                                if (isset($appointments_by_date[$date])): 
                                                    $count = count($appointments_by_date[$date]);
                                            ?>
                                                <div class="small mb-1">
                                                    <?php echo date('d.m', strtotime($date)); ?>: 
                                                    <span class="badge bg-info"><?php echo $count; ?></span>
                                                </div>
                                            <?php endif; endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ближайшие записи -->
            <div class="tab-pane fade" id="upcoming-appointments">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Ближайшие записи на приём</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> У врача нет предстоящих записей.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Дата и время</th>
                                            <th>Пациент</th>
                                            <th>Телефон</th>
                                            <th>Статус</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $app): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($app['date_only'])); ?>
                                                <br>
                                                <span class="text-primary"><?php echo substr($app['time_only'], 0, 5); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $app['patient_phone'] ?: '<span class="text-muted">не указан</span>'; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_class = [
                                                    'pending' => 'warning',
                                                    'completed' => 'success',
                                                    'cancelled' => 'secondary'
                                                ][$app['status']];
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo $app['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_appointment.php?id=<?php echo $app['id']; ?>" 
                                                       class="btn btn-outline-info" title="Просмотр">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($app['status'] == 'pending'): ?>
                                                        <a href="change_status.php?id=<?php echo $app['id']; ?>&status=completed" 
                                                           class="btn btn-outline-success" title="Завершить">
                                                            <i class="bi bi-check-lg"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Календарь
            <div class="tab-pane fade" id="calendar-view">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Календарь записей</h5>
                    </div>
                    <div class="card-body">
                        <div id="doctorCalendar"></div>
                    </div>
                </div>
            </div>
        </div> -->
        
        <!-- Статистика -->
        <div class="row mt-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3><?php echo count($appointments); ?></h3>
                        <p class="text-muted mb-0">Ближайшие записи</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <?php
                        $working_days = array_filter($schedule, fn($s) => $s['is_working']);
                        ?>
                        <h3><?php echo count($working_days); ?></h3>
                        <p class="text-muted mb-0">Рабочих дней в неделю</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <?php
                        $pending_count = array_filter($appointments, fn($a) => $a['status'] == 'pending');
                        ?>
                        <h3><?php echo count($pending_count); ?></h3>
                        <p class="text-muted mb-0">Ожидают приёма</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <?php
                        $today = date('Y-m-d');
                        $today_count = isset($appointments_by_date[$today]) ? count($appointments_by_date[$today]) : 0;
                        ?>
                        <h3><?php echo $today_count; ?></h3>
                        <p class="text-muted mb-0">Записей на сегодня</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/ru.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Включение/выключение полей времени
        function toggleTimeInput(checkbox, day) {
            const isChecked = checkbox.checked;
            const startInput = document.querySelector(`input[name="schedule[${day}][start_time]"]`);
            const endInput = document.querySelector(`input[name="schedule[${day}][end_time]"]`);
            
            if (startInput) startInput.disabled = !isChecked;
            if (endInput) endInput.disabled = !isChecked;
            
            if (!isChecked) {
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }
        
        // Инициализация календаря
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('doctorCalendar');
            
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'ru',
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: function(fetchInfo, successCallback) {
                        // Загружаем записи врача
                        fetch(`api/get_doctor_appointments.php?doctor_id=<?php echo $doctor_id; ?>&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => response.json())
                            .then(data => {
                                const events = data.map(app => ({
                                    id: app.id,
                                    title: app.patient_name,
                                    start: app.appointment_datetime,
                                    backgroundColor: app.status === 'pending' ? '#ffc107' : 
                                                    app.status === 'completed' ? '#198754' : '#6c757d',
                                    borderColor: app.status === 'pending' ? '#ffc107' : 
                                                 app.status === 'completed' ? '#198754' : '#6c757d',
                                    extendedProps: {
                                        phone: app.patient_phone,
                                        status: app.status
                                    }
                                }));
                                successCallback(events);
                            })
                            .catch(error => {
                                console.error('Ошибка загрузки событий:', error);
                                successCallback([]);
                            });
                    },
                    eventClick: function(info) {
                        window.open(`view_appointment.php?id=${info.event.id}`, '_blank');
                    },
                    eventDidMount: function(info) {
                        info.el.title = `${info.event.title}\nТелефон: ${info.event.extendedProps.phone || 'не указан'}\nСтатус: ${info.event.extendedProps.status}`;
                    }
                });
                
                calendar.render();
            }
            
            // Активация вкладок
            const triggerTabList = document.querySelectorAll('#scheduleTabs button');
            triggerTabList.forEach(triggerEl => {
                triggerEl.addEventListener('click', event => {
                    event.preventDefault();
                    const tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                });
            });
            
            // Показ сообщения об успешном сохранении
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success')) {
                alert('Расписание успешно обновлено!');
                urlParams.delete('success');
                window.history.replaceState({}, '', `${window.location.pathname}?${urlParams}`);
            }
        });
    </script>
</body>
</html>