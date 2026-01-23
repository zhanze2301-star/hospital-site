<?php
// admin_header.php - простой заголовок
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="bi bi-hospital"></i> Админ-панель
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">
                            <i class="bi bi-speedometer2"></i> Панель
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_appointments.php">
                            <i class="bi bi-calendar-check"></i> Записи
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_doctors.php">
                            <i class="bi bi-people"></i> Врачи
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_reports.php">
                            <i class="bi bi-bar-chart"></i> Отчёты
                        </a>
                    </li>
                </ul>
                <div class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['admin_name'] ?? 'Администратор'; ?>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Выйти
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-3">