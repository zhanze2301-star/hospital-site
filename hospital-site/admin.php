<?php
session_start();
require_once 'api/config.php';

// Проверяем авторизацию
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Получаем статистику
$stats = [
    'total_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'pending_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn(),
    'completed_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn(),
    'total_doctors' => $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn(),
    'today_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = CURDATE()")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель - Управление записями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background: #2c3e50;
            color: white;
            min-height: 100vh;
        }
        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            margin: 5px 0;
        }
        .sidebar a:hover {
            background: #34495e;
            color: white;
        }
        .sidebar a.active {
            background: #3498db;
        }
        .stat-card {
            transition: transform 0.3s;
            border: none;
            border-radius: 10px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-hospital"></i> Админ-панель
                    </h4>
                    <p class="text-muted mb-4">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo $_SESSION['admin_name'] ?? 'Администратор'; ?>
                    </p>
                    
                    <div class="mb-4">
                        <a href="admin.php" class="active">
                            <i class="bi bi-calendar-check"></i> Записи на приём
                        </a>
                        <a href="admin_doctors.php">
                            <i class="bi bi-people"></i> Врачи
                        </a>
                        <a href="admin_schedule.php">
                            <i class="bi bi-clock"></i> Расписание
                        </a>
                        <a href="admin_reports.php">
                            <i class="bi bi-bar-chart"></i> Отчёты
                        </a>
                    </div>
                    
                    <div class="mt-5">
                        <a href="logout.php" class="text-danger">
                            <i class="bi bi-box-arrow-right"></i> Выйти
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-clipboard-data"></i> Панель управления
                    </h2>
                    <span class="text-muted">
                        <?php echo date('d.m.Y H:i'); ?>
                    </span>
                </div>

                <!-- Статистика -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['total_appointments']; ?></h5>
                                        <p class="card-text">Всего записей</p>
                                    </div>
                                    <i class="bi bi-calendar4" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['pending_appointments']; ?></h5>
                                        <p class="card-text">Ожидают приёма</p>
                                    </div>
                                    <i class="bi bi-clock-history" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['completed_appointments']; ?></h5>
                                        <p class="card-text">Завершённые</p>
                                    </div>
                                    <i class="bi bi-check-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['today_appointments']; ?></h5>
                                        <p class="card-text">Сегодня</p>
                                    </div>
                                    <i class="bi bi-today" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Таблица записей -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-task"></i> Управление записями
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT a.*, d.name as doctor_name, d.photo_url, s.name as speciality_name
                            FROM appointments a
                            LEFT JOIN doctors d ON a.doctor_id = d.id
                            LEFT JOIN specialities s ON d.speciality_id = s.id
                            ORDER BY a.appointment_datetime DESC
                            LIMIT 50
                        ");
                        $appointments = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($appointments)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                Нет записей на приём. 
                                <a href="create_test_data.php" class="alert-link">Создать тестовые данные</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Пациент</th>
                                            <th>Врач</th>
                                            <th>Дата и время</th>
                                            <th>Статус</th>
                                            <th>Оплата</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $app): 
                                            $datetime = new DateTime($app['appointment_datetime']);
                                        ?>
                                        <tr>
                                            <td>#<?php echo $app['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong>
                                                <?php if ($app['patient_phone']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-telephone"></i> 
                                                        <?php echo htmlspecialchars($app['patient_phone']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($app['photo_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($app['photo_url']); ?>" 
                                                             class="rounded-circle me-2" 
                                                             width="40" height="40" 
                                                             style="object-fit: cover;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <div><?php echo htmlspecialchars($app['doctor_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($app['speciality_name']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo $datetime->format('d.m.Y'); ?></div>
                                                <div class="text-primary"><?php echo $datetime->format('H:i'); ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_config = [
                                                    'pending' => ['class' => 'warning', 'text' => 'Ожидает', 'icon' => 'clock'],
                                                    'completed' => ['class' => 'success', 'text' => 'Завершён', 'icon' => 'check-circle'],
                                                    'cancelled' => ['class' => 'secondary', 'text' => 'Отменён', 'icon' => 'x-circle']
                                                ];
                                                $status = $status_config[$app['status']] ?? $status_config['pending'];
                                                ?>
                                                <span class="badge bg-<?php echo $status['class']; ?> status-badge">
                                                    <i class="bi bi-<?php echo $status['icon']; ?>"></i>
                                                    <?php echo $status['text']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($app['payment_status'] == 'paid'): ?>
                                                    <span class="badge bg-success status-badge">
                                                        <i class="bi bi-credit-card"></i> Оплачено
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger status-badge">
                                                        <i class="bi bi-cash"></i> Не оплачено
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($app['status'] == 'pending'): ?>
                                                        <button class="btn btn-success" 
                                                                onclick="updateStatus(<?php echo $app['id']; ?>, 'completed')"
                                                                title="Завершить приём">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button class="btn btn-warning" 
                                                                onclick="updatePayment(<?php echo $app['id']; ?>, 'paid')"
                                                                title="Отметить оплату">
                                                            <i class="bi bi-credit-card"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-danger" 
                                                            onclick="updateStatus(<?php echo $app['id']; ?>, 'cancelled')"
                                                            title="Отменить запись">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
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
            </main>
        </div>
    </div>

    <script>
        // Функции обновления статуса
        async function updateStatus(appointmentId, newStatus) {
            const statusText = {
                'completed': 'завершён',
                'cancelled': 'отменён'
            };
            
            if (!confirm(`Вы уверены, что хотите отметить запись как ${statusText[newStatus]}?`)) return;
            
            const formData = new FormData();
            formData.append('id', appointmentId);
            formData.append('status', newStatus);
            
            try {
                const response = await fetch('api/update_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Статус обновлён!');
                    location.reload();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                alert('Ошибка сети: ' + error.message);
            }
        }
        
        async function updatePayment(appointmentId, paymentStatus) {
            const paymentText = paymentStatus === 'paid' ? 'оплаченной' : 'неоплаченной';
            
            if (!confirm(`Отметить запись как ${paymentText}?`)) return;
            
            const formData = new FormData();
            formData.append('id', appointmentId);
            formData.append('payment_status', paymentStatus);
            
            try {
                const response = await fetch('api/update_payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Статус оплаты обновлён!');
                    location.reload();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                alert('Ошибка сети: ' + error.message);
            }
        }
        
        // Автообновление каждые 30 секунд
        setInterval(() => {
            if (document.hasFocus()) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>