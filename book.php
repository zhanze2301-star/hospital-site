<?php 
// Подключаем конфиг/ Добавить надписи, что они могут позвонить в службу поддержки и администрация за них их запишет . Может .Если прям не могут разобраться
require_once 'config.php';

$doctor_id = $_GET['doctor_id'] ?? null;

// ОТЛАДКА: выведем doctor_id
echo "<!-- DEBUG: doctor_id from URL = " . htmlspecialchars($doctor_id) . " -->";

if (!$doctor_id) {
    die('<div class="alert alert-danger">Не указан врач. Вернитесь на страницу врачей.</div>');
}
 
// Получаем информацию о враче
$stmt = $pdo->prepare("SELECT d.*, s.name as speciality_name 
                       FROM doctors d 
                       LEFT JOIN specialities s ON d.speciality_id = s.id 
                       WHERE d.id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    die('<div class="alert alert-danger">Врач не найден в базе данных.</div>');
}

// В функции проверки доступности времени
function isDoctorAvailable($doctor_id, $datetime) {
    global $pdo;
    
    // Проверяем исключения
    $sql = "SELECT COUNT(*) as count FROM doctor_unavailable 
            WHERE doctor_id = ? 
            AND ? >= start_datetime 
            AND ? <= end_datetime";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor_id, $datetime, $datetime]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return false; // Врач отсутствует
    }
    
    // Проверяем регулярное расписание
    $day_of_week = date('N', strtotime($datetime)); // 1-7
    $time = date('H:i:s', strtotime($datetime));
    
    $sql = "SELECT * FROM doctor_schedule 
            WHERE doctor_id = ? 
            AND day_of_week = ? 
            AND is_working = 1 
            AND ? >= start_time 
            AND ? <= end_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor_id, $day_of_week, $time, $time]);
    
    return $stmt->rowCount() > 0;
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Запись к врачу <?php echo htmlspecialchars($doctor['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .doctor-card {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .time-slot {
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-slot:hover {
            transform: scale(1.05);
            background-color: #e7f1ff !important;
        }
    </style>
</head>
<body>
    <!-- добавить кнопку выбор процедур при заполнении формы записи -->
    <div class="container py-5">
        <!-- Карточка врача -->
        <div class="doctor-card">
            <div class="row">
                <div class="col-md-3 text-center">
                    <?php if (!empty($doctor['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" 
                             class="img-fluid rounded-circle mb-3" 
                             alt="Фото врача" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" 
                             style="width: 150px; height: 150px; margin: 0 auto;">
                            <i class="bi bi-person-circle" style="font-size: 80px; color: #6c757d;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h2 class="mb-1"><?php echo htmlspecialchars($doctor['name']); ?></h2>
                    <p class="text-muted mb-2">
                        <i class="bi bi-briefcase"></i> 
                        <?php echo htmlspecialchars($doctor['speciality_name'] ?? 'Специальность не указана'); ?>
                    </p>
                    <?php if ($doctor['rating'] > 0): ?>
                        <div class="mb-2">
                            <span class="rating-stars">
                                <?php 
                                $fullStars = floor($doctor['rating']);
                                $halfStar = ($doctor['rating'] - $fullStars) >= 0.5;
                                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                
                                for ($i = 0; $i < $fullStars; $i++) echo '★';
                                if ($halfStar) echo '☆';
                                for ($i = 0; $i < $emptyStars; $i++) echo '☆';
                                ?>
                            </span>
                            <strong class="ms-2"><?php echo number_format($doctor['rating'], 1); ?></strong>
                            <span class="text-muted">/ 5.0</span>
                        </div>
                    <?php else: ?>
                        <div class="mb-2 text-muted">
                            <i class="bi bi-star"></i> Пока нет оценок
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($doctor['description'])): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($doctor['description'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Форма записи -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h3 class="mb-4"><i class="bi bi-calendar-plus"></i> Форма записи на приём</h3>
                
                <form id="bookingForm">
                    <input type="hidden" id="doctor_id" value="<?php echo $doctor['id']; ?>">
                    
                    <div class="row">
                        <!-- Левая колонка: данные пациента -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patient_name" class="form-label">Ваше полное имя *</label>
                                <input type="text" class="form-control" id="patient_name" 
                                       placeholder="Иванов Иван Иванович" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="patient_phone" class="form-label">Номер телефона</label>
                                <input type="tel" class="form-control" id="patient_phone" 
                                       placeholder="+996 555 123456">
                                <div class="form-text">Для связи с вами перед приёмом</div>
                            </div>
                        </div>
                        
                        <!-- Правая колонка: выбор времени -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date" class="form-label">Дата приёма *</label>
                                <input type="date" class="form-control" id="date" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       max="<?php echo date('Y-m-d', strtotime('+2 months')); ?>" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Выберите время *</label>
                                <div class="row g-2" id="timeSlots">
                                    <!-- Слоты времени появятся здесь после выбора даты -->
                                </div>
                                <div class="form-text mt-2">Рабочие часы: 9:00 - 18:00</div>
                            </div>
                            
                            <input type="hidden" id="selectedTime" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-md-2" 
                                onclick="window.history.back()">
                            <i class="bi bi-arrow-left"></i> Назад
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Записаться на приём
                        </button>
                    </div>
                </form>
                
                <!-- Результат -->
                <div id="message" class="mt-4"></div>
            </div>
        </div>
    </div>














    
<script>
// ПРОСТАЯ генерация слотов времени (без API)
function generateSimpleTimeSlots() {
    const slotsContainer = document.getElementById('timeSlots');
    const selectedDate = document.getElementById('date').value;
    
    if (!selectedDate) {
        slotsContainer.innerHTML = '<div class="text-muted">Сначала выберите дату</div>';
        return;
    }
    
    // Проверяем, не прошедшая ли дата
    const selected = new Date(selectedDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selected < today) {
        slotsContainer.innerHTML = '<div class="text-danger">Нельзя выбрать прошедшую дату</div>';
        return;
    }
    
    slotsContainer.innerHTML = '';
    
    // Создаём слоты с 9:00 до 18:00 каждые 30 минут
    const slots = [];
    for (let hour = 9; hour < 18; hour++) {
        for (let minute of [0, 30]) {
            const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            
            // Если сегодняшняя дата, пропускаем прошедшее время
            if (selected.toDateString() === today.toDateString()) {
                const now = new Date();
                const slotTime = new Date();
                slotTime.setHours(hour, minute, 0, 0);
                if (slotTime <= now) {
                    continue;
                }
            }
            
            slots.push(timeStr);
        }
    }
    
    if (slots.length === 0) {
        slotsContainer.innerHTML = '<div class="text-warning">Нет доступных слотов на выбранную дату</div>';
        return;
    }
    
    // Отображаем слоты
    slots.forEach(time => {
        const slotDiv = document.createElement('div');
        slotDiv.className = 'col-4 col-sm-3 mb-2';
        slotDiv.innerHTML = `
            <div class="time-slot p-2 text-center border rounded bg-light" 
                 data-time="${time}"
                 onclick="selectTimeSlot(this, '${time}')"
                 style="cursor: pointer;">
                ${time}
            </div>
        `;
        slotsContainer.appendChild(slotDiv);
    });
}

// Функция для выбора слота времени
function selectTimeSlot(element, time) {
    // Сбрасываем выделение у всех слотов
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('bg-primary', 'text-white', 'border-primary');
        slot.classList.add('bg-light');
    });
    
    // Выделяем выбранный слот
    element.classList.remove('bg-light');
    element.classList.add('bg-primary', 'text-white', 'border-primary');
    
    // Сохраняем выбранное время в скрытом поле
    document.getElementById('selectedTime').value = time;
    
    // Показываем сообщение
    const resultSpan = document.getElementById('timeCheckResult');
    if (resultSpan) {
        resultSpan.innerHTML = `<span class="text-success">
            <i class="bi bi-check-circle"></i> Выбрано время: ${time}
        </span>`;
    }
}

// Функция проверки времени (упрощённая, всегда возвращает true)
async function checkTime() {
    const selectedTime = document.getElementById('selectedTime').value;
    
    if (!selectedTime) {
        alert('Сначала выберите время приёма');
        return;
    }
    
    const resultSpan = document.getElementById('timeCheckResult');
    if (resultSpan) {
        resultSpan.innerHTML = '<span class="text-success">✓ Время доступно для записи</span>';
    }
    return true;
}

// Обработка отправки формы записи
document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Получаем данные формы
    const patient_name = document.getElementById('patient_name').value.trim();
    const patient_phone = document.getElementById('patient_phone').value.trim();
    const doctor_id = document.getElementById('doctor_id').value;
    const date = document.getElementById('date').value;
    const selectedTime = document.getElementById('selectedTime').value;
    
    console.log('Отправка формы:', { patient_name, patient_phone, doctor_id, date, selectedTime });
    
    // Валидация
    if (!patient_name) {
        showMessage('Введите ваше полное имя', 'danger');
        return;
    }
    
    if (!doctor_id) {
        showMessage('Не указан врач. Вернитесь на страницу врачей.', 'danger');
        return;
    }
    
    if (!date) {
        showMessage('Выберите дату приёма', 'danger');
        return;
    }
    
    if (!selectedTime) {
        showMessage('Выберите время приёма', 'danger');
        return;
    }
    
    // Формируем данные для отправки
    const formData = {
        patient_name: patient_name,
        patient_phone: patient_phone,
        doctor_id: doctor_id,
        date: date,
        time: selectedTime
    };
    
    console.log('Данные для отправки:', formData);
    
    // Показываем загрузку
    showMessage('<div class="spinner-border spinner-border-sm"></div> Отправка данных...', 'info');
    
    try {
        // Отправляем запрос на сервер
        const response = await fetch('api/create_appointment.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        // Проверяем тип ответа
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Сервер вернул не JSON:', text.substring(0, 200));
            throw new Error('Сервер вернул некорректный ответ. Проверьте API.');
        }
        
        const result = await response.json();
        console.log('Ответ сервера:', result);
        
        if (result.success) {
            const successHtml = `
                <div class="alert alert-success">
                    <h4><i class="bi bi-check-circle-fill"></i> Запись успешно создана!</h4>
                    <p>${result.message}</p>
                    <hr>
                    <p><strong>Номер записи:</strong> ${result.appointment_id}</p>
                    <p><strong>Пациент:</strong> ${patient_name}</p>
                    <p><strong>Дата и время:</strong> ${date} в ${selectedTime}</p>
                    <p class="mt-3">
                        <a href="index.php" class="btn btn-success">Вернуться на главную</a>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="bi bi-printer"></i> Распечатать
                        </button>
                    </p>
                </div>
            `;
            showMessage(successHtml, 'success');
            
            // Сбрасываем форму (кроме врача)
            document.getElementById('bookingForm').reset();
            document.getElementById('doctor_id').value = doctor_id; // Сохраняем врача
            document.getElementById('selectedTime').value = '';
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('bg-primary', 'bg-danger', 'text-white');
                slot.classList.add('bg-light');
            });
            
            const resultSpan = document.getElementById('timeCheckResult');
            if (resultSpan) resultSpan.innerHTML = '';
            
        } else {
            showMessage(`<i class="bi bi-exclamation-triangle"></i> Ошибка: ${result.error}`, 'danger');
        }
        
    } catch (error) {
        console.error('Ошибка при отправке:', error);
        showMessage(`<i class="bi bi-exclamation-triangle"></i> Ошибка сети: ${error.message}`, 'danger');
    }
});

// Функция для показа сообщений
function showMessage(content, type = 'info') {
    const messageDiv = document.getElementById('message');
    if (!messageDiv) {
        console.error('Элемент #message не найден!');
        // Создаём элемент, если его нет
        const newDiv = document.createElement('div');
        newDiv.id = 'message';
        newDiv.className = 'mt-3';
        document.querySelector('.container').appendChild(newDiv);
        newDiv.innerHTML = `<div class="alert alert-${type}">${content}</div>`;
        return;
    }
    
    if (type === 'info' || type === 'success' || type === 'danger' || type === 'warning') {
        messageDiv.innerHTML = `<div class="alert alert-${type}">${content}</div>`;
    } else {
        messageDiv.innerHTML = content;
    }
}

// Измените вызов в обработчике изменения даты
document.getElementById('date').addEventListener('change', generateSimpleTimeSlots);

// И при загрузке страницы
window.addEventListener('load', function() {
    // Проверяем, что doctor_id передан в URL
    const urlParams = new URLSearchParams(window.location.search);
    const doctorIdFromUrl = urlParams.get('doctor_id');
    
    if (doctorIdFromUrl) {
        // Устанавливаем doctor_id в скрытое поле
        document.getElementById('doctor_id').value = doctorIdFromUrl;
        console.log('Doctor ID установлен:', doctorIdFromUrl);
    } else {
        console.warn('Doctor ID не передан в URL');
        showMessage('Не указан врач. Вернитесь на страницу врачей.', 'danger');
    }
    
    // Устанавливаем завтрашнюю дату
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];
    
    document.getElementById('date').value = tomorrowStr;
    document.getElementById('date').min = tomorrowStr;
    document.getElementById('date').max = new Date(tomorrow.getTime() + 30 * 24 * 60 * 60 * 1000)
        .toISOString().split('T')[0]; // +30 дней
    
    // Генерируем слоты
    generateSimpleTimeSlots();
});
</script>
</body>
</html>