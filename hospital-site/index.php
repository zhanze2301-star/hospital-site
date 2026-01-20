<?php
include 'config.php';

// Получаем все специальности
$stmt = $pdo->query("SELECT * FROM specialities ORDER BY name");
$specialities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Запись на приём — Больница</title>
    <!-- Bootstrap CDN для красивого вида -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h1 class="text-center mb-4">Онлайн-запись на приём</h1>
    
    <div class="card p-4">
        <h3>1. Выберите специальность</h3>
        <form action="doctors.php" method="GET">
            <div class="mb-3">
                <select name="speciality_id" class="form-select" required>
                    <option value="">-- Выберите отделение --</option>
                    <?php foreach ($specialities as $spec): ?>
                        <option value="<?= $spec['id'] ?>"><?= htmlspecialchars($spec['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Показать врачей →</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>