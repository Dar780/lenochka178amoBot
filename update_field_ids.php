<?php
// Скрипт для обновления field_id в handler.php после создания новых полей

require_once 'amo/amo.class.php';

$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

echo "=== ПОИСК НОВЫХ FIELD_ID ===\n\n";

try {
    $response = $amoCRM->call('GET', 'leads/custom_fields');
    
    if (isset($response['_embedded']['custom_fields'])) {
        $fieldMap = [];
        foreach ($response['_embedded']['custom_fields'] as $field) {
            $fieldMap[$field['name']] = $field['id'];
        }
        
        // Ищем field_id для новых полей
        $newFields = [
            'RCS кейбокс' => 'keybox',
            'RCS подъезд' => 'entrance', 
            'RCS этаж' => 'floor'
        ];
        
        $foundIds = [];
        
        echo "Поиск field_id для новых полей:\n\n";
        foreach ($newFields as $fieldName => $shortName) {
            if (isset($fieldMap[$fieldName])) {
                $fieldId = $fieldMap[$fieldName];
                $foundIds[$shortName] = $fieldId;
                echo "✅ '$fieldName' → field_id: $fieldId\n";
            } else {
                echo "❌ '$fieldName' → НЕ НАЙДЕНО! Создайте поле в AmoCRM\n";
            }
        }
        
        if (count($foundIds) === 3) {
            echo "\n🎉 ВСЕ ПОЛЯ НАЙДЕНЫ!\n\n";
            echo "Обновите handler.php:\n\n";
            
            echo "Замените в handler.php:\n";
            echo "- 873621 → {$foundIds['keybox']} (RCS кейбокс)\n";
            echo "- 873623 → {$foundIds['entrance']} (RCS подъезд)\n";
            echo "- 873625 → {$foundIds['floor']} (RCS этаж)\n\n";
            
            // Автоматическое обновление handler.php
            $handlerContent = file_get_contents('amo/handler.php');
            
            $handlerContent = str_replace('873621', $foundIds['keybox'], $handlerContent);
            $handlerContent = str_replace('873623', $foundIds['entrance'], $handlerContent);
            $handlerContent = str_replace('873625', $foundIds['floor'], $handlerContent);
            
            file_put_contents('amo/handler.php', $handlerContent);
            
            echo "✅ handler.php автоматически обновлён!\n";
            
        } else {
            echo "\n⚠️ Не все поля найдены. Создайте недостающие поля в AmoCRM.\n";
        }
        
    } else {
        echo "❌ Ошибка получения полей из AmoCRM\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
}

echo "\n=== КОНЕЦ ===\n";
?> 