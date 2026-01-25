<?php
// admin_notifications.php - Уведомления
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Получаем непрочитанные уведомления
$notifications = $pdo->query("
    SELECT * FROM notifications 
    WHERE admin_seen = 0 
    ORDER BY created_at DESC 
    LIMIT 50
")->fetchAll();

// Получаем статистику
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN admin_seen = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'new_appointment' THEN 1 ELSE 0 END) as new_appointments,
        SUM(CASE WHEN type = 'cancellation' THEN 1 ELSE 0 END) as cancellations,
        SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders
    FROM notifications
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Уведомления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .notification-item {
            border-left: 4px solid;
            transition: all 0.3s;
            cursor: pointer;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .notification-item.unread {
            background-color: #e7f3ff;
        }
        .notification-new { border-left-color: #3498db; }
        .notification-cancellation { border-left-color: #e74c3c; }
        .notification-reminder { border-left-color: #f39c12; }
        .notification-system { border-left-color: #2ecc71; }
        .notification-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell"></i> Уведомления</h2>
            <div class="btn-group">
                <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                    <i class="bi bi-check-all"></i> Отметить все как прочитанные
                </button>
                <button class="btn btn-outline-danger" onclick="clearAllNotifications()">
                    <i class="bi bi-trash"></i> Очистить все
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                    <i class="bi bi-send"></i> Отправить уведомление
                </button>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['unread'] ?? 0; ?></h3>
                        <p class="mb-0">Непрочитанных</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['new_appointments'] ?? 0; ?></h3>
                        <p class="mb-0">Новых записей</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['reminders'] ?? 0; ?></h3>
                        <p class="mb-0">Напоминаний</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['cancellations'] ?? 0; ?></h3>
                        <p class="mb-0">Отмен</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Список уведомлений -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Последние уведомления</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ddd;"></i>
                        <h4 class="mt-3 text-muted">Нет новых уведомлений</h4>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notif): 
                            $type_class = [
                                'new_appointment' => 'notification-new',
                                'cancellation' => 'notification-cancellation',
                                'reminder' => 'notification-reminder',
                                'system' => 'notification-system'
                            ][$notif['type']] ?? '';
                            
                            $type_text = [
                                'new_appointment' => 'Новая запись',
                                'cancellation' => 'Отмена записи',
                                'reminder' => 'Напоминание',
                                'system' => 'Системное'
                            ][$notif['type']] ?? 'Уведомление';
                            
                            $time_ago = getTimeAgo($notif['created_at']);
                        ?>
                        <div class="list-group-item notification-item <?php echo $type_class; ?> <?php echo $notif['admin_seen'] == 0 ? 'unread' : ''; ?>"
                             onclick="viewNotification(<?php echo $notif['id']; ?>, <?php echo $notif['appointment_id'] ?? 'null'; ?>)">
                            <div class="d-flex w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-1">
                                        <span class="badge bg-secondary"><?php echo $type_text; ?></span>
                                        <?php echo htmlspecialchars($notif['title']); ?>
                                    </h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </div>
                                <div class="text-end">
                                    <small class="notification-time"><?php echo $time_ago; ?></small>
                                    <?php if ($notif['admin_seen'] == 0): ?>
                                        <span class="badge bg-danger notification-badge">NEW</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно отправки уведомления -->
    <div class="modal fade" id="sendNotificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="sendNotificationForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Отправить уведомление</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Тип уведомления</label>
                            <select name="type" class="form-select" required>
                                <option value="system">Системное</option>
                                <option value="reminder">Напоминание</option>
                                <option value="announcement">Объявление</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Заголовок</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Сообщение</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Получатель</label>
                            <select name="recipient" class="form-select">
                                <option value="all">Все администраторы</option>
                                <option value="specific">Конкретный администратор</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Функция для форматирования времени
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'только что';
            if (diffMins < 60) return `${diffMins} мин назад`;
            if (diffHours < 24) return `${diffHours} ч назад`;
            if (diffDays < 7) return `${diffDays} дн назад`;
            return date.toLocaleDateString('ru-RU');
        }
        
        // Просмотр уведомления
        function viewNotification(notificationId, appointmentId) {
            // Отмечаем как прочитанное
            fetch(`api/mark_notification_read.php?id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Обновляем вид уведомления
                        const item = document.querySelector(`.notification-item[onclick*="${notificationId}"]`);
                        if (item) {
                            item.classList.remove('unread');
                            const badge = item.querySelector('.notification-badge');
                            if (badge) badge.remove();
                        }
                        
                        // Если есть связанная запись, открываем её
                        if (appointmentId) {
                            window.open(`view_appointment.php?id=${appointmentId}`, '_blank');
                        }
                    }
                })
                .catch(error => console.error('Ошибка:', error));
        }
        
        // Отметить все как прочитанные
        function markAllAsRead() {
            if (!confirm('Отметить все уведомления как прочитанные?')) return;
            
            fetch('api/mark_all_notifications_read.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Все уведомления отмечены как прочитанные');
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка обновления уведомлений');
                });
        }
        
        // Очистить все уведомления
        function clearAllNotifications() {
            if (!confirm('Удалить все уведомления? Это действие нельзя отменить.')) return;
            
            fetch('api/clear_all_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Все уведомления удалены');
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка удаления уведомлений');
                });
        }
        
        // Отправка уведомления
        document.getElementById('sendNotificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/send_notification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Уведомление отправлено');
                    bootstrap.Modal.getInstance(document.getElementById('sendNotificationModal')).hide();
                    this.reset();
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Ошибка сети');
            });
        });
        
        // Автообновление уведомлений каждую минуту
        setInterval(() => {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            if (unreadCount > 0 && !document.hidden) {
                // Обновляем счетчик в заголовке
                document.title = `(${unreadCount}) Уведомления - Админ-панель`;
                
                // Можно добавить звуковое уведомление
                if (unreadCount > 0 && Notification.permission === 'granted') {
                    new Notification('Новые уведомления', {
                        body: `У вас ${unreadCount} новых уведомлений`,
                        icon: '/favicon.ico'
                    });
                }
            } else {
                document.title = 'Уведомления - Админ-панель';
            }
        }, 60000);
        
        // Запрос разрешения на уведомления
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>

<?php
// Вспомогательная функция для времени
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return floor($diff/60) . ' мин назад';
    if ($diff < 86400) return floor($diff/3600) . ' ч назад';
    if ($diff < 604800) return floor($diff/86400) . ' дн назад';
    return date('d.m.Y H:i', $time);
}
?>