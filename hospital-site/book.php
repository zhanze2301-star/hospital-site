<?php
// Подключаем конфиг
require_once 'api/config.php';

$doctor_id = $_GET['doctor_id'] ?? null;
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
        // Генерация слотов времени (каждые 30 минут с 9:00 до 18:00)
        function generateTimeSlots() {
            const slotsContainer = document.getElementById('timeSlots');
            slotsContainer.innerHTML = '';
            
            // Проверяем, выбрана ли дата
            const selectedDate = document.getElementById('date').value;
            if (!selectedDate) {
                slotsContainer.innerHTML = '<div class="text-muted">Сначала выберите дату</div>';
                return;
            }
            
            const slots = [];
            const startHour = 9;
            const endHour = 18;
            
            for (let hour = startHour; hour < endHour; hour++) {
                // Два слота в час: :00 и :30
                for (let minute of ['00', '30']) {
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute}`;
                    
                    // Проверяем, не прошедшее ли это время для сегодняшней даты
                    const selectedDateTime = new Date(selectedDate + 'T' + timeString + ':00');
                    const now = new Date();
                    if (selectedDate === now.toISOString().split('T')[0] && selectedDateTime <= now) {
                        continue; // Пропускаем прошедшее время сегодня
                    }
                    
                    slots.push(`
                        <div class="col-4 col-sm-3">
                            <div class="time-slot p-2 text-center border rounded bg-light" 
                                 data-time="${timeString}"
                                 onclick="selectTimeSlot(this, '${timeString}')">
                                ${timeString}
                            </div>
                        </div>
                    `);
                }
            }
            
            if (slots.length === 0) {
                slotsContainer.innerHTML = '<div class="text-danger">На выбранную дату нет доступных слотов времени</div>';
            } else {
                slotsContainer.innerHTML = slots.join('');
            }
        }
        
        // Выбор слота времени
        function selectTimeSlot(element, time) {
            // Убираем выделение у всех слотов
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('bg-primary', 'text-white');
                slot.classList.add('bg-light');
            });
            
            // Выделяем выбранный слот
            element.classList.remove('bg-light');
            element.classList.add('bg-primary', 'text-white');
            
            // Сохраняем выбранное время в скрытом поле
            document.getElementById('selectedTime').value = time;
            
            // Проверяем доступность времени
            checkTimeAvailability(time);
        }
        
        // Проверка доступности времени
        async function checkTimeAvailability(time) {
            const doctor_id = document.getElementById('doctor_id').value;
            const date = document.getElementById('date').value;
            
            if (!doctor_id || !date || !time) return;
            
            const response = await fetch('api/check_time.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ doctor_id, date, time })
            });
            
            const result = await response.json();
            
            // Находим выбранный слот и показываем статус
            const slotElement = document.querySelector(`.time-slot[data-time="${time}"]`);
            if (slotElement) {
                if (result.available) {
                    slotElement.innerHTML = `${time} <i class="bi bi-check-lg"></i>`;
                    slotElement.classList.remove('bg-danger');
                } else {
                    slotElement.innerHTML = `${time} <i class="bi bi-x-lg"></i>`;
                    slotElement.classList.add('bg-danger', 'text-white');
                    // Сбрасываем выбор, если время занято
                    document.getElementById('selectedTime').value = '';
                }
            }
        }
        
        // Обработка отправки формы
        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const patient_name = document.getElementById('patient_name').value.trim();
            const selectedTime = document.getElementById('selectedTime').value;
            
            if (!patient_name) {
                showMessage('Введите ваше имя', 'danger');
                return;
            }
            
            if (!selectedTime) {
                showMessage('Выберите время приёма', 'danger');
                return;
            }
            
            const formData = {
                patient_name: patient_name,
                patient_phone: document.getElementById('patient_phone').value.trim(),
                doctor_id: document.getElementById('doctor_id').value,
                date: document.getElementById('date').value,
                time: selectedTime
            };
            
            // Показываем загрузку
            showMessage('<i class="bi bi-hourglass-split"></i> Отправка данных...', 'info');
            
            const response = await fetch('api/create_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                const successHtml = `
                    <div class="alert alert-success">
                        <h4><i class="bi bi-check-circle-fill"></i> Запись успешно создана!</h4>
                        <p>${result.message}</p>
                        <hr>
                        <p><strong>Номер записи:</strong> ${result.appointment_id}</p>
                        <p><strong>Дата и время:</strong> ${formatDateTime(result.datetime)}</p>
                        <p><strong>Врач:</strong> <?php echo htmlspecialchars($doctor['name']); ?></p>
                        <p class="mt-3">Сохраните эту информацию. Наш администратор свяжется с вами для подтверждения.</p>
                    </div>
                `;
                showMessage(successHtml, 'success');
                document.getElementById('bookingForm').reset();
                document.getElementById('selectedTime').value = '';
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('bg-primary', 'bg-danger', 'text-white');
                    slot.classList.add('bg-light');
                });
            } else {
                showMessage(`<i class="bi bi-exclamation-triangle"></i> Ошибка: ${result.error}`, 'danger');
            }
        });
        
        // Функция для показа сообщений
        function showMessage(content, type = 'info') {
            const messageDiv = document.getElementById('message');
            if (type === 'info' || type === 'success' || type === 'danger') {
                messageDiv.innerHTML = `<div class="alert alert-${type}">${content}</div>`;
            } else {
                messageDiv.innerHTML = content;
            }
        }
        
        // Форматирование даты и времени для отображения
        function formatDateTime(datetimeString) {
            const date = new Date(datetimeString);
            return date.toLocaleDateString('ru-RU', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Генерируем слоты времени при изменении даты
        document.getElementById('date').addEventListener('change', generateTimeSlots);
        
        // Инициализация: генерируем слоты для сегодняшней даты (если нужно)
        window.addEventListener('load', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').value = today;
            document.getElementById('date').min = today;
            generateTimeSlots();
        });
    </script>
</body>
</html>