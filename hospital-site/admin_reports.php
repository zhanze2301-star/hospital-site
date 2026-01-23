<?php
// admin_reports.php - ИСПРАВЛЕННАЯ ВЕРСИЯ
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Тип отчёта
$report_type = $_GET['type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

// Ежедневный отчёт
if ($report_type == 'daily') {
    $report_title = "Ежедневный отчёт за " . date('d.m.Y', strtotime($date));
    
    // ИСПРАВЛЕННЫЕ ЗАПРОСЫ - правильно используем fetchColumn()
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ?");
    $stmt->execute([$date]);
    $daily_stats['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ? AND status = 'pending'");
    $stmt->execute([$date]);
    $daily_stats['pending'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ? AND status = 'completed'");
    $stmt->execute([$date]);
    $daily_stats['completed'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ? AND status = 'cancelled'");
    $stmt->execute([$date]);
    $daily_stats['cancelled'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ? AND payment_status = 'paid'");
    $stmt->execute([$date]);
    $daily_stats['paid'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = ? AND payment_status = 'unpaid'");
    $stmt->execute([$date]);
    $daily_stats['unpaid'] = $stmt->fetchColumn();
    
    // Расписание по часам
    $hourly_schedule = $pdo->prepare("
        SELECT HOUR(appointment_datetime) as hour, 
               COUNT(*) as count,
               GROUP_CONCAT(CONCAT(patient_name, ' (', TIME(appointment_datetime), ')') SEPARATOR ', ') as patients
        FROM appointments 
        WHERE DATE(appointment_datetime) = ?
        GROUP BY HOUR(appointment_datetime)
        ORDER BY hour
    ");
    $hourly_schedule->execute([$date]);
    $hourly_data = $hourly_schedule->fetchAll();
    
    // Врачи с количеством записей
    $doctors_daily = $pdo->prepare("
        SELECT d.name, d.speciality_id, s.name as speciality_name,
               COUNT(a.id) as appointments_count,
               SUM(CASE WHEN a.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialities s ON d.speciality_id = s.id
        WHERE DATE(a.appointment_datetime) = ?
        GROUP BY d.id
        ORDER BY appointments_count DESC
    ");
    $doctors_daily->execute([$date]);
    $doctors_stats = $doctors_daily->fetchAll();
}

// Месячный отчёт
elseif ($report_type == 'monthly') {
    $report_title = "Месячный отчёт за " . date('F Y', strtotime($month . '-01'));
    
    $monthly_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
            COUNT(DISTINCT patient_name) as unique_patients,
            COUNT(DISTINCT doctor_id) as active_doctors
        FROM appointments 
        WHERE DATE_FORMAT(appointment_datetime, '%Y-%m') = ?
    ");
    $monthly_stats->execute([$month]);
    $stats = $monthly_stats->fetch();
    
    // Динамика по дням
    $daily_trend = $pdo->prepare("
        SELECT DATE(appointment_datetime) as date,
               COUNT(*) as appointments,
               SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM appointments 
        WHERE DATE_FORMAT(appointment_datetime, '%Y-%m') = ?
        GROUP BY DATE(appointment_datetime)
        ORDER BY date
    ");
    $daily_trend->execute([$month]);
    $trend_data = $daily_trend->fetchAll();
}

// Отчёт по врачам
elseif ($report_type == 'doctors') {
    $report_title = "Статистика по врачам";
    
    $doctors_report = $pdo->query("
        SELECT d.id, d.name, d.rating, d.workplace,
               s.name as speciality_name,
               COUNT(a.id) as total_appointments,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
               AVG(r.score) as avg_rating,
               COUNT(DISTINCT a.patient_name) as unique_patients,
               MIN(a.appointment_datetime) as first_appointment,
               MAX(a.appointment_datetime) as last_appointment
        FROM doctors d
        LEFT JOIN specialities s ON d.speciality_id = s.id
        LEFT JOIN appointments a ON d.id = a.doctor_id
        LEFT JOIN ratings r ON a.id = r.appointment_id
        GROUP BY d.id
        ORDER BY total_appointments DESC
    ");
    $doctors_data = $doctors_report->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отчёты и статистика</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border-radius: 10px;
            transition: all 0.3s;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .trend-up { color: #198754; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
    </style>
</head>
<body>
    <?php 
    // Включаем заголовок
    if (file_exists('admin_header.php')) {
        include 'admin_header.php';
    } else {
        // Альтернативный заголовок
        ?>
        <nav class="navbar navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="admin.php">Админ-панель</a>
            </div>
        </nav>
        <?php
    }
    ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-bar-chart"></i> Отчёты и статистика</h2>
        
        <!-- Навигация по отчётам -->
        <div class="card mb-4">
            <div class="card-body">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type=='daily'?'active':''; ?>" 
                           href="?type=daily">Ежедневный</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type=='monthly'?'active':''; ?>" 
                           href="?type=monthly">Месячный</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type=='doctors'?'active':''; ?>" 
                           href="?type=doctors">По врачам</a>
                    </li>
                </ul>
                
                <!-- Фильтры для отчётов -->
                <div class="mt-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        
                        <?php if ($report_type == 'daily'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Дата отчёта</label>
                            <input type="date" name="date" class="form-control" 
                                   value="<?php echo $date; ?>">
                        </div>
                        <?php elseif ($report_type == 'monthly'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Месяц</label>
                            <input type="month" name="month" class="form-control" 
                                   value="<?php echo $month; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Обновить</button>
                        </div>
                        <div class="col-md-7 text-end">
                            <button type="button" class="btn btn-success" onclick="printReport()">
                                <i class="bi bi-printer"></i> Печать отчёта
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportReport()">
                                <i class="bi bi-download"></i> Экспорт
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Заголовок отчёта -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $report_title; ?></h3>
                <p class="text-muted mb-0">Сформировано: <?php echo date('d.m.Y H:i'); ?></p>
            </div>
        </div>
        
        <!-- Ежедневный отчёт -->
        <?php if ($report_type == 'daily'): ?>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-primary text-white">
                    <div class="card-body text-center">
                        <div class="stat-number"><?php echo $daily_stats['total']; ?></div>
                        <div class="stat-label">Всего записей</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="stat-number"><?php echo $daily_stats['completed']; ?></div>
                        <div class="stat-label">Завершено</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-warning text-dark">
                    <div class="card-body text-center">
                        <div class="stat-number"><?php echo $daily_stats['pending']; ?></div>
                        <div class="stat-label">Ожидают</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-info text-white">
                    <div class="card-body text-center">
                        <div class="stat-number"><?php echo $daily_stats['paid']; ?></div>
                        <div class="stat-label">Оплачено</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- График по часам -->
        <?php if (!empty($hourly_data)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Расписание по часам</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Врачи дня -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Врачи дня</h5>
            </div>
            <div class="card-body">
                <?php if (empty($doctors_stats)): ?>
                    <div class="alert alert-info">На выбранную дату нет записей</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Врач</th>
                                    <th>Специальность</th>
                                    <th>Записей</th>
                                    <th>Оплачено</th>
                                    <th>Загруженность</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors_stats as $doc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doc['name']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['speciality_name']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $doc['appointments_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $doc['paid_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $percentage = min(100, ($doc['appointments_count'] / 20) * 100);
                                        $color = $percentage > 80 ? 'danger' : ($percentage > 50 ? 'warning' : 'success');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $color; ?>" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo round($percentage); ?>%
                                            </div>
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
        
        <?php if (!empty($hourly_data)): ?>
        <script>
            // График по часам
            const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            const hourlyLabels = <?php echo json_encode(array_column($hourly_data, 'hour')); ?>;
            const hourlyCounts = <?php echo json_encode(array_column($hourly_data, 'count')); ?>;
            
            new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: hourlyLabels.map(h => h + ':00'),
                    datasets: [{
                        label: 'Количество записей',
                        data: hourlyCounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
        
        <?php elseif ($report_type == 'monthly'): ?>
        <!-- Месячный отчёт -->
        <div class="row mb-4">
            <?php 
            $stat_items = [
                ['total', 'Всего записей', 'primary'],
                ['unique_patients', 'Уникальных пациентов', 'success'],
                ['active_doctors', 'Активных врачей', 'info'],
                ['paid', 'Оплаченных записей', 'warning'],
                ['completed', 'Завершённых', 'success'],
                ['pending', 'Ожидают', 'warning']
            ];
            
            foreach ($stat_items as $item): 
                $value = $stats[$item[0]] ?? 0;
            ?>
            <div class="col-md-4 mb-3">
                <div class="card report-card">
                    <div class="card-body text-center">
                        <div class="stat-number"><?php echo $value; ?></div>
                        <div class="stat-label"><?php echo $item[1]; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- График динамики -->
        <?php if (!empty($trend_data)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Динамика за месяц</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <script>
            // График динамики за месяц
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            const trendDates = <?php echo json_encode(array_column($trend_data, 'date')); ?>;
            const trendAppointments = <?php echo json_encode(array_column($trend_data, 'appointments')); ?>;
            const trendPaid = <?php echo json_encode(array_column($trend_data, 'paid')); ?>;
            
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: trendDates.map(d => d.split('-')[2]),
                    datasets: [
                        {
                            label: 'Всего записей',
                            data: trendAppointments,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.3
                        },
                        {
                            label: 'Оплачено',
                            data: trendPaid,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
        
        <?php elseif ($report_type == 'doctors'): ?>
        <!-- Отчёт по врачам -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-activity"></i> Статистика врачей</h5>
            </div>
            <div class="card-body">
                <?php if (empty($doctors_data)): ?>
                    <div class="alert alert-info">Нет данных о врачах</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Врач</th>
                                    <th>Специальность</th>
                                    <th>Место</th>
                                    <th>Всего записей</th>
                                    <th>Завершено</th>
                                    <th>Рейтинг</th>
                                    <th>Уникальных пациентов</th>
                                    <th>Период активности</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors_data as $doc): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($doc['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['speciality_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $doc['workplace']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $doc['total_appointments']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $completion_rate = $doc['total_appointments'] > 0 
                                            ? round(($doc['completed'] / $doc['total_appointments']) * 100) 
                                            : 0;
                                        $color = $completion_rate > 80 ? 'success' : ($completion_rate > 50 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo $doc['completed']; ?> (<?php echo $completion_rate; ?>%)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($doc['avg_rating']): ?>
                                            <div class="text-warning">
                                                <?php
                                                $rating = round($doc['avg_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $rating ? '★' : '☆';
                                                }
                                                ?>
                                            </div>
                                            <small><?php echo round($doc['avg_rating'], 1); ?>/5</small>
                                        <?php else: ?>
                                            <span class="text-muted">Нет оценок</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $doc['unique_patients']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($doc['first_appointment']): ?>
                                            <small>
                                                <?php echo date('d.m.Y', strtotime($doc['first_appointment'])); ?> - 
                                                <?php echo date('d.m.Y', strtotime($doc['last_appointment'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Нет записей</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Печать отчёта
        function printReport() {
            window.print();
        }
        
        // Экспорт отчёта
        function exportReport() {
            alert('Функция экспорта в разработке. Можно сохранить страницу как PDF (Ctrl+P)');
        }
        
        // Автообновление отчёта каждые 5 минут
        setInterval(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>