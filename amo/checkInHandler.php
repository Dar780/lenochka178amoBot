<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/amo.class.php');

$logFile = __DIR__ . '/checkin_handler.log';
$isCli = php_sapi_name() === 'cli';

// Функция для безопасной записи в лог
function safeLog($logFile, $message) {
    if (is_writable(dirname($logFile))) {
        @file_put_contents($logFile, $message, FILE_APPEND);
    } else {
        error_log(strip_tags($message));
    }
}

safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Check-in handler request registered\n");

// Получаем данные вебхука
if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Только POST запросы разрешены']);
        exit;
    }
    
    // Получаем сырые данные
    $rawInput = file_get_contents('php://input');
    
    // Пробуем парсить как JSON
    $postData = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Если JSON не парсится, пробуем $_POST
        $postData = $_POST;
    }
    
    if (empty($postData)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Нет полученных данных']);
        exit;
    }
} else {
    // Тестовый payload для CLI
    $postData = [
        'params' => [
            'leads' => [
                ['id' => 12345678, 'status_id' => 74364970, 'pipeline_id' => 9266190]
            ]
        ],
        'account' => [
            'id' => 32247010,
            'subdomain' => 'lenasutochno178'
        ]
    ];
}



// Конфигурация AmoCRM
$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

// ID конфигурация
$CHECK_IN_DATE_FIELD_ID = 833655;  // Поле "Дата заезда"
$SEND_CODE_STAGE_ID = 74364970;    // ID этапа "отправка кода от двери"
$RESIDENCE_STAGE_ID = 74364974;    // ID этапа "Проживание"

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Статусы, которые нужно исключить из любой автоматической обработки
$EXCLUDED_STATUS_IDS = [77524106, 76864146, 79570730, 79570734, 79893902, 143];

// Извлекаем ID сделок из webhook или params
$leadsArray = [];

// Извлекаем сделки из различных форматов webhook
if (isset($postData['data']['leads']) && is_array($postData['data']['leads'])) {
    $leadsArray = $postData['data']['leads'];
}
elseif (isset($postData['0']['question'][0]['params']['data']['leads'])) {
    $leadsArray = $postData['0']['question'][0]['params']['data']['leads'];
}
elseif (isset($postData['0']['question'][0]['params']['leads'])) {
    $leadsArray = $postData['0']['question'][0]['params']['leads'];
}
elseif (isset($postData['params']['leads']) && is_array($postData['params']['leads'])) {
    $leadsArray = $postData['params']['leads'];
} else {
    // Fallback на стандартный webhook
    if (isset($postData['leads']['add'])) {
        $leadsArray = array_merge($leadsArray, $postData['leads']['add']);
    }
    if (isset($postData['leads']['status'])) {
        $leadsArray = array_merge($leadsArray, $postData['leads']['status']);
    }
    if (isset($postData['leads']['update'])) {
        $leadsArray = array_merge($leadsArray, $postData['leads']['update']);
    }
}

safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Получено сделок для обработки: " . count($leadsArray) . " шт.\n");

$leadsToMove = [];
$today = date('Y-m-d');

foreach ($leadsArray as $lead) {
    $leadId = (int)$lead['id'];
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Обрабатываем сделку ID: $leadId\n");
    
    try {
        // Получаем данные сделки из AmoCRM
        $leadData = $amoCRM->call('GET', "leads/{$leadId}");
        
        // Пропускаем сделки из исключённых статусов
        if (isset($leadData['status_id']) && in_array((int)$leadData['status_id'], $EXCLUDED_STATUS_IDS, true)) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP: Lead $leadId is in an excluded status (" . $leadData['status_id'] . ")\n");
            continue;
        }

        if (!$leadData || !isset($leadData['custom_fields_values'])) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка $leadId: нет данных или custom_fields_values\n");
            continue;
        }
        
        // Получаем значение даты заезда через метод getCustomFieldValue
        $checkInDateValues = $amoCRM->getCustomFieldValue($leadData, $CHECK_IN_DATE_FIELD_ID);
        
        if (empty($checkInDateValues)) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка $leadId: поле 'Дата заезда' не найдено или пустое\n");
            continue;
        }
        
        $checkInDate = $checkInDateValues[0];
        

        
        // Проверяем, является ли значение числовым (timestamp)
        if (is_numeric($checkInDate)) {
            // AmoCRM передает timestamp в UTC, но нам нужно перевести его в московское время
            // Добавляем смещение для московской временной зоны (UTC+3)
            $checkInDateFormatted = date('Y-m-d', $checkInDate + (3*3600)); // +3 часа в секундах
        } elseif (strpos($checkInDate, ' ') !== false) {
            // Если дата содержит пробел (формат с временем), извлекаем только дату
            $checkInDateFormatted = explode(' ', $checkInDate)[0];
        } else {
            // Если это просто строка даты
            $checkInDateFormatted = $checkInDate;
        }
        
        // Проверяем, совпадает ли дата заезда с сегодняшней
        if ($checkInDateFormatted === $today) {
            $leadsToMove[$leadId] = $RESIDENCE_STAGE_ID;
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Сделка $leadId: дата заезда $checkInDateFormatted = сегодня, переводим в 'Проживание'\n");
        } else {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка $leadId: дата заезда $checkInDateFormatted ≠ сегодня ($today)\n");
        }
        
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Ошибка при обработке сделки $leadId: " . $e->getMessage() . "\n");
    }
}

// Перемещаем сделки, если есть что перемещать
if (!empty($leadsToMove)) {
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Найдено " . count($leadsToMove) . " сделок для перемещения\n");
    
    try {
        // Проверяем, что ID этапа установлен
        if (!$RESIDENCE_STAGE_ID) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ ОШИБКА: ID этапа 'Проживание' не установлен\n");
            throw new Exception("ID этапа 'Проживание' не установлен");
        }
        
        $moveResponse = $amoCRM->moveLeads($leadsToMove);
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Сделки успешно перемещены в этап 'Проживание'\n");
        
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Ошибка при перемещении сделок: " . $e->getMessage() . "\n");
    }
} else {
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Нет сделок для перемещения на сегодня ($today)\n");
}

// Отправляем ответ
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success', 
    'message' => 'Webhook обработан',
    'processed_leads' => count($leadsArray),
    'leads_to_move' => count($leadsToMove),
    'target_date' => $today
], JSON_UNESCAPED_UNICODE);
exit; 