<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Получаем список таблиц
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Проверяем ключевые таблицы
    $required_tables = ['doctors', 'appointments', 'specialities'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        if (!in_array($table, $tables)) {
            $missing_tables[] = $table;
        }
    }
    
    // Получаем количество записей в основных таблицах
    $counts = [];
    foreach ($tables as $table) {
        try {
            $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $counts[$table] = $count['count'];
        } catch (Exception $e) {
            $counts[$table] = 'error';
        }
    }
    
    echo json_encode([
        'success' => true,
        'database' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'tables' => $tables,
        'table_counts' => $counts,
        'missing_required_tables' => $missing_tables,
        'has_required_tables' => empty($missing_tables),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'request_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage(),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
        ]
    ]);
}
?>