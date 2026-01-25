<?php
// admin_doctors.php - Управление врачами с поиском и фильтрацией
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Получаем параметры фильтрации
$search = $_GET['search'] ?? '';
$speciality_id = $_GET['speciality_id'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_order = $_GET['sort_order'] ?? 'asc';

// Допустимые поля для сортировки
$allowed_sort_fields = ['name', 'rating', 'id', 'speciality_id'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'name';
}

// Допустимые порядки сортировки
$allowed_orders = ['asc', 'desc'];
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'asc';
}

// Подготавливаем SQL запрос
$sql = "SELECT d.*, s.name as speciality_name,
               (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) as appointments_count
        FROM doctors d
        LEFT JOIN specialities s ON d.speciality_id = s.id
        WHERE 1=1";

$params = [];

// Поиск по имени
if (!empty($search)) {
    $sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

// Фильтр по специальности
if (!empty($speciality_id) && is_numeric($speciality_id)) {
    $sql .= " AND d.speciality_id = ?";
    $params[] = $speciality_id;
}

// Сортировка
$sql .= " ORDER BY $sort_by $sort_order";

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Получаем специальности для фильтра
$specialities = $pdo->query("SELECT * FROM specialities ORDER BY name")->fetchAll();

// Обработка добавления врача
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = trim($_POST['name']);
    $speciality_id = intval($_POST['speciality_id']);
    $description = trim($_POST['description'] ?? '');
    $photo_url = trim($_POST['photo_url'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $workplace = trim($_POST['workplace'] ?? 'Главный корпус');
    
    if (!empty($name) && $speciality_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO doctors (name, speciality_id, description, photo_url, experience, education, workplace) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $speciality_id, $description, $photo_url, $experience, $education, $workplace]);
        header('Location: admin_doctors.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление врачами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .doctor-card {
            transition: transform 0.3s;
            border: 2px solid transparent;
        }
        .doctor-card:hover {
            transform: translateY(-5px);
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
        }
        .workplace-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 4px;
        }
        .table th {
            cursor: pointer;
            user-select: none;
        }
        .table th:hover {
            background-color: #f8f9fa;
        }
        .sort-icon {
            font-size: 0.8rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>
  <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-people"></i> Управление врачами</h2>
        
        <!-- Панель поиска и фильтров -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Поиск врача</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="ФИО или описание..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Специальность</label>
                        <select name="speciality_id" class="form-select">
                            <option value="">Все специальности</option>
                            <?php foreach ($specialities as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                    <?php echo ($speciality_id == $spec['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Сортировать по</label>
                        <select name="sort_by" class="form-select">
                            <option value="name" <?php echo $sort_by=='name'?'selected':''; ?>>Имени</option>
                            <option value="rating" <?php echo $sort_by=='rating'?'selected':''; ?>>Рейтингу</option>
                            <option value="speciality_id" <?php echo $sort_by=='speciality_id'?'selected':''; ?>>Специальности</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Порядок</label>
                        <select name="sort_order" class="form-select">
                            <option value="asc" <?php echo $sort_order=='asc'?'selected':''; ?>>По возрастанию</option>
                            <option value="desc" <?php echo $sort_order=='desc'?'selected':''; ?>>По убыванию</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                </form>
                
                <div class="mt-3">
                    <a href="admin_doctors.php" class="btn btn-sm btn-outline-secondary">Сбросить фильтры</a>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                        <i class="bi bi-plus-circle"></i> Добавить врача
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3><?php echo count($doctors); ?></h3>
                        <p class="text-muted mb-0">Всего врачей</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3><?php echo count(array_filter($doctors, fn($d) => $d['rating'] > 0)); ?></h3>
                        <p class="text-muted mb-0">С оценками</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3><?php echo array_sum(array_column($doctors, 'appointments_count')); ?></h3>
                        <p class="text-muted mb-0">Всего записей</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <?php
                        $unique_workplaces = array_unique(array_column($doctors, 'workplace'));
                        $workplaces_count = count(array_filter($unique_workplaces));
                        ?>
                        <h3><?php echo $workplaces_count; ?></h3>
                        <p class="text-muted mb-0">Корпусов</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Таблица врачей -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Список врачей (<?php echo count($doctors); ?>)</h5>
                <div class="text-muted">
                    <?php if ($search): ?>
                        Поиск: "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($doctors)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Врачи не найдены. 
                        <?php if ($search || $speciality_id): ?>
                            Попробуйте изменить условия поиска.
                        <?php else: ?>
                            Добавьте первого врача.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th onclick="sortTable('name')">
                                        ФИО врача
                                        <?php if ($sort_by == 'name'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order == 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortTable('speciality_id')">
                                        Специальность
                                        <?php if ($sort_by == 'speciality_id'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order == 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th onclick="sortTable('rating')">
                                        Рейтинг
                                        <?php if ($sort_by == 'rating'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order == 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Место работы</th>
                                    <th>Записей</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                <tr class="doctor-card">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($doctor['photo_url']): ?>
                                                <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" 
                                                     class="rounded-circle me-3" 
                                                     width="50" height="50" 
                                                     style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person" style="font-size: 24px;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                                <?php if ($doctor['experience']): ?>
                                                    <br><small class="text-muted">Опыт: <?php echo $doctor['experience']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($doctor['speciality_name']); ?></span>
                                        <?php if ($doctor['description']): ?>
                                            <br><small><?php echo $doctor['description']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doctor['rating'] > 0): ?>
                                            <div class="text-warning">
                                                <?php
                                                $full_stars = floor($doctor['rating']);
                                                for ($i = 0; $i < $full_stars; $i++) echo '★';
                                                for ($i = $full_stars; $i < 5; $i++) echo '☆';
                                                ?>
                                            </div>
                                            <strong><?php echo number_format($doctor['rating'], 1); ?></strong>/5
                                            <br><small class="text-muted">на основе оценок</small>
                                        <?php else: ?>
                                            <span class="text-muted">Нет оценок</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="workplace-badge"><?php echo htmlspecialchars($doctor['workplace'] ?? 'Главный корпус'); ?></span>
                                        <?php if ($doctor['education']): ?>
                                            <br><small class="text-muted"><?php echo $doctor['education']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $doctor['appointments_count']; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="book.php?doctor_id=<?php echo $doctor['id']; ?>" 
                                               class="btn btn-outline-primary" title="Записать пациента" target="_blank">
                                                <i class="bi bi-calendar-plus"></i>
                                            </a>
                                            <a href="edit_doctor.php?id=<?php echo $doctor['id']; ?>" 
                                               class="btn btn-outline-secondary" title="Редактировать">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="doctor_schedule.php?id=<?php echo $doctor['id']; ?>" 
                                               class="btn btn-outline-info" title="Расписание">
                                                <i class="bi bi-clock"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars(addslashes($doctor['name'])); ?>')"
                                                    title="Удалить">
                                                <i class="bi bi-trash"></i>
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
    
    <!-- Модальное окно добавления врача -->
    <div class="modal fade" id="addDoctorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить нового врача</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ФИО врача *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Специальность *</label>
                                <select name="speciality_id" class="form-select" required>
                                    <option value="">Выберите специальность</option>
                                    <?php foreach ($specialities as $spec): ?>
                                        <option value="<?php echo $spec['id']; ?>"><?php echo $spec['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Место работы</label>
                                <select name="workplace" class="form-select">
                                    <option value="Главный корпус">Главный корпус</option>
                                    <option value="Филиал №1">Филиал №1</option>
                                    <option value="Филиал №2">Филиал №2</option>
                                    <option value="Детское отделение">Детское отделение</option>
                                    <option value="Травмпункт">Травмпункт</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">URL фото</label>
                                <input type="text" name="photo_url" class="form-control" placeholder="https://...">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Опыт работы</label>
                                <input type="text" name="experience" class="form-control" placeholder="10 лет">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Образование</label>
                                <input type="text" name="education" class="form-control" placeholder="КГМА, 2010">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Описание</label>
                                <textarea name="description" class="form-control" rows="3" 
                                          placeholder="Краткое описание, достижения..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="add_doctor" class="btn btn-primary">Добавить врача</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Функция сортировки
        function sortTable(field) {
            const urlParams = new URLSearchParams(window.location.search);
            let currentSort = urlParams.get('sort_by') || 'name';
            let currentOrder = urlParams.get('sort_order') || 'asc';
            
            let newOrder = 'asc';
            if (currentSort === field && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            urlParams.set('sort_by', field);
            urlParams.set('sort_order', newOrder);
            
            window.location.search = urlParams.toString();
        }
        
        // Функция подтверждения удаления
        function confirmDelete(doctorId, doctorName) {
            if (confirm(`Вы уверены, что хотите удалить врача "${doctorName}"?`)) {
                window.location.href = `delete_doctor.php?id=${doctorId}`;
            }
        }
        
        // Если есть параметр success, показываем сообщение
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success')) {
            alert('Врач успешно добавлен!');
            urlParams.delete('success');
            window.history.replaceState({}, '', `${window.location.pathname}?${urlParams}`);
        }
    </script>
</body>
</html>