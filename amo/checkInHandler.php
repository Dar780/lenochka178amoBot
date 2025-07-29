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
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Начинаем обработку web-запроса\n");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка: не POST запрос\n");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Только POST запросы разрешены']);
        exit;
    }
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Метод запроса: " . $_SERVER['REQUEST_METHOD'] . "\n");
    
    // Получаем сырые данные
    try {
        $rawInput = file_get_contents('php://input');
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сырые данные получены, длина: " . strlen($rawInput) . "\n");
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сырые данные: " . $rawInput . "\n");
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка получения сырых данных: " . $e->getMessage() . "\n");
        $rawInput = '';
    }
    
    // Пробуем парсить как JSON
    try {
        $postData = json_decode($rawInput, true);
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] JSON decode выполнен\n");
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] JSON parse error: " . json_last_error_msg() . ", используем $_POST\n");
            $postData = $_POST;
        }
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка JSON decode: " . $e->getMessage() . "\n");
        $postData = $_POST;
    }
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Данные получены, проверяем на пустоту\n");
    
    if (empty($postData)) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка: нет полученных данных\n");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Нет полученных данных']);
        exit;
    }
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Данные получены успешно\n");
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

// Логируем входящие данные вебхука
safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Входящие данные вебхука:\n" . print_r($postData, true) . "\n");

// Конфигурация AmoCRM
$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

// ID конфигурация
$CHECK_IN_DATE_FIELD_ID = 833655;  // Поле "Дата заезда"
$SEND_CODE_STAGE_ID = 74364970;    // ID этапа "отправка кода от двери"
$RESIDENCE_STAGE_ID = 74364974;    // ID этапа "Проживание"

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Папка с JSON-файлами
$bookingsDir = __DIR__ . '/bookings';
if (!is_dir($bookingsDir)) {
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Папка bookings не найдена.\n");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Папка bookings не найдена']);
    exit;
}

// Собираем все сделки для перемещения
$leadsToMove = [];
$today = date('Y-m-d');

$files = glob($bookingsDir . '/*.json');
safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Найдено " . count($files) . " JSON файлов для проверки\n");

foreach ($files as $file) {
    $json = file_get_contents($file);
    $bookingData = json_decode($json, true);
    if (!$bookingData) {
        continue;
    }
    
    // Проверяем наличие необходимых полей
    if (!isset($bookingData['begin_date'], $bookingData['apartment_id'], $bookingData['lead_id'])) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Файл " . basename($file) . ": отсутствуют необходимые поля\n");
        continue;
    }
    
    // Получаем дату заезда и обрабатываем возможные форматы
    $checkInDate = $bookingData['begin_date'];
    
    // Обрабатываем дату с учетом возможного формата timestamp
    if (is_numeric($checkInDate)) {
        // AmoCRM передает timestamp в UTC, добавляем смещение для московской временной зоны
        $checkInDateFormatted = date('Y-m-d', $checkInDate + (3*3600)); // +3 часа в секундах
    } elseif (strpos($checkInDate, ' ') !== false) {
        // Если дата содержит пробел (формат с временем), извлекаем только дату
        $checkInDateFormatted = explode(' ', $checkInDate)[0];
    } else {
        // Если это просто строка даты
        $checkInDateFormatted = $checkInDate;
    }
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь ID " . $bookingData['id'] . ", дата заезда: " . $bookingData['begin_date'] . ", после обработки: $checkInDateFormatted, сравнивается с today=$today\n");
    
    // Проверяем, совпадает ли дата заезда с сегодняшней
    if ($checkInDateFormatted === $today) {
        $leadsToMove[$bookingData['lead_id']] = $RESIDENCE_STAGE_ID;
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Для брони ID " . $bookingData['id'] . " найдено совпадение. Lead ID: " . $bookingData['lead_id'] . " → этап 'Проживание'\n");
    } else {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Бронь ID " . $bookingData['id'] . ": дата заезда НЕ совпадает с сегодня\n");
    }
}

$leadsToMove = [];
$today = date('Y-m-d');



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
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Ответ перемещения сделок:\n" . print_r($moveResponse, true) . "\n");
        
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
    'message' => 'Проверка завершена',
    'total_bookings_checked' => count($files),
    'leads_to_move' => count($leadsToMove),
    'target_date' => $today
], JSON_UNESCAPED_UNICODE);
exit; 