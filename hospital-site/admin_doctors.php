<?php
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Обработка добавления врача
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = trim($_POST['name']);
    $speciality_id = intval($_POST['speciality_id']);
    $description = trim($_POST['description'] ?? '');
    $photo_url = trim($_POST['photo_url'] ?? '');
     
    if (!empty($name) && $speciality_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO doctors (name, speciality_id, description, photo_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $speciality_id, $description, $photo_url]);
        header('Location: admin_doctors.php?success=1');
        exit;
    }
}
//Тут исправь переменные , чтобы фильтрация по именам , отделениям и специализациям работала
$search = $_GET['search'] ?? '';
$speciality_id = $_GET['speciality_id'] ?? '';
$location = $_GET['location'] ?? '';

$sql = "SELECT * FROM doctors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR surname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($speciality_id)) {
    $sql .= " AND speciality_id = ?";
    $params[] = $speciality_id;
}
if (!empty($location)) {
    $sql .= " AND location = ?";
    $params[] = $location;
}

// Сортировка
$order_by = $_GET['order_by'] ?? 'name';
$order_dir = $_GET['order_dir'] ?? 'ASC';
$allowed_orders = ['name', 'speciality_id', 'location', 'experience'];
if (in_array($order_by, $allowed_orders)) {
    $sql .= " ORDER BY $order_by $order_dir";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();


// Получаем врачей
$doctors = $pdo->query("
    SELECT d.*, s.name as speciality_name
    FROM doctors d
    LEFT JOIN specialities s ON d.speciality_id = s.id
    ORDER BY d.name
")->fetchAll();

// Получаем специальности для формы
$specialities = $pdo->query("SELECT * FROM specialities ORDER BY name")->fetchAll();



?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление врачами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-people"></i> Управление врачами</h2>
        
        <!-- Форма добавления врача -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Добавить нового врача</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control" placeholder="ФИО врача" required>
                        </div>
                        <div class="col-md-3">
                            <select name="speciality_id" class="form-select" required>
                                <option value="">Выберите специальность</option>
                                <?php foreach ($specialities as $spec): ?>
                                    <option value="<?php echo $spec['id']; ?>"><?php echo $spec['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="photo_url" class="form-control" placeholder="URL фото (необязательно)">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add_doctor" class="btn btn-primary w-100">Добавить</button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Описание, опыт работы..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
<form method="GET" action="">
    <input type="text" name="search" placeholder="Поиск по имени" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    <select name="speciality_id">
        <option value="">Все специализации</option>
        <option value="Кардиолог" <?= ($_GET['speciality_id'] ?? '') == 'Кардиолог' ? 'selected' : '' ?>>Кардиолог</option>
        <!-- другие специализации -->
    </select>
    <select name="location">
        <option value="">Все отделения</option>
        <option value="Терапевтическое" <?= ($_GET['location'] ?? '') == 'Терапевтическое' ? 'selected' : '' ?>>Терапевтическое</option>
        <!-- другие отделения -->
    </select>
    <button type="submit">Найти</button>
</form>

        <!-- Таблица врачей -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Список врачей (<?php echo count($doctors); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Фото</th>
                                <th>ФИО</th>
                                <th>Специальность</th>
                                <th>Рейтинг</th>
                                <th>Записей</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td>
                                    <?php if ($doctor['photo_url']): ?>
                                        <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" 
                                             class="rounded-circle" width="50" height="50" 
                                             style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person" style="font-size: 24px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                    <?php if ($doctor['description']): ?>
                                        <br><small class="text-muted"><?php echo $doctor['description']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($doctor['speciality_name']); ?></td>
                                <td>
                                    <?php if ($doctor['rating'] > 0): ?>
                                        <span class="badge bg-warning">
                                            <?php echo number_format($doctor['rating'], 1); ?> / 5
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Нет оценок</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $appointments_count = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
                                    $appointments_count->execute([$doctor['id']]);
                                    echo $appointments_count->fetchColumn();
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="book.php?doctor_id=<?php echo $doctor['id']; ?>" 
                                           class="btn btn-outline-primary" title="Записать пациента">
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
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</body>
</html>