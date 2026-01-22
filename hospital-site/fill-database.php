<?php
require_once 'config.php';

echo "<h2>Заполнение базы данных тестовыми данными</h2>";

// 1. Проверяем специальности
$specialities_count = $pdo->query("SELECT COUNT(*) FROM specialities")->fetchColumn();
echo "<p>Специальностей в базе: $specialities_count</p>";

// 2. Проверяем врачей
$doctors_count = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
echo "<p>Врачей в базе: $doctors_count</p>";

// 3. Добавляем тестовые записи на приём, если их нет
$appointments_count = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

if ($appointments_count == 0) {
    echo "<h3>Создаём тестовые записи на приём...</h3>";
    
    // Получаем список врачей
    $doctors = $pdo->query("SELECT id FROM doctors LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($doctors)) {
        echo "<p style='color:red'>Нет врачей в базе! Сначала добавьте врачей.</p>";
    } else {
        $test_patients = [
            ['Алимова Айзада Руслановна', '+996 555 123456'],
            ['Бекиров Нурлан Таштанбекович', '+996 555 234567'],
            ['Касымова Гульнара Муратбековна', '+996 555 345678'],
            ['Омурзаков Азамат Болотович', '+996 555 456789'],
            ['Сатыбалдиева Айгерим Каныбековна', '+996 555 567890'],
            ['Токтосунов Данияр Амангельдиевич', '+996 555 678901'],
            ['Усубалиева Жибек Каримовна', '+996 555 789012'],
            ['Шамшиев Эрлан Бакытбекович', '+996 555 890123']
        ];
        
        $created_count = 0;
        $today = new DateTime();
        
        foreach ($test_patients as $index => $patient) {
            $doctor_id = $doctors[array_rand($doctors)];
            
            // Случайная дата в ближайшие 14 дней
            $days_offset = rand(1, 14);
            $date = clone $today;
            $date->modify("+$days_offset days");
            
            // Время с 9:00 до 17:00 с интервалом 30 минут
            $hour = rand(9, 16);
            $minute = rand(0, 1) * 30; // 0 или 30
            $date->setTime($hour, $minute);
            
            $datetime = $date->format('Y-m-d H:i:00');
            
            // Статус: 70% pending, 20% completed, 10% cancelled
            $rand = rand(1, 100);
            if ($rand <= 70) $status = 'pending';
            elseif ($rand <= 90) $status = 'completed';
            else $status = 'cancelled';
            
            // Оплата: если completed, то 80% оплачено
            $payment_status = ($status == 'completed' && rand(1, 100) <= 80) ? 'paid' : 'unpaid';
            
            $stmt = $pdo->prepare("INSERT INTO appointments 
                                  (patient_name, patient_phone, doctor_id, appointment_datetime, status, payment_status) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            
            try {
                $stmt->execute([
                    $patient[0],
                    $patient[1],
                    $doctor_id,
                    $datetime,
                    $status,
                    $payment_status
                ]);
                $created_count++;
                echo "<p style='color:green'>✓ Запись #" . $pdo->lastInsertId() . " для $patient[0]</p>";
            } catch (Exception $e) {
                echo "<p style='color:red'>✗ Ошибка: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<h3>Создано $created_count тестовых записей</h3>";
    }
} else {
    echo "<p>В базе уже есть $appointments_count записей</p>";
}

// 4. Добавляем тестовые оценки (ratings)
echo "<h3>Добавляем тестовые оценки...</h3>";

// Получаем завершённые записи без оценок
$completed_appointments = $pdo->query("
    SELECT a.id, a.doctor_id 
    FROM appointments a 
    LEFT JOIN ratings r ON a.id = r.appointment_id 
    WHERE a.status = 'completed' AND r.id IS NULL
    LIMIT 10
")->fetchAll();

$ratings_added = 0;
foreach ($completed_appointments as $app) {
    // Случайная оценка от 3 до 5 (чтобы врачи были с хорошим рейтингом)
    $score = rand(3, 5);
    
    $stmt = $pdo->prepare("INSERT INTO ratings (appointment_id, score) VALUES (?, ?)");
    try {
        $stmt->execute([$app['id'], $score]);
        $ratings_added++;
        echo "<p style='color:green'>✓ Оценка $score/5 для записи #{$app['id']}</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Ошибка оценки: " . $e->getMessage() . "</p>";
    }
}

// 5. Обновляем рейтинги врачей
echo "<h3>Обновляем рейтинги врачей...</h3>";

// Создадим триггер в базе или обновим вручную
$doctors_with_ratings = $pdo->query("
    SELECT d.id, 
           COALESCE(AVG(r.score), 0) as avg_rating,
           COUNT(r.id) as rating_count
    FROM doctors d
    LEFT JOIN appointments a ON d.id = a.doctor_id
    LEFT JOIN ratings r ON a.id = r.appointment_id
    GROUP BY d.id
")->fetchAll();

foreach ($doctors_with_ratings as $doctor) {
    $stmt = $pdo->prepare("UPDATE doctors SET rating = ROUND(?, 1) WHERE id = ?");
    $stmt->execute([$doctor['avg_rating'], $doctor['id']]);
    
    if ($doctor['rating_count'] > 0) {
        echo "<p>Врач #{$doctor['id']}: {$doctor['avg_rating']}/5 (на основе {$doctor['rating_count']} оценок)</p>";
    }
}

echo "<hr>";
echo "<h3>Итог:</h3>";
echo "<ul>";
echo "<li>Специальностей: " . $pdo->query("SELECT COUNT(*) FROM specialities")->fetchColumn() . "</li>";
echo "<li>Врачей: " . $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn() . "</li>";
echo "<li>Записей на приём: " . $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn() . "</li>";
echo "<li>Оценок: " . $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn() . "</li>";
echo "</ul>";

echo "<div class='mt-4'>";
echo "<a href='admin.php' class='btn btn-primary me-2'><i class='bi bi-speedometer2'></i> Перейти в админку</a>";
echo "<a href='index.php' class='btn btn-secondary'><i class='bi bi-house'></i> На главную</a>";
echo "</div>";
?>