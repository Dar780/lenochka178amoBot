<?php
require_once 'amo/amo.class.php';

$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

echo "=== ПРОВЕРКА ПОЛЕЙ В AmoCRM ===\n\n";

// Список обязательных полей из handler.php
$requiredFields = [
    // Финансовые поля
    850717 => 'Общая сумма брони',
    848327 => 'Внесённая оплата', 
    850719 => 'Остаток к доплате',
    
    // Данные квартиры
    852841 => 'Улица',
    852843 => 'Номер дома', 
    852845 => 'Номер квартиры',
    852847 => 'Код калитки',
    852849 => 'Код домофона',
    852851 => 'Размер депозита',
    852853 => 'Стоимость уборки', 
    852855 => 'Банк',
    852857 => 'Получатель',
    
    // WiFi данные
    873617 => 'Название WiFi',
    873619 => 'Пароль WiFi',
    
    // Новые поля
    977279 => 'Код кейбокса',
    977281 => 'Номер подъезда',
    977283 => 'Номер этажа'
];

try {
    // Получаем все кастомные поля для сделок
    $response = $amoCRM->call('GET', 'leads/custom_fields');
    
    if (isset($response['_embedded']['custom_fields'])) {
        $existingFields = [];
        foreach ($response['_embedded']['custom_fields'] as $field) {
            $existingFields[$field['id']] = $field['name'];
        }
        
        echo "Проверяем наличие обязательных полей:\n\n";
        
        $missingFields = [];
        foreach ($requiredFields as $fieldId => $description) {
            if (isset($existingFields[$fieldId])) {
                echo "✅ field_id: $fieldId - '$description' → '{$existingFields[$fieldId]}'\n";
            } else {
                echo "❌ field_id: $fieldId - '$description' → НЕ НАЙДЕНО!\n";
                $missingFields[$fieldId] = $description;
            }
        }
        
        if (!empty($missingFields)) {
            echo "\n🚨 КРИТИЧЕСКАЯ ОШИБКА!\n";
            echo "Отсутствуют " . count($missingFields) . " обязательных полей в AmoCRM:\n\n";
            
            foreach ($missingFields as $fieldId => $description) {
                echo "- field_id: $fieldId ($description)\n";
            }
            
            echo "\n📋 ИНСТРУКЦИЯ:\n";
            echo "1. Зайдите в AmoCRM → Настройки → Поля → Сделки\n";
            echo "2. Создайте недостающие поля:\n";
            echo "   - Финансовые поля → тип 'Число'\n";
            echo "   - Текстовые поля → тип 'Текст'\n";
            echo "3. После создания поля запишите его field_id\n";
            echo "4. Обновите field_id в handler.php если они отличаются\n\n";
            
        } else {
            echo "\n✅ ВСЕ ПОЛЯ НАЙДЕНЫ! Вебхук должен работать корректно.\n\n";
        }
        
        echo "=== ПОЛНЫЙ СПИСОК ПОЛЕЙ В AmoCRM ===\n";
        foreach ($existingFields as $id => $name) {
            echo "field_id: $id → '$name'\n";
        }
        
    } else {
        echo "❌ Не удалось получить список полей из AmoCRM\n";
        echo "Ответ API: " . print_r($response, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА ПОДКЛЮЧЕНИЯ К AmoCRM: " . $e->getMessage() . "\n";
    echo "Проверьте токен и права доступа.\n";
}

echo "\n=== КОНЕЦ ПРОВЕРКИ ===\n";
?> 