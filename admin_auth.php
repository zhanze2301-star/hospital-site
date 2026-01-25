<?php
// admin_auth.php - безопасная аутентификация

class AdminAuth {
    private $pdo;
    private $session_timeout = 1800; // 30 минут
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        session_start();
    }
    
    // Хеширование пароля
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    // Проверка пароля
    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Создание администратора
    public function createAdmin($username, $password) {
        $hash = $this->hashPassword($password);
        $stmt = $this->pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        return $stmt->execute([$username, $hash]);
    }
    
    // Вход
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $this->verifyPassword($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_login_time'] = time();
            
            // Создаем токен для защиты от CSRF
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            return true;
        }
        return false;
    }
    
    // Проверка авторизации
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        
        // Проверка времени сессии
        if (isset($_SESSION['admin_login_time'])) {
            if (time() - $_SESSION['admin_login_time'] > $this->session_timeout) {
                $this->logout();
                return false;
            }
            // Обновляем время
            $_SESSION['admin_login_time'] = time();
        }
        
        return true;
    }
    
    // Выход
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    // Проверка CSRF токена
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Получить CSRF токен
    public function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
}

// Использование:
// require_once 'admin_auth.php';
// $auth = new AdminAuth($pdo);
?> 