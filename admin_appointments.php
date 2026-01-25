<?php
// admin_appointments.php - Расширенное управление записями
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Получаем параметры фильтрации
$status = $_GET['status'] ?? 'all';
$doctor_id = $_GET['doctor_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Подготавливаем SQL запрос
$sql = "SELECT a.*, d.name as doctor_name, d.photo_url, d.workplace, 
               s.name as speciality_name,
               (SELECT score FROM ratings WHERE appointment_id = a.id) as rating
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialities s ON d.speciality_id = s.id
        WHERE 1=1";

$count_sql = "SELECT COUNT(*) as total 
              FROM appointments a
              LEFT JOIN doctors d ON a.doctor_id = d.id
              WHERE 1=1";

$params = [];
$count_params = [];

// Фильтр по статусу
if ($status !== 'all') {
    $sql .= " AND a.status = ?";
    $count_sql .= " AND a.status = ?";
    $params[] = $status;
    $count_params[] = $status;
}

// Фильтр по врачу
if (!empty($doctor_id) && is_numeric($doctor_id)) {
    $sql .= " AND a.doctor_id = ?";
    $count_sql .= " AND a.doctor_id = ?";
    $params[] = $doctor_id;
    $count_params[] = $doctor_id;
}

// Фильтр по дате "от"
if (!empty($date_from)) {
    $sql .= " AND DATE(a.appointment_datetime) >= ?";
    $count_sql .= " AND DATE(a.appointment_datetime) >= ?";
    $params[] = $date_from;
    $count_params[] = $date_from;
}

// Фильтр по дате "до"
if (!empty($date_to)) {
    $sql .= " AND DATE(a.appointment_datetime) <= ?";
    $count_sql .= " AND DATE(a.appointment_datetime) <= ?";
    $params[] = $date_to;
    $count_params[] = $date_to;
}

// Поиск
if (!empty($search)) {
    $sql .= " AND (a.patient_name LIKE ? OR a.patient_phone LIKE ? OR d.name LIKE ?)";
    $count_sql .= " AND (a.patient_name LIKE ? OR a.patient_phone LIKE ? OR d.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

// Сортировка и пагинация
$sql .= " ORDER BY a.appointment_datetime DESC";
$offset = ($page - 1) * $per_page;
$sql .= " LIMIT $offset, $per_page";

// Получаем записи
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Получаем общее количество для пагинации
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Получаем врачей для фильтра
$doctors = $pdo->query("SELECT id, name FROM doctors ORDER BY name")->fetchAll();

// Статистика для фильтров
$stats = [
    'all' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = CURDATE()")->fetchColumn(),
    'tomorrow' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление записями на приём</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-pending { border-left-color: #ffc107; }
        .status-completed { border-left-color: #198754; }
        .status-cancelled { border-left-color: #6c757d; }
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-badge:hover {
            transform: scale(1.05);
        }
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        .pagination .page-item.active .page-link {
            background-color: #3498db;
            border-color: #3498db;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    <div class="container mt-4">
        <h2><i class="bi bi-calendar-check"></i> Управление записями на приём</h2>
        
        <!-- Быстрые фильтры -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="admin_appointments.php" 
                       class="badge filter-badge <?php echo $status=='all'?'bg-primary':'bg-secondary'; ?> text-decoration-none p-2">
                        Все <span class="badge bg-light text-dark"><?php echo $stats['all']; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=pending" 
                       class="badge filter-badge <?php echo $status=='pending'?'bg-warning':'bg-secondary'; ?> text-decoration-none p-2">
                        Ожидают <span class="badge bg-light text-dark"><?php echo $stats['pending']; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=completed" 
                       class="badge filter-badge <?php echo $status=='completed'?'bg-success':'bg-secondary'; ?> text-decoration-none p-2">
                        Завершены <span class="badge bg-light text-dark"><?php echo $stats['completed']; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=cancelled" 
                       class="badge filter-badge <?php echo $status=='cancelled'?'bg-secondary':'bg-secondary'; ?> text-decoration-none p-2">
                        Отменены <span class="badge bg-light text-dark"><?php echo $stats['cancelled']; ?></span>
                    </a>
                    <a href="admin_appointments.php?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" 
                       class="badge filter-badge bg-info text-decoration-none p-2">
                        Сегодня <span class="badge bg-light text-dark"><?php echo $stats['today']; ?></span>
                    </a>
                    <a href="admin_appointments.php?date_from=<?php echo date('Y-m-d', strtotime('+1 day')); ?>&date_to=<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                       class="badge filter-badge bg-info text-decoration-none p-2">
                        Завтра <span class="badge bg-light text-dark"><?php echo $stats['tomorrow']; ?></span>
                    </a>
                </div>
                
                <!-- Расширенные фильтры -->
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Поиск по имени, телефону..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="doctor_id" class="form-select">
                            <option value="">Все врачи</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['id']; ?>"
                                    <?php echo ($doctor_id == $doc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="all">Все статусы</option>
                            <option value="pending" <?php echo $status=='pending'?'selected':''; ?>>Ожидают</option>
                            <option value="completed" <?php echo $status=='completed'?'selected':''; ?>>Завершены</option>
                            <option value="cancelled" <?php echo $status=='cancelled'?'selected':''; ?>>Отменены</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Экспорт данных -->
                <div class="mt-3">
                    <a href="export_appointments.php?<?php echo http_build_query($_GET); ?>" 
                       class="btn btn-sm btn-success">
                        <i class="bi bi-download"></i> Экспорт в Excel
                    </a>
                    <a href="print_appointments.php?<?php echo http_build_query($_GET); ?>" 
                       target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-printer"></i> Печать
                    </a>
                    <button class="btn btn-sm btn-outline-info" id="toggleView">
                        <i class="bi bi-grid"></i> Переключить вид
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Результаты поиска -->
        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <div>
                Найдено записей: <strong><?php echo $total_count; ?></strong>
                <?php if ($search): ?>
                    по запросу: "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </div>
            <div>
                <?php if ($total_count > 0): ?>
                    Показано: <?php echo ($page-1)*$per_page+1; ?>-<?php echo min($page*$per_page, $total_count); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Табличный вид (по умолчанию) -->
        <div id="tableView">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div class="alert alert-warning text-center py-5">
                            <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Записей не найдено</h4>
                            <p>Попробуйте изменить условия поиска</p>
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
                                        <th>Оценка</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $app): 
                                        $datetime = new DateTime($app['appointment_datetime']);
                                    ?>
                                    <tr class="appointment-card status-<?php echo $app['status']; ?>">
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
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($app['speciality_name']); ?>
                                                        <?php if ($app['workplace']): ?>
                                                            <br><span class="badge bg-light text-dark"><?php echo $app['workplace']; ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo $datetime->format('d.m.Y'); ?></div>
                                            <div class="text-primary"><?php echo $datetime->format('H:i'); ?></div>
                                            <small class="text-muted">
                                                <?php echo $datetime->format('l'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $status_config = [
                                                'pending' => ['class' => 'warning', 'text' => 'Ожидает', 'icon' => 'clock'],
                                                'completed' => ['class' => 'success', 'text' => 'Завершён', 'icon' => 'check-circle'],
                                                'cancelled' => ['class' => 'secondary', 'text' => 'Отменён', 'icon' => 'x-circle']
                                            ];
                                            $status_info = $status_config[$app['status']];
                                            ?>
                                            <span class="badge bg-<?php echo $status_info['class']; ?>">
                                                <i class="bi bi-<?php echo $status_info['icon']; ?>"></i>
                                                <?php echo $status_info['text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($app['payment_status'] == 'paid'): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-credit-card"></i> Оплачено
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-cash"></i> Не оплачено
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($app['rating']): ?>
                                                <div class="rating-stars">
                                                    <?php
                                                    $rating = intval($app['rating']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $rating ? '★' : '☆';
                                                    }
                                                    ?>
                                                </div>
                                                <small><?php echo $app['rating']; ?>/5</small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($app['status'] == 'pending'): ?>
                                                    <a href="change_status.php?id=<?php echo $app['id']; ?>&status=completed" 
                                                       class="btn btn-sm btn-success" title="Завершить">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <a href="change_status.php?id=<?php echo $app['id']; ?>&status=cancelled" 
                                                       class="btn btn-sm btn-danger" title="Отменить">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-warning" 
                                                            onclick="markAsPaid(<?php echo $app['id']; ?>)" 
                                                            title="Отметить оплату">
                                                        <i class="bi bi-credit-card"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="view_appointment.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-sm btn-info" title="Подробнее">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="editAppointment(<?php echo $app['id']; ?>)" 
                                                        title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
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
        </div>
        
        <!-- Карточный вид (скрыт по умолчанию) -->
        <div id="cardView" style="display: none;">
            <div class="row">
                <?php foreach ($appointments as $app): 
                    $datetime = new DateTime($app['appointment_datetime']);
                    $status_config = [
                        'pending' => ['class' => 'warning', 'text' => 'Ожидает', 'icon' => 'clock'],
                        'completed' => ['class' => 'success', 'text' => 'Завершён', 'icon' => 'check-circle'],
                        'cancelled' => ['class' => 'secondary', 'text' => 'Отменён', 'icon' => 'x-circle']
                    ];
                    $status_info = $status_config[$app['status']];
                ?>
                <div class="col-md-6 mb-3">
                    <div class="card appointment-card status-<?php echo $app['status']; ?> h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($app['patient_name']); ?>
                                    </h5>
                                    <h6 class="card-subtitle text-muted">
                                        <i class="bi bi-person-badge"></i> 
                                        <?php echo htmlspecialchars($app['doctor_name']); ?>
                                    </h6>
                                </div>
                                <span class="badge bg-<?php echo $status_info['class']; ?>">
                                    <?php echo $status_info['text']; ?>
                                </span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Дата и время:</small>
                                    <div class="fw-bold">
                                        <?php echo $datetime->format('d.m.Y H:i'); ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Телефон:</small>
                                    <div>
                                        <?php echo $app['patient_phone'] ?: '<span class="text-muted">не указан</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($app['speciality_name']); ?>
                                    </span>
                                    <span class="badge bg-info">
                                        <?php echo $app['workplace']; ?>
                                    </span>
                                </div>
                                <div class="action-buttons">
                                    <?php if ($app['status'] == 'pending'): ?>
                                        <a href="change_status.php?id=<?php echo $app['id']; ?>&status=completed" 
                                           class="btn btn-sm btn-success" title="Завершить">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="view_appointment.php?id=<?php echo $app['id']; ?>" 
                                       class="btn btn-sm btn-info" title="Подробнее">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Навигация по страницам">
            <ul class="pagination justify-content-center mt-4">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <script>
        // Переключение между видами
        document.getElementById('toggleView').addEventListener('click', function() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const icon = this.querySelector('i');
            
            if (tableView.style.display === 'none') {
                tableView.style.display = 'block';
                cardView.style.display = 'none';
                icon.className = 'bi bi-grid';
                this.innerHTML = '<i class="bi bi-grid"></i> Переключить вид';
            } else {
                tableView.style.display = 'none';
                cardView.style.display = 'block';
                icon.className = 'bi bi-table';
                this.innerHTML = '<i class="bi bi-table"></i> Переключить вид';
            }
        });
        
        // Отметить как оплаченное
        async function markAsPaid(appointmentId) {
            if (!confirm('Отметить запись как оплаченную?')) return;
            
            try {
                const formData = new FormData();
                formData.append('id', appointmentId);
                formData.append('payment_status', 'paid');
                
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
        
        // Редактировать запись
        function editAppointment(id) {
            // Можно открыть модальное окно или перейти на страницу редактирования
            window.open(`edit_appointment.php?id=${id}`, '_blank');
        }
        
        // Автоматическое обновление каждые 2 минуты если есть pending записи
        setTimeout(() => {
            const hasPending = document.querySelector('.badge.bg-warning');
            if (hasPending && !document.hidden) {
                location.reload();
            }
        }, 120000);
    </script>
</body>
</html>