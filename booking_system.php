<?php
// booking_system.php - Улучшенная система записи
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система записи на приём</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #1abc9c;
        }
        
        .booking-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .step-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            background: white;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .step-number {
            width: 36px;
            height: 36px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .step-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }
        
        .specialization-card, 
        .hospital-card,
        .doctor-card,
        .service-card {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .specialization-card:hover, 
        .hospital-card:hover,
        .doctor-card:hover,
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: #dee2e6;
        }
        
        .specialization-card.selected, 
        .hospital-card.selected,
        .doctor-card.selected,
        .service-card.selected {
            border-color: var(--accent-color);
            background-color: rgba(26, 188, 156, 0.05);
        }
        
        .map-container {
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 20px;
            border: 2px solid #dee2e6;
            display: none; /* Скрыта по умолчанию */
        }
        
        .time-slot {
            cursor: pointer;
            padding: 10px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.2s ease;
            background: white;
        }
        
        .time-slot:hover {
            background-color: #f8f9fa;
        }
        
        .time-slot.selected {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .time-slot.unavailable {
            background-color: #f8d7da;
            color: #721c24;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .progress-container {
            margin: 30px 0;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        
        .progress-step::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .progress-step:first-child::before {
            display: none;
        }
        
        .progress-step.active .step-circle {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .progress-step.completed .step-circle {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .progress-step.completed::before {
            background: var(--accent-color);
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #e9ecef;
            background: white;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .step-label {
            margin-top: 8px;
            font-size: 0.85rem;
            text-align: center;
            color: #6c757d;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 12px;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--accent-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .booking-container {
                padding: 15px;
            }
            
            .step-card {
                padding: 15px;
            }
            
            .map-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="booking-container py-4">
        <!-- Заголовок -->
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold text-primary">Запись на приём к врачу</h1>
            <p class="text-muted">Быстрая и удобная запись на медицинский приём</p>
        </div>
        
        <!-- Помощь -->
        <div class="alert alert-info mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-telephone fs-5 me-3"></i>
                <div>
                    <strong>Нужна помощь?</strong> Позвоните нам: <a href="tel:+996555123456" class="fw-bold">+996 555 123 456</a>
                </div>
            </div>
        </div>
        
        <!-- Прогресс бар -->
        <div class="progress-container">
            <div class="row">
                <div class="col-3 progress-step active" id="step1-progress">
                    <div class="step-circle">1</div>
                    <div class="step-label">Специализация</div>
                </div>
                <div class="col-3 progress-step" id="step2-progress">
                    <div class="step-circle">2</div>
                    <div class="step-label">Больница</div>
                </div>
                <div class="col-3 progress-step" id="step3-progress">
                    <div class="step-circle">3</div>
                    <div class="step-label">Врач</div>
                </div>
                <div class="col-3 progress-step" id="step4-progress">
                    <div class="step-circle">4</div>
                    <div class="step-label">Запись</div>
                </div>
            </div>
        </div>
        
        <!-- Шаг 1: Выбор специализации -->
        <div class="step-card active" id="step1">
            <div class="step-header">
                <div class="step-number">1</div>
                <h2 class="step-title">Выберите специализацию врача</h2>
            </div>
            
            <div class="row" id="specializationsList">
                <!-- Специализации загружаются через AJAX -->
                <div class="col-12 text-center py-4">
                    <div class="loading-spinner"></div>
                    <p class="mt-2 text-muted">Загрузка специализаций...</p>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-outline-secondary" onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Назад
                </button>
                <button class="btn btn-primary" onclick="proceedToStep(2)" id="step1-next" disabled>
                    Далее <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Шаг 2: Выбор больницы -->
        <div class="step-card disabled" id="step2">
            <div class="step-header">
                <div class="step-number">2</div>
                <h2 class="step-title">Выберите больницу</h2>
            </div>
            
            <div class="mb-3">
                <button class="btn btn-outline-primary btn-sm" onclick="toggleMap()" id="toggleMapBtn">
                    <i class="bi bi-map"></i> Показать на карте
                </button>
            </div>
            
            <!-- Карта (скрыта по умолчанию) -->
            <div class="map-container" id="hospitalMap">
                <div class="text-center py-5">
                    <div class="loading-spinner"></div>
                    <p>Загрузка карты...</p>
                </div>
            </div>
            
            <!-- Список больниц -->
            <div class="row" id="hospitalsList">
                <!-- Больницы загружаются через AJAX -->
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-outline-secondary" onclick="backToStep(1)">
                    <i class="bi bi-arrow-left"></i> Назад
                </button>
                <button class="btn btn-primary" onclick="proceedToStep(3)" id="step2-next" disabled>
                    Далее <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Шаг 3: Выбор врача -->
        <div class="step-card disabled" id="step3">
            <div class="step-header">
                <div class="step-number">3</div>
                <h2 class="step-title">Выберите врача</h2>
            </div>
            
            <!-- Фильтры -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="filterDate" class="form-label">Предпочтительная дата</label>
                    <input type="date" class="form-control" id="filterDate" 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6">
                    <label for="filterTime" class="form-label">Предпочтительное время</label>
                    <select class="form-select" id="filterTime">
                        <option value="">Любое время</option>
                        <option value="morning">Утро (9:00-12:00)</option>
                        <option value="afternoon">День (12:00-15:00)</option>
                        <option value="evening">Вечер (15:00-18:00)</option>
                    </select>
                </div>
            </div>
            
            <!-- Список врачей -->
            <div class="row" id="doctorsList">
                <!-- Врачи загружаются через AJAX -->
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-outline-secondary" onclick="backToStep(2)">
                    <i class="bi bi-arrow-left"></i> Назад
                </button>
                <button class="btn btn-primary" onclick="proceedToStep(4)" id="step3-next" disabled>
                    Далее <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Шаг 4: Запись на приём -->
        <div class="step-card disabled" id="step4">
            <div class="step-header">
                <div class="step-number">4</div>
                <h2 class="step-title">Запись на приём</h2>
            </div>
            
            <!-- Информация о выборе -->
            <div class="alert alert-light mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Специализация:</strong><br>
                        <span id="selectedSpecialization">-</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Больница:</strong><br>
                        <span id="selectedHospital">-</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Врач:</strong><br>
                        <span id="selectedDoctor">-</span>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Левая колонка: выбор даты/времени -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="appointmentDate" class="form-label">Дата приёма *</label>
                        <input type="date" class="form-control" id="appointmentDate" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Выберите время *</label>
                        <div class="row g-2" id="timeSlotsContainer">
                            <!-- Слоты времени загружаются через AJAX -->
                        </div>
                    </div>
                </div>
                
                <!-- Правая колонка: данные пациента -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="patientName" class="form-label">Ваше имя *</label>
                        <input type="text" class="form-control" id="patientName" 
                               placeholder="Иванов Иван" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="patientPhone" class="form-label">Телефон *</label>
                        <input type="tel" class="form-control" id="patientPhone" 
                               placeholder="+996 555 123 456" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="patientNotes" class="form-label">Жалобы или симптомы</label>
                        <textarea class="form-control" id="patientNotes" rows="2" 
                                  placeholder="Опишите вашу проблему"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Скрытые поля -->
            <input type="hidden" id="selectedSpecializationId">
            <input type="hidden" id="selectedHospitalId">
            <input type="hidden" id="selectedDoctorId">
            <input type="hidden" id="selectedTime">
            
            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-outline-secondary" onclick="backToStep(3)">
                    <i class="bi bi-arrow-left"></i> Назад
                </button>
                <button class="btn btn-success" onclick="submitBooking()" id="submitBookingBtn" disabled>
                    <i class="bi bi-check-circle"></i> Записаться
                </button>
            </div>
        </div>
        
        <!-- Результат -->
        <div id="bookingResult" class="mt-4" style="display: none;"></div>
    </div>

    <!-- JavaScript библиотеки -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Основной скрипт системы записи -->
    <script>
    // Глобальные переменные
    let selectedSpecialization = null;
    let selectedHospital = null;
    let selectedDoctor = null;
    let selectedTime = null;
    let map = null;
    let markers = [];
    let userLocation = null;
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        loadSpecializations();
        setupEventListeners();
        getUserLocation(); // Получаем местоположение пользователя заранее
    });
    
    // Получение местоположения пользователя
    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    console.log('User location detected:', userLocation);
                },
                function(error) {
                    console.log('Geolocation error:', error.message);
                    // По умолчанию Бишкек
                    userLocation = { lat: 42.8746, lng: 74.5698 };
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        } else {
            userLocation = { lat: 42.8746, lng: 74.5698 };
        }
    }
    
    // Настройка слушателей событий
    function setupEventListeners() {
        // Фильтр даты для врачей
        document.getElementById('filterDate').addEventListener('change', function() {
            if (selectedHospital && selectedSpecialization) {
                loadDoctors();
            }
        });
        
        // Фильтр времени для врачей
        document.getElementById('filterTime').addEventListener('change', function() {
            if (selectedHospital && selectedSpecialization) {
                loadDoctors();
            }
        });
        
        // Выбор даты приёма
        document.getElementById('appointmentDate').addEventListener('change', function() {
            if (selectedDoctor) {
                loadAvailableTimeSlots();
            }
        });
    }
    
    // Загрузка специализаций
    function loadSpecializations() {
        fetch('api/get_specializations.php')
            .then(response => response.json())
            .then(data => {
                renderSpecializationsList(data);
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showError('Не удалось загрузить специализации');
            });
    }
    
    // Рендеринг списка специализаций
    function renderSpecializationsList(specializations) {
        const container = document.getElementById('specializationsList');
        
        if (!specializations || specializations.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Нет доступных специализаций</div></div>';
            return;
        }
        
        let html = '';
        specializations.forEach(spec => {
            html += `
                <div class="col-6 col-md-4 col-lg-3 mb-3">
                    <div class="specialization-card card h-100" onclick="selectSpecialization(${spec.id}, '${spec.name}')" id="spec-${spec.id}">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-heart-pulse fs-2 text-primary"></i>
                            </div>
                            <h6 class="card-title mb-0">${spec.name}</h6>
                            ${spec.description ? `<p class="text-muted small mt-2">${spec.description.substring(0, 60)}...</p>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Выбор специализации
    function selectSpecialization(specId, specName) {
        // Сброс выбора
        document.querySelectorAll('.specialization-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Выделение выбранной
        const selectedCard = document.getElementById(`spec-${specId}`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }
        
        // Обновление переменных
        selectedSpecialization = specId;
        document.getElementById('selectedSpecializationId').value = specId;
        document.getElementById('selectedSpecialization').textContent = specName;
        
        // Активация кнопки продолжения
        document.getElementById('step1-next').disabled = false;
        
        // Загрузка больниц для этой специализации
        loadHospitalsForSpecialization(specId);
    }
    
    // Загрузка больниц для специализации
    function loadHospitalsForSpecialization(specId) {
        showLoading('step2');
        
        fetch(`api/get_hospitals_by_specialization.php?specialization_id=${specId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading('step2');
                renderHospitalsList(data);
            })
            .catch(error => {
                hideLoading('step2');
                console.error('Ошибка:', error);
                showError('Не удалось загрузить больницы');
            });
    }
    
    // Рендеринг списка больниц
    function renderHospitalsList(hospitals) {
        const container = document.getElementById('hospitalsList');
        
        if (!hospitals || hospitals.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Нет больниц с этой специализацией</div></div>';
            return;
        }
        
        let html = '';
        hospitals.forEach(hospital => {
            // Рассчитываем расстояние если есть местоположение пользователя
            let distanceInfo = '';
            if (userLocation && hospital.latitude && hospital.longitude) {
                const distance = calculateDistance(
                    userLocation.lat, userLocation.lng,
                    hospital.latitude, hospital.longitude
                );
                distanceInfo = `<span class="badge bg-light text-dark border ms-2">${distance} км</span>`;
            }
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="hospital-card card h-100" onclick="selectHospital(${hospital.id}, '${hospital.name}')" id="hospital-${hospital.id}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title mb-1">
                                    <i class="bi bi-hospital me-2"></i>${hospital.name}
                                    ${distanceInfo}
                                </h6>
                                <span class="badge bg-success">Открыто</span>
                            </div>
                            <p class="card-text text-muted small mb-2">
                                <i class="bi bi-geo-alt"></i> ${hospital.address}
                            </p>
                            ${hospital.phone ? `<p class="card-text small"><i class="bi bi-telephone"></i> ${hospital.phone}</p>` : ''}
                            <div class="mt-2">
                                <span class="badge bg-info">Врачей: ${hospital.doctor_count || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Выбор больницы
    function selectHospital(hospitalId, hospitalName) {
        // Сброс выбора
        document.querySelectorAll('.hospital-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Выделение выбранной
        const selectedCard = document.getElementById(`hospital-${hospitalId}`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }
        
        // Обновление переменных
        selectedHospital = hospitalId;
        document.getElementById('selectedHospitalId').value = hospitalId;
        document.getElementById('selectedHospital').textContent = hospitalName;
        
        // Активация кнопки продолжения
        document.getElementById('step2-next').disabled = false;
        
        // Загрузка врачей для больницы и специализации
        loadDoctors();
    }
    
    // Загрузка врачей
    function loadDoctors() {
        if (!selectedHospital || !selectedSpecialization) return;
        
        showLoading('step3');
        
        const filterDate = document.getElementById('filterDate').value;
        const filterTime = document.getElementById('filterTime').value;
        
        let url = `api/get_doctors_by_hospital.php?hospital_id=${selectedHospital}&specialization_id=${selectedSpecialization}`;
        
        if (filterDate) url += `&date=${filterDate}`;
        if (filterTime) url += `&time_preference=${filterTime}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                hideLoading('step3');
                renderDoctorsList(data);
            })
            .catch(error => {
                hideLoading('step3');
                console.error('Ошибка:', error);
                showError('Не удалось загрузить врачей');
            });
    }
    
    // Рендеринг списка врачей
    function renderDoctorsList(doctors) {
        const container = document.getElementById('doctorsList');
        
        if (!doctors || doctors.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Нет врачей с доступными записями</div></div>';
            return;
        }
        
        let html = '';
        doctors.forEach(doctor => {
            // Рейтинг
            let ratingStars = '';
            if (doctor.rating > 0) {
                const fullStars = Math.floor(doctor.rating);
                const hasHalfStar = (doctor.rating - fullStars) >= 0.5;
                
                for (let i = 0; i < fullStars; i++) ratingStars += '★';
                if (hasHalfStar) ratingStars += '½';
                for (let i = ratingStars.length; i < 5; i++) ratingStars += '☆';
            } else {
                ratingStars = '<span class="text-muted">Нет оценок</span>';
            }
            
            // Индикатор доступности
            const availability = doctor.available_slots > 0 ? 
                `<span class="badge bg-success">Свободных мест: ${doctor.available_slots}</span>` :
                `<span class="badge bg-warning">Запись ограничена</span>`;
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="doctor-card card h-100" onclick="selectDoctor(${doctor.id}, '${doctor.name}')" id="doctor-${doctor.id}">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    ${doctor.photo_url ? 
                                        `<img src="${doctor.photo_url}" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;" alt="${doctor.name}">` :
                                        `<div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <i class="bi bi-person fs-3 text-secondary"></i>
                                        </div>`
                                    }
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-1">${doctor.name}</h6>
                                    <p class="text-muted small mb-2">${doctor.speciality_name}</p>
                                    <div class="mb-2">
                                        <span class="text-warning">${ratingStars}</span>
                                        <span class="ms-2">${doctor.rating ? doctor.rating.toFixed(1) : '0.0'}</span>
                                    </div>
                                    ${doctor.experience ? `<p class="small mb-2"><i class="bi bi-clock-history"></i> ${doctor.experience}</p>` : ''}
                                    <div class="mt-2">
                                        ${availability}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Выбор врача
    function selectDoctor(doctorId, doctorName) {
        // Сброс выбора
        document.querySelectorAll('.doctor-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Выделение выбранного
        const selectedCard = document.getElementById(`doctor-${doctorId}`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }
        
        // Обновление переменных
        selectedDoctor = doctorId;
        document.getElementById('selectedDoctorId').value = doctorId;
        document.getElementById('selectedDoctor').textContent = doctorName;
        
        // Активация кнопки продолжения
        document.getElementById('step3-next').disabled = false;
        
        // Устанавливаем завтрашнюю дату по умолчанию
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];
        
        document.getElementById('appointmentDate').value = tomorrowStr;
        document.getElementById('appointmentDate').min = tomorrowStr;
        
        // Загружаем доступное время
        loadAvailableTimeSlots();
    }
    
    // Загрузка доступных слотов времени
    function loadAvailableTimeSlots() {
        if (!selectedDoctor) return;
        
        const date = document.getElementById('appointmentDate').value;
        if (!date) return;
        
        const container = document.getElementById('timeSlotsContainer');
        container.innerHTML = '<div class="col-12"><div class="text-center py-3"><div class="loading-spinner"></div> Загрузка...</div></div>';
        
        fetch(`api/get_available_slots.php?doctor_id=${selectedDoctor}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                renderTimeSlots(data);
            })
            .catch(error => {
                console.error('Ошибка:', error);
                container.innerHTML = '<div class="col-12"><div class="alert alert-danger">Ошибка загрузки времени</div></div>';
            });
    }
    
    // Рендеринг слотов времени
    function renderTimeSlots(slots) {
        const container = document.getElementById('timeSlotsContainer');
        
        if (!slots || slots.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Нет доступного времени на эту дату</div></div>';
            return;
        }
        
        let html = '';
        slots.forEach(slot => {
            const isAvailable = slot.available;
            const time = slot.time;
            
            html += `
                <div class="col-4 col-md-3 mb-2">
                    <div class="time-slot ${isAvailable ? '' : 'unavailable'}" 
                         onclick="${isAvailable ? `selectTimeSlot('${time}')` : 'return false'}"
                         id="slot-${time.replace(':', '-')}">
                        ${time}
                        ${isAvailable ? '' : '<div class="small text-danger">×</div>'}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Выбор слота времени
    function selectTimeSlot(time) {
        // Сброс выбора
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        // Выделение выбранного
        const selectedSlot = document.getElementById(`slot-${time.replace(':', '-')}`);
        if (selectedSlot) {
            selectedSlot.classList.add('selected');
        }
        
        // Сохранение времени
        selectedTime = time;
        document.getElementById('selectedTime').value = time;
        
        // Активация кнопки отправки
        document.getElementById('submitBookingBtn').disabled = false;
    }
    
    // Переключение карты
    function toggleMap() {
        const mapContainer = document.getElementById('hospitalMap');
        const toggleBtn = document.getElementById('toggleMapBtn');
        
        if (mapContainer.style.display === 'none' || mapContainer.style.display === '') {
            // Показываем карту
            mapContainer.style.display = 'block';
            toggleBtn.innerHTML = '<i class="bi bi-map"></i> Скрыть карту';
            
            // Инициализируем карту если еще не инициализирована
            if (!map && selectedHospital) {
                initMapWithSelectedHospitals();
            } else if (map) {
                setTimeout(() => map.invalidateSize(), 100);
            }
        } else {
            // Скрываем карту
            mapContainer.style.display = 'none';
            toggleBtn.innerHTML = '<i class="bi bi-map"></i> Показать на карте';
        }
    }
    
    // Инициализация карты с выбранными больницами
    function initMapWithSelectedHospitals() {
        if (!selectedSpecialization) return;
        
        const mapContainer = document.getElementById('hospitalMap');
        
        // Загружаем больницы с координатами
        fetch(`api/get_hospitals_by_specialization.php?specialization_id=${selectedSpecialization}&with_coords=1`)
            .then(response => response.json())
            .then(hospitals => {
                mapContainer.innerHTML = '';
                
                // Центрируем карту на местоположении пользователя или первой больнице
                let centerLat = 42.8746;
                let centerLng = 74.5698;
                
                if (userLocation) {
                    centerLat = userLocation.lat;
                    centerLng = userLocation.lng;
                } else if (hospitals.length > 0 && hospitals[0].latitude) {
                    centerLat = hospitals[0].latitude;
                    centerLng = hospitals[0].longitude;
                }
                
                // Создаем карту
                map = L.map('hospitalMap').setView([centerLat, centerLng], 12);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Добавляем маркер местоположения пользователя
                if (userLocation) {
                    L.marker([userLocation.lat, userLocation.lng])
                        .addTo(map)
                        .bindPopup('<strong>Вы здесь</strong>')
                        .openPopup()
                        .setIcon(L.icon({
                            iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34]
                        }));
                }
                
                // Добавляем маркеры больниц
                hospitals.forEach(hospital => {
                    if (hospital.latitude && hospital.longitude) {
                        const marker = L.marker([hospital.latitude, hospital.longitude])
                            .addTo(map)
                            .bindPopup(`
                                <strong>${hospital.name}</strong><br>
                                ${hospital.address}<br>
                                <button class="btn btn-sm btn-primary mt-1" onclick="selectHospital(${hospital.id}, '${hospital.name}')">
                                    Выбрать
                                </button>
                            `);
                        
                        // Подсвечиваем выбранную больницу
                        if (selectedHospital == hospital.id) {
                            marker.setIcon(L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41]
                            }));
                        }
                        
                        markers.push(marker);
                    }
                });
            })
            .catch(error => {
                console.error('Ошибка загрузки карты:', error);
                mapContainer.innerHTML = '<div class="alert alert-danger">Не удалось загрузить карту</div>';
            });
    }
    
    // Переход между шагами
    function proceedToStep(step) {
        // Деактивация текущего шага
        document.querySelectorAll('.step-card').forEach(card => {
            card.classList.remove('active');
            card.classList.add('disabled');
        });
        
        // Активация следующего шага
        const nextStep = document.getElementById(`step${step}`);
        if (nextStep) {
            nextStep.classList.remove('disabled');
            nextStep.classList.add('active');
        }
        
        // Обновление прогресс-бара
        updateProgressBar(step);
        
        // Прокрутка к верху
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function backToStep(step) {
        proceedToStep(step);
    }
    
    // Обновление прогресс-бара
    function updateProgressBar(currentStep) {
        // Сбрасываем все шаги
        document.querySelectorAll('.progress-step').forEach(step => {
            step.classList.remove('active', 'completed');
        });
        
        // Устанавливаем текущий и завершённые шаги
        for (let i = 1; i <= 4; i++) {
            const stepElement = document.getElementById(`step${i}-progress`);
            if (i < currentStep) {
                stepElement.classList.add('completed');
            } else if (i === currentStep) {
                stepElement.classList.add('active');
            }
        }
    }
    
    // Отправка записи
    function submitBooking() {
        // Валидация
        const patientName = document.getElementById('patientName').value.trim();
        const patientPhone = document.getElementById('patientPhone').value.trim();
        const appointmentDate = document.getElementById('appointmentDate').value;
        
        if (!patientName || !patientPhone || !appointmentDate || !selectedTime) {
            showError('Заполните все обязательные поля');
            return;
        }
        
        // Сбор данных
        const bookingData = {
            doctor_id: selectedDoctor,
            appointment_date: appointmentDate,
            appointment_time: selectedTime,
            patient_name: patientName,
            patient_phone: patientPhone,
            patient_notes: document.getElementById('patientNotes').value || ''
        };
        
        // Показываем загрузку
        const submitBtn = document.getElementById('submitBookingBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading-spinner"></div>';
        submitBtn.disabled = true;
        
        // Отправка
        fetch('api/create_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bookingData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showBookingSuccess(data);
            } else {
                showError(data.error || 'Ошибка при записи');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showError('Ошибка сети');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // Показать успешное завершение
    function showBookingSuccess(data) {
        const resultContainer = document.getElementById('bookingResult');
        
        resultContainer.innerHTML = `
            <div class="alert alert-success">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-2 me-3"></i>
                    <div>
                        <h4>Запись создана!</h4>
                        <p>Ваш номер записи: <strong>#${data.appointment_id}</strong></p>
                        <p>${data.message}</p>
                        <div class="mt-3">
                            <button class="btn btn-outline-primary" onclick="printBooking()">
                                <i class="bi bi-printer"></i> Распечатать
                            </button>
                            <button class="btn btn-primary ms-2" onclick="createNewBooking()">
                                <i class="bi bi-plus-circle"></i> Новая запись
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        resultContainer.style.display = 'block';
        document.getElementById('step4').style.display = 'none';
        resultContainer.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Новая запись
    function createNewBooking() {
        // Сброс
        selectedSpecialization = selectedHospital = selectedDoctor = selectedTime = null;
        document.getElementById('bookingForm').reset();
        document.getElementById('bookingResult').style.display = 'none';
        document.getElementById('step4').style.display = 'block';
        
        // Сброс выбора
        document.querySelectorAll('.specialization-card, .hospital-card, .doctor-card, .time-slot')
            .forEach(el => el.classList.remove('selected'));
        
        proceedToStep(1);
    }
    
    // Вспомогательные функции
    function showLoading(stepId) {
        const step = document.getElementById(stepId);
        let overlay = step.querySelector('.loading-overlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div><div class="loading-spinner"></div><p class="mt-2 text-muted">Загрузка...</p></div>';
            step.style.position = 'relative';
            step.appendChild(overlay);
        }
    }
    
    function hideLoading(stepId) {
        const step = document.getElementById(stepId);
        const overlay = step.querySelector('.loading-overlay');
        if (overlay) overlay.remove();
    }
    
    function showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mt-3';
        alert.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.querySelector('.booking-container').prepend(alert);
        
        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }, 5000);
    }
    
    // Расчет расстояния между координатами (в км)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Радиус Земли в км
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c * 10) / 10; // Округляем до 0.1 км
    }
    
    function printBooking() {
        window.print();
    }
    </script>
</body>
</html>