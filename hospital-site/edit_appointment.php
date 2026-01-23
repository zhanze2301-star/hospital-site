<?php
// edit_doctor.php - Редактирование информации о враче
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

// Получаем все специальности
$specialities = $pdo->query("SELECT * FROM specialities ORDER BY name")->fetchAll();

// Обработка формы
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $speciality_id = intval($_POST['speciality_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $photo_url = trim($_POST['photo_url'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $workplace = trim($_POST['workplace'] ?? 'Главный корпус');
    
    // Валидация
    if (empty($name)) {
        $errors[] = 'Введите ФИО врача';
    }
    
    if ($speciality_id <= 0) {
        $errors[] = 'Выберите специальность';
    }
    
    if (empty($errors)) {
        try {
            $update_stmt = $pdo->prepare("
                UPDATE doctors 
                SET name = ?, speciality_id = ?, description = ?, 
                    photo_url = ?, experience = ?, education = ?, workplace = ?
                WHERE id = ?
            ");
            
            $update_stmt->execute([
                $name, $speciality_id, $description, 
                $photo_url, $experience, $education, $workplace,
                $doctor_id
            ]);
            
            $success = true;
            // Обновляем данные врача
            $doctor = array_merge($doctor, [
                'name' => $name,
                'speciality_id' => $speciality_id,
                'description' => $description,
                'photo_url' => $photo_url,
                'experience' => $experience,
                'education' => $education,
                'workplace' => $workplace
            ]);
            
        } catch (PDOException $e) {
            $errors[] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .doctor-photo {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #dee2e6;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <!-- Заголовок -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-pencil"></i> Редактирование врача
            </h2>
            <div class="btn-group">
                <a href="admin_doctors.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Назад
                </a>
                <a href="doctor_schedule.php?id=<?php echo $doctor_id; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-clock"></i> Расписание
                </a>
                <a href="book.php?doctor_id=<?php echo $doctor_id; ?>" 
                   target="_blank" class="btn btn-primary">
                    <i class="bi bi-calendar-plus"></i> Записать пациента
                </a>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Информация о враче успешно обновлена!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5><i class="bi bi-exclamation-triangle"></i> Ошибки:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Левая колонка - фото и основная информация -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if ($doctor['photo_url']): ?>
                            <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" 
                                 class="doctor-photo mb-3" 
                                 alt="Фото врача"
                                 id="doctorPhotoPreview">
                        <?php else: ?>
                            <div class="doctor-photo bg-light d-flex align-items-center justify-content-center mb-3">
                                <i class="bi bi-person" style="font-size: 4rem; color: #6c757d;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h4><?php echo htmlspecialchars($doctor['name']); ?></h4>
                        <p class="text-muted">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($doctor['speciality_name']); ?></span>
                        </p>
                        
                        <?php if ($doctor['workplace']): ?>
                            <p>
                                <i class="bi bi-building"></i> 
                                <?php echo htmlspecialchars($doctor['workplace']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($doctor['experience']): ?>
                            <p>
                                <i class="bi bi-clock-history"></i> 
                                Опыт: <?php echo htmlspecialchars($doctor['experience']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Статистика -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Статистика</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total_appointments,
                                AVG(r.score) as avg_rating,
                                COUNT(r.id) as rating_count
                            FROM appointments a
                            LEFT JOIN ratings r ON a.id = r.appointment_id
                            WHERE a.doctor_id = ?
                        ");
                        $stats->execute([$doctor_id]);
                        $stat_data = $stats->fetch();
                        ?>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Всего записей:</span>
                                <strong><?php echo $stat_data['total_appointments']; ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Средний рейтинг:</span>
                                <strong>
                                    <?php if ($stat_data['avg_rating']): ?>
                                        <span class="text-warning">
                                            <?php
                                            $rating = round($stat_data['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rating ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                        (<?php echo round($stat_data['avg_rating'], 1); ?>/5)
                                    <?php else: ?>
                                        <span class="text-muted">нет оценок</span>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Кол-во оценок:</span>
                                <strong><?php echo $stat_data['rating_count']; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Правая колонка - форма редактирования -->
            <div class="col-md-8">
                <form method="POST" id="editDoctorForm">
                    <div class="form-section">
                        <h5><i class="bi bi-person-badge"></i> Основная информация</h5>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">ФИО врача *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Специальность *</label>
                                <select name="speciality_id" class="form-select" required>
                                    <option value="">Выберите специальность</option>
                                    <?php foreach ($specialities as $spec): ?>
                                        <option value="<?php echo $spec['id']; ?>"
                                            <?php echo $spec['id'] == $doctor['speciality_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($spec['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">URL фотографии</label>
                            <input type="url" name="photo_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($doctor['photo_url'] ?? ''); ?>"
                                   placeholder="https://example.com/photo.jpg"
                                   onchange="updatePhotoPreview(this.value)">
                            <div class="form-text">
                                Ссылка на фото врача. Можно оставить пустым.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($doctor['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5><i class="bi bi-briefcase"></i> Дополнительная информация</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Опыт работы</label>
                                <input type="text" name="experience" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['experience'] ?? ''); ?>"
                                       placeholder="15 лет">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Образование</label>
                                <input type="text" name="education" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['education'] ?? ''); ?>"
                                       placeholder="КГМА, 2010">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Место работы</label>
                            <select name="workplace" class="form-select">
                                <option value="Главный корпус" <?php echo ($doctor['workplace'] ?? '') == 'Главный корпус' ? 'selected' : ''; ?>>Главный корпус</option>
                                <option value="Филиал №1" <?php echo ($doctor['workplace'] ?? '') == 'Филиал №1' ? 'selected' : ''; ?>>Филиал №1</option>
                                <option value="Филиал №2" <?php echo ($doctor['workplace'] ?? '') == 'Филиал №2' ? 'selected' : ''; ?>>Филиал №2</option>
                                <option value="Детское отделение" <?php echo ($doctor['workplace'] ?? '') == 'Детское отделение' ? 'selected' : ''; ?>>Детское отделение</option>
                                <option value="Травмпункт" <?php echo ($doctor['workplace'] ?? '') == 'Травмпункт' ? 'selected' : ''; ?>>Травмпункт</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="admin_doctors.php" class="btn btn-secondary me-2">Отмена</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Обновление предпросмотра фото
        function updatePhotoPreview(url) {
            const preview = document.getElementById('doctorPhotoPreview');
            if (preview && url) {
                preview.src = url;
            }
        }
        
        // Подтверждение перед уходом, если есть несохраненные изменения
        let formChanged = false;
        document.getElementById('editDoctorForm').addEventListener('input', () => {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите уйти?';
            }
        });
        
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                if (formChanged && !confirm('У вас есть несохраненные изменения. Вы уверены, что хотите уйти?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>