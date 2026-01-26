<?php
include 'config.php';  // Подключаем БД

// Проверяем, передан ли speciality_id через GET
if (!isset($_GET['speciality_id']) || !is_numeric($_GET['speciality_id'])) {
    header("Location: index.php");  // Если нет ID — назад на главную
    exit;
}

$speciality_id = (int)$_GET['speciality_id'];

// Получаем название выбранной специальности (для заголовка)
$stmt_spec = $pdo->prepare("SELECT name FROM specialities WHERE id = ?");
$stmt_spec->execute([$speciality_id]); 
$speciality = $stmt_spec->fetch();

if (!$speciality) {
    echo "<h1>Специальность не найдена</h1>";
    exit;
}

// Получаем врачей по этой специальности + их рейтинг
$stmt = $pdo->prepare("
    SELECT d.id, d.name, d.description, d.photo_url, d.rating
    FROM doctors d
    WHERE d.speciality_id = ?
    ORDER BY d.rating DESC, d.name
");
$stmt->execute([$speciality_id]);
$doctors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Врачи — <?= htmlspecialchars($speciality['name']) ?></title>
    <!-- Bootstrap 5.3.8 CDN (актуальная версия на 2026 год, с integrity для безопасности) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" 
          crossorigin="anonymous">
</head>
<body class="bg-light">
   
<div class="container my-5">
    <h1 class="text-center mb-4">Врачи по специальности: <?= htmlspecialchars($speciality['name']) ?></h1>
    
    <a href="index.php" class="btn btn-secondary mb-4">← Назад к выбору специальности</a>

    <?php if (empty($doctors)): ?>
        <div class="alert alert-info">
            Пока нет врачей по этой специальности. Добавьте их в базу через phpMyAdmin.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($doctors as $doctor): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <?php if (!empty($doctor['photo_url'])): ?>
                            <img src="<?= htmlspecialchars($doctor['photo_url']) ?>" 
                                 class="card-img-top" alt="<?= htmlspecialchars($doctor['name']) ?>" 
                                 style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=Фото+врача" 
                                 class="card-img-top" alt="Фото врача">
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($doctor['name']) ?></h5>
                            <p class="card-text">
                                Рейтинг: 
                                <span class="badge bg-success">
                                    <?= number_format($doctor['rating'], 1) ?> ★
                                </span>
                            </p>
                            <p class="card-text text-muted">
                                <?= nl2br(htmlspecialchars($doctor['description'] ?? 'Описание отсутствует')) ?>
                            </p>
                            <a href="booking_system.php?doctor_id=<?= $doctor['id'] ?>" 
                               class="btn btn-primary w-100">
                                Записаться на приём
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" 
        crossorigin="anonymous"></script>
</body>
</html>