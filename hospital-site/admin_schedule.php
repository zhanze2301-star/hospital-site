<?php
// admin_calendar.php - Календарь приёмов
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Получаем врачей для фильтра
$doctors = $pdo->query("SELECT id, name FROM doctors ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Календарь приёмов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <style>
        .fc {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .fc-toolbar-title {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        .fc-event {
            border-radius: 4px;
            border: none;
            padding: 2px 4px;
            cursor: pointer;
        }
        .event-pending { background-color: #ffc107; color: #000; }
        .event-completed { background-color: #198754; color: #fff; }
        .event-cancelled { background-color: #6c757d; color: #fff; }
        .calendar-filters {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-calendar-week"></i> Календарь приёмов</h2>
        
        <!-- Фильтры календаря -->
        <div class="calendar-filters">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label">Врач:</label>
                    <select id="doctorFilter" class="form-select">
                        <option value="">Все врачи</option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Статус:</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">Все статусы</option>
                        <option value="pending">Ожидают</option>
                        <option value="completed">Завершены</option>
                        <option value="cancelled">Отменены</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Вид календаря:</label>
                    <select id="viewFilter" class="form-select">
                        <option value="dayGridMonth">Месяц</option>
                        <option value="timeGridWeek">Неделя</option>
                        <option value="timeGridDay">День</option>
                        <option value="listWeek">Список</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Действия:</label>
                    <div class="btn-group w-100">
                        <button id="todayBtn" class="btn btn-outline-primary">
                            <i class="bi bi-calendar-day"></i> Сегодня
                        </button>
                        <button id="addEventBtn" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
                            <i class="bi bi-plus-circle"></i> Добавить
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Календарь -->
        <div id="calendar"></div>
    </div>
    
    <!-- Модальное окно для просмотра/редактирования события -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Детали записи</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody">
                    <!-- Загружается динамически -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <a href="#" id="editEventBtn" class="btn btn-primary">Редактировать</a>
                    <button type="button" id="deleteEventBtn" class="btn btn-danger">Удалить</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно добавления записи -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addEventForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить запись на приём</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Пациент *</label>
                            <input type="text" name="patient_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="patient_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Врач *</label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Выберите врача</option>
                                <?php foreach ($doctors as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Дата *</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Время *</label>
                                <input type="time" name="time" class="form-control" step="1800" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Добавить запись</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Скрипты -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/ru.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let calendar;
            let currentFilters = {
                doctor_id: '',
                status: ''
            };
            
            // Инициализация календаря
            function initCalendar() {
                const calendarEl = document.getElementById('calendar');
                
                calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'ru',
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    views: {
                        timeGridWeek: {
                            slotDuration: '00:30:00',
                            slotMinTime: '08:00:00',
                            slotMaxTime: '20:00:00'
                        },
                        timeGridDay: {
                            slotDuration: '00:30:00',
                            slotMinTime: '08:00:00',
                            slotMaxTime: '20:00:00'
                        }
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        // Загружаем события с фильтрами
                        loadEvents(fetchInfo.start, fetchInfo.end, successCallback);
                    },
                    eventClick: function(info) {
                        showEventDetails(info.event.id);
                    },
                    eventDidMount: function(info) {
                        // Добавляем всплывающую подсказку
                        info.el.title = `${info.event.title}\n${info.event.extendedProps.patient_phone || 'Телефон не указан'}`;
                    },
                    editable: true,
                    eventDrop: function(info) {
                        updateAppointmentTime(info.event.id, info.event.start);
                    },
                    eventResize: function(info) {
                        updateAppointmentTime(info.event.id, info.event.start, info.event.end);
                    },
                    selectable: true,
                    select: function(info) {
                        // При выборе области открываем форму добавления
                        document.querySelector('#addEventModal input[name="date"]').value = info.startStr.split('T')[0];
                        document.querySelector('#addEventModal input[name="time"]').value = info.startStr.split('T')[1]?.substring(0,5) || '09:00';
                        
                        const modal = new bootstrap.Modal(document.getElementById('addEventModal'));
                        modal.show();
                        
                        calendar.unselect();
                    }
                });
                
                calendar.render();
            }
            
            // Загрузка событий
            function loadEvents(start, end, callback) {
                const params = new URLSearchParams({
                    start: start.toISOString().split('T')[0],
                    end: end.toISOString().split('T')[0],
                    ...currentFilters
                });
                
                fetch(`api/get_calendar_events.php?${params}`)
                    .then(response => response.json())
                    .then(data => {
                        const events = data.map(event => ({
                            id: event.id,
                            title: `${event.patient_name} - ${event.doctor_name}`,
                            start: event.appointment_datetime,
                            end: event.appointment_datetime,
                            backgroundColor: getEventColor(event.status),
                            borderColor: getEventColor(event.status),
                            extendedProps: {
                                patient_name: event.patient_name,
                                patient_phone: event.patient_phone,
                                doctor_name: event.doctor_name,
                                status: event.status,
                                payment_status: event.payment_status
                            }
                        }));
                        callback(events);
                    })
                    .catch(error => {
                        console.error('Ошибка загрузки событий:', error);
                        callback([]);
                    });
            }
            
            // Цвет события в зависимости от статуса
            function getEventColor(status) {
                switch(status) {
                    case 'pending': return '#ffc107';
                    case 'completed': return '#198754';
                    case 'cancelled': return '#6c757d';
                    default: return '#3498db';
                }
            }
            
            // Показать детали события
            function showEventDetails(eventId) {
                fetch(`api/get_appointment_details.php?id=${eventId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const appointment = data.appointment;
                            
                            document.getElementById('eventModalTitle').textContent = 
                                `Запись #${appointment.id} - ${appointment.patient_name}`;
                            
                            const statusColor = getEventColor(appointment.status);
                            const statusText = {
                                'pending': 'Ожидает',
                                'completed': 'Завершена',
                                'cancelled': 'Отменена'
                            }[appointment.status];
                            
                            document.getElementById('eventModalBody').innerHTML = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Информация о пациенте:</h6>
                                        <p><strong>ФИО:</strong> ${appointment.patient_name}</p>
                                        <p><strong>Телефон:</strong> ${appointment.patient_phone || 'не указан'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Информация о приёме:</h6>
                                        <p><strong>Врач:</strong> ${appointment.doctor_name}</p>
                                        <p><strong>Дата и время:</strong> ${appointment.formatted_datetime}</p>
                                        <p>
                                            <strong>Статус:</strong> 
                                            <span class="badge" style="background-color: ${statusColor}">
                                                ${statusText}
                                            </span>
                                        </p>
                                        <p>
                                            <strong>Оплата:</strong> 
                                            <span class="badge bg-${appointment.payment_status === 'paid' ? 'success' : 'danger'}">
                                                ${appointment.payment_status === 'paid' ? 'Оплачено' : 'Не оплачено'}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            `;
                            
                            document.getElementById('editEventBtn').href = `edit_appointment.php?id=${eventId}`;
                            document.getElementById('deleteEventBtn').onclick = () => deleteAppointment(eventId);
                            
                            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
                            modal.show();
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка загрузки деталей:', error);
                        alert('Ошибка загрузки данных');
                    });
            }
            
            // Обновление времени записи (при перетаскивании в календаре)
            function updateAppointmentTime(appointmentId, start, end = null) {
                const formData = new FormData();
                formData.append('id', appointmentId);
                formData.append('datetime', start.toISOString().replace('T', ' ').substring(0, 19));
                
                fetch('api/update_appointment_time.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Ошибка обновления времени: ' + (data.error || 'Неизвестная ошибка'));
                        calendar.refetchEvents(); // Перезагружаем события
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка сети');
                    calendar.refetchEvents();
                });
            }
            
            // Удаление записи
            function deleteAppointment(appointmentId) {
                if (!confirm('Вы уверены, что хотите удалить эту запись?')) return;
                
                fetch(`api/delete_appointment.php?id=${appointmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Запись удалена');
                            calendar.refetchEvents();
                            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                        } else {
                            alert('Ошибка удаления: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        alert('Ошибка сети');
                    });
            }
            
            // Форма добавления записи
            document.getElementById('addEventForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                
                fetch('api/create_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Запись успешно добавлена!');
                        calendar.refetchEvents();
                        bootstrap.Modal.getInstance(document.getElementById('addEventModal')).hide();
                        this.reset();
                    } else {
                        alert('Ошибка: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка сети');
                });
            });
            
            // Фильтры
            document.getElementById('doctorFilter').addEventListener('change', function() {
                currentFilters.doctor_id = this.value;
                calendar.refetchEvents();
            });
            
            document.getElementById('statusFilter').addEventListener('change', function() {
                currentFilters.status = this.value;
                calendar.refetchEvents();
            });
            
            document.getElementById('viewFilter').addEventListener('change', function() {
                calendar.changeView(this.value);
            });
            
            // Кнопка "Сегодня"
            document.getElementById('todayBtn').addEventListener('click', function() {
                calendar.today();
            });
            
            // Автоматически устанавливаем сегодняшнюю дату в форму добавления
            document.querySelector('#addEventModal input[name="date"]').value = 
                new Date().toISOString().split('T')[0];
            
            // Инициализация календаря
            initCalendar();
        });
    </script>
</body>
</html>