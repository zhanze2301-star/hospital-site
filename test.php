<?php
// test_services_selection.php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор процедуры по специальности</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 900px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4192 100%);
        }
        .service-item {
            border-left: 4px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .service-item:hover {
            background-color: #f8f9ff;
            transform: translateX(5px);
        }
        .specialty-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .price-badge {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .duration-badge {
            background-color: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .selected-service {
            background-color: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 15px;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Выбор медицинской процедуры</h3>
                        <p class="mb-0">Выберите специальность врача и доступную процедуру</p>
                    </div>
                    <div class="card-body">
                        <!-- Шаг 1: Выбор специальности -->
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-user-md me-2"></i>1. Выберите специальность врача</h5>
                            <div class="row" id="specialties-container">
                                <div class="col-12 loading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                    <p class="mt-2">Загрузка специальностей...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Шаг 2: Выбор процедуры -->
                        <div id="services-section" class="mb-4" style="display: none;">
                            <h5 class="mb-3"><i class="fas fa-procedures me-2"></i>2. Выберите процедуру</h5>
                            <div id="services-container">
                                <!-- Процедуры загружаются здесь динамически -->
                            </div>
                        </div>

                        <!-- Шаг 3: Выбранная процедура и врачи -->
                        <div id="selection-section" class="mb-4" style="display: none;">
                            <h5 class="mb-3"><i class="fas fa-check-circle me-2"></i>3. Ваш выбор</h5>
                            <div class="selected-service mb-3" id="selected-service-info">
                                <!-- Информация о выбранной процедуре -->
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="fas fa-user-md me-2"></i>Врачи, выполняющие эту процедуру:</h6>
                                <div id="doctors-container">
                                    <!-- Список врачей загружается здесь -->
                                </div>
                            </div>
                        </div>

                        <!-- Кнопка записи -->
                        <div id="booking-section" style="display: none;">
                            <button class="btn btn-primary btn-lg w-100" onclick="bookAppointment()">
                                <i class="fas fa-calendar-check me-2"></i>Записаться на процедуру
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Информационная панель -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Как это работает?</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="fas fa-user-md fa-2x"></i>
                                    </div>
                                    <h6 class="mt-2">Выберите специальность</h6>
                                    <p class="text-muted small">Кардиолог, терапевт, ЛОР и другие</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="fas fa-procedures fa-2x"></i>
                                    </div>
                                    <h6 class="mt-2">Выберите процедуру</h6>
                                    <p class="text-muted small">Консультация, обследование, анализы</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                    <h6 class="mt-2">Запишитесь к врачу</h6>
                                    <p class="text-muted small">Выберите удобное время и дату</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для записи -->
    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Запись на процедуру</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bookingForm">
                        <input type="hidden" id="selected_service_id">
                        <input type="hidden" id="selected_specialty_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Выбранная процедура</label>
                            <input type="text" class="form-control" id="modal_service_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Специальность</label>
                            <input type="text" class="form-control" id="modal_specialty_name" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Выберите врача</label>
                                <select class="form-select" id="doctor_select" required>
                                    <option value="">Выберите врача</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Дата</label>
                                <input type="date" class="form-control" id="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Время</label>
                                <select class="form-select" id="appointment_time" required>
                                    <option value="">Выберите время</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Стоимость</label>
                                <input type="text" class="form-control" id="service_price" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ваше имя</label>
                            <input type="text" class="form-control" id="patient_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="patient_phone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email (необязательно)</label>
                            <input type="email" class="form-control" id="patient_email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Примечания</label>
                            <textarea class="form-control" id="patient_notes" rows="3"></textarea>
                        </div>
                    </form>
                    
                    <div id="bookingResult" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="submitBooking()">Записаться</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedSpecialty = null;
        let selectedService = null;
        let availableDoctors = [];

        // Загружаем специальности при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            loadSpecialties();
        });

        // Функция загрузки специальностей
        async function loadSpecialties() {
            try {
                const response = await fetch('api/get_specializations.php');
                const data = await response.json();
                
                if (data.success) {
                    displaySpecialties(data.specializations);
                } else {
                    showError('Ошибка загрузки специальностей');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Ошибка подключения к серверу');
            }
        }

        // Отображение специальностей
        function displaySpecialties(specialties) {
            const container = document.getElementById('specialties-container');
            container.innerHTML = '';
            
            if (specialties.length === 0) {
                container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Нет доступных специальностей</div></div>';
                return;
            }
            
            specialties.forEach(specialty => {
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                
                const description = specialty.description ? specialty.description.substring(0, 60) + '...' : '';
                
                col.innerHTML = '<div class="card h-100 cursor-pointer" onclick="selectSpecialty(' + specialty.id + ', \'' + escapeHtml(specialty.name) + '\')" style="cursor: pointer; transition: all 0.3s;">' +
                                '<div class="card-body text-center">' +
                                '<div class="mb-2">' +
                                '<i class="fas fa-user-md fa-3x text-primary"></i>' +
                                '</div>' +
                                '<h5 class="card-title">' + escapeHtml(specialty.name) + '</h5>' +
                                (description ? '<p class="card-text text-muted small">' + escapeHtml(description) + '</p>' : '') +
                                '<span class="badge bg-info">Выбрать</span>' +
                                '</div>' +
                                '</div>';
                
                container.appendChild(col);
            });
        }

        // Выбор специальности
        async function selectSpecialty(id, name) {
            selectedSpecialty = { id: id, name: name };
            
            // Подсветка выбранной специальности
            document.querySelectorAll('.card').forEach(card => {
                card.style.border = 'none';
            });
            event.currentTarget.style.border = '3px solid #667eea';
            
            // Показываем секцию процедур
            document.getElementById('services-section').style.display = 'block';
            
            // Загружаем процедуры для этой специальности
            await loadServices(id);
        }

        // Загрузка процедур для выбранной специальности
        async function loadServices(specialtyId) {
            const container = document.getElementById('services-container');
            container.innerHTML = '<div class="loading"><div class="spinner-border text-primary"></div><p class="mt-2">Загрузка процедур...</p></div>';
            
            try {
                const response = await fetch('api/get_services.php?specialization_id=' + specialtyId);
                const data = await response.json();
                
                if (data.success && data.services && data.services.length > 0) {
                    displayServices(data.services);
                } else {
                    container.innerHTML = '<div class="alert alert-info">' +
                                         '<i class="fas fa-info-circle me-2"></i>' +
                                         'Для выбранной специальности пока нет доступных процедур.' +
                                         '</div>';
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">' +
                                     '<i class="fas fa-exclamation-circle me-2"></i>' +
                                     'Ошибка загрузки процедур.' +
                                     '</div>';
            }
        }

        // Отображение процедур
        function displayServices(services) {
            const container = document.getElementById('services-container');
            container.innerHTML = '';
            
            services.forEach(service => {
                const serviceDiv = document.createElement('div');
                serviceDiv.className = 'service-item';
                
                const description = service.description ? '<p class="text-muted small mb-2">' + escapeHtml(service.description) + '</p>' : '';
                
                serviceDiv.innerHTML = '<div class="d-flex justify-content-between align-items-start">' +
                                      '<div>' +
                                      '<h6 class="mb-1">' + escapeHtml(service.name) + '</h6>' +
                                      description +
                                      '<div>' +
                                      '<span class="duration-badge me-2">' +
                                      '<i class="fas fa-clock me-1"></i>' + (service.duration_minutes || 30) + ' мин.' +
                                      '</span>' +
                                      '<span class="price-badge">' +
                                      '<i class="fas fa-tag me-1"></i>' + (parseFloat(service.price) || 0).toFixed(2) + ' KGS' +
                                      '</span>' +
                                      '</div>' +
                                      '</div>' +
                                      '<button class="btn btn-outline-primary btn-sm" onclick="selectService(' + service.id + ', \'' + escapeHtml(service.name) + '\', ' + (parseFloat(service.price) || 0) + ', ' + (service.duration_minutes || 30) + ')">' +
                                      '<i class="fas fa-check me-1"></i>Выбрать' +
                                      '</button>' +
                                      '</div>';
                
                container.appendChild(serviceDiv);
            });
        }

        // Выбор процедуры
        async function selectService(id, name, price, duration) {
            selectedService = { id: id, name: name, price: price, duration: duration };
            
            // Показываем информацию о выбранной процедуре
            const infoDiv = document.getElementById('selected-service-info');
            infoDiv.innerHTML = '<div class="d-flex justify-content-between align-items-center">' +
                               '<div>' +
                               '<h5 class="mb-1">' + escapeHtml(name) + '</h5>' +
                               '<p class="mb-0 text-muted">Специальность: ' + escapeHtml(selectedSpecialty.name) + '</p>' +
                               '<div class="mt-2">' +
                               '<span class="duration-badge me-2">' +
                               '<i class="fas fa-clock me-1"></i>' + duration + ' мин.' +
                               '</span>' +
                               '<span class="price-badge">' +
                               '<i class="fas fa-tag me-1"></i>' + price.toFixed(2) + ' KGS' +
                               '</span>' +
                               '</div>' +
                               '</div>' +
                               '<button class="btn btn-outline-secondary btn-sm" onclick="deselectService()">' +
                               '<i class="fas fa-times me-1"></i>Изменить' +
                               '</button>' +
                               '</div>';
            
            // Показываем секцию выбора
            document.getElementById('selection-section').style.display = 'block';
            
            // Загружаем врачей для этой процедуры
            await loadDoctorsForService(id);
        }

        // Загрузка врачей для выбранной процедуры
        async function loadDoctorsForService(serviceId) {
            const container = document.getElementById('doctors-container');
            container.innerHTML = '<div class="loading"><div class="spinner-border spinner-border-sm text-primary"></div> Загрузка врачей...</div>';
            
            try {
                const response = await fetch('api/get_doctors.php?service_id=' + serviceId);
                const data = await response.json();
                
                if (data.success && data.doctors && data.doctors.length > 0) {
                    availableDoctors = data.doctors;
                    displayDoctors(data.doctors);
                    
                    // Показываем кнопку записи
                    document.getElementById('booking-section').style.display = 'block';
                } else {
                    container.innerHTML = '<div class="alert alert-warning">' +
                                         '<i class="fas fa-exclamation-triangle me-2"></i>' +
                                         'Нет доступных врачей для этой процедуры.' +
                                         '</div>';
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">' +
                                     '<i class="fas fa-exclamation-circle me-2"></i>' +
                                     'Ошибка загрузки врачей.' +
                                     '</div>';
            }
        }

        // Отображение врачей
        function displayDoctors(doctors) {
            const container = document.getElementById('doctors-container');
            container.innerHTML = '';
            
            doctors.forEach(doctor => {
                const doctorDiv = document.createElement('div');
                doctorDiv.className = 'card mb-2';
                
                const photoHtml = doctor.photo_url ? 
                    '<img src="' + doctor.photo_url + '" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;" alt="' + escapeHtml(doctor.name) + '">' : 
                    '<div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">' +
                    '<i class="fas fa-user-md text-muted"></i>' +
                    '</div>';
                
                doctorDiv.innerHTML = '<div class="card-body py-2">' +
                                     '<div class="d-flex align-items-center">' +
                                     photoHtml +
                                     '<div class="flex-grow-1">' +
                                     '<h6 class="mb-0">' + escapeHtml(doctor.name) + '</h6>' +
                                     '<small class="text-muted">' +
                                     '<i class="fas fa-briefcase me-1"></i>' + (doctor.experience || 'Опыт не указан') + ' • ' +
                                     '<i class="fas fa-star text-warning me-1"></i>' + (parseFloat(doctor.rating) || 0).toFixed(1) + '/10' +
                                     '</small>' +
                                     '</div>' +
                                     '<span class="badge bg-light text-dark">' +
                                     '<i class="fas fa-map-marker-alt me-1"></i>' + (doctor.workplace || 'Главный корпус') +
                                     '</span>' +
                                     '</div>' +
                                     '</div>';
                
                container.appendChild(doctorDiv);
            });
        }

        // Отмена выбора процедуры
        function deselectService() {
            selectedService = null;
            document.getElementById('selection-section').style.display = 'none';
            document.getElementById('booking-section').style.display = 'none';
        }

        // Запись на процедуру
        function bookAppointment() {
            if (!selectedService || !selectedSpecialty) {
                showError('Пожалуйста, выберите процедуру и специальность');
                return;
            }
            
            // Заполняем модальное окно
            document.getElementById('selected_service_id').value = selectedService.id;
            document.getElementById('selected_specialty_id').value = selectedSpecialty.id;
            document.getElementById('modal_service_name').value = selectedService.name;
            document.getElementById('modal_specialty_name').value = selectedSpecialty.name;
            document.getElementById('service_price').value = selectedService.price.toFixed(2) + ' KGS';
            
            // Заполняем список врачей
            const doctorSelect = document.getElementById('doctor_select');
            doctorSelect.innerHTML = '<option value="">Выберите врача</option>';
            
            availableDoctors.forEach(doctor => {
                const option = document.createElement('option');
                option.value = doctor.id;
                option.textContent = doctor.name + ' (' + (doctor.workplace || 'Главный корпус') + ')';
                doctorSelect.appendChild(option);
            });
            
            // Показываем модальное окно
            const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            modal.show();
        }

        // Обработчик изменения выбора врача
        document.getElementById('doctor_select').addEventListener('change', async function() {
            const doctorId = this.value;
            const dateInput = document.getElementById('appointment_date');
            
            if (doctorId && dateInput.value) {
                await loadAvailableTimes(doctorId, dateInput.value);
            }
        });

        // Обработчик изменения даты
        document.getElementById('appointment_date').addEventListener('change', async function() {
            const doctorId = document.getElementById('doctor_select').value;
            const date = this.value;
            
            if (doctorId && date) {
                await loadAvailableTimes(doctorId, date);
            }
        });

        // Загрузка доступных времен
        async function loadAvailableTimes(doctorId, date) {
            const timeSelect = document.getElementById('appointment_time');
            timeSelect.innerHTML = '<option value="">Загрузка...</option>';
            timeSelect.disabled = true;
            
            try {
                const response = await fetch('api/get_available_time.php?doctor_id=' + doctorId + '&date=' + date);
                const data = await response.json();
                
                if (data.success && data.available_times && data.available_times.length > 0) {
                    timeSelect.innerHTML = '<option value="">Выберите время</option>';
                    data.available_times.forEach(time => {
                        const option = document.createElement('option');
                        option.value = time;
                        option.textContent = time;
                        timeSelect.appendChild(option);
                    });
                    timeSelect.disabled = false;
                } else {
                    timeSelect.innerHTML = '<option value="">Нет доступных времен</option>';
                    timeSelect.disabled = true;
                }
            } catch (error) {
                console.error('Error:', error);
                timeSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
                timeSelect.disabled = true;
            }
        }

        // Отправка формы записи
        async function submitBooking() {
            const form = document.getElementById('bookingForm');
            const resultDiv = document.getElementById('bookingResult');
            
            // Проверка заполнения полей
            const requiredFields = ['doctor_select', 'appointment_date', 'appointment_time', 'patient_name', 'patient_phone'];
            for (const fieldId of requiredFields) {
                const field = document.getElementById(fieldId);
                if (!field.value) {
                    field.classList.add('is-invalid');
                    showError('Заполните все обязательные поля');
                    return;
                }
                field.classList.remove('is-invalid');
            }
            
            // Сбор данных
            const bookingData = {
                doctor_id: document.getElementById('doctor_select').value,
                service_id: selectedService.id,
                appointment_datetime: document.getElementById('appointment_date').value + ' ' + document.getElementById('appointment_time').value,
                patient_name: document.getElementById('patient_name').value,
                patient_phone: document.getElementById('patient_phone').value,
                patient_email: document.getElementById('patient_email').value || '',
                patient_notes: document.getElementById('patient_notes').value || ''
            };
            
            // Отправка данных
            try {
                const response = await fetch('api/create_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(bookingData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">' +
                                         '<h5><i class="fas fa-check-circle me-2"></i>Запись успешно создана!</h5>' +
                                         '<p>Номер записи: <strong>#' + data.appointment_id + '</strong></p>' +
                                         '<p>Дата и время: <strong>' + (data.formatted_datetime || bookingData.appointment_datetime) + '</strong></p>' +
                                         '<p>Мы свяжемся с вами для подтверждения записи.</p>' +
                                         '</div>';
                    
                    // Очистка формы через 3 секунды
                    setTimeout(() => {
                        form.reset();
                        resultDiv.innerHTML = '';
                        const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                        if (modal) modal.hide();
                        
                        // Обновляем страницу
                        window.location.reload();
                    }, 3000);
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger">' +
                                         '<h5><i class="fas fa-exclamation-circle me-2"></i>Ошибка</h5>' +
                                         '<p>' + (data.error || 'Не удалось создать запись') + '</p>' +
                                         '</div>';
                }
            } catch (error) {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                                     '<h5><i class="fas fa-exclamation-circle me-2"></i>Ошибка подключения</h5>' +
                                     '<p>Проверьте подключение к интернету и попробуйте снова.</p>' +
                                     '</div>';
            }
        }

        // Вспомогательная функция для отображения ошибок
        function showError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
            alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + message +
                                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            
            document.querySelector('.container').prepend(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Функция для экранирования HTML
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>