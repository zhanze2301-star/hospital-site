<?php
session_start();
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Заголовки для Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Получаем данные
// ... аналогично admin_appointments.php ...

echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Пациент</th>
        <th>Телефон</th>
        <th>Врач</th>
        <th>Дата и время</th>
        <th>Статус</th>
        <th>Оплата</th>
    </tr>";

// ... вывод данных ...
echo "</table>";
?>