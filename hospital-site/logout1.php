<?php
// logout.php
session_start();

// Удаляем все данные сессии
$_SESSION = array();

// Удаляем куку сессии
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Удаляем куку авторизации
setcookie('admin_auth', '', time()-3600, '/');

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную 
header('Location: index.php');
exit;
?>