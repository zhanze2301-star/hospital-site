<?php
// edit_doctor.php - Редактирование информации о враче. Работает - кнопки там где должны быть .
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

// Получаем завершенные записи этого врача для оценки
$completed_appointments = $pdo->prepare("
    SELECT a.id, a.patient_name, a.appointment_datetime
    FROM appointments a
    WHERE a.doctor_id = ? 
    AND a.status = 'completed'
    AND NOT EXISTS (
        SELECT 1 FROM ratings r WHERE r.appointment_id = a.id
    )
    ORDER BY a.appointment_datetime DESC
    LIMIT 10
");
$completed_appointments->execute([$doctor_id]);

// Получаем существующие оценки врача
$existing_ratings = $pdo->prepare("
    SELECT r.*, a.patient_name, a.appointment_datetime
    FROM ratings r
    JOIN appointments a ON r.appointment_id = a.id
    WHERE a.doctor_id = ?
    ORDER BY r.id DESC
");
$existing_ratings->execute([$doctor_id]);

// Обработка формы редактирования врача
$errors = [];
$success = false;
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем, какая форма отправлена
    if (isset($_POST['add_rating'])) {
        // Обработка добавления рейтинга
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        $score = intval($_POST['score'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $reviewer_name = trim($_POST['reviewer_name'] ?? 'Администратор');
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        if ($appointment_id <= 0) {
            $errors[] = 'Выберите запись для оценки';
        }
        
        if ($score < 1 || $score > 5) {
            $errors[] = 'Оценка должна быть от 1 до 5';
        }
        
        if (empty($errors)) {
            try {
                // Проверяем, что запись принадлежит этому врачу
                $check_stmt = $pdo->prepare("
                    SELECT id FROM appointments 
                    WHERE id = ? AND doctor_id = ? AND status = 'completed'
                ");
                $check_stmt->execute([$appointment_id, $doctor_id]);
                
                if ($check_stmt->fetch()) {
                    // Добавляем оценку
                    $rating_stmt = $pdo->prepare("
                        INSERT INTO ratings (appointment_id, score, comment, reviewer_name, is_visible, admin_added)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $rating_stmt->execute([$appointment_id, $score, $comment, $reviewer_name, $is_visible]);
                    
                    // Обновляем средний рейтинг врача
                    $update_rating_stmt = $pdo->prepare("
                        UPDATE doctors 
                        SET rating = (
                            SELECT AVG(r.score) 
                            FROM ratings r
                            JOIN appointments a ON r.appointment_id = a.id
                            WHERE a.doctor_id = ? AND r.is_visible = 1
                        )
                        WHERE id = ?
                    ");
                    $update_rating_stmt->execute([$doctor_id, $doctor_id]);
                    
                    $success = true;
                    $success_message = 'Оценка успешно добавлена!';
                    
                    // Обновляем списки
                    $completed_appointments->execute([$doctor_id]);
                    $existing_ratings->execute([$doctor_id]);
                } else {
                    $errors[] = 'Запись не найдена или не завершена';
                }
            } catch (PDOException $e) {
                $errors[] = 'Ошибка базы данных: ' . $e->getMessage();
            }
        }
    } else {
        // Обработка редактирования данных врача
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
                $success_message = 'Информация о враче успешно обновлена!';
                
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
        .star-rating {
            font-size: 1.5rem;
            color: #ffc107;
            cursor: pointer;
        }
        .star-rating .bi-star {
            color: #e4e5e9;
        }
        .rating-item {
            border-left: 4px solid #ffc107;
            padding-left: 15px;
        }
        .rating-badge {
            font-size: 0.8rem;
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
                <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5><i class="bi bi-exclamation-triangle"></i> Ошибки:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Левая колонка - фото и основная информация -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if (!empty($doctor['photo_url'])): ?>
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
                        
                        <!-- Рейтинг врача -->
                        <div class="mb-3">
                            <?php
                            $avg_rating = $doctor['rating'] ?? 0;
                            if ($avg_rating > 0):
                            ?>
                                <div class="mb-2">
                                    <span class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= round($avg_rating)): ?>
                                                <i class="bi bi-star-fill"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo number_format($avg_rating, 1); ?> / 5
                                    </small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Нет оценок</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($doctor['workplace'])): ?>
                            <p>
                                <i class="bi bi-building"></i> 
                                <?php echo htmlspecialchars($doctor['workplace']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($doctor['experience'])): ?>
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
                        // Статистика по врачу
                        $stats_stmt = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total_appointments,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                                AVG(r.score) as avg_rating,
                                COUNT(r.id) as rating_count
                            FROM appointments a
                            LEFT JOIN ratings r ON a.id = r.appointment_id
                            WHERE a.doctor_id = ?
                        ");
                        $stats_stmt->execute([$doctor_id]);
                        $stat_data = $stats_stmt->fetch();
                        ?>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Всего записей:</span>
                                <strong><?php echo $stat_data['total_appointments']; ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Завершено:</span>
                                <strong><?php echo $stat_data['completed_appointments']; ?></strong>
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
                <!-- Форма редактирования врача -->
                <form method="POST" id="editDoctorForm" class="mb-4">
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
                    
                    <!-- <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="admin_doctors.php" class="btn btn-secondary me-2">Отмена</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить изменения
                        </button>
                    </div> -->
                </form>
                
                <!-- Секция для добавления рейтинга -->
                <div class="form-section">
                    <h5><i class="bi bi-star-fill text-warning"></i> Добавить оценку врачу</h5>
                    
                    <form method="POST" id="addRatingForm">
                        <input type="hidden" name="add_rating" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Запись для оценки *</label>
                                <select name="appointment_id" class="form-select" required>
                                    <option value="">Выберите завершенную запись</option>
                                    <?php while ($appointment = $completed_appointments->fetch()): ?>
                                        <option value="<?php echo $appointment['id']; ?>">
                                            #<?php echo $appointment['id']; ?> - 
                                            <?php echo htmlspecialchars($appointment['patient_name']); ?> - 
                                            <?php echo date('d.m.Y H:i', strtotime($appointment['appointment_datetime'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <?php if ($completed_appointments->rowCount() == 0): ?>
                                        <option value="" disabled>Нет завершенных записей без оценки</option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">
                                    Только завершенные записи, которые еще не оценены
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Оценка * (1-5)</label>
                                <div class="star-rating mb-2" id="starRating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star" data-value="<?php echo $i; ?>" 
                                           onmouseover="hoverStars(<?php echo $i; ?>)" 
                                           onmouseout="resetStars()" 
                                           onclick="setRating(<?php echo $i; ?>)"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="score" id="ratingScore" value="0" required>
                                <div id="ratingText" class="text-muted">Наведите курсор на звезды</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ваше имя (от кого оценка)</label>
                                <input type="text" name="reviewer_name" class="form-control" 
                                       value="Администратор" placeholder="Администратор">
                                <div class="form-text">
                                    Будет отображаться как автор отзыва
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Видимость отзыва</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="isVisible" value="1" checked>
                                    <label class="form-check-label" for="isVisible">
                                        Показывать отзыв на сайте
                                    </label>
                                </div>
                                <div class="form-text">
                                    Если снять галочку, оценка будет учтена в статистике, но не будет публичной
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий (отзыв)</label>
                            <textarea name="comment" class="form-control" rows="3" 
                                      placeholder="Оставьте комментарий о работе врача..."></textarea>
                            <div class="form-text">
                                Комментарий будет виден пациентам на сайте
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-warning" id="submitRatingBtn" disabled>
                                <i class="bi bi-star-fill"></i> Добавить оценку
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Список существующих оценок -->
                <?php 
                $existing_ratings->execute([$doctor_id]);
                if ($existing_ratings->rowCount() > 0): 
                ?>
                <div class="form-section">
                    <h5><i class="bi bi-list-stars text-warning"></i> Существующие оценки (<?php echo $existing_ratings->rowCount(); ?>)</h5>
                    
                    <div class="list-group">
                        <?php while ($rating = $existing_ratings->fetch()): ?>
                        <div class="list-group-item rating-item mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="mb-1">
                                        <span class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $rating['score']): ?>
                                                    <i class="bi bi-star-fill"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="badge bg-<?php echo isset($rating['is_visible']) && $rating['is_visible'] ? 'success' : 'secondary'; ?> rating-badge ms-2">
                                            <?php echo isset($rating['is_visible']) && $rating['is_visible'] ? 'Публичный' : 'Скрытый'; ?>
                                        </span>
                                        <?php if (isset($rating['admin_added']) && $rating['admin_added']): ?>
                                            <span class="badge bg-info rating-badge ms-1">Добавлен админом</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($rating['comment'])): ?>
                                        <p class="mb-1">"<?php echo htmlspecialchars($rating['comment']); ?>"</p>
                                    <?php endif; ?>
                                    
                                    <small class="text-muted">
                                        <i class="bi bi-person-circle"></i> 
                                        <?php echo htmlspecialchars($rating['reviewer_name'] ?? 'Администратор'); ?> 
                                        • 
                                        <i class="bi bi-calendar"></i> 
                                        Запись #<?php echo $rating['appointment_id']; ?> 
                                        (<?php echo date('d.m.Y', strtotime($rating['appointment_datetime'])); ?>)
                                    </small>
                                </div>
                                <?php if (!empty($rating['created_at'])): ?>
                                <small class="text-muted">
                                    <?php echo date('d.m.Y', strtotime($rating['created_at'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="admin_doctors.php" class="btn btn-secondary me-2">Отмена</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить изменения
                        </button>
                </div>
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
        
        // Логика звездного рейтинга
        let currentRating = 0;
        const ratingTexts = {
            0: 'Наведите курсор на звезды',
            1: 'Ужасно',
            2: 'Плохо',
            3: 'Нормально',
            4: 'Хорошо',
            5: 'Отлично'
        };
        
        function hoverStars(stars) {
            const starElements = document.querySelectorAll('#starRating .bi');
            starElements.forEach((star, index) => {
                if (index < stars) {
                    star.classList.remove('bi-star');
                    star.classList.add('bi-star-fill');
                } else {
                    star.classList.remove('bi-star-fill');
                    star.classList.add('bi-star');
                }
            });
            document.getElementById('ratingText').textContent = ratingTexts[stars] || '';
        }
        
        function resetStars() {
            const starElements = document.querySelectorAll('#starRating .bi');
            starElements.forEach((star, index) => {
                if (index < currentRating) {
                    star.classList.remove('bi-star');
                    star.classList.add('bi-star-fill');
                } else {
                    star.classList.remove('bi-star-fill');
                    star.classList.add('bi-star');
                }
            });
            document.getElementById('ratingText').textContent = 
                currentRating > 0 ? ratingTexts[currentRating] : ratingTexts[0];
        }
        
        function setRating(stars) {
            currentRating = stars;
            document.getElementById('ratingScore').value = stars;
            document.getElementById('submitRatingBtn').disabled = false;
            document.getElementById('ratingText').textContent = ratingTexts[stars];
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
        
        // Валидация формы рейтинга
        document.getElementById('addRatingForm').addEventListener('submit', function(e) {
            const score = document.getElementById('ratingScore').value;
            if (score < 1 || score > 5) {
                e.preventDefault();
                alert('Пожалуйста, поставьте оценку от 1 до 5 звезд');
                return false;
            }
        });
    </script>
</body>
</html>