<?php
require_once 'api/config.php';

// Получаем параметры фильтрации
$doctor_id = $_GET['doctor_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');
$speciality_id = $_GET['speciality_id'] ?? null;

// Получаем врачей для фильтра
$doctors_sql = "SELECT d.id, d.name, s.name as speciality_name 
                FROM doctors d 
                LEFT JOIN specialities s ON d.speciality_id = s.id";
                
if ($speciality_id) {
    $doctors_sql .= " WHERE d.speciality_id = ?";
    $doctors_stmt = $pdo->prepare($doctors_sql);
    $doctors_stmt->execute([$speciality_id]);
} else {
    $doctors_stmt = $pdo->query($doctors_sql);
}
$doctors = $doctors_stmt->fetchAll();

// Получаем специальности для фильтра
$specialities = $pdo->query("SELECT * FROM specialities ORDER BY name")->fetchAll();

// Получаем расписание
$schedule_sql = "
    SELECT a.*, d.name as doctor_name, s.name as speciality_name,
           TIME(a.appointment_datetime) as time_only,
           DATE(a.appointment_datetime) as date_only
    FROM appointments a
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN specialities s ON d.speciality_id = s.id
    WHERE DATE(a.appointment_datetime) = ?
";

$params = [$date];

if ($doctor_id) {
    $schedule_sql .= " AND a.doctor_id = ?";
    $params[] = $doctor_id;
}

$schedule_sql .= " ORDER BY a.appointment_datetime, d.name";

$schedule_stmt = $pdo->prepare($schedule_sql);
$schedule_stmt->execute($params);
$schedule = $schedule_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расписание приёмов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .schedule-day {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .time-slot {
            border-left: 4px solid #3498db;
            padding: 10px 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .time-slot.pending { border-left-color: #f39c12; }
        .time-slot.completed { border-left-color: #27ae60; }
        .time-slot.cancelled { border-left-color: #95a5a6; }
        .doctor-card {
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .doctor-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
        }
        .time-badge {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-heart-pulse"></i> Расписание
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="index.php">Главная</a>
                <a class="nav-link" href="schedule.php">Расписание</a>
                <a class="nav-link" href="login.php">Админка</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">
            <i class="bi bi-calendar-week"></i> Расписание приёмов
        </h1>

        <!-- Фильтры -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Дата</label>
                        <input type="date" 
                               class="form-control" 
                               id="date" 
                               name="date" 
                               value="<?php echo htmlspecialchars($date); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="speciality_id" class="form-label">Специальность</label>
                        <select class="form-select" id="speciality_id" name="speciality_id">
                            <option value="">Все специальности</option>
                            <?php foreach ($specialities as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                    <?php echo ($speciality_id == $spec['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="doctor_id" class="form-label">Врач</label>
                        <select class="form-select" id="doctor_id" name="doctor_id">
                            <option value="">Все врачи</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['id']; ?>"
                                    <?php echo ($doctor_id == $doc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doc['name']); ?> 
                                    (<?php echo htmlspecialchars($doc['speciality_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Статистика дня -->
        <?php
        $day_stats = [
            'total' => count($schedule),
            'pending' => count(array_filter($schedule, fn($a) => $a['status'] == 'pending')),
            'completed' => count(array_filter($schedule, fn($a) => $a['status'] == 'completed')),
        ];