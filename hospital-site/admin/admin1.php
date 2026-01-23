<?php
// admin.php - ИСПРАВЛЕННАЯ АДМИН-ПАНЕЛЬ
session_start();
require_once 'config.php';

// Пароль администратора (ПОМЕНЯЙТЕ на свой сложный!)
$admin_password = 'HospitalAdmin2025!';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        
        if ($password === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name'] = 'Администратор';
            
            if (isset($_POST['remember'])) {
                setcookie('admin_auth', md5($password), time() + 30*24*60*60, '/');
            }
            
            header('Location: admin.php');
            exit;
        } else {
            $login_error = 'Неверный пароль!';
        }
    }
    
    // Показываем форму входа
    showLoginForm($login_error ?? '');
    exit;
}

// Функция для отображения формы входа
function showLoginForm($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в админ-панель</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; }
            .login-card { background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .hospital-icon { font-size: 48px; color: #667eea; }
        </style>
    </head>
    <body>
        <div class="container h-100 d-flex align-items-center">
            <div class="row w-100 justify-content-center">
                <div class="col-md-5">
                    <div class="login-card p-5">
                        <div class="text-center mb-4">
                            <div class="hospital-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <h3>Доступ к админ-панели</h3>
                            <p class="text-muted">Больница "Здоровье"</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль администратора</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Введите пароль" required>
                                <div class="form-text">
                                    Для тестирования: <?php echo $GLOBALS['admin_password']; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                                <label class="form-check-label" for="remember">Запомнить меня (30 дней)</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Войти
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> На главную
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    </body>
    </html>
    <?php
}

// Если мы здесь, значит пользователь авторизован

// Получаем статистику (ИСПРАВЛЕННЫЙ ЗАПРОС!)
$stats = [
    'total_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = CURDATE()")->fetchColumn(),
    'tomorrow' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn(),
    'total_doctors' => $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn(),
    'doctors_with_rating' => $pdo->query("SELECT COUNT(*) FROM doctors WHERE rating > 0")->fetchColumn(),
    'recent_patients' => $pdo->query("SELECT COUNT(DISTINCT patient_name) FROM appointments WHERE appointment_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель - Управление</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: #2c3e50;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        .appointment-row:hover {
            background-color: #f8f9fa;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 2px 0;
            transition: background 0.3s;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .sidebar a.active {
            background: #3498db;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar p-3">
        <h4 class="mb-4">
            <i class="bi bi-hospital"></i> Админ-панель
        </h4>
        
        <div class="mb-4">
            <div class="text-muted mb-2">Главное меню</div>
            <a href="admin1.php" class="active">
                <i class="bi bi-speedometer2"></i> Панель управления
            </a>
            <a href="admin_appointments.php">
                <i class="bi bi-calendar-check"></i> Записи на приём
            </a>
            <a href="admin_doctors.php">
                <i class="bi bi-people"></i> Врачи
            </a>
            <a href="admin_schedule.php">
                <i class="bi bi-clock"></i> Расписание
            </a>
        </div>
        
        <div class="mb-4">
            <div class="text-muted mb-2">Отчёты</div>
            <a href="admin_reports.php?type=daily">
                <i class="bi bi-bar-chart"></i> Ежедневный отчёт
            </a>
            <a href="admin_reports.php?type=doctors">
                <i class="bi bi-graph-up"></i> Статистика врачей
            </a>
        </div>
        
        <div class="mt-5 pt-4 border-top">
            <div class="text-muted mb-2">
                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['admin_name']; ?>
            </div>
            <a href="logout.php" class="text-danger">
                <i class="bi bi-box-arrow-right"></i> Выйти
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Заголовок -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <i class="bi bi-speedometer2"></i> Панель управления
            </h1>
            <div class="text-muted">
                <?php echo date('d.m.Y H:i'); ?>
            </div>
        </div>

        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $stats['total_appointments']; ?></h3>
                                <p class="card-text">Всего записей</p>
                            </div>
                            <i class="bi bi-calendar4" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $stats['pending']; ?></h3>
                                <p class="card-text">Ожидают приёма</p>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $stats['today']; ?></h3>
                                <p class="card-text">Приёмов сегодня</p>
                            </div>
                            <i class="bi bi-calendar-day" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $stats['tomorrow']; ?></h3>
                                <p class="card-text">На завтра</p>
                            </div>
                            <i class="bi bi-calendar-plus" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последние записи -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history"></i> Последние записи
                </h5>
                <a href="admin_appointments.php" class="btn btn-sm btn-outline-primary">
                    Все записи <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body">
                <?php
                $recent_appointments = $pdo->query("
                    SELECT a.*, d.name as doctor_name, s.name as speciality_name
                    FROM appointments a
                    LEFT JOIN doctors d ON a.doctor_id = d.id
                    LEFT JOIN specialities s ON d.speciality_id = s.id
                    ORDER BY a.appointment_datetime DESC
                    LIMIT 10
                ")->fetchAll();
                ?>
                
                <?php if (empty($recent_appointments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Нет записей на приём.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Пациент</th>
                                    <th>Врач</th>
                                    <th>Дата/время</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $app): ?>
                                <tr class="appointment-row">
                                    <td>#<?php echo $app['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong>
                                        <?php if ($app['patient_phone']): ?>
                                            <br><small class="text-muted"><?php echo $app['patient_phone']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['doctor_name']); ?></td>
                                    <td>
                                        <?php 
                                        $datetime = new DateTime($app['appointment_datetime']);
                                        echo $datetime->format('d.m.Y H:i');
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_config = [
                                            'pending' => ['class' => 'warning', 'icon' => 'clock'],
                                            'completed' => ['class' => 'success', 'icon' => 'check-circle'],
                                            'cancelled' => ['class' => 'secondary', 'icon' => 'x-circle']
                                        ];
                                        $status = $status_config[$app['status']];
                                        ?>
                                        <span class="badge bg-<?php echo $status['class']; ?> status-badge">
                                            <i class="bi bi-<?php echo $status['icon']; ?>"></i>
                                            <?php echo $app['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($app['status'] == 'pending'): ?>
                                                <a href="change_status.php?id=<?php echo $app['id']; ?>&status=completed" 
                                                   class="btn btn-success" title="Завершить">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                                <a href="change_status.php?id=<?php echo $app['id']; ?>&status=cancelled" 
                                                   class="btn btn-danger" title="Отменить">
                                                    <i class="bi bi-x-lg"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="view_appointment.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-info" title="Подробнее">
                                                <i class="bi bi-eye"></i>
                                            </a>
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

    <script>
        // Автообновление каждые 60 секунд
        setInterval(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 60000);
    </script>
</body>
</html>