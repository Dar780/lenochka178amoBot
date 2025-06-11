<?php
require_once 'amo/config.php';

echo "=== ПРОВЕРКА И ДОБАВЛЕНИЕ ПОЛЕЙ В БД ===\n\n";

// Проверяем текущую структуру таблицы
echo "1. Проверка текущих полей:\n";
$result = $db->query("DESCRIBE apartments");
$existingFields = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingFields[] = $row['Field'];
        if (in_array($row['Field'], ['keybox_code', 'entrance_number', 'floor_number'])) {
            echo "✅ Поле '{$row['Field']}' существует\n";
        }
    }
} else {
    echo "❌ Ошибка: " . $db->error . "\n";
    exit;
}

echo "\n2. Проверка недостающих полей:\n";
$requiredFields = ['keybox_code', 'entrance_number', 'floor_number'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!in_array($field, $existingFields)) {
        $missingFields[] = $field;
        echo "❌ Поле '$field' отсутствует\n";
    } else {
        echo "✅ Поле '$field' найдено\n";
    }
}

if (!empty($missingFields)) {
    echo "\n3. Добавление недостающих полей:\n";
    
    foreach ($missingFields as $field) {
        $sql = "ALTER TABLE apartments ADD COLUMN $field VARCHAR(255) DEFAULT NULL";
        echo "Выполняю: $sql\n";
        
        if ($db->query($sql)) {
            echo "✅ Поле '$field' успешно добавлено\n";
        } else {
            echo "❌ Ошибка добавления поля '$field': " . $db->error . "\n";
        }
    }
    
    echo "\n4. Проверка после добавления:\n";
    $result = $db->query("DESCRIBE apartments");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['Field'], $requiredFields)) {
                echo "✅ Поле '{$row['Field']}' ({$row['Type']}) - Null: {$row['Null']}\n";
            }
        }
    }
} else {
    echo "\n✅ Все необходимые поля уже есть в БД!\n";
}

echo "\n=== ТЕСТ ВЕБХУКА ===\n";
echo "Теперь можно протестировать вебхук:\n";
echo "1. Создайте новую бронь в RealtyCalendar\n";
echo "2. Проверьте логи: tail -f amo/handler_log.txt\n";
echo "3. Убедитесь что поля заполняются в AmoCRM\n\n";

echo "=== ГОТОВО! ===\n";
?> 