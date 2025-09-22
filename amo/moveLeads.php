<?php

require_once(__DIR__ . '/amo.class.php');
require_once(__DIR__ . '/config.php'); // Подключение конфигурации (БД, настройки и пр.)

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/cron_log.txt';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Cron script started\n", FILE_APPEND);

// Захардкоженный subdomain и токен для amoCRM
$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Статусы, которые нужно исключить из любой автоматической обработки
$EXCLUDED_STATUS_IDS = [77524106, 76864146, 79570730, 79570734, 79893902];

// Папка с JSON-файлами
$bookingsDir = __DIR__ . '/bookings';
if (!is_dir($bookingsDir)) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Папка bookings не найдена.\n", FILE_APPEND);
    exit;
}

// Собираем все сделки для перемещения
$leadsToMove = [];
$targetDate = date("Y-m-d", strtotime("+1 day")); // Проверяем дату выселения на завтра

$files = glob($bookingsDir . '/*.json');
foreach ($files as $file) {
    $json = file_get_contents($file);
    $bookingData = json_decode($json, true);
    if (!$bookingData) {
        continue;
    }
    
    // Проверяем наличие необходимых полей
    if (!isset($bookingData['end_date'], $bookingData['apartment_id'], $bookingData['lead_id'])) {
        continue;
    }
    
    // Проверяем текущий статус сделки и пропускаем, если она в исключённых статусах
    try {
        $leadData = $amoCRM->getLeadById((int)$bookingData['lead_id']);
        if (isset($leadData['status_id']) && in_array((int)$leadData['status_id'], $EXCLUDED_STATUS_IDS, true)) {
            file_put_contents(
                $logFile,
                "[" . date('Y-m-d H:i:s') . "] SKIP: Бронь ID " . ($bookingData['id'] ?? 'unknown') . ", lead " . $bookingData['lead_id'] .
                " находится в исключённом статусе (" . $leadData['status_id'] . ").\n",
                FILE_APPEND
            );
            continue;
        }
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка получения сделки " . $bookingData['lead_id'] . ": " . $e->getMessage() . "\n", FILE_APPEND);
        // В случае ошибки запроса к AmoCRM — безопаснее пропустить, чтобы не переместить лишнее
        continue;
    }

    // Получаем дату выезда и обрабатываем возможные форматы
    $endDate = $bookingData['end_date'];
    
    // Обрабатываем дату с учетом возможного формата timestamp
    if (is_numeric($endDate)) {
        // AmoCRM передает timestamp в UTC, добавляем смещение для московской временной зоны
        $endDate = date('Y-m-d', $endDate + (3*3600)); // +3 часа в секундах
    } elseif (strpos($endDate, ' ') !== false) {
        // Если дата содержит пробел (формат с временем), извлекаем только дату
        $endDate = explode(' ', $endDate)[0];
    }
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: Бронь ID " . $bookingData['id'] . ", дата выезда: " . $bookingData['end_date'] . ", после обработки: $endDate, сравнивается с targetDate=$targetDate\n", FILE_APPEND);
    
    // Проверяем, совпадает ли дата выезда с сегодняшней
    if (
        $endDate === $targetDate &&
        (!isset($bookingData['is_moved_amo']) || $bookingData['is_moved_amo'] == 0)
    ) {
        $leadsToMove[$bookingData['lead_id']] = 74365494; // ID нового этапа - "Выселение"
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Для брони ID " . $bookingData['id'] . " найдено совпадение. Lead ID: " . $bookingData['lead_id'] . "\n", FILE_APPEND);
    }
}

if (!empty($leadsToMove)) {
    try {
        $moveResponse = $amoCRM->moveLeads($leadsToMove);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ответ перемещения сделок:\n" . print_r($moveResponse, true) . "\n", FILE_APPEND);
        
        // После успешного перемещения обновляем flag в файлах
        foreach ($files as $file) {
            $json = file_get_contents($file);
            $bookingData = json_decode($json, true);
            if (!$bookingData) {
                continue;
            }
            if (isset($bookingData['end_date']) && $bookingData['end_date'] === $targetDate && isset($bookingData['lead_id']) && (!isset($bookingData['is_moved_amo']) || $bookingData['is_moved_amo'] == 0)) {
                $bookingData['is_moved_amo'] = 1;
                file_put_contents($file, json_encode($bookingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка при перемещении сделок: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Нет сделок для перемещения.\n", FILE_APPEND);
}

echo "Done\n";
